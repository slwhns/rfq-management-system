@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quote-show.css') }}">
<link rel="stylesheet" href="{{ asset('css/admin-quote-review.css') }}">

<div class="quote-toolbar mg-b-20">
    <a href="{{ route('rfqs.index') }}" class="quote-toolbar-btn quote-btn-ghost">Back</a>
    <div class="quote-toolbar-actions">
        @php
            $currentRole = auth()->user()?->normalizedRole();
            $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
            $isApprovedStatus = \App\Models\Quote::normalizeStatus($quote->status) === \App\Models\Quote::STATUS_APPROVED;
            $isDeclinedStatus = \App\Models\Quote::normalizeStatus($quote->status) === \App\Models\Quote::STATUS_DECLINED;
            $canDecideRfq = $currentRole === \App\Models\User::ROLE_CLIENT
                && $quote->quotation_sent_at !== null
                && ! $isApprovedStatus
                && ! $isDeclinedStatus;
            $canSubmitRfq = $currentRole === \App\Models\User::ROLE_CLIENT && $normalizedStatus === \App\Models\Quote::STATUS_DRAFT;
            $canEditRfq = in_array($normalizedStatus, [\App\Models\Quote::STATUS_DRAFT, \App\Models\Quote::STATUS_CANCELLED], true);
            $canReissueRfq = in_array($currentRole, [\App\Models\User::ROLE_CLIENT, \App\Models\User::ROLE_STAFF], true)
                && in_array($normalizedStatus, [\App\Models\Quote::STATUS_APPROVED, \App\Models\Quote::STATUS_DECLINED], true);
        @endphp
        @if($canSubmitRfq)
            <form action="{{ route('rfqs.submit', $quote->id) }}" method="POST" data-no-spa="true" class="quote-toolbar-form">
                @csrf
                <button type="submit" class="quote-toolbar-btn quote-btn-success">Send RFQ</button>
            </form>
        @endif
        @if($canEditRfq)
            <a href="{{ route('rfqs.edit', $quote->id) }}" class="quote-toolbar-btn quote-btn-outline">Edit</a>
        @endif
        @if($canReissueRfq)
            <button type="button" id="open-reissue-rfq-modal" class="quote-toolbar-btn quote-btn-success">Re-issue RFQ</button>
        @endif
        <button type="button" class="quote-toolbar-btn quote-btn-primary quote-print-btn" data-print-trigger="true" data-print-quote-id="{{ $quote->quote_number }}" data-print-project-name="{{ $quote->project->project_name ?? 'project' }}">Print</button>
    </div>
</div>

<div id="reissue-rfq-modal" class="reject-modal-backdrop" aria-hidden="true">
    <dialog class="reject-modal-card" aria-labelledby="reissue-modal-title" open>
        <div class="reject-modal-head">
            <h3 id="reissue-modal-title" class="reject-modal-title">Re-issue RFQ</h3>
        </div>
        <form action="{{ route('rfqs.reissue', $quote->id) }}" method="POST" data-no-spa="true" class="reject-modal-body">
            @csrf

            <div class="fs-12 mg-b-8">Reason for re-issue</div>
            <select id="reissue-reason-select" name="reissue_reason" class="pd-10 bdr-all-22 br-5" style="width:100%;" required>
                <option value="change_quantity" {{ old('reissue_reason') === 'change_quantity' ? 'selected' : '' }}>Change quantity</option>
                <option value="request_discount" {{ old('reissue_reason') === 'request_discount' ? 'selected' : '' }}>Request for discount</option>
                <option value="others" {{ old('reissue_reason') === 'others' ? 'selected' : '' }}>Others</option>
            </select>

            <div id="reissue-reason-others-wrap" class="mg-t-10" style="display:none;">
                <label for="reissue-reason-custom" class="fs-12 fw-bold mg-b-5 d-block">Please specify</label>
                <textarea id="reissue-reason-custom" name="reissue_reason_custom" class="reject-modal-textarea" placeholder="Type your reason...">{{ old('reissue_reason_custom') }}</textarea>
            </div>

            <div class="reject-modal-actions">
                <button type="button" id="close-reissue-rfq-modal" class="reject-btn-cancel">Cancel</button>
                <button type="submit" class="reject-btn-submit reissue-btn-submit">Proceed</button>
            </div>
        </form>
    </dialog>
</div>

@if($normalizedStatus === \App\Models\Quote::STATUS_DECLINED)
    <div class="bg-white5 pd-15 br-10 box-shadow-basic mg-b-20" style="border:1px solid #f9c8c8;">
        <div class="fw-bold mg-b-8" style="color:#bf2f2f;">RFQ Rejected</div>
        <div class="fs-12 clr-grey1 mg-b-10">Review the reason below, then click "Re-issue RFQ" to create a new RFQ version for editing and resubmission.</div>
        <div class="fs-12 pd-10 br-5" style="background:#fff5f5; border:1px solid #ffd9d9; white-space:pre-wrap; word-break:break-word;">
            {{ !empty($quote->admin_notes) ? $quote->admin_notes : 'No rejection reason provided by admin.' }}
        </div>
    </div>
@endif

@include('staff.quotes.partials.rfq-document', [
    'quote' => $quote,
    'showPurchasingStatus' => true,
    'showResubmittedBadge' => true,
])

@push('scripts')
<script>
    (function () {
        const openBtn = document.getElementById('open-reissue-rfq-modal');
        const closeBtn = document.getElementById('close-reissue-rfq-modal');
        const modal = document.getElementById('reissue-rfq-modal');
        const reasonSelect = document.getElementById('reissue-reason-select');
        const othersWrap = document.getElementById('reissue-reason-others-wrap');
        const othersInput = document.getElementById('reissue-reason-custom');

        if (!modal || !reasonSelect || !othersWrap || !othersInput) {
            return;
        }

        const syncOthersVisibility = () => {
            const isOthers = reasonSelect.value === 'others';
            othersWrap.style.display = isOthers ? '' : 'none';
            othersInput.required = isOthers;
        };

        const openModal = () => {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            syncOthersVisibility();
        };

        const closeModal = () => {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        };

        openBtn?.addEventListener('click', openModal);
        closeBtn?.addEventListener('click', closeModal);
        reasonSelect.addEventListener('change', syncOthersVisibility);

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

        const hasReissueValidationError = @json($errors->has('reissue_reason') || $errors->has('reissue_reason_custom'));
        if (hasReissueValidationError) {
            openModal();
        } else {
            syncOthersVisibility();
        }
    }());
</script>
@endpush
@endsection

