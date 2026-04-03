@extends('layouts.app')

@section('title', 'Project Details')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">{{ $project->project_name }}</h2>
        <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary btn-sm">Back to Projects</a>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <small class="text-muted d-block">Location</small>
                    <strong>{{ $project->location ?: '-' }}</strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Project Type</small>
                    <strong>{{ ucfirst($project->project_type) }}</strong>
                </div>
                <div class="col-md-4">
                    <small class="text-muted d-block">Status</small>
                    <strong>{{ ucfirst($project->status) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <h5 class="mb-3">Assigned Components</h5>
            @if ($project->components->isEmpty())
                <p class="text-muted mb-0">No components assigned yet.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Quantity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($project->components as $item)
                                <tr>
                                    <td>{{ $item->component->component_code ?? '-' }}</td>
                                    <td>{{ $item->component->component_name ?? '-' }}</td>
                                    <td>{{ $item->quantity }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
