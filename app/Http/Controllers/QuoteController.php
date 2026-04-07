<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComponent;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\QuoteStatusHistory;
use App\Models\Supplier;
use App\Models\User;
use App\Http\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    protected $quoteService;

    public function __construct(QuoteService $quoteService)
    {
        $this->quoteService = $quoteService;
    }

    /**
    * Generate purchase request from project - PUBLIC
     */
    public function generate(Request $request)
    {
        $response = null;
        $currentUser = $request->user();

        if (! $currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $request->validate([
            'project_id' => 'required|exists:projects,id'
        ]);

        try {
            $project = Project::findOrFail($request->project_id);

            // Check if project has components
            if ($project->components()->count() === 0) {
                $response = response()->json([
                    'success' => false,
                    'message' => 'Cannot generate purchase request: Project has no components'
                ], 400);
            } else {
                // Generate purchase request using service
                $quotes = $this->quoteService->generateFromProject(
                    $project,
                    $currentUser->id,
                    $currentUser->department
                );

                if ($quotes->isEmpty()) {
                    $response = response()->json([
                        'success' => false,
                        'message' => 'Failed to generate purchase request: No line items found'
                    ], 422);
                } else {
                    $quoteNumbers = $quotes->pluck('quote_number')->values();
                    $quoteIds = $quotes->pluck('id')->values();
                    $totalAmount = (float) $quotes->sum('total_amount');

                    $response = response()->json([
                        'success' => true,
                        'message' => 'Purchase Request generated successfully',
                        'data' => [
                            'quote_number' => $quoteNumbers->first(),
                            'quote_numbers' => $quoteNumbers,
                            'ids' => $quoteIds,
                            'total' => $totalAmount
                        ]
                    ]);
                }
            }
        } catch (\Exception $e) {
            $response = response()->json([
                'success' => false,
                'message' => 'Failed to generate purchase request',
                'error' => $e->getMessage()
            ], 500);
        }

        return $response;
    }

    /**
     * Display all quotes - PUBLIC
     */
    public function index()
    {
        $currentUser = request()->user();
        $role = $currentUser?->normalizedRole();
        $visibleStatuses = Quote::visibleStatusesForRole($role);
        $statusOptions = Quote::visibleStatusOptionsForRole($role);
        if ($role === User::ROLE_SUPERADMIN) {
            $quoteListSubtitle = 'Superadmin purchase request review list.';
        } elseif ($role === User::ROLE_ADMIN) {
            $quoteListSubtitle = 'Admin purchase request workspace list.';
        } else {
            $quoteListSubtitle = 'Staff purchase request workspace list.';
        }
        $selectedStatus = (string) request()->query('status', '');
        $projectSearch = trim((string) request()->query('project', ''));
        $statusStyles = Quote::statusBadgeStyles();
        $statusUpdateOptions = Quote::mutableStatusOptionsForRole($currentUser?->normalizedRole());

        $query = Quote::with(['project', 'approvedBy', 'adminNotesUpdatedBy', 'staffResponseUpdatedBy'])
            ->whereIn('status', $visibleStatuses);
        if (in_array($selectedStatus, $visibleStatuses, true)) {
            $query->where('status', $selectedStatus);
        }
        if ($projectSearch !== '') {
            $query->whereHas('project', function ($projectQuery) use ($projectSearch) {
                $projectQuery->where('project_name', 'like', '%' . $projectSearch . '%');
            });
        }

        $quotes = $query->latest()->paginate(8);
        if ($selectedStatus !== '' || $projectSearch !== '') {
            $quotes->appends([
                'status' => $selectedStatus,
                'project' => $projectSearch,
            ]);
        }

        $statusCounts = Quote::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereIn('status', $visibleStatuses)
            ->groupBy('status')
            ->pluck('total', 'status');

        $statusSummary = [];
        foreach ($statusOptions as $status => $label) {
            $statusSummary[$status] = (int) ($statusCounts[$status] ?? 0);
        }

        $allQuotesCount = Quote::count();

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $quotes,
                'status_summary' => $statusSummary,
                'status_options' => $statusOptions,
            ]);
        }

        return view($this->roleView('quotes.index'), compact(
            'quotes',
            'statusOptions',
            'statusUpdateOptions',
            'statusStyles',
            'statusSummary',
            'selectedStatus',
            'projectSearch',
            'allQuotesCount',
            'quoteListSubtitle'
        ));
    }

    /**
     * Display specific purchase request - PUBLIC
     */
    public function show($id)
    {
        $quote = Quote::with(['project', 'items.component'])
            ->findOrFail($id);

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

            foreach ($projectComponents as $component) {
                $notes = json_decode((string) $component->notes, true);
                $supplierName = trim((string) (($notes['supplier_name'] ?? '') ?: $defaultCompany));

                if (!isset($companyQueueByComponent[$component->component_id])) {
                    $companyQueueByComponent[$component->component_id] = [];
                }

                $companyQueueByComponent[$component->component_id][] = $supplierName;
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

        $companySections = $itemsByCompany->map(function ($items, $companyName) use ($supplierDirectory, $quote) {
            $supplier = $supplierDirectory->get($companyName);

            return [
                'company_name' => $companyName,
                'bill_to' => [
                    'name' => $supplier?->name ?? $companyName,
                    'address' => $supplier?->address ?? ($quote->project->location ?? 'Street Address'),
                    'phone' => $supplier?->phone ?? '-',
                ],
                'items' => collect($items)->values(),
            ];
        })->values();

        $billTo = $companySections->first()['bill_to'] ?? [
            'name' => $defaultCompany,
            'address' => $quote->project->location ?? 'Street Address',
            'phone' => '-',
        ];

        return view($this->roleView('quotes.show'), compact('quote', 'billTo', 'companySections'));
    }

    /**
     * Edit quote
     */
    public function edit($id)
    {
        $quote = Quote::with(['project', 'items.component'])
            ->findOrFail($id);

        if (Quote::normalizeStatus($quote->status) === Quote::STATUS_APPROVED) {
            abort(403, 'Approved purchase requests can no longer be edited.');
        }

        $statusOptions = Quote::mutableStatusOptionsForRole(request()->user()?->normalizedRole());
        if (!array_key_exists(Quote::normalizeStatus($quote->status), $statusOptions)) {
            $allOptions = Quote::statusOptions();
            $normalized = Quote::normalizeStatus($quote->status);
            $statusOptions = [$normalized => ($allOptions[$normalized] ?? ucfirst(str_replace('_', ' ', $normalized)))] + $statusOptions;
        }

        return view($this->roleView('quotes.edit'), compact('quote', 'statusOptions'));
    }

    /**
     * Update quote
     */
    public function update(Request $request, $id)
    {
        $quote = Quote::with(['items.component', 'project'])->findOrFail($id);
        if (Quote::normalizeStatus($quote->status) === Quote::STATUS_APPROVED) {
            abort(403, 'Approved purchase requests can no longer be updated.');
        }

        $role = $request->user()?->normalizedRole();
        $allowedStatuses = array_unique(array_merge(
            [Quote::normalizeStatus($quote->status)],
            array_keys(Quote::mutableStatusOptionsForRole($role))
        ));

        $validated = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
            'date_requested' => 'nullable|date',
            'date_needed' => 'nullable|date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:purchase_request_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $incomingItems = collect($validated['items']);
        $incomingIds = $incomingItems->pluck('id')->map(fn($value) => (int) $value)->all();

        $ownedCount = QuoteItem::where('quote_id', $quote->id)
            ->whereIn('id', $incomingIds)
            ->count();

        if ($ownedCount !== count($incomingIds)) {
            return back()->withErrors(['items' => 'Invalid quote items submitted.'])->withInput();
        }

        $fromStatus = Quote::normalizeStatus($quote->status);
        $toStatus = Quote::normalizeStatus($validated['status']);

        if (!Quote::canUpdateStatusForRole($role, $fromStatus, $toStatus)) {
            abort(403, 'You do not have permission to update quote to this status.');
        }

        DB::transaction(function () use ($quote, $validated, $incomingItems, $fromStatus, $toStatus, $request) {
            $subtotal = 0.0;
            $discountTotal = 0.0;

            foreach ($incomingItems as $itemData) {
                $quantity = (int) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];
                $discountPercent = (float) ($itemData['discount_percent'] ?? 0);
                $quoteItem = collect($quote->items)->firstWhere('id', (int) $itemData['id']);

                $lineSubtotal = $quantity * $unitPrice;
                $lineDiscount = $lineSubtotal * ($discountPercent / 100);
                $lineTotal = $lineSubtotal - $lineDiscount;

                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;

                QuoteItem::where('id', $itemData['id'])->update([
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'line_total' => round($lineTotal, 2),
                ]);

                if ($quote->project && $quoteItem?->component_id) {
                    $projectComponent = ProjectComponent::where('project_id', $quote->project_id)
                        ->where('component_id', $quoteItem->component_id)
                        ->first();

                    if ($projectComponent) {
                        $notes = [];
                        if (!empty($projectComponent->notes)) {
                            $decodedNotes = json_decode((string) $projectComponent->notes, true);
                            if (is_array($decodedNotes)) {
                                $notes = $decodedNotes;
                            }
                        }

                        $notes['discount_percent'] = $discountPercent;

                        $projectComponent->update([
                            'quantity' => $quantity,
                            'notes' => empty($notes) ? null : json_encode($notes),
                        ]);
                    }
                }
            }

            $afterDiscount = $subtotal - $discountTotal;
            $taxRate = (float) $validated['tax_rate'];
            $taxAmount = $afterDiscount * ($taxRate / 100);
            $totalAmount = $afterDiscount + $taxAmount;

            $quoteUpdatePayload = [
                'status' => $validated['status'],
                'date_requested' => $validated['date_requested'] ?? null,
                'date_needed' => $validated['date_needed'] ?? null,
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'tax_rate' => round($taxRate, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($totalAmount, 2),
            ];

            if ($toStatus === Quote::STATUS_APPROVED && $fromStatus !== Quote::STATUS_APPROVED) {
                $quoteUpdatePayload['approved_by'] = $request->user()?->id;
            }

            $quote->update($quoteUpdatePayload);

            if ($quote->project) {
                $quote->project->update([
                    'tax_rate' => round($taxRate, 2),
                ]);
            }
        });

        $this->logStatusChange(
            $quote,
            $fromStatus,
            Quote::normalizeStatus($validated['status']),
            $request->user()?->id
        );

        return redirect()->route('quotes.show', $quote->id);
    }

    /**
     * Delete quote
     */
    public function destroy($id)
    {
        $quote = Quote::findOrFail($id);
        if (Quote::normalizeStatus($quote->status) === Quote::STATUS_APPROVED) {
            abort(403, 'Approved purchase requests can no longer be deleted.');
        }

        $quote->delete();

        return redirect()->route('quotes.index');
    }

    /**
     * Update quote status - API
     */
    public function updateStatus(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $allowedStatuses = array_keys(Quote::mutableStatusOptionsForRole($currentUser->normalizedRole()));
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in($allowedStatuses)],
            'status_note' => ['nullable', 'string', 'max:500'],
        ]);

        $quote = Quote::findOrFail($id);
        $fromStatus = Quote::normalizeStatus($quote->status);
        $toStatus = Quote::normalizeStatus($validated['status']);

        if (!Quote::canUpdateStatusForRole($currentUser->normalizedRole(), $fromStatus, $toStatus)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to set this status',
            ], 403);
        }

        $quoteUpdatePayload = ['status' => $toStatus];
        if ($toStatus === Quote::STATUS_APPROVED && $fromStatus !== Quote::STATUS_APPROVED) {
            $quoteUpdatePayload['approved_by'] = $currentUser->id;
        }

        $quote->update($quoteUpdatePayload);

        $this->logStatusChange(
            $quote,
            $fromStatus,
            $toStatus,
            $currentUser->id,
            $validated['status_note'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $quote
        ]);
    }

    public function updateAdminNotes(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update admin notes',
            ], 403);
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $quote = Quote::findOrFail($id);
        $adminNotes = trim((string) ($validated['admin_notes'] ?? '')) ?: null;
        $quote->update([
            'admin_notes' => $adminNotes,
            'admin_notes_updated_at' => $adminNotes ? now() : null,
            'admin_notes_updated_by' => $adminNotes ? $currentUser->id : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Admin note updated successfully',
            'data' => [
                'id' => $quote->id,
                'admin_notes' => $quote->admin_notes,
                'admin_notes_updated_at' => optional($quote->admin_notes_updated_at)->toDateTimeString(),
                'admin_notes_updated_by' => $quote->admin_notes_updated_by,
            ],
        ]);
    }

    public function updateStaffResponse(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || $currentUser->normalizedRole() !== User::ROLE_STAFF) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update staff response',
            ], 403);
        }

        $validated = $request->validate([
            'staff_response' => ['nullable', 'string', 'max:5000'],
        ]);

        $quote = Quote::findOrFail($id);
        $staffResponse = trim((string) ($validated['staff_response'] ?? '')) ?: null;
        $quote->update([
            'staff_response' => $staffResponse,
            'staff_response_updated_at' => $staffResponse ? now() : null,
            'staff_response_updated_by' => $staffResponse ? $currentUser->id : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Staff response updated successfully',
            'data' => [
                'id' => $quote->id,
                'staff_response' => $quote->staff_response,
                'staff_response_updated_at' => optional($quote->staff_response_updated_at)->toDateTimeString(),
                'staff_response_updated_by' => $quote->staff_response_updated_by,
            ],
        ]);
    }

    private function roleView(string $view): string
    {
        $role = request()->user()?->normalizedRole();
        if (in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true) && view()->exists("admin.{$view}")) {
            return "admin.{$view}";
        }

        if (view()->exists("staff.{$view}")) {
            return "staff.{$view}";
        }

        abort(404, "View not found for role: {$view}");
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
