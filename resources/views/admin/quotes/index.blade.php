@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quotes-index.css') }}">
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">Purchase Request (PR)</div>
    </div>
</div>

<div id="quotes-page-admin" class="d-grid gap-20" style="grid-template-columns: 1fr;" data-status-update-url-template="{{ route('quotes.status.update', ['id' => '__QUOTE_ID__']) }}" data-note-update-url-template="{{ route('quotes.admin-notes.update', ['id' => '__QUOTE_ID__']) }}">
    <div class="bg-white5 pd-20 br-10 box-shadow-basic">
        <div class="d-flex jc-between ai-center mg-b-15">
            <div>
                <div class="fw-bold">Purchase Request List</div>
                <div class="fs-12 clr-grey1 mg-t-5">{{ $quoteListSubtitle ?? 'All generated purchase requests.' }}</div>
            </div>
            <div class="d-flex ai-center" style="gap: 10px;">
                <form method="GET" action="{{ route('quotes.index') }}" class="d-flex ai-center" style="gap: 8px;">
                    <label for="quote-status-filter" class="fs-12 clr-grey1">Filter</label>
                    <select id="quote-status-filter" name="status" class="pd-6 bdr-all-22 br-5 fs-12" style="border:1px solid #d9d9d9;" onchange="this.form.submit()">
                        <option value="">All status</option>
                        @foreach($statusOptions as $status => $label)
                            <option value="{{ $status }}" {{ $selectedStatus === $status ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="d-flex ai-center mg-b-15" style="flex-wrap: wrap; gap: 8px;">
            @foreach($statusOptions as $status => $label)
                @php
                    $style = $statusStyles[$status] ?? ['background' => '#f5f5f5', 'color' => '#4d4d4d', 'border' => '#dddddd'];
                @endphp
                <span class="status-summary-chip fs-11 fw-bold pd-6 br-5" data-status="{{ $status }}" style="background: {{ $style['background'] }}; color: {{ $style['color'] }}; border: 1px solid {{ $style['border'] }};">
                    {{ $label }}: <span class="status-summary-count">{{ $statusSummary[$status] ?? 0 }}</span>
                </span>
            @endforeach
        </div>

        @if($quotes->count() === 0)
            <div class="pd-20 fs-13 clr-grey1">No purchase requests generated yet.</div>
        @else
            <div class="of-auto">
                <table style="width:100%; border-collapse: collapse; min-width: 760px;">
                    <thead>
                        <tr style="border-bottom:1px solid #d8d8d8;">
                            <th style="text-align:left; padding:12px 8px;">Quote</th>
                            <th style="text-align:left; padding:12px 8px;">Project</th>
                            <th style="text-align:left; padding:12px 8px;">Date</th>
                            <th style="text-align:center; padding:12px 8px; width:170px;">Status</th>
                            <th style="text-align:left; padding:12px 8px; min-width:360px;">Notes</th>
                            <th style="text-align:center; padding:12px 8px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($quotes as $quote)
                            <tr style="border-bottom:1px solid #ececec;">
                                <td style="padding:10px 8px;">{{ $quote->quote_number }}</td>
                                <td style="padding:10px 8px;">{{ $quote->project->project_name ?? '-' }}</td>
                                <td style="padding:10px 8px;">{{ optional($quote->created_at)->format('d M Y') }}</td>
                                <td style="padding:10px 8px; text-align:center;">
                                    @php
                                        $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
                                        $canSelectCurrent = array_key_exists($normalizedStatus, $statusUpdateOptions);
                                    @endphp
                                    <div class="d-flex ai-center jc-center" style="gap:8px; flex-wrap:wrap;">
                                        <select class="quote-status-select pd-5 bdr-all-22 br-5 fs-12" data-quote-id="{{ $quote->id }}" data-prev-status="{{ $normalizedStatus }}" style="border: 1px solid #d9d9d9; background: #ffffff; color: #222222; min-width: 132px;">
                                            @if(!$canSelectCurrent)
                                                <option value="{{ $normalizedStatus }}" selected>{{ $statusOptions[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus)) }}</option>
                                            @endif
                                            @foreach($statusUpdateOptions as $status => $optionLabel)
                                                <option value="{{ $status }}" {{ $normalizedStatus === $status ? 'selected' : '' }}>{{ $optionLabel }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                                <td style="padding:10px 8px;">
                                    <div class="d-flex" style="flex-direction:column; gap:6px;">
                                        <div class="fs-11 fw-bold clr-grey1">Admin Note to Staff</div>
                                        <textarea
                                            class="quote-admin-note pd-8 bdr-all-22 br-5 fs-12"
                                            data-quote-id="{{ $quote->id }}"
                                            style="width:100%; min-height:72px; resize:vertical; font-family:inherit;"
                                            placeholder="Add note for staff...">{{ $quote->admin_notes }}</textarea>
                                        <div class="d-flex ai-center" style="gap:8px;">
                                            <button type="button" class="save-admin-note-btn pd-6 br-5 fs-11 cursor-pointer" data-quote-id="{{ $quote->id }}" style="border:1px solid #2f55c7; background:#2f55c7; color:#fff;">Save Note</button>
                                            <span class="admin-note-status fs-11 clr-grey1" data-quote-id="{{ $quote->id }}"></span>
                                        </div>
                                        @if($quote->admin_notes_updated_at)
                                            <div class="fs-11 clr-grey1">Admin note updated: {{ optional($quote->admin_notes_updated_at)->format('d M Y, h:i A') }}</div>
                                        @endif
                                        <div class="mg-t-8 pd-8 br-5" style="background:#f8f9fb; border:1px solid #e2e6ef;">
                                            <div class="fs-11 fw-bold clr-grey1 mg-b-4">Staff Response</div>
                                            @if(!empty($quote->staff_response))
                                                <div class="fs-12" style="white-space:pre-wrap; word-break:break-word;">{{ $quote->staff_response }}</div>
                                                @if($quote->staff_response_updated_at)
                                                    <div class="fs-11 clr-grey1 mg-t-5">Updated: {{ optional($quote->staff_response_updated_at)->format('d M Y, h:i A') }}</div>
                                                @endif
                                            @else
                                                <div class="fs-11 clr-grey1">No response from staff yet</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:10px 8px; text-align:center;">
                                    <div class="d-flex" style="flex-direction:column; gap:6px; align-items:center;">
                                        @php
                                            $canCreatePoRow = in_array(auth()->user()?->normalizedRole(), [\App\Models\User::ROLE_SUPERADMIN, \App\Models\User::ROLE_ADMIN], true)
                                                && \App\Models\Quote::normalizeStatus($quote->status) === \App\Models\Quote::STATUS_APPROVED;
                                        @endphp
                                        @if($canCreatePoRow)
                                            <a href="{{ route('purchase-orders.create', $quote->id) }}" class="quote-action-btn action-po txt-none d-flex ai-center jc-center" title="Create PO" aria-label="Create purchase order" style="width:30px; height:30px; border:1px solid #1f8a4c; color:#1f8a4c; border-radius:6px;">
                                                <i class="ri-file-paper-2-line"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('quotes.show', $quote->id) }}" class="quote-action-btn action-view txt-none d-flex ai-center jc-center" title="View" aria-label="View quote" style="width:30px; height:30px; border:1px solid #2f55c7; color:#2f55c7; border-radius:6px;">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('quotes.edit', $quote->id) }}" class="quote-action-btn action-edit txt-none d-flex ai-center jc-center" title="Edit" aria-label="Edit quote" style="width:30px; height:30px; border:1px solid #4b5563; color:#4b5563; border-radius:6px;">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <a href="{{ route('quotes.destroy', $quote->id) }}" class="quote-action-btn action-delete txt-none d-flex ai-center jc-center" title="Delete" aria-label="Delete quote" style="width:30px; height:30px; border:1px solid #bf2f2f; color:#bf2f2f; border-radius:6px;" onclick="event.preventDefault(); if(confirm('Are you sure you want to delete this quote?')) { document.getElementById('delete-form-{{ $quote->id }}').submit(); }">
                                            <i class="ri-delete-bin-line"></i>
                                        </a>
                                    </div>
                                    <form id="delete-form-{{ $quote->id }}" action="{{ route('quotes.destroy', $quote->id) }}" method="POST" style="display:none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mg-t-15">
                {{ $quotes->links() }}
            </div>
        @endif
    </div>
</div>

@endsection

<script src="{{ asset('js/modules/admin-quotes-index.js') }}" defer></script>
