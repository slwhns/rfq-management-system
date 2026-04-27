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
    * Generate RFQ from project - PUBLIC
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
                    'message' => 'Cannot generate RFQ: Project has no components'
                ], 400);
            } else {
                // Generate RFQ using service
                $quotes = $this->quoteService->generateFromProject(
                    $project,
                    $currentUser->id,
                    $currentUser->department
                );

                if ($quotes->isEmpty()) {
                    $response = response()->json([
                        'success' => false,
                        'message' => 'Failed to generate RFQ: No line items found'
                    ], 422);
                } else {
                    $quoteNumbers = $quotes->pluck('quote_number')->values();
                    $quoteIds = $quotes->pluck('id')->values();
                    $totalAmount = (float) $quotes->sum('total_amount');

                    $response = response()->json([
                        'success' => true,
                        'message' => 'RFQ generated successfully',
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
                'message' => 'Failed to generate RFQ',
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
        $isAdminRole = in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true);
        $statusOptions = Quote::visibleStatusOptionsForRole($role);
        if ($role === User::ROLE_SUPERADMIN) {
            $quoteListSubtitle = 'RFQs submitted by clients.';
        } elseif ($role === User::ROLE_ADMIN) {
            $quoteListSubtitle = 'RFQs submitted by clients.';
        } else {
            $quoteListSubtitle = 'Client RFQ workspace list.';
        }
        $selectedStatus = (string) request()->query('status', '');
        $projectSearch = trim((string) request()->query('search', request()->query('project', '')));
        $filterStatuses = [
            Quote::STATUS_DRAFT,
            Quote::STATUS_SENT,
            Quote::STATUS_APPROVED,
            Quote::STATUS_DECLINED,
        ];

        $filterOptions = ['' => 'All status'] + collect($filterStatuses)
            ->filter(fn ($status) => isset($statusOptions[$status]))
            ->mapWithKeys(fn ($status) => [$status => $statusOptions[$status]])
            ->all();
        if ($selectedStatus !== '' && !array_key_exists($selectedStatus, $filterOptions)) {
            $filterOptions[$selectedStatus] = $statusOptions[$selectedStatus]
                ?? ucfirst(str_replace('_', ' ', Quote::normalizeStatus($selectedStatus)));
        }
        $statusStyles = Quote::statusBadgeStyles();
        $statusUpdateOptions = Quote::mutableStatusOptionsForRole($currentUser?->normalizedRole());

        $expandStatuses = static function (array $statuses): array {
            return collect($statuses)
                ->flatMap(function ($status) {
                    $normalizedStatus = Quote::normalizeStatus($status);

                    return collect(Quote::LEGACY_STATUS_MAP)
                        ->filter(fn ($mappedStatus) => $mappedStatus === $normalizedStatus)
                        ->keys()
                        ->push($normalizedStatus)
                        ->all();
                })
                ->unique()
                ->values()
                ->all();
        };

        $query = Quote::with(['project', 'createdByUser', 'approvedBy', 'adminNotesUpdatedBy', 'staffResponseUpdatedBy'])
            ->withCount(['statusHistories as submission_count' => function ($statusHistoryQuery) {
                $statusHistoryQuery->whereIn('to_status', [
                    Quote::STATUS_SENT,
                    'in_progress',
                    'negotiation',
                    'viewed',
                ]);
            }])
            ->whereIn('status', $expandStatuses($visibleStatuses));
        if ($isAdminRole) {
            $query->with(['statusHistories' => function ($statusHistoryQuery) {
                $statusHistoryQuery
                    ->where('to_status', Quote::STATUS_DRAFT)
                    ->where('status_note', 'like', 'RFQ re-issued from%')
                    ->latest();
            }])->where(function ($q) use ($expandStatuses) {
                $q->whereNotIn('status', $expandStatuses([Quote::STATUS_DRAFT]))
                  ->orWhereHas('createdByUser', function ($uQuery) {
                      $uQuery->whereIn('role', [User::ROLE_SUPERADMIN, User::ROLE_ADMIN]);
                  });
            });
        }
        if (in_array($role, [User::ROLE_CLIENT, User::ROLE_STAFF], true) && $currentUser) {
            $query->where('created_by', $currentUser->id);
        }
        $normalizedSelectedStatus = Quote::normalizeStatus($selectedStatus);
        if ($selectedStatus !== '' && in_array($normalizedSelectedStatus, $visibleStatuses, true)) {
            $query->whereIn('status', $expandStatuses([$normalizedSelectedStatus]));
        }
        if ($projectSearch !== '') {
            $query->where(function ($searchQuery) use ($projectSearch) {
                $searchQuery->whereHas('project', function ($projectQuery) use ($projectSearch) {
                    $projectQuery->where(function ($projectNameQuery) use ($projectSearch) {
                        $projectNameQuery->where('project_name', 'like', '%' . $projectSearch . '%')
                            ->orWhere('project_title', 'like', '%' . $projectSearch . '%');
                    });
                })->orWhereHas('createdByUser', function ($userQuery) use ($projectSearch) {
                    $userQuery->where('name', 'like', '%' . $projectSearch . '%')
                        ->orWhere('email', 'like', '%' . $projectSearch . '%');
                });
            });
        }

        $quotes = $query->latest()->paginate(7);
        if ($isAdminRole) {
            foreach ($quotes as $quote) {
                $latestReissueHistory = $quote->statusHistories->first();
                $quote->reissue_reason_label = $this->extractReissueReasonFromNote((string) ($latestReissueHistory->status_note ?? ''));
            }
        }
        if ($selectedStatus !== '' || $projectSearch !== '') {
            $quotes->appends([
                'status' => $selectedStatus,
                'search' => $projectSearch,
            ]);
        }

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $quotes,
                'status_options' => $statusOptions,
            ]);
        }

        return view($this->roleView('quotes.index'), compact(
            'quotes',
            'statusOptions',
            'filterOptions',
            'statusUpdateOptions',
            'statusStyles',
            'selectedStatus',
            'projectSearch',
            'quoteListSubtitle'
        ));
    }

    /**
    * Display specific RFQ - PUBLIC
     */
    public function show($id)
    {
        $quote = Quote::with(['project', 'items.component', 'createdByUser'])
            ->findOrFail($id);
        $this->enforceOwnQuoteForClientStaff(request()->user(), $quote);
        $this->enforceAdminCannotAccessClientDraft(request()->user(), $quote);

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

    public function submitRfq(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser) {
            abort(403, 'You do not have permission to submit RFQ.');
        }

        $quote = Quote::findOrFail($id);
        if ((int) ($quote->created_by ?? 0) !== 0 && (int) $quote->created_by !== (int) $currentUser->id) {
            return redirect()->route('rfqs.index')
                ->withErrors(['status' => 'You can only submit your own RFQ.']);
        }

        $fromStatus = Quote::normalizeStatus($quote->status);
        if ($fromStatus !== Quote::STATUS_DRAFT) {
            return redirect()->route('rfqs.show', $quote->id)
            ->withErrors(['status' => 'Only draft RFQs can be submitted.']);
        }

        $quote->update([
            'status' => Quote::STATUS_SENT,
            'date_requested' => $quote->date_requested ?? now(),
            'created_by' => $quote->created_by ?? $currentUser->id,
        ]);

        $this->logStatusChange(
            $quote,
            $fromStatus,
            Quote::STATUS_SENT,
            $currentUser->id,
            'RFQ submitted by client.'
        );

        return redirect()->route('rfqs.show', $quote->id)
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'RFQ submitted to admin successfully.');
    }

    public function reissueRfq(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
            abort(403, 'You do not have permission to re-issue RFQ.');
        }

        $quote = Quote::with(['items'])->findOrFail($id);
        $this->enforceOwnQuoteForClientStaff($currentUser, $quote);

        $validated = $request->validate([
            'reissue_reason' => ['required', Rule::in(['change_quantity', 'request_discount', 'others'])],
            'reissue_reason_custom' => ['nullable', 'string', 'max:500', 'required_if:reissue_reason,others'],
        ]);

        $fromStatus = Quote::normalizeStatus($quote->status);
        if (!in_array($fromStatus, [Quote::STATUS_APPROVED, Quote::STATUS_DECLINED], true)) {
            return redirect()->route('rfqs.show', $quote->id)
                ->withErrors(['status' => 'Only approved or rejected RFQs can be re-issued.']);
        }

        $reissueReasonKey = (string) $validated['reissue_reason'];
        $reissueReasonCustom = trim((string) ($validated['reissue_reason_custom'] ?? ''));
        $reissueReasonLabels = [
            'change_quantity' => 'Change quantity',
            'request_discount' => 'Request for discount',
            'others' => 'Others',
        ];
        $reissueReasonLabel = $reissueReasonLabels[$reissueReasonKey] ?? 'Others';
        if ($reissueReasonKey === 'others' && $reissueReasonCustom !== '') {
            $reissueReasonLabel .= ' - ' . $reissueReasonCustom;
        }

        $reissuedQuote = DB::transaction(function () use ($quote, $currentUser, $reissueReasonLabel, $reissueReasonKey) {
            $sourceQuoteNumber = (string) $quote->quote_number;
            $baseQuoteNumber = $sourceQuoteNumber;
            $currentVersion = 1;

            if (preg_match('/^(.*)-V(\d+)$/i', $sourceQuoteNumber, $sourceMatches)) {
                $baseQuoteNumber = (string) $sourceMatches[1];
                $currentVersion = (int) $sourceMatches[2];
            }

            $relatedQuoteNumbers = Quote::query()
                ->where('quote_number', $baseQuoteNumber)
                ->orWhere('quote_number', 'like', $baseQuoteNumber . '-V%')
                ->pluck('quote_number');

            $maxVersion = 1;
            foreach ($relatedQuoteNumbers as $relatedQuoteNumber) {
                if ($relatedQuoteNumber === $baseQuoteNumber) {
                    $maxVersion = max($maxVersion, 1);
                    continue;
                }

                if (preg_match('/^' . preg_quote($baseQuoteNumber, '/') . '-V(\d+)$/i', (string) $relatedQuoteNumber, $relatedMatches)) {
                    $maxVersion = max($maxVersion, (int) $relatedMatches[1]);
                }
            }

            $nextVersion = max($maxVersion, $currentVersion) + 1;
            $nextQuoteNumber = sprintf('%s-V%02d', $baseQuoteNumber, $nextVersion);

            $newQuote = Quote::create([
                'project_id' => $quote->project_id,
                'quote_number' => $nextQuoteNumber,
                'version' => $nextVersion,
                'subtotal' => (float) $quote->subtotal,
                'discount_total' => (float) $quote->discount_total,
                'discount_scope' => $quote->discount_scope,
                'discount_type' => $quote->discount_type,
                'discount_value' => $quote->discount_value !== null ? (float) $quote->discount_value : null,
                'tax_rate' => (float) $quote->tax_rate,
                'tax_amount' => (float) $quote->tax_amount,
                'total_amount' => (float) $quote->total_amount,
                'status' => Quote::STATUS_DRAFT,
                'created_by' => $currentUser->id,
                'date_requested' => now(),
                'date_needed' => $quote->date_needed,
                'department' => $quote->department,
                'approved_by' => null,
                'admin_notes' => null,
                'staff_response' => null,
                'quotation_sent_at' => null,
                'client_decision_note' => null,
                'client_decision_at' => null,
            ]);

            foreach ($quote->items as $item) {
                QuoteItem::create([
                    'quote_id' => $newQuote->id,
                    'component_id' => $item->component_id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'discount_percent' => (float) ($item->discount_percent ?? 0),
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value !== null ? (float) $item->discount_value : null,
                    'line_total' => (float) $item->line_total,
                ]);
            }

            QuoteStatusHistory::create([
                'quote_id' => $newQuote->id,
                'from_status' => null,
                'to_status' => Quote::STATUS_DRAFT,
                'status_note' => 'RFQ re-issued from ' . $quote->quote_number . '. Reason: ' . $reissueReasonLabel,
                'changed_by' => $currentUser->id,
            ]);

            if ($reissueReasonKey === 'request_discount') {
                $newQuote->update([
                    'status' => Quote::STATUS_SENT,
                ]);

                QuoteStatusHistory::create([
                    'quote_id' => $newQuote->id,
                    'from_status' => Quote::STATUS_DRAFT,
                    'to_status' => Quote::STATUS_SENT,
                    'status_note' => 'RFQ auto-submitted to admin for discount review.',
                    'changed_by' => $currentUser->id,
                ]);
            }

            return $newQuote;
        });

        if ($reissueReasonKey === 'request_discount') {
            return redirect()->route('rfqs.show', $reissuedQuote->id)
                ->with('toast_type', 'success')
                ->with('toast_title', 'Success')
                ->with('toast_message', 'RFQ re-issued and submitted to admin for discount review.');
        }

        return redirect()->route('rfqs.edit', $reissuedQuote->id)
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', 'RFQ re-issued successfully. Please edit and send the new RFQ.');
    }

    private function extractReissueReasonFromNote(?string $statusNote): ?string
    {
        $note = trim((string) $statusNote);
        if ($note === '') {
            return null;
        }

        if (preg_match('/Reason:\s*(.+)$/i', $note, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        return null;
    }

    /**
     * Edit quote
     */
    public function edit($id)
    {
        $quote = Quote::with(['project', 'items.component', 'createdByUser'])
            ->findOrFail($id);
        $this->enforceOwnQuoteForClientStaff(request()->user(), $quote);
        $this->enforceAdminCannotAccessClientDraft(request()->user(), $quote);

        $role = request()->user()?->normalizedRole();
        $normalizedStatus = Quote::normalizeStatus($quote->status);
        if (in_array($role, [User::ROLE_CLIENT, User::ROLE_STAFF], true) && $normalizedStatus === Quote::STATUS_SENT) {
            abort(403, 'Submitted RFQs can no longer be edited by client.');
        }

        if (in_array($role, [User::ROLE_CLIENT, User::ROLE_STAFF], true) && $normalizedStatus === Quote::STATUS_APPROVED) {
            abort(403, 'Approved RFQs can no longer be edited.');
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
        $quote = Quote::with(['items.component', 'project', 'createdByUser'])->findOrFail($id);
        $this->enforceOwnQuoteForClientStaff($request->user(), $quote);
        $this->enforceAdminCannotAccessClientDraft($request->user(), $quote);
        $normalizedStatus = Quote::normalizeStatus($quote->status);
        $role = $request->user()?->normalizedRole();
        if (in_array($role, [User::ROLE_CLIENT, User::ROLE_STAFF], true) && $normalizedStatus === Quote::STATUS_SENT) {
            abort(403, 'Submitted RFQs can no longer be updated by client.');
        }

        if (in_array($role, [User::ROLE_CLIENT, User::ROLE_STAFF], true) && $normalizedStatus === Quote::STATUS_APPROVED) {
            abort(403, 'Approved RFQs can no longer be updated.');
        }

        $allowedStatuses = array_unique(array_merge(
            [$normalizedStatus],
            array_keys(Quote::mutableStatusOptionsForRole($role))
        ));

        $validated = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
            'date_requested' => 'nullable|date',
            'date_needed' => 'nullable|date',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'client_note' => 'nullable|string|max:5000',
            'discount_scope' => ['nullable', Rule::in(['item', 'lumpsum'])],
            'discount_type' => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:purchase_request_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_value' => 'nullable|numeric|min:0',
        ]);

        $discountScope = $validated['discount_scope'] ?? $quote->discount_scope ?? 'item';
        $discountType = $validated['discount_type'] ?? $quote->discount_type ?? 'percent';
        if ($discountScope === 'lumpsum' && $discountType === 'percent') {
            $request->validate([
                'discount_value' => 'nullable|numeric|min:0|max:100',
            ]);
        }

        $incomingItems = collect($validated['items']);
        $incomingIds = $incomingItems->pluck('id')->map(fn($value) => (int) $value)->all();

        $ownedCount = QuoteItem::where('quote_id', $quote->id)
            ->whereIn('id', $incomingIds)
            ->count();

        if ($ownedCount !== count($incomingIds)) {
            return back()->withErrors(['items' => 'Invalid quote items submitted.'])->withInput();
        }

        $removedItemIds = QuoteItem::where('quote_id', $quote->id)
            ->whereNotIn('id', $incomingIds)
            ->pluck('id');

        $fromStatus = Quote::normalizeStatus($quote->status);
        $toStatus = Quote::normalizeStatus($validated['status']);

        if (!Quote::canUpdateStatusForRole($role, $fromStatus, $toStatus)) {
            abort(403, 'You do not have permission to update quote to this status.');
        }

        DB::transaction(function () use ($quote, $validated, $incomingItems, $fromStatus, $toStatus, $request, $role, $removedItemIds, $discountScope, $discountType) {
            $subtotal = 0.0;
            $discountTotal = 0.0;

            $quoteDiscountValue = array_key_exists('discount_value', $validated)
                ? (float) ($validated['discount_value'] ?? 0)
                : (float) ($quote->discount_value ?? 0);

            if ($discountType === 'percent') {
                $quoteDiscountValue = max(0, min(100, $quoteDiscountValue));
            } else {
                $quoteDiscountValue = max(0, $quoteDiscountValue);
            }

            foreach ($incomingItems as $itemData) {
                $quantity = (int) $itemData['quantity'];
                $quoteItem = collect($quote->items)->firstWhere('id', (int) $itemData['id']);
                $unitPrice = array_key_exists('unit_price', $itemData)
                    ? (float) ($itemData['unit_price'] ?? 0)
                    : (float) ($quoteItem?->unit_price ?? 0);

                $lineSubtotal = $quantity * $unitPrice;
                $lineDiscount = 0.0;
                $lineTotal = $lineSubtotal;
                $itemDiscountPercent = 0.0;
                $itemDiscountValue = 0.0;

                if ($discountScope === 'item') {
                    $itemDiscountValue = array_key_exists('discount_value', $itemData)
                        ? (float) ($itemData['discount_value'] ?? 0)
                        : (
                            array_key_exists('discount_percent', $itemData)
                                ? (float) ($itemData['discount_percent'] ?? 0)
                                : (
                                    $discountType === 'amount'
                                        ? (float) ($quoteItem?->discount_value ?? 0)
                                        : (float) ($quoteItem?->discount_percent ?? 0)
                                )
                        );

                    if ($discountType === 'percent') {
                        $itemDiscountValue = max(0, min(100, $itemDiscountValue));
                        $itemDiscountPercent = $itemDiscountValue;
                        $lineDiscount = $lineSubtotal * ($itemDiscountPercent / 100);
                    } else {
                        $itemDiscountValue = max(0, $itemDiscountValue);
                        $lineDiscount = min($itemDiscountValue, $lineSubtotal);
                        $itemDiscountPercent = $lineSubtotal > 0
                            ? ($lineDiscount / $lineSubtotal) * 100
                            : 0;
                    }

                    $lineTotal = $lineSubtotal - $lineDiscount;
                }

                $subtotal += $lineSubtotal;
                if ($discountScope === 'item') {
                    $discountTotal += $lineDiscount;
                }

                QuoteItem::where('id', $itemData['id'])->update([
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => round($itemDiscountPercent, 2),
                    'discount_type' => $discountScope === 'item' ? $discountType : 'percent',
                    'discount_value' => round($discountScope === 'item' ? $itemDiscountValue : 0, 2),
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

                        $notes['discount_percent'] = round($itemDiscountPercent, 2);

                        $projectComponent->update([
                            'quantity' => $quantity,
                            'notes' => empty($notes) ? null : json_encode($notes),
                        ]);
                    }
                }
            }

            if ($discountScope === 'lumpsum') {
                if ($discountType === 'percent') {
                    $discountTotal = $subtotal * ($quoteDiscountValue / 100);
                } else {
                    $discountTotal = min($quoteDiscountValue, $subtotal);
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
                'discount_scope' => $discountScope,
                'discount_type' => $discountType,
                'discount_value' => $discountScope === 'lumpsum' ? round($quoteDiscountValue, 2) : null,
                'tax_rate' => round($taxRate, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($totalAmount, 2),
            ];

            if ($toStatus === Quote::STATUS_APPROVED && $fromStatus !== Quote::STATUS_APPROVED) {
                $quoteUpdatePayload['approved_by'] = $request->user()?->id;
            }

            if (in_array($role, [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
                $clientNote = trim((string) ($validated['client_note'] ?? ''));
                $quoteUpdatePayload['staff_response'] = $clientNote !== '' ? $clientNote : null;
                $quoteUpdatePayload['staff_response_updated_at'] = $clientNote !== '' ? now() : null;
                $quoteUpdatePayload['staff_response_updated_by'] = $clientNote !== '' ? $request->user()?->id : null;
            }

            $quote->update($quoteUpdatePayload);

            if ($removedItemIds->isNotEmpty()) {
                QuoteItem::whereIn('id', $removedItemIds)->delete();
            }

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

        return redirect()->route('rfqs.show', $quote->id);
    }

    /**
     * Delete quote
     */
    public function destroy($id)
    {
        $quote = Quote::with(['createdByUser'])->findOrFail($id);
        $this->enforceOwnQuoteForClientStaff(request()->user(), $quote);
        $this->enforceAdminCannotAccessClientDraft(request()->user(), $quote);
        if (Quote::normalizeStatus($quote->status) === Quote::STATUS_APPROVED) {
            abort(403, 'Approved RFQs can no longer be deleted.');
        }

        $quote->delete();

        return redirect()->route('rfqs.index');
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

        $quote = Quote::with(['createdByUser'])->findOrFail($id);
        $this->enforceOwnQuoteForClientStaff($currentUser, $quote);
        $this->enforceAdminCannotAccessClientDraft($currentUser, $quote);
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
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update client response',
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
            'message' => 'Client response updated successfully',
            'data' => [
                'id' => $quote->id,
                'staff_response' => $quote->staff_response,
                'staff_response_updated_at' => optional($quote->staff_response_updated_at)->toDateTimeString(),
                'staff_response_updated_by' => $quote->staff_response_updated_by,
            ],
        ]);
    }

    public function sendQuotationToClient(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            abort(403, 'You do not have permission to send RFQ to client.');
        }

        $quote = Quote::findOrFail($id);
        $fromStatus = Quote::normalizeStatus($quote->status);
        $wasPreviouslyShared = $quote->quotation_sent_at !== null;

        if ($fromStatus !== Quote::STATUS_APPROVED) {
            return back()->withErrors(['status' => 'RFQ can only be shared after RFQ is approved.']);
        }

        $quote->update([
            'quotation_sent_at' => now(),
            // Re-share starts a new client decision cycle for the updated RFQ.
            'client_decision_note' => null,
            'client_decision_at' => null,
        ]);

        QuoteStatusHistory::create([
            'quote_id' => $quote->id,
            'from_status' => $fromStatus,
            'to_status' => $fromStatus,
            'status_note' => $wasPreviouslyShared
                ? 'RFQ re-shared with client. Client decision cycle reset.'
                : 'RFQ shared with client.',
            'changed_by' => $currentUser->id,
        ]);

        return redirect()->route('rfqs.show', $quote->id)
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', $wasPreviouslyShared
                ? 'RFQ re-shared with client successfully. Client can submit a new decision.'
                : 'RFQ shared with client successfully.');
    }

    public function submitClientDecision(Request $request, $id)
    {
        $currentUser = $request->user();
        if (! $currentUser || ! in_array($currentUser->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
            abort(403, 'You do not have permission to submit client decision.');
        }

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'client_decision_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $quote = Quote::findOrFail($id);

        if ((int) $quote->created_by !== (int) $currentUser->id) {
            abort(403, 'You can only decide your own RFQ.');
        }

        if ($quote->quotation_sent_at === null) {
            return back()->withErrors(['decision' => 'RFQ has not been sent by admin yet.']);
        }

        if ($quote->client_decision_at !== null) {
            return back()->withErrors(['decision' => 'A client decision has already been submitted for this RFQ share.']);
        }

        $fromStatus = Quote::normalizeStatus($quote->status);

        $decision = (string) $validated['decision'];
        $toStatus = $decision === 'accepted' ? Quote::STATUS_APPROVED : Quote::STATUS_DECLINED;
        $decisionNote = isset($validated['client_decision_note'])
            ? trim((string) $validated['client_decision_note'])
            : null;

        $quoteUpdatePayload = [
            'status' => $toStatus,
            'client_decision_note' => $decisionNote ?: null,
            'client_decision_at' => now(),
        ];

        if ($decision === 'rejected') {
            $quoteUpdatePayload['approved_by'] = null;
        }

        $quote->update($quoteUpdatePayload);

        $this->logStatusChange(
            $quote,
            $fromStatus,
            $toStatus,
            $currentUser->id,
            $decision === 'accepted'
                ? 'Client accepted RFQ.'
                : 'Client rejected RFQ.'
        );

        return redirect()->route('rfqs.show', $quote->id)
            ->with('toast_type', 'success')
            ->with('toast_title', 'Success')
            ->with('toast_message', $decision === 'accepted' ? 'RFQ accepted.' : 'RFQ rejected.');
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

    private function enforceOwnQuoteForClientStaff(?User $currentUser, Quote $quote): void
    {
        if (! $currentUser) {
            return;
        }

        if (in_array($currentUser->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
            abort_unless((int) $quote->created_by === (int) $currentUser->id, 403, 'You can only access your own RFQ.');
        }
    }

    private function enforceAdminCannotAccessClientDraft(?User $currentUser, Quote $quote): void
    {
        if (! $currentUser) {
            return;
        }

        if (in_array($currentUser->normalizedRole(), [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            if (Quote::normalizeStatus($quote->status) === Quote::STATUS_DRAFT) {
                $creatorRole = $quote->createdByUser?->normalizedRole();
                if (!in_array($creatorRole, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
                    abort(403, 'You cannot access a client\'s draft RFQ until it is submitted.');
                }
            }
        }
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
