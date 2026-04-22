@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quotes-index.css') }}">
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">Request for Quotation (RFQ)</div>
    </div>
</div>

@if($errors->any())
    <div class="bg-white5 pd-15 br-10 mg-b-15" style="border:1px solid #f0b3b3;">
        @foreach($errors->all() as $error)
            <div class="fs-12" style="color:#b33;">{{ $error }}</div>
        @endforeach
    </div>
@endif

<div id="quotes-page-staff" class="d-grid gap-20" style="grid-template-columns: 1fr;">
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="d-flex jc-between ai-center mg-b-15">
            <div>
                <div class="fw-bold">RFQ List</div>
                <div class="fs-12 clr-grey1 mg-t-5">{{ $quoteListSubtitle ?? 'All generated RFQs.' }}</div>
            </div>
            <div class="d-flex ai-center" style="gap: 10px;">
                <form id="quote-filter-form" method="GET" action="{{ route('rfqs.index') }}" class="d-flex ai-center" style="gap: 8px;">
                    <label for="quote-status-filter" class="fs-12 clr-grey1">Filter</label>
                    <select id="quote-status-filter" name="status" class="pd-6 bdr-all-22 br-5 fs-12" style="border:1px solid #d9d9d9;">
                        @foreach(($filterOptions ?? []) as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}" {{ ($selectedStatus ?? '') === $statusValue ? 'selected' : '' }}>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                    <input id="quote-project-search" type="text" name="project" value="{{ $projectSearch ?? '' }}" placeholder="Search project" class="pd-6 bdr-all-22 br-5 fs-12" style="border:1px solid #d9d9d9; min-width:160px;">
                    <button type="button" id="quote-filter-reset" class="pd-6 br-5 fs-12 cursor-pointer" style="border:1px solid #b87f13; background:#d9a628; color:#ffffff;">Reset</button>
                </form>
            </div>
        </div>

        @if($quotes->count() === 0)
            <div class="pd-20 fs-13 clr-grey1">No RFQs generated yet.</div>
        @else
            <div class="of-auto" style="max-height: 520px; overflow-y: auto;">
                <table style="width:100%; border-collapse: collapse; min-width: 760px;">
                    <thead>
                        <tr style="border-bottom:1px solid #d8d8d8;">
                            <th style="text-align:left; padding:12px 8px;">Quote</th>
                            <th style="text-align:left; padding:12px 8px;">Project</th>
                            <th style="text-align:left; padding:12px 8px;">Date</th>
                            <th style="text-align:center; padding:12px 8px; width:170px;">Status</th>
                            <th style="text-align:center; padding:12px 8px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $quote)
                            <tr style="border-bottom:1px solid #ececec;">
                                <td style="padding:10px 8px;">
                                    <a href="{{ route('rfqs.show', $quote->id) }}" style="color:#2f55c7; text-decoration:underline;">{{ $quote->quote_number }}</a>
                                </td>
                                <td style="padding:10px 8px;">
                                    <a href="{{ route('rfqs.show', $quote->id) }}" style="color:#2f55c7; text-decoration:underline;">{{ $quote->project->project_name ?? '-' }}</a>
                                </td>
                                <td style="padding:10px 8px;">{{ optional($quote->created_at)->format('d M Y') }}</td>
                                <td style="padding:10px 8px; text-align:center;">
                                    @php
                                        $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
                                        $statusStyle = $statusStyles[$normalizedStatus] ?? ['background' => '#f5f5f5', 'color' => '#4d4d4d', 'border' => '#dddddd'];
                                    @endphp
                                    <span class="fs-11 fw-bold pd-6 br-5" style="display:inline-block; background: {{ $statusStyle['background'] }}; color: {{ $statusStyle['color'] }}; border:1px solid {{ $statusStyle['border'] }};">
                                        {{ $statusOptions[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus)) }}
                                    </span>
                                </td>
                                <td style="padding:10px 8px; text-align:center;">
                                    <div class="d-flex ai-center jc-center" style="gap:6px; flex-wrap:wrap;">
                                        @if($normalizedStatus === \App\Models\Quote::STATUS_DRAFT)
                                            <form action="{{ route('rfqs.submit', $quote->id) }}" method="POST" data-no-spa="true" style="display:inline; margin:0; padding:0;">
                                                @csrf
                                                <button type="submit" class="quote-action-btn action-send txt-none d-flex ai-center jc-center" title="Send RFQ" aria-label="Send RFQ to admin" style="width:30px; height:30px; border:1px solid #1f8a4c; color:#1f8a4c; border-radius:6px; background:none; cursor:pointer;">
                                                    <i class="ri-send-plane-line"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @php
                                            $canEditOrDelete = in_array($normalizedStatus, [\App\Models\Quote::STATUS_DRAFT, \App\Models\Quote::STATUS_CANCELLED], true);
                                        @endphp
                                        @if($canEditOrDelete)
                                            <a href="{{ route('rfqs.edit', $quote->id) }}" class="quote-action-btn action-edit txt-none d-flex ai-center jc-center" title="Edit" aria-label="Edit quote" style="width:30px; height:30px; border:1px solid #4b5563; color:#4b5563; border-radius:6px;">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="{{ route('rfqs.destroy', $quote->id) }}" class="quote-action-btn action-delete txt-none d-flex ai-center jc-center" title="Delete" aria-label="Delete quote" style="width:30px; height:30px; border:1px solid #bf2f2f; color:#bf2f2f; border-radius:6px;" onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this quote?')) { document.getElementById('delete-form-{{ $quote->id }}').submit(); }">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                        @endif
                                    </div>
                                    @if($canEditOrDelete)
                                        <form id="delete-form-{{ $quote->id }}" action="{{ route('rfqs.destroy', $quote->id) }}" method="POST" style="display:none;">
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @endif
                                </td>
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

<script src="{{ asset('js/modules/staff-quotes-index.js') }}" defer></script>

