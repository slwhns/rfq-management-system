<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProjectComponent;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = $request->user();
        $role = $currentUser?->normalizedRole();

        abort_unless(in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true), 403);

        $purchaseOrders = PurchaseOrder::with(['purchaseRequest.project'])
            ->latest()
            ->paginate(15);

        return view($this->roleView('purchase-orders.index'), compact('purchaseOrders'));
    }

    public function create(Request $request, Quote $quote)
    {
        $quote->load(['project', 'items.component']);
        $shouldDownload = (bool) $request->boolean('download');

        $currentUser = $request->user();
        $role = $currentUser?->normalizedRole();

        abort_unless(in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true), 403);
        abort_unless(Quote::normalizeStatus($quote->status) === Quote::STATUS_APPROVED, 422, 'Purchase Order can only be created from an approved Purchase Request.');

        $sections = $this->buildCompanySections($quote);
        $requestedCompany = trim((string) $request->query('company', ''));

        $selectedSection = $sections->firstWhere('company_name', $requestedCompany) ?? $sections->first();
        abort_if(!$selectedSection, 422, 'No supplier section is available for this Purchase Request.');

        $items = collect($selectedSection['items'] ?? []);

        $vendor = $selectedSection['vendor'] ?? [
            'name' => $selectedSection['company_name'] ?? 'Vendor Name',
            'address_lines' => ['Address'],
        ];

        $purchaseOrder = $this->upsertPurchaseOrder(
            $quote,
            $selectedSection['company_name'],
            $vendor,
            $items,
            (int) $currentUser->id
        );

        $items = $purchaseOrder->items()->with('component')->get();
        $subtotal = (float) $purchaseOrder->subtotal;

        $deliverTo = [
            'name' => trim((string) ($currentUser?->company_name ?? 'Your Company Name')),
            'address_lines' => array_values(array_filter([
                $quote->project->project_name ?? null,
                $quote->project->location ?? null,
                'Malaysia',
            ])),
            'email' => $currentUser?->email,
        ];

        return view($this->roleView('purchase-orders.create'), [
            'quote' => $quote,
            'poNumber' => $purchaseOrder->po_number,
            'purchaseOrder' => $purchaseOrder,
            'companySections' => $sections,
            'selectedCompany' => $selectedSection['company_name'],
            'items' => $items,
            'subtotal' => $subtotal,
            'vendor' => $vendor,
            'deliverTo' => $deliverTo,
            'shouldDownload' => $shouldDownload,
        ]);
    }

    private function upsertPurchaseOrder(Quote $quote, string $companyName, array $vendor, Collection $items, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($quote, $companyName, $vendor, $items, $userId) {
            $subtotal = (float) $items->sum(fn ($item) => (float) ($item->line_total ?? 0));

            $purchaseOrder = PurchaseOrder::firstOrNew([
                'purchase_request_id' => (int) $quote->id,
                'company_name' => $companyName,
            ]);

            if (!$purchaseOrder->exists) {
                $purchaseOrder->po_number = $this->generatePoNumber();
                $purchaseOrder->created_by = $userId;
                $purchaseOrder->status = 'draft';
            }

            $purchaseOrder->project_id = $quote->project_id;
            $purchaseOrder->vendor_name = (string) ($vendor['name'] ?? $companyName);
            $purchaseOrder->vendor_address = implode("\n", $vendor['address_lines'] ?? []);
            $purchaseOrder->vendor_phone = $vendor['phone'] ?? null;
            $purchaseOrder->subtotal = round($subtotal, 2);
            $purchaseOrder->total_amount = round($subtotal, 2);
            $purchaseOrder->save();

            $purchaseOrder->items()->delete();

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'component_id' => $item->component_id,
                    'description' => trim((string) ($item->component->description ?? '')),
                    'quantity' => round((float) ($item->quantity ?? 0), 2),
                    'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                    'line_total' => round((float) ($item->line_total ?? 0), 2),
                ]);
            }

            return $purchaseOrder;
        });
    }

    private function generatePoNumber(): string
    {
        $last = PurchaseOrder::query()->orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', (string) $last->po_number)) + 1 : 1;

        return sprintf('PO-%05d', $next);
    }

    private function buildCompanySections(Quote $quote): Collection
    {
        $componentIds = collect($quote->items)->pluck('component_id')->filter()->unique()->values();
        $defaultCompany = trim((string) optional($quote->project?->user)->company_name);
        if ($defaultCompany === '') {
            $defaultCompany = $quote->project->project_name ?? 'General';
        }

        $companyQueueByComponent = [];
        if ($componentIds->isNotEmpty()) {
            $projectComponents = ProjectComponent::where('project_id', $quote->project_id)
                ->whereIn('component_id', $componentIds)
                ->get(['component_id', 'notes']);

            foreach ($projectComponents as $projectComponent) {
                $notes = json_decode((string) $projectComponent->notes, true);
                $supplierName = trim((string) (($notes['supplier_name'] ?? '') ?: $defaultCompany));

                if (!isset($companyQueueByComponent[$projectComponent->component_id])) {
                    $companyQueueByComponent[$projectComponent->component_id] = [];
                }

                $companyQueueByComponent[$projectComponent->component_id][] = $supplierName;
            }
        }

        $itemsByCompany = collect($quote->items)->groupBy(function ($item) use (&$companyQueueByComponent, $defaultCompany) {
            $componentId = (int) $item->component_id;

            if (isset($companyQueueByComponent[$componentId]) && count($companyQueueByComponent[$componentId]) > 0) {
                return array_shift($companyQueueByComponent[$componentId]);
            }

            return $defaultCompany;
        });

        $companyNames = $itemsByCompany->keys()->filter()->values();
        $supplierDirectory = Supplier::whereIn('name', $companyNames)->get()->keyBy('name');

        return $itemsByCompany->map(function ($items, $companyName) use ($supplierDirectory) {
            $supplier = $supplierDirectory->get($companyName);
            $address = trim((string) ($supplier?->address ?? 'Address'));

            return [
                'company_name' => $companyName,
                'vendor' => [
                    'name' => $supplier?->name ?? $companyName,
                    'address_lines' => array_values(array_filter(preg_split('/\r\n|\r|\n/', $address) ?: ['Address'])),
                    'phone' => $supplier?->phone,
                ],
                'items' => collect($items)->values(),
            ];
        })->values();
    }

    private function roleView(string $view): string
    {
        $role = request()->user()?->normalizedRole();
        if (in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true) && view()->exists("admin.{$view}")) {
            return "admin.{$view}";
        }

        abort(404, "View not found for role: {$view}");
    }
}
