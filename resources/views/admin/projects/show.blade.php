@extends('layouts.app')

@section('title', 'Project Details')

@section('content')

@php
    $projectName  = trim((string) ($project->project_name  ?? ''));
    $projectTitle = trim((string) ($project->project_title ?? ''));
    if ($projectName !== '' && $projectTitle !== '' && strcasecmp($projectName, $projectTitle) !== 0) {
        $projectHeading = $projectName . ' — ' . $projectTitle;
    } else {
        $projectHeading = $projectName !== '' ? $projectName : ($projectTitle !== '' ? $projectTitle : '-');
    }
@endphp

{{-- Page Title --}}
<div class="dash-title-wrap mg-b-20">
    <div class="d-flex fd-column ai-center jc-center gap-8 txt-center">
        <div class="d-flex ai-center gap-10 jc-center">
            <span class="dash-greeting-emoji">◉</span>
            <div class="dash-greeting-text">{{ $projectHeading }}</div>
        </div>
        <div class="dash-greeting-sub">Project Details</div>
    </div>
</div>

{{-- Back button --}}
<div class="mg-b-16">
    <a href="{{ route('projects.index') }}" class="proj-text-btn">
        ← Back to Projects
    </a>
</div>

{{-- Project Info Card --}}
<div class="dash-table-card mg-b-20">
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">Project Information</div>
            <div class="dash-table-subtitle">Key details for this project</div>
        </div>
    </div>
    <div class="d-grid gap-16 mg-t-12" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
        <div class="proj-detail-field">
            <div class="proj-detail-label">Project Name</div>
            <div class="proj-detail-value">{{ $projectName !== '' ? $projectName : '-' }}</div>
        </div>
        <div class="proj-detail-field">
            <div class="proj-detail-label">Project Title</div>
            <div class="proj-detail-value">{{ $projectTitle !== '' ? $projectTitle : '-' }}</div>
        </div>
        <div class="proj-detail-field">
            <div class="proj-detail-label">Location</div>
            <div class="proj-detail-value">{{ $project->location ?: '-' }}</div>
        </div>
        <div class="proj-detail-field">
            <div class="proj-detail-label">Project Type</div>
            <div class="proj-detail-value">{{ ucfirst($project->project_type) }}</div>
        </div>
        <div class="proj-detail-field">
            <div class="proj-detail-label">Status</div>
            <div class="proj-detail-value">{{ ucfirst($project->status) }}</div>
        </div>
    </div>
</div>

{{-- Assigned Items Card --}}
<div class="dash-table-card">
    <div class="dash-table-header">
        <div>
            <div class="dash-table-title">Assigned Items</div>
            <div class="dash-table-subtitle">All items/components assigned to this project</div>
        </div>
    </div>
    @if($project->components->isEmpty())
        <div class="dash-empty-state">
            <i class="ri-box-3-line"></i>
            <div>No items assigned yet.</div>
        </div>
    @else
        <div class="of-auto">
            <table class="dash-table" style="min-width: 500px;">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($project->components as $item)
                        <tr>
                            <td class="fw-bold">{{ $item->component->component_code ?? '-' }}</td>
                            <td>{{ $item->component->component_name ?? '-' }}</td>
                            <td>{{ $item->quantity }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
