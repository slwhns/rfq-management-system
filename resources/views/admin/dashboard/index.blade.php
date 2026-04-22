@extends('layouts.app')

@section('content')

<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="fs-15 fw-bold">Dashboard</div>
</div>

<div class="d-grid gap-20" style="grid-template-columns: repeat(2, minmax(220px, 1fr));">
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">RFQs Need Review</div>
        <div class="fs-25 fw-bold">{{ $pendingReviewCount ?? ($pendingReviewPrs ?? collect())->count() }}</div>
        <div class="fs-11 clr-grey1 mg-t-5">Sorted by nearest date needed</div>
    </div>

    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="fs-12 clr-grey1 mg-b-5">RFQs Approved</div>
        <div class="fs-25 fw-bold">{{ $approvedQuoteCount ?? 0 }}</div>
        <div class="fs-11 clr-grey1 mg-t-5">Approved RFQs in total</div>
    </div>
</div>

<div class="bg-white5 pd-20 br-10 box-shadow-basic mg-t-20">
    <div class="d-flex jc-between ai-center mg-b-12" style="gap:10px; flex-wrap:wrap;">
        <div>
            <div class="fw-bold">RFQs Need Review &amp; Approval</div>
        </div>
        <div class="fs-12 fw-bold" style="color:#2f55c7;">{{ $pendingReviewCount ?? ($pendingReviewPrs ?? collect())->count() }} pending</div>
    </div>
    @if(($pendingReviewPrs ?? collect())->isEmpty())
        <div class="fs-12 clr-grey1">No RFQs pending review.</div>
    @else
        <div class="of-auto" style="max-height: 460px; overflow-y: auto; padding-right: 4px;">
            <table style="width:100%; min-width:860px; border-collapse:collapse;">
                <thead>
                    <tr style="border-bottom:1px solid #dfdfdf;">
                        <th style="text-align:left; padding:10px 8px;">RFQ #</th>
                        <th style="text-align:left; padding:10px 8px;">Project</th>
                        <th style="text-align:left; padding:10px 8px;">Client</th>
                        <th style="text-align:left; padding:10px 8px;">Date Needed</th>
                        <th style="text-align:left; padding:10px 8px;">Priority</th>
                        <th style="text-align:left; padding:10px 8px;">Status</th>
                        <th style="text-align:left; padding:10px 8px;">Requested</th>
                        <th style="text-align:center; padding:10px 8px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingReviewPrs as $pr)
                        @php
                            $dateNeeded = $pr->date_needed;
                            $daysToNeeded = $dateNeeded ? now()->startOfDay()->diffInDays($dateNeeded->copy()->startOfDay(), false) : null;
                            $priorityLabel = 'Upcoming';
                            $priorityStyle = 'background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe;';

                            if ($daysToNeeded !== null) {
                                if ($daysToNeeded < 0) {
                                    $priorityLabel = 'Overdue';
                                    $priorityStyle = 'background:#fff1f1; color:#bf2f2f; border:1px solid #f5c2c2;';
                                } elseif ($daysToNeeded <= 2) {
                                    $priorityLabel = 'Urgent';
                                    $priorityStyle = 'background:#fff4e8; color:#b45309; border:1px solid #fed7aa;';
                                }
                            }
                        @endphp
                        <tr style="border-bottom:1px solid #f1f1f1;">
                            <td style="padding:10px 8px;">{{ $pr->quote_number }}</td>
                            <td style="padding:10px 8px;">{{ $pr->project->project_name ?? '-' }}</td>
                            <td style="padding:10px 8px;">{{ $pr->createdByUser->name ?? ($pr->createdByUser->email ?? '-') }}</td>
                            <td style="padding:10px 8px;">{{ optional($dateNeeded)->format('d M Y') ?: '-' }}</td>
                            <td style="padding:10px 8px;">
                                <span class="fs-11 fw-bold pd-6 br-5" style="display:inline-block; {{ $priorityStyle }}">{{ $priorityLabel }}</span>
                            </td>
                            <td style="padding:10px 8px;">{{ \App\Models\Quote::statusOptions()[\App\Models\Quote::normalizeStatus($pr->status)] ?? ucfirst(str_replace('_', ' ', $pr->status)) }}</td>
                            <td style="padding:10px 8px;">{{ optional($pr->date_requested)->format('d M Y') ?: optional($pr->created_at)->format('d M Y') }}</td>
                            <td style="padding:10px 8px; text-align:center;">
                                <a href="{{ route('rfqs.show', $pr->id) }}" class="txt-none fs-12" style="color:#2f55c7; text-decoration:underline;">Review</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

