@extends('layouts.app')

@section('content')

<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold">Dashboard</div>
</div>

<div class="d-grid gap-20" style="grid-template-columns: repeat(3, minmax(220px, 1fr));">
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">PR Need Review</div>
        <div class="fs-25 fw-bold">{{ ($pendingReviewPrs ?? collect())->count() }}</div>
        <div class="fs-11 clr-grey1 mg-t-5">Draft + In Progress + Negotiation</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">Total PO Approved</div>
        <div class="fs-25 fw-bold">{{ $approvedPoCount ?? 0 }}</div>
        <div class="fs-11 clr-grey1 mg-t-5">Purchase orders with status approved</div>
    </div>

    @if(($role ?? null) === \App\Models\User::ROLE_SUPERADMIN)
        <div class="bg-white5 pd-20 br-10 box-shadow-basic">
            <div class="fw-bold mg-b-10">Staff Directory</div>
            <div class="fs-12 clr-grey1 mg-b-10">Name and role list</div>
            <div style="max-height: 280px; overflow-y: auto; border:1px solid #ececec; border-radius:8px;">
                @forelse(($staffUsers ?? collect()) as $staff)
                    <div class="d-flex jc-between ai-center pd-10" style="border-bottom:1px solid #f1f1f1;">
                        <div class="fs-12 fw-bold">{{ $staff->name }}</div>
                        <div class="fs-11 clr-grey1">{{ ucfirst($staff->normalizedRole()) }}</div>
                    </div>
                @empty
                    <div class="pd-10 fs-12 clr-grey1">No staff found.</div>
                @endforelse
            </div>
        </div>
    @endif
</div>

<div class="bg-white5 pd-20 br-10 box-shadow-basic mg-t-20">
    <div class="fw-bold mg-b-10">Purchase Requests To Review</div>
    @if(($pendingReviewPrs ?? collect())->isEmpty())
        <div class="fs-12 clr-grey1">No purchase requests pending review.</div>
    @else
        <div class="of-auto">
            <table style="width:100%; min-width:620px; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #dfdfdf;">
                        <th style="text-align:left; padding:10px 8px;">PR #</th>
                        <th style="text-align:left; padding:10px 8px;">Project</th>
                        <th style="text-align:left; padding:10px 8px;">Status</th>
                        <th style="text-align:left; padding:10px 8px;">Date</th>
                        <th style="text-align:center; padding:10px 8px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingReviewPrs as $pr)
                        <tr style="border-bottom:1px solid #f1f1f1;">
                            <td style="padding:10px 8px;">{{ $pr->quote_number }}</td>
                            <td style="padding:10px 8px;">{{ $pr->project->project_name ?? '-' }}</td>
                            <td style="padding:10px 8px;">{{ \App\Models\Quote::statusOptions()[\App\Models\Quote::normalizeStatus($pr->status)] ?? ucfirst(str_replace('_', ' ', $pr->status)) }}</td>
                            <td style="padding:10px 8px;">{{ optional($pr->created_at)->format('d M Y') }}</td>
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
