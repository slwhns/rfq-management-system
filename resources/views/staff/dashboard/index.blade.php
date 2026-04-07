@extends('layouts.app')

@section('content')

<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold">Dashboard</div>
</div>

<div class="d-grid gap-20" style="grid-template-columns: repeat(3, minmax(220px, 1fr));">
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Total Items</div>
        <div class="fs-25 fw-bold">{{ $totalItems ?? 0 }}</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Total Categories</div>
        <div class="fs-25 fw-bold">{{ $totalCategories ?? 0 }}</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Supplier Companies</div>
        <div class="fs-25 fw-bold">{{ $totalSuppliers ?? 0 }}</div>
    </div>
</div>

<div class="bg-white5 pd-20 br-10 box-shadow-basic mg-t-20">
    <div class="fw-bold mg-b-10">Purchase Request</div>
    @if(($quotesWithAdminComments ?? collect())->isEmpty())
        <div class="fs-12 clr-grey1">No purchase requests yet.</div>
    @else
        <div class="of-auto">
            <table style="width:100%; min-width:760px; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #dfdfdf;">
                        <th style="text-align:left; padding:10px 8px;">PR #</th>
                        <th style="text-align:left; padding:10px 8px;">Project</th>
                        <th style="text-align:left; padding:10px 8px;">Comment</th>
                        <th style="text-align:left; padding:10px 8px;">Commented By</th>
                        <th style="text-align:left; padding:10px 8px;">Updated</th>
                        <th style="text-align:center; padding:10px 8px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($quotesWithAdminComments as $pr)
                        <tr style="border-bottom:1px solid #f1f1f1;">
                            <td style="padding:10px 8px;">{{ $pr->quote_number }}</td>
                            <td style="padding:10px 8px;">{{ $pr->project->project_name ?? '-' }}</td>
                            <td style="padding:10px 8px; max-width:340px;">
                                <div style="white-space:pre-wrap; word-break:break-word;">{{ \Illuminate\Support\Str::limit($pr->admin_notes, 120) }}</div>
                            </td>
                            <td style="padding:10px 8px;">{{ $pr->adminNotesUpdatedBy->name ?? 'Admin' }}</td>
                            <td style="padding:10px 8px;">{{ optional($pr->admin_notes_updated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                            <td style="padding:10px 8px; text-align:center;">
                                <a href="{{ route('quotes.show', $pr->id) }}" class="txt-none fs-12" style="color:#2f55c7;">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>


@endsection
