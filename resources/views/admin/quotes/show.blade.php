@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quote-show.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-quote-review.css') }}">

<div class="d-flex jc-between ai-center mg-b-20 quote-toolbar">
    <a href="{{ route('rfqs.index') }}" class="fs-12 clr-blue txt-none">Back</a>
    <div class="d-flex ai-center quote-toolbar-actions">
        @php
            $currentRole = auth()->user()?->normalizedRole();
            $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
            $isApprovedStatus = $normalizedStatus === \App\Models\Quote::STATUS_APPROVED;
            $isDeclinedStatus = $normalizedStatus === \App\Models\Quote::STATUS_DECLINED;
            $canTakeAdminAction = in_array($currentRole, [\App\Models\User::ROLE_SUPERADMIN, \App\Models\User::ROLE_ADMIN], true)
                && !in_array($normalizedStatus, [\App\Models\Quote::STATUS_APPROVED, \App\Models\Quote::STATUS_DECLINED], true);
        @endphp
           @php
               $statusBadgeStyles = \App\Models\Quote::statusBadgeStyles();
               $statusStyle = $statusBadgeStyles[$normalizedStatus] ?? [];
               $topStatusLabel = $normalizedStatus === \App\Models\Quote::STATUS_SENT
                   ? ''
                   : (\App\Models\Quote::statusOptions()[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus)));
           @endphp
           @if($topStatusLabel !== '')
               <div style="margin-right: 20px; padding: 6px 12px; background-color: {{ $statusStyle['background'] ?? '#eff1f7' }}; color: {{ $statusStyle['color'] ?? '#4a5470' }}; border-radius: 5px; font-size: 13px; font-weight: 500; border: 1px solid {{ $statusStyle['border'] ?? '#dbe0ee' }};">
                   {{ $topStatusLabel }}
               </div>
           @endif
        @if($canTakeAdminAction)
            <form action="{{ route('rfqs.accept', $quote->id) }}" method="POST" style="display:inline;">
                @csrf
                    <button type="submit" class="admin-action-btn admin-action-approve">Approve</button>
            </form>
            <button type="button" id="open-reject-modal" class="admin-action-btn admin-action-reject">Reject</button>
            <a href="{{ route('rfqs.edit', $quote->id) }}" class="admin-action-btn admin-action-print txt-none d-inline-flex ai-center jc-center">Edit Discount</a>
        @endif
        @if($normalizedStatus === \App\Models\Quote::STATUS_APPROVED)
            <a href="{{ route('rfqs.edit', $quote->id) }}" class="admin-action-btn admin-action-print txt-none d-inline-flex ai-center jc-center">Edit Discount</a>
            <form action="{{ route('rfqs.send-quotation', $quote->id) }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="admin-action-btn admin-action-approve">
                    {{ $quote->quotation_sent_at ? 'Re-share RFQ' : 'Share RFQ' }}
                </button>
            </form>
        @endif
        <button type="button" class="admin-action-btn admin-action-print quote-print-btn" data-print-trigger="true" data-print-quote-id="{{ $quote->quote_number }}" data-print-project-name="{{ $quote->project->project_name ?? 'project' }}">Print</button>
    </div>
</div>

@if($quote->quotation_sent_at)
    <div class="bg-white5 pd-10 br-10 mg-b-15 fs-12" style="border:1px solid #d9e4ff; color:#35508f;">
        RFQ was shared with client on {{ optional($quote->quotation_sent_at)->format('d M Y, h:i A') }}.
    </div>
@endif

<div id="reject-rfq-modal" class="reject-modal-backdrop" aria-hidden="true">
    <dialog class="reject-modal-card" aria-labelledby="reject-modal-title" open>
        <div class="reject-modal-head">
            <h3 id="reject-modal-title" class="reject-modal-title">Reject RFQ</h3>
        </div>
        <form action="{{ route('rfqs.reject', $quote->id) }}" method="POST" class="reject-modal-body">
            @csrf
            <textarea id="reject-reason-input" name="reject_reason" class="reject-modal-textarea" placeholder="Type rejection reason..." required>{{ old('reject_reason') }}</textarea>
            @error('reject_reason')
                <div class="reject-modal-error">{{ $message }}</div>
            @enderror
            <div class="reject-modal-actions">
                <button type="button" id="close-reject-modal" class="reject-btn-cancel">Cancel</button>
                <button type="submit" class="reject-btn-submit">Confirm Reject</button>
            </div>
        </form>
    </dialog>
</div>

