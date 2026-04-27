@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quotes-index.css') }}">
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">Request for Quotation (RFQ)</div>
    </div>
</div>

<div id="quotes-page-admin" class="d-grid gap-20" style="grid-template-columns: 1fr;" data-status-update-url-template="{{ route('rfqs.status.update', ['id' => '__QUOTE_ID__']) }}" data-note-update-url-template="{{ route('rfqs.admin-notes.update', ['id' => '__QUOTE_ID__']) }}">
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="d-flex jc-between ai-center mg-b-15">
            <div>
                <div class="fw-bold">RFQ List</div>
                <div class="fs-12 clr-grey1 mg-t-5">{{ $quoteListSubtitle ?? 'All generated RFQs.' }}</div>
            </div>
            <div class="d-flex ai-center" style="gap:10px;">
                <form id="admin-quote-filter-form" method="GET" action="{{ route('rfqs.index') }}" class="d-flex ai-center" style="gap:8px;">
                    <label for="admin-quote-status-filter" class="fs-12 clr-grey1">Filter</label>
                    <select id="admin-quote-status-filter" name="status" class="pd-6 bdr-all-22 br-5 fs-12" style="border:1px solid #d9d9d9;">
                        @foreach(($filterOptions ?? []) as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" {{ ($selectedStatus ?? '') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                    <input id="admin-quote-search" type="text" name="search" value="{{ $projectSearch ?? '' }}" placeholder="Search project or client" class="pd-6 bdr-all-22 br-5 fs-12" style="border:1px solid #d9d9d9; min-width:200px;">
                    <button type="submit" class="pd-6 br-5 fs-12 cursor-pointer" style="border:1px solid #2f55c7; background:#2f55c7; color:#ffffff;">Apply</button>
                    <a href="{{ route('rfqs.index') }}" class="pd-6 br-5 fs-12 txt-none" style="border:1px solid #c8cfdf; background:#ffffff; color:#4a5470;">Reset</a>
                </form>
            </div>
        </div>

        @if($quotes->count() === 0)
            <div class="pd-20 fs-13 clr-grey1">No submitted RFQs yet.</div>
        @else
            <div class="of-auto">
                <table style="width:100%; border-collapse: collapse; min-width: 1040px;">
                    <thead>
                        <tr style="border-bottom:1px solid #d8d8d8;">
                            <th style="text-align:left; padding:12px 8px;">Quote</th>
                            <th style="text-align:left; padding:12px 8px;">Project Name</th>
                            <th style="text-align:left; padding:12px 8px;">Project Title</th>
                            <th style="text-align:left; padding:12px 8px;">Client</th>
                            <th style="text-align:left; padding:12px 8px;">Date</th>
                            <th style="text-align:center; padding:12px 8px; width:170px;">Status</th>
                            <th style="text-align:left; padding:12px 8px;">Reason Re-issue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $quote)
                            @php
                                $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
                                $statusStyle = $statusStyles[$normalizedStatus] ?? ['background' => '#f5f5f5', 'color' => '#4d4d4d', 'border' => '#dddddd'];
                                $projectName = trim((string) ($quote->project->project_name ?? ''));
                                $projectTitle = trim((string) ($quote->project->project_title ?? ''));
                            @endphp
                            <tr style="border-bottom:1px solid #ececec;">
                                <td style="padding:10px 8px;"><a href="{{ route('rfqs.show', $quote->id) }}" class="txt-none" style="color:#2f55c7;">{{ $quote->quote_number }}</a></td>
                                <td style="padding:10px 8px;"><a href="{{ route('rfqs.show', $quote->id) }}" class="txt-none" style="color:#2f55c7;">{{ $projectName !== '' ? $projectName : '-' }}</a></td>
                                <td style="padding:10px 8px;"><a href="{{ route('rfqs.show', $quote->id) }}" class="txt-none" style="color:#2f55c7;">{{ $projectTitle !== '' ? $projectTitle : '-' }}</a></td>
                                <td style="padding:10px 8px;">{{ $quote->createdByUser->email ?? 'No Email' }}</td>
                                <td style="padding:10px 8px;">{{ optional($quote->date_requested)->format('d M Y') ?: optional($quote->created_at)->format('d M Y') }}</td>
                                <td style="padding:10px 8px; text-align:center;">
                                    <span class="fs-11 fw-bold pd-6 br-5" style="display:inline-block; background: {{ $statusStyle['background'] }}; color: {{ $statusStyle['color'] }}; border:1px solid {{ $statusStyle['border'] }};">
                                        {{ $statusOptions[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus)) }}
                                    </span>
                                </td>
                                <td style="padding:10px 8px; white-space:pre-wrap; word-break:break-word;">{{ $quote->reissue_reason_label ?? '-' }}</td>
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
</div>

@endsection

<script src="{{ asset('js/modules/admin-quotes-index.js') }}" defer></script>

