@extends('layouts.app')

@section('content')

<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold">Dashboard</div>
</div>

<div class="d-grid gap-20" style="grid-template-columns: repeat(4, minmax(180px, 1fr));">

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Total Projects</div>
        <div class="fs-25 fw-bold">{{ $stats['total_projects'] ?? 0 }}</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Active Quotes</div>
        <div class="fs-25 fw-bold">{{ $stats['active_quotes'] ?? 0 }}</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Total Quote Value</div>
        <div class="fs-25 fw-bold">RM {{ number_format($stats['total_value'] ?? 0, 2) }}</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Total Components</div>
        <div class="fs-25 fw-bold">{{ $stats['total_components'] ?? 0 }}</div>
    </div>

</div>

<div class="mg-t-20"></div>

<div class="bg-white5 pd-20 br-10 box-shadow-basic">
    <div class="fw-bold mg-b-10">KPI Panels (Integration Ready)</div>
    <div class="clr-grey1">This section is reserved for KPI charts, trends, and alerts. You can integrate real analytics modules later without changing the page structure.</div>
</div>


@endsection