<div id="quote-template" class="bg-white pd-30 br-10 box-shadow-basic quote-template-card">
    @php
        $requesterName = optional($quote->createdByUser)->name ?? 'Unknown User';
        $companyName = optional($quote->createdByUser)->company_name ?? 'QS Smart Data Center';
        $dateRequested = optional($quote->date_requested)->format('d M Y') ?: optional($quote->created_at)->format('d M Y');
        $dateNeeded = optional($quote->date_needed)->format('d M Y') ?: '-';
        $statusLabels = \App\Models\Quote::statusOptions();
        $statusLabel = $statusLabels[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus));
        $templateStatusLabel = $normalizedStatus === \App\Models\Quote::STATUS_SENT ? '' : $statusLabel;
    @endphp

    <div class="d-flex jc-between ai-start mg-b-25">
        <div class="fg-1">
            <div class="fs-24 fw-bold">KHALEEF NET SDN BHD</div>
            <div class="mg-t-8 fs-14">
                LOT 133 BLOCK P, 1ST FLOOR, LORONG PLAZA PERMAI 2<br>
                ALAMESRA<br>
                SULAMAN COASTAL HIGHWAY<br>
                KOTA KINABALU Sabah 88450<br>
                Malaysia<br>
                account@khaleefnet.com
            </div>
        </div>

        <div class="quote-header-right">
            <div class="fs-30 fw-bold quote-title">REQUEST FOR QUOTATION</div>
            <div class="mg-t-8 mg-b-20 fs-14 fw-bold"># {{ $quote->quote_number }}</div>
               @php
                   $isResubmitted = $quote->statusHistories()
                       ->where('from_status', 'declined')
                       ->where('to_status', 'sent')
                       ->exists();
               @endphp
               @if($isResubmitted)
                   <div style="margin-bottom:12px; padding: 6px 10px; background-color: #fff3cd; color: #856404; border-radius: 4px; font-size: 12px; font-weight: 600; border: 1px solid #ffeaa7; display: inline-block;">
                       RESUBMITTED
                   </div>
                   <div style="margin-bottom:20px;"></div>
               @endif

            <div class="quote-meta-grid">
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Requested By :</div>
                    <div class="quote-meta-value">{{ $requesterName }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Company :</div>
                    <div class="quote-meta-value">{{ $companyName }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Project :</div>
                    <div class="quote-meta-value">{{ $quote->project->project_name ?? '-' }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Date Requested :</div>
                    <div class="quote-meta-value">{{ $dateRequested }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Date Needed :</div>
                    <div class="quote-meta-value">{{ $dateNeeded }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label" style="white-space: nowrap;">Purchasing Dept Use Only :</div>
                    <div class="quote-meta-value quote-meta-value-compact">{{ $templateStatusLabel }}</div>
                </div>
            </div>
        </div>
    </div>

    <table class="quote-items-table">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th>QTY</th>
                <th>UOM</th>
                <th>Price</th>
                <th>AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineItemCount = 0;
            @endphp
            @forelse($quote->items as $item)
                @php
                    $lineItemCount++;
                    $component = $item->component;
                    $itemSku = trim((string) ($component->component_code ?? ''));
                    $itemUnit = trim((string) ($component->unit ?? ''));
                    $uomLabel = $itemUnit !== '' ? ucfirst(strtolower($itemUnit)) : '-';
                    $lineSubtotal = (float) $item->quantity * (float) $item->unit_price;
                    $lineDiscount = max(0, $lineSubtotal - (float) $item->line_total);
                    $discountPercent = (float) ($item->discount_percent ?? 0);
                @endphp
                <tr>
                    <td>
                        <div class="fw-bold">{{ $itemSku !== '' ? $itemSku : '-' }}</div>
                    </td>
                    <td>
                        <div class="fw-bold">{{ $component->component_name ?? 'Item' }}</div>
                        @if($discountPercent > 0 || $lineDiscount > 0)
                            <div class="fs-12 clr-grey1">Discount: {{ number_format($discountPercent, 2) }}% (RM{{ number_format($lineDiscount, 2) }})</div>
                        @endif
                        @if(!empty($component->description))
                            <div class="fs-12 clr-grey1">{{ $component->description }}</div>
                        @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $uomLabel }}</td>
                    <td>RM{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>RM{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No line items</td>
                </tr>
            @endforelse

            @for($i = $lineItemCount; $i < 4; $i++)
                <tr class="quote-empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor

        </tbody>
    </table>

    <div class="d-flex jc-end mg-t-15">
        <table class="quote-summary-table">
            <thead style="display:none;">
                <tr>
                    <th>Label</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tr>
                <td>Subtotal</td>
                <td>RM{{ number_format((float) $quote->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Taxable</td>
                <td>RM{{ number_format((float) ($quote->subtotal - $quote->discount_total), 2) }}</td>
            </tr>
            <tr>
                <td>Tax Rate</td>
                <td>{{ number_format((float) $quote->tax_rate, 2) }}%</td>
            </tr>
            <tr>
                <td>Tax Amount</td>
                <td>RM{{ number_format((float) $quote->tax_amount, 2) }}</td>
            </tr>
            <tr class="quote-summary-total">
                <td>TOTAL</td>
                <td>RM{{ number_format((float) $quote->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

</div>

@endsection

@push('scripts')
<script>
    (function () {
        const openBtn = document.getElementById('open-reject-modal');
        const closeBtn = document.getElementById('close-reject-modal');
        const modal = document.getElementById('reject-rfq-modal');

        if (!openBtn || !closeBtn || !modal) {
            return;
        }

        const openModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        };

        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-open')) {
                closeModal();
            }
        });

        const hasRejectValidationError = @json($errors->has('reject_reason'));
        if (hasRejectValidationError) {
            openModal();
        }
    }());
</script>
@endpush

