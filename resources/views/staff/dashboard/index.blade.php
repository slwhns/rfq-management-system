@extends('layouts.app')

@section('content')

@php
    $quoteStateSummary = collect($quoteStateSummary ?? []);
    $recentActivities = collect($recentActivities ?? []);
@endphp

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


<div class="dash-table-card mg-b-20">
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">Project Overview</div>
            <div class="dash-table-subtitle">Summary of your project statuses</div>
        </div>
        <span class="dash-badge-count">
            {{ $totalClientProjects ?? 0 }} total projects
        </span>
    </div>

    @if($quoteStateSummary->isEmpty())
        <div class="dash-empty-state">
            <i class="ri-folder-open-line"></i>
            <div>No projects yet.</div>
        </div>
    @else
        <div class="d-grid gap-15" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            @foreach($quoteStateSummary as $statusItem)
                @php
                    $statColor = 'dash-stat-blue';
                    $labelLower = strtolower($statusItem['label']);
                    if ($labelLower === 'approved') {
                        $statColor = 'dash-stat-green';
                    } elseif (in_array($labelLower, ['declined', 'rejected'])) {
                        $statColor = 'dash-stat-red';
                    } elseif ($labelLower === 'draft') {
                        $statColor = 'dash-stat-yellow';
                    }
                @endphp
                <div class="dash-stat-card {{ $statColor }}" style="padding: 15px;">
                    <div class="dash-stat-body">
                        <div class="dash-stat-label">{{ $statusItem['label'] }}</div>
                        <div class="dash-stat-value">{{ $statusItem['count'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="dash-table-card">
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">Recent Activity</div>
            <div class="dash-table-subtitle">Track the latest admin updates, project movement, and re-issue responses.</div>
        </div>
    </div>

    @if($recentActivities->isEmpty())
        <div class="dash-empty-state">
            <i class="ri-history-line"></i>
            <div>No activity yet.</div>
        </div>
    @else
        <div style="display:flex; flex-direction:column; gap:12px; max-height:460px; overflow-y:auto; padding-right:4px;" class="sbar-w-none">
            @foreach($recentActivities as $activity)
                <div class="dash-activity-card">
                    <div class="d-flex jc-between ai-start" style="gap:12px; flex-wrap:wrap;">
                        <div>
                            <div class="d-flex ai-center" style="gap:8px; flex-wrap:wrap;">
                                <div class="dash-activity-title">{{ $activity['title'] }}</div>
                                @if(!empty($activity['is_admin_activity']))
                                    <span class="dash-priority-badge dash-priority-blue">Admin Update</span>
                                @endif
                            </div>
                            <div class="dash-activity-desc fs-12 mg-t-4">{{ $activity['description'] }}</div>
                            @if(!empty($activity['project_name'] ?? ''))
                                <div class="dash-activity-desc fs-11 mg-t-4">Project: {{ $activity['project_name'] }}</div>
                            @endif
                            @if(!empty($activity['details'] ?? ''))
                                <div class="fs-11 mg-t-4 clr-plt2" style="white-space:pre-wrap;">{{ $activity['details'] }}</div>
                            @endif
                        </div>
                        <div class="fs-11 clr-plt3">{{ optional($activity['timestamp'])->format('d M Y, h:i A') }}</div>
                    </div>

                    @if(($activity['type'] ?? '') === 'rfq' && !empty($activity['quote_id']))
                        <div class="mg-t-8">
                            <a href="{{ route('rfqs.show', $activity['quote_id']) }}" class="dash-review-link">Open RFQ</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

@endsection

