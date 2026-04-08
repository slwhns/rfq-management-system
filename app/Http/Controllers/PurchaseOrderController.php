<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ComponentSupplier;
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

        $currentUser = $request->user();
        $role = $currentUser?->normalizedRole();

        abort_unless(in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true), 403);
        abort_unless(Quote::normalizeStatus($quote->status) === Quote::STATUS_APPROVED, 422, 'Purchase Order can only be created from an approved Purchase Request.');

        $sections = $this->buildCompanySections($quote);
        abort_if($sections->isEmpty(), 422, 'No supplier section is available for this Purchase Request.');

        // Create POs for all company sections
        foreach ($sections as $section) {
            $items = collect($section['items'] ?? []);
            $supplier = $section['supplier'] ?? null;
            
            $vendor = $section['vendor'] ?? [
                'name' => $section['company_name'] ?? 'Vendor Name',
                'address_lines' => ['Address'],
            ];

            $this->upsertPurchaseOrder(
                $quote,
                $section['company_name'],
                $supplier?->id,
                $vendor,
                $items,
                (int) $currentUser->id
            );
        }

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase Orders created successfully for all suppliers.');
    }

    public function show(Request $request, PurchaseOrder $purchaseOrder)
    {
        $currentUser = $request->user();
        $role = $currentUser?->normalizedRole();

        abort_unless(in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true), 403);

        $shouldDownload = (bool) $request->boolean('download');
        $purchaseOrder->load(['purchaseRequest.project', 'purchaseRequest.items', 'purchaseRequest.approvedBy', 'purchaseRequest.statusHistories', 'supplier']);

        $quoteDiscountByComponent = collect($purchaseOrder->purchaseRequest?->items ?? [])
            ->groupBy('component_id')
            ->map(fn ($quoteItems) => round((float) ($quoteItems->first()->discount_percent ?? 0), 2));

        $items = $purchaseOrder->items()->with('component')->get()->map(function ($item) use ($quoteDiscountByComponent) {
            $storedDiscount = round((float) ($item->discount_percent ?? 0), 2);
            $fallbackDiscount = round((float) ($quoteDiscountByComponent->get($item->component_id) ?? 0), 2);

            // Prefer stored PO discount; fallback to PR discount to keep old POs aligned.
            $item->discount_percent = $storedDiscount > 0 ? $storedDiscount : $fallbackDiscount;

            return $item;
        });
        $subtotal = (float) $purchaseOrder->subtotal;
        $taxRate = round((float) ($purchaseOrder->purchaseRequest?->tax_rate ?? 0), 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $totalAmount = round($subtotal + $taxAmount, 2);
        $approvalDetails = $this->resolveApprovalDetails($purchaseOrder);

        $supplier = $this->resolveSupplierForPurchaseOrder($purchaseOrder, $items);
        if ($supplier && !$purchaseOrder->supplier_id) {
            $purchaseOrder->forceFill(['supplier_id' => $supplier->id])->save();
        }
        $vendorAddress = trim((string) ($supplier?->address ?: $purchaseOrder->vendor_address));
        $vendorPhone = $supplier?->phone ?: $purchaseOrder->vendor_phone;

        $deliverTo = [
            'name' => trim((string) ($currentUser?->company_name ?? 'Your Company Name')),
            'address_lines' => array_values(array_filter([
                $purchaseOrder->purchaseRequest->project->project_name ?? null,
                $purchaseOrder->purchaseRequest->project->location ?? null,
                'Malaysia',
            ])),
            'email' => $currentUser?->email,
        ];

        $vendor = [
            'name' => $supplier?->name ?? $purchaseOrder->vendor_name,
            'address_lines' => array_values(array_filter(preg_split('/\r\n|\r|\n/', $vendorAddress) ?: ['Address'])),
            'phone' => $vendorPhone,
        ];

        return view($this->roleView('purchase-orders.show'), [
            'quote' => $purchaseOrder->purchaseRequest,
            'poNumber' => $purchaseOrder->po_number,
            'purchaseOrder' => $purchaseOrder,
            'items' => $items,
            'subtotal' => $subtotal,
            'taxRate' => $taxRate,
            'taxAmount' => $taxAmount,
            'totalAmount' => $totalAmount,
            'vendor' => $vendor,
            'deliverTo' => $deliverTo,
            'shouldDownload' => $shouldDownload,
            'approvalDetails' => $approvalDetails,
        ]);
    }

    private function upsertPurchaseOrder(Quote $quote, string $companyName, ?int $supplierId, array $vendor, Collection $items, int $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($quote, $companyName, $supplierId, $vendor, $items, $userId) {
            $subtotal = (float) $items->sum(fn ($item) => (float) ($item->line_total ?? 0));
            $taxRate = (float) ($quote->tax_rate ?? 0);
            $taxAmount = $subtotal * ($taxRate / 100);
            $totalAmount = $subtotal + $taxAmount;

            $purchaseOrderQuery = PurchaseOrder::query()->where('purchase_request_id', (int) $quote->id);
            if ($supplierId !== null) {
                $purchaseOrderQuery->where('supplier_id', $supplierId);
            } else {
                $purchaseOrderQuery->where('company_name', $companyName);
            }

            $purchaseOrder = $purchaseOrderQuery->first() ?? new PurchaseOrder();

            if (!$purchaseOrder->exists) {
                $purchaseOrder->po_number = $this->generatePoNumber();
                $purchaseOrder->created_by = $userId;
                $purchaseOrder->status = 'draft';
            }

            $purchaseOrder->purchase_request_id = (int) $quote->id;
            $purchaseOrder->project_id = $quote->project_id;
            $purchaseOrder->supplier_id = $supplierId ?? $purchaseOrder->supplier_id;
            $purchaseOrder->company_name = $companyName;
            $purchaseOrder->vendor_name = (string) ($vendor['name'] ?? $companyName);
            $purchaseOrder->vendor_address = implode("\n", $vendor['address_lines'] ?? []);
            $purchaseOrder->vendor_phone = $vendor['phone'] ?? null;
            $purchaseOrder->subtotal = round($subtotal, 2);
            $purchaseOrder->total_amount = round($totalAmount, 2);
            $purchaseOrder->save();

            $purchaseOrder->items()->delete();

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'component_id' => $item->component_id,
                    'description' => trim((string) ($item->component->description ?? '')),
                    'quantity' => round((float) ($item->quantity ?? 0), 2),
                    'unit_price' => round((float) ($item->unit_price ?? 0), 2),
                    'discount_percent' => round((float) ($item->discount_percent ?? 0), 2),
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
                'supplier' => $supplier,
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

    private function resolveSupplierForPurchaseOrder(PurchaseOrder $purchaseOrder, Collection $items): ?Supplier
    {
        if ($purchaseOrder->relationLoaded('supplier') && $purchaseOrder->supplier) {
            return $purchaseOrder->supplier;
        }

        return $this->findSupplierByStoredIdentifiers($purchaseOrder) ?? $this->findSupplierByComponents($items);
    }

    private function resolveApprovalDetails(PurchaseOrder $purchaseOrder): array
    {
        $quote = $purchaseOrder->purchaseRequest;
        $approvalHistory = $quote?->statusHistories
            ?->where('to_status', Quote::STATUS_APPROVED)
            ->sortByDesc('created_at')
            ->first();
        $approvedBy = $quote?->approvedBy;

        return [
            'approval_date' => $approvalHistory?->created_at,
            'approved_by_name' => $approvedBy?->name,
            'approved_by_department' => $approvedBy?->department,
            'approved_by_email' => $approvedBy?->email,
            'approved_by_phone' => $approvedBy?->phone_number,
        ];
    }

    private function findSupplierByStoredIdentifiers(PurchaseOrder $purchaseOrder): ?Supplier
    {
        $supplier = null;

        if ($purchaseOrder->supplier_id) {
            $supplier = $this->findSupplierById((int) $purchaseOrder->supplier_id);
        }

        if (!$supplier) {
            $supplier = $this->findSupplierByName((string) ($purchaseOrder->company_name ?: $purchaseOrder->vendor_name));
        }

        return $supplier;
    }

    private function findSupplierById(int $supplierId): ?Supplier
    {
        return Supplier::query()->find($supplierId);
    }

    private function findSupplierByName(string $supplierName): ?Supplier
    {
        $supplierName = trim($supplierName);

        if ($supplierName === '') {
            return null;
        }

        return Supplier::query()->where('name', $supplierName)->first();
    }

    private function findSupplierByComponents(Collection $items): ?Supplier
    {
        $componentIds = $items->pluck('component_id')->filter()->unique()->values();

        $supplier = null;

        if ($componentIds->isNotEmpty()) {
            $supplierCounts = ComponentSupplier::query()
                ->whereIn('component_id', $componentIds)
                ->pluck('supplier_id')
                ->filter()
                ->countBy();

            if ($supplierCounts->isNotEmpty()) {
                $sortedCounts = $supplierCounts->sortDesc();
                $topSupplierId = (int) $sortedCounts->keys()->first();
                $topCount = (int) $sortedCounts->first();
                $secondCount = (int) ($sortedCounts->values()->skip(1)->first() ?? 0);

                if ($topCount > $secondCount) {
                    $supplier = $this->findSupplierById($topSupplierId);
                }
            }
        }

        return $supplier;
    }
}
