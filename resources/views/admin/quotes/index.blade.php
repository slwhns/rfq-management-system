@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quotes-index.css') }}">

{{-- Page Title --}}
<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji">📋</span>
            <div class="dash-greeting-text">Request For Quotation</div>
        </div>
        <div class="dash-greeting-sub">Manage and review all submitted RFQs</div>
    </div>
</div>

{{-- RFQ Table Card --}}
<div id="quotes-page-admin"
    class="dash-table-card"
    data-status-update-url-template="{{ route('rfqs.status.update', ['id' => '__QUOTE_ID__']) }}"
    data-note-update-url-template="{{ route('rfqs.admin-notes.update', ['id' => '__QUOTE_ID__']) }}">

    {{-- Card Header --}}
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">RFQ List</div>
            <div class="dash-table-subtitle">{{ $quoteListSubtitle ?? 'All generated RFQs.' }}</div>
        </div>

        {{-- Filter Form --}}
        <form id="admin-quote-filter-form" method="GET" action="{{ route('rfqs.index') }}" class="d-flex ai-center fw-wrap gap-8">
            <label for="admin-quote-status-filter" class="fs-12 rfq-filter-label">Filter</label>
            <select id="admin-quote-status-filter" name="status" class="rfq-filter-input">
                @foreach(($filterOptions ?? []) as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}" {{ ($selectedStatus ?? '') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                @endforeach
            </select>
            <input id="admin-quote-search" type="text" name="search"
                value="{{ $projectSearch ?? '' }}"
                placeholder="Search project or client"
                class="rfq-filter-input rfq-search-input">
            <button type="submit" class="rfq-filter-btn-apply">Apply</button>
            <a href="{{ route('rfqs.index') }}" class="rfq-filter-btn-reset">Reset</a>
        </form>
    </div>

    {{-- Table --}}
    @if($quotes->count() === 0)
        <div class="dash-empty-state">
            <i class="ri-file-list-3-line"></i>
            <div>No submitted RFQs yet.</div>
        </div>
    @else
        <div class="of-auto">
            <table class="dash-table" style="min-width:1040px;">
                <thead>
                    <tr>
                        <th>Quote</th>
                        <th>Project Name</th>
                        <th>Project Title</th>
                        <th>Client</th>
                        <th>Date</th>
                        <th style="text-align:center; width:170px;">Status</th>
                        <th>Reason Re-issue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quotes as $quote)
                        @php
                            $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
                            $statusStyle = $statusStyles[$normalizedStatus] ?? ['background' => '#f5f5f5', 'color' => '#4d4d4d', 'border' => '#dddddd'];
                            $projectName  = trim((string) ($quote->project->project_name  ?? ''));
                            $projectTitle = trim((string) ($quote->project->project_title ?? ''));
                        @endphp
                        <tr>
                            <td class="fw-bold">
                                <a href="{{ route('rfqs.show', $quote->id) }}" class="dash-review-link">
                                    {{ $quote->quote_number }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('rfqs.show', $quote->id) }}" class="rfq-link">
                                    {{ $projectName !== '' ? $projectName : '-' }}
                                </a>
                            </td>
                            <td>
                                <a href="{{ route('rfqs.show', $quote->id) }}" class="rfq-link">
                                    {{ $projectTitle !== '' ? $projectTitle : '-' }}
                                </a>
                            </td>
                            <td>{{ $quote->createdByUser->email ?? 'No Email' }}</td>
                            <td>{{ optional($quote->date_requested)->format('d M Y') ?: optional($quote->created_at)->format('d M Y') }}</td>
                            <td style="text-align:center;">
                                <span class="rfq-status-badge" style="background:{{ $statusStyle['background'] }}; color:{{ $statusStyle['color'] }}; border:1px solid {{ $statusStyle['border'] }};">
                                    {{ $statusOptions[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus)) }}
                                </span>
                            </td>
                            <td class="rfq-reissue-cell">{{ $quote->reissue_reason_label ?? '-' }}</td>
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

@endsection

<script src="{{ asset('js/modules/admin-quotes-index.js') }}" defer></script>
