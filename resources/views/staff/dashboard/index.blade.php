@extends('layouts.app')

@section('content')

@php
    $quoteStateSummary = collect($quoteStateSummary ?? []);
    $recentActivities = collect($recentActivities ?? []);
@endphp

<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold">Client Dashboard</div>
</div>

<div class="bg-white5 pd-20 br-10 box-shadow-basic">
    <div class="d-flex jc-between ai-center mg-b-15" style="flex-wrap:wrap; gap:10px;">
        <div>
            <div class="fw-bold">Project Overview</div>
        </div>
        <div class="fs-12 clr-grey1">{{ $totalClientProjects ?? 0 }} total projects</div>
    </div>

    @if($quoteStateSummary->isEmpty())
        <div class="fs-12 clr-grey1">No projects yet.</div>
    @else
        <div class="d-grid gap-15" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            @foreach($quoteStateSummary as $statusItem)
                <div class="pd-15 br-10" style="background:#f9fbff; border:1px solid #dbe6ff;">
                    <div class="fs-12 clr-grey1 mg-b-5">{{ $statusItem['label'] }}</div>
                    <div class="fs-28 fw-bold" style="color:#1f3f8f;">{{ $statusItem['count'] }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<div class="bg-white5 pd-20 br-10 box-shadow-basic mg-t-20">
    <div class="d-flex jc-between ai-center mg-b-15" style="flex-wrap:wrap; gap:10px;">
        <div>
            <div class="fw-bold">Recent Activity</div>
            <div class="fs-12 clr-grey1 mg-t-5">Track the latest admin updates, project movement, and re-issue responses.</div>
        </div>
    </div>

    @if($recentActivities->isEmpty())
        <div class="fs-12 clr-grey1">No activity yet.</div>
    @else
        <div style="display:flex; flex-direction:column; gap:12px; max-height:460px; overflow-y:auto; padding-right:4px;">
            @foreach($recentActivities as $activity)
                <div class="pd-15 br-10" style="border:1px solid #eceff7; background:#ffffff;">
                    <div class="d-flex jc-between ai-start" style="gap:12px; flex-wrap:wrap;">
                        <div>
                            <div class="d-flex ai-center" style="gap:8px; flex-wrap:wrap;">
                                <div class="fw-bold">{{ $activity['title'] }}</div>
                                @if(!empty($activity['is_admin_activity']))
                                    <span class="fs-11 fw-bold pd-6 br-5" style="background:#edf5ff; color:#2f55c7; border:1px solid #d6e6ff;">Admin Update</span>
                                @endif
                            </div>
                            <div class="fs-12 clr-grey1 mg-t-4">{{ $activity['description'] }}</div>
                            @if(!empty($activity['project_name'] ?? ''))
                                <div class="fs-11 clr-grey1 mg-t-4">Project: {{ $activity['project_name'] }}</div>
                            @endif
                            @if(!empty($activity['details'] ?? ''))
                                <div class="fs-11 mg-t-4" style="color:#30415f; white-space:pre-wrap;">{{ $activity['details'] }}</div>
                            @endif
                        </div>
                        <div class="fs-11 clr-grey1">{{ optional($activity['timestamp'])->format('d M Y, h:i A') }}</div>
                    </div>

                    @if(($activity['type'] ?? '') === 'rfq' && !empty($activity['quote_id']))
                        <div class="mg-t-8">
                            <a href="{{ route('rfqs.show', $activity['quote_id']) }}" class="txt-none fs-12" style="color:#2f55c7; text-decoration:underline;">Open RFQ</a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

@endsection

