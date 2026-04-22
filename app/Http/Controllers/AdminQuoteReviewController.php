<?php

namespace App\Http\Controllers;

use App\Models\ProjectComponent;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quote;
use App\Models\QuoteStatusHistory;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminQuoteReviewController extends Controller
{
    public function accept(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            abort(403, 'You do not have permission to accept RFQ.');
        }

        $quote = Quote::with(['project', 'items.component', 'createdByUser'])->findOrFail($id);
        $fromStatus = Quote::normalizeStatus($quote->status);
        if (in_array($fromStatus, [Quote::STATUS_APPROVED, Quote::STATUS_DECLINED], true)) {
            return back()->withErrors(['status' => 'This RFQ has already been finalized.']);
        }

        DB::transaction(function () use ($quote, $currentUser, $fromStatus) {
            $quote->update([
                'status' => Quote::STATUS_APPROVED,
                'approved_by' => $currentUser->id,
            ]);

            $this->createPurchaseOrdersForQuote($quote, $currentUser);

            $this->logStatusChange(
                $quote,
                $fromStatus,
                Quote::STATUS_APPROVED,
                $currentUser->id,
                'RFQ accepted by admin.'
            );
        });

        return redirect()->route('rfqs.show', $quote->id)
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'RFQ approved. You may now apply discount updates and share the final PDF with client.');
    }

    public function reject(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            abort(403, 'You do not have permission to reject RFQ.');
        }

        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:2000'],
        ]);

        $quote = Quote::findOrFail($id);
        $fromStatus = Quote::normalizeStatus($quote->status);
        if (in_array($fromStatus, [Quote::STATUS_APPROVED, Quote::STATUS_DECLINED], true)) {
            return back()->withErrors(['status' => 'This RFQ has already been finalized.']);
        }

        $rejectReason = trim((string) $validated['reject_reason']);

        $quote->update([
            'status' => Quote::STATUS_DECLINED,
            'admin_notes' => $rejectReason,
            'admin_notes_updated_at' => now(),
            'admin_notes_updated_by' => $currentUser->id,
        ]);

        $this->logStatusChange(
            $quote,
            $fromStatus,
            Quote::STATUS_DECLINED,
            $currentUser->id,
            'RFQ rejected by admin: ' . $rejectReason
        );

        return redirect()->route('rfqs.show', $quote->id)
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'RFQ rejected successfully.');
    }

    private function createPurchaseOrdersForQuote(Quote $quote, User $currentUser): void
    {
        $companySections = $this->buildPurchaseOrderSections($quote);
        abort_if($companySections->isEmpty(), 422, 'No supplier section is available for this RFQ.');

        foreach ($companySections as $section) {
            $items = collect($section['items'] ?? []);
            $supplier = $section['supplier'] ?? null;
            $vendor = $section['vendor'] ?? [
                'name' => $section['company_name'] ?? 'Vendor Name',
                'address_lines' => ['Address'],
                'phone' => null,
            ];

            $subtotal = round((float) $items->sum(fn ($item) => (float) ($item->line_total ?? 0)), 2);
            $taxRate = round((float) ($quote->tax_rate ?? 0), 2);
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $totalAmount = round($subtotal + $taxAmount, 2);

            $purchaseOrderQuery = PurchaseOrder::query()->where('purchase_request_id', (int) $quote->id);
            if ($supplier?->id) {
                $purchaseOrderQuery->where('supplier_id', $supplier->id);
            } else {
                $purchaseOrderQuery->where('company_name', (string) ($section['company_name'] ?? 'General'));
            }

            $purchaseOrder = $purchaseOrderQuery->first() ?? new PurchaseOrder();

            if (! $purchaseOrder->exists) {
                $purchaseOrder->po_number = $this->generatePoNumber();
                $purchaseOrder->created_by = $currentUser->id;
                $purchaseOrder->status = 'draft';
            }

            $purchaseOrder->purchase_request_id = (int) $quote->id;
            $purchaseOrder->project_id = $quote->project_id;
            $purchaseOrder->supplier_id = $supplier?->id;
            $purchaseOrder->company_name = (string) ($section['company_name'] ?? 'General');
            $purchaseOrder->vendor_name = (string) ($vendor['name'] ?? $section['company_name'] ?? 'Vendor Name');
            $purchaseOrder->vendor_address = implode("\n", $vendor['address_lines'] ?? []);
            $purchaseOrder->vendor_phone = $vendor['phone'] ?? null;
            $purchaseOrder->subtotal = $subtotal;
            $purchaseOrder->total_amount = $totalAmount;
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
        }
    }

    private function generatePoNumber(): string
    {
        $last = PurchaseOrder::query()->orderByDesc('id')->first();
        $next = $last ? ((int) preg_replace('/\D/', '', (string) $last->po_number)) + 1 : 1;

        return sprintf('PO-%05d', $next);
    }

    private function buildPurchaseOrderSections(Quote $quote): Collection
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

                if (! isset($companyQueueByComponent[$projectComponent->component_id])) {
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

    protected function logStatusChange(Quote $quote, ?string $fromStatus, string $toStatus, ?int $changedBy, ?string $statusNote = null): void
    {
        if ($fromStatus === $toStatus) {
            return;
        }

        QuoteStatusHistory::create([
            'quote_id' => $quote->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'status_note' => $statusNote,
            'changed_by' => $changedBy,
        ]);
    }
}
