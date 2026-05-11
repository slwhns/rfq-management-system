@extends('layouts.app')

@section('content')

{{-- ═══════════════════════════════════════════
     PART 1 — PAGE TITLE
═══════════════════════════════════════════ --}}
<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji">👋</span>
            <div class="dash-greeting-text">RFQ Management System</div>
        </div>
        <div class="dash-greeting-sub">Welcome back, {{ auth()->user()->name ?? 'User' }}</div>
    </div>
</div>

{{-- ═══════════════════════════════════════════
     PART 2 — STAT CARDS GRID
═══════════════════════════════════════════ --}}
<div class="d-grid gap-20 mg-b-20" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">

    {{-- Card: RFQs Need Review --}}
    <div class="dash-stat-card dash-stat-orange">
        <div class="dash-stat-icon"><i class="ri-file-list-3-line"></i></div>
        <div class="dash-stat-body">
            <div class="dash-stat-label">RFQs Need Review</div>
            <div class="dash-stat-value">{{ $pendingReviewCount ?? ($pendingReviewPrs ?? collect())->count() }}</div>
            <div class="dash-stat-sub">Sorted by nearest date needed</div>
        </div>
    </div>

    {{-- Card: RFQs Approved --}}
    <div class="dash-stat-card dash-stat-green">
        <div class="dash-stat-icon"><i class="ri-checkbox-circle-line"></i></div>
        <div class="dash-stat-body">
            <div class="dash-stat-label">RFQs Approved</div>
            <div class="dash-stat-value">{{ $approvedQuoteCount ?? 0 }}</div>
            <div class="dash-stat-sub">Approved RFQs in total</div>
        </div>
    </div>

</div>

{{-- ═══════════════════════════════════════════
     PART 3 — PENDING RFQs TABLE
═══════════════════════════════════════════ --}}
<div class="dash-table-card">
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">RFQs Pending Review & Approval</div>
            <div class="dash-table-subtitle">Action required before deadline</div>
        </div>
        <span class="dash-badge-count">
            {{ $pendingReviewCount ?? ($pendingReviewPrs ?? collect())->count() }} pending
        </span>
    </div>

    @if(($pendingReviewPrs ?? collect())->isEmpty())
        <div class="dash-empty-state">
            <i class="ri-inbox-2-line"></i>
            <div>No RFQs pending review.</div>
        </div>
    @else
        <div class="of-auto" style="max-height: 480px; overflow-y: auto; padding-right: 2px;">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>RFQ #</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Date Needed</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingReviewPrs as $pr)
                        @php
                            $dateNeeded = $pr->date_needed;
                            $daysToNeeded = $dateNeeded ? now()->startOfDay()->diffInDays($dateNeeded->copy()->startOfDay(), false) : null;
                            $priorityLabel = 'Upcoming';
                            $priorityClass = 'dash-priority-blue';

                            if ($daysToNeeded !== null) {
                                if ($daysToNeeded < 0) {
                                    $priorityLabel = 'Overdue';
                                    $priorityClass = 'dash-priority-red';
                                } elseif ($daysToNeeded <= 2) {
                                    $priorityLabel = 'Urgent';
                                    $priorityClass = 'dash-priority-orange';
                                }
                            }
                        @endphp
                        <tr>
                            <td class="fw-bold">{{ $pr->quote_number }}</td>
                            <td>{{ $pr->project->project_name ?? '-' }}</td>
                            <td>{{ $pr->createdByUser->name ?? ($pr->createdByUser->email ?? '-') }}</td>
                            <td>{{ optional($dateNeeded)->format('d M Y') ?: '-' }}</td>
                            <td>
                                <span class="dash-priority-badge {{ $priorityClass }}">{{ $priorityLabel }}</span>
                            </td>
                            <td>{{ \App\Models\Quote::statusOptions()[\App\Models\Quote::normalizeStatus($pr->status)] ?? ucfirst(str_replace('_', ' ', $pr->status)) }}</td>
                            <td>{{ optional($pr->date_requested)->format('d M Y') ?: optional($pr->created_at)->format('d M Y') }}</td>
                            <td style="text-align:center;">
                                <a href="{{ route('rfqs.show', $pr->id) }}" class="dash-review-link">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

