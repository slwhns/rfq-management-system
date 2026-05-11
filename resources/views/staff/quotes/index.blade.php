@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quotes-index.css') }}">
{{-- Page Title --}}
<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji">📄</span>
            <div class="dash-greeting-text">Request for Quotation (RFQ)</div>
        </div>
        <div class="dash-greeting-sub">View and manage your RFQs</div>
    </div>
</div>
@if($errors->any())
    <div class="bg-white5 pd-15 br-10 mg-b-15" style="border:1px solid #f0b3b3;">
        @foreach($errors->all() as $error)
            <div class="fs-12" style="color:#b33;">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div id="quotes-page-staff" class="d-grid gap-20" style="grid-template-columns: 1fr;">
    <div class="dash-table-card">
        <div class="dash-table-header">
            <div>
                <div class="dash-table-title">RFQ List</div>
                <div class="dash-table-subtitle">{{ $quoteListSubtitle ?? 'All generated RFQs.' }}</div>
            </div>
            <div class="d-flex ai-center" style="gap: 10px;">
                <form id="quote-filter-form" method="GET" action="{{ route('rfqs.index') }}" class="d-flex ai-center" style="gap: 8px;">
                    <label for="quote-status-filter" class="fs-12 clr-plt2">Filter</label>
                    <select id="quote-status-filter" name="status" class="rfq-filter-input pd-6 br-5 fs-12">
                        @foreach(($filterOptions ?? []) as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" {{ ($selectedStatus ?? '') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                    <input id="quote-project-search" type="text" name="project" value="{{ $projectSearch ?? '' }}" placeholder="Search project" class="rfq-filter-input pd-6 br-5 fs-12" style="min-width:160px;">
                    <button type="button" id="quote-filter-reset" class="rfq-filter-btn-reset pd-6 br-5 fs-12">Reset</button>
                </form>
            </div>
        </div>

        @if($quotes->count() === 0)
            <div class="dash-empty-state">
                <i class="ri-file-list-line"></i>
                <div>No RFQs generated yet.</div>
            </div>
        @else
            <div class="of-auto sbar-h-none" style="max-height: 520px; overflow-y: auto; padding-right: 2px;">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th>Quote</th>
                            <th>Project Name</th>
                            <th>Project Title</th>
                            <th>Date</th>
                            <th style="text-align:center; width:140px;">Status</th>
                            <th style="text-align:center; width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $quote)
                            @php
                                $projectName = trim((string) ($quote->project->project_name ?? ''));
                                $projectTitle = trim((string) ($quote->project->project_title ?? ''));
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('rfqs.show', $quote->id) }}" class="dash-review-link">{{ $quote->quote_number }}</a>
                                </td>
                                <td>{{ $projectName !== '' ? $projectName : '-' }}</td>
                                <td>{{ $projectTitle !== '' ? $projectTitle : '-' }}</td>
                                <td>{{ optional($quote->created_at)->format('d M Y') }}</td>
                                <td style="text-align:center;">
                                    @php
                                        $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
                                        $statusClass = 'dash-priority-blue';
                                        if ($normalizedStatus === \App\Models\Quote::STATUS_APPROVED) {
                                            $statusClass = 'dash-priority-green';
                                        } elseif ($normalizedStatus === \App\Models\Quote::STATUS_DECLINED) {
                                            $statusClass = 'dash-priority-red';
                                        } elseif ($normalizedStatus === \App\Models\Quote::STATUS_DRAFT || $normalizedStatus === \App\Models\Quote::STATUS_CANCELLED) {
                                            $statusClass = 'dash-priority-gray';
                                        }
                                    @endphp
                                    <span class="dash-priority-badge {{ $statusClass }}">
                                        {{ $statusOptions[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus)) }}
                                    </span>
                                </td>
                                <td style="text-align:center;">
                                    <div class="d-flex ai-center jc-center" style="gap:6px; flex-wrap:wrap;">
                                        @if($normalizedStatus === \App\Models\Quote::STATUS_DRAFT)
                                            <form action="{{ route('rfqs.submit', $quote->id) }}" method="POST" data-no-spa="true" style="display:inline; margin:0; padding:0;">
                                                @csrf
                                                <button type="submit" class="dash-review-link" title="Send RFQ" aria-label="Send RFQ to admin" style="padding: 4px 8px; font-size: 14px; cursor: pointer; background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--plt2);">
                                                    <i class="ri-send-plane-line"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @php
                                            $canEditOrDelete = in_array($normalizedStatus, [\App\Models\Quote::STATUS_DRAFT, \App\Models\Quote::STATUS_CANCELLED], true);
                                        @endphp
                                        @if($canEditOrDelete)
                                            <a href="{{ route('rfqs.edit', $quote->id) }}" class="dash-review-link" title="Edit" aria-label="Edit quote" style="padding: 4px 8px; font-size: 14px; background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--plt2);">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="{{ route('rfqs.destroy', $quote->id) }}" class="dash-review-link" title="Delete" aria-label="Delete quote" style="padding: 4px 8px; font-size: 14px; background: transparent; border: 1px solid rgba(255,255,255,0.1); color: var(--plt2);" onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this quote?')) { document.getElementById('delete-form-{{ $quote->id }}').submit(); }">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        @endif
                                    </div>
                                    @if($canEditOrDelete)
                                        <form id="delete-form-{{ $quote->id }}" action="{{ route('rfqs.destroy', $quote->id) }}" method="POST" style="display:none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mg-t-15">
                {{ $quotes->links('vendor.pagination.qs') }}
            </div>
        @endif
    </div>
</div>

@endsection

<script src="{{ asset('js/modules/staff-quotes-index.js') }}" defer></script>

