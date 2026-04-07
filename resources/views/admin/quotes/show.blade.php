@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/quote-show.css') }}">

<div class="d-flex jc-between ai-center mg-b-20 quote-toolbar">
    <a href="{{ route('quotes.index') }}" class="fs-12 clr-blue txt-none">Back</a>
    <div class="d-flex ai-center quote-toolbar-actions">
        @php
            $currentRole = auth()->user()?->normalizedRole();
            $isApprovedStatus = \App\Models\Quote::normalizeStatus($quote->status) === \App\Models\Quote::STATUS_APPROVED;
            $canCreatePo = in_array($currentRole, [\App\Models\User::ROLE_SUPERADMIN, \App\Models\User::ROLE_ADMIN], true)
                && $isApprovedStatus;
        @endphp
        @if($canCreatePo)
            <form action="{{ route('purchase-orders.create', $quote->id) }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="bg-green clr-white pd-10 br-5 txt-none" style="border:0; cursor:pointer;">Create PO</button>
            </form>
        @endif
        @if(!$isApprovedStatus)
            <a href="{{ route('quotes.edit', $quote->id) }}" class="bg-white clr-blue pd-10 br-5 txt-none">Edit</a>
        @endif
        <button type="button" class="bg-blue clr-white pd-10 br-5 cursor-pointer quote-print-btn" data-print-trigger="true">Print</button>
    </div>
</div>

<div id="quote-template" class="bg-white pd-30 br-10 box-shadow-basic quote-template-card">
    @php
        $requesterName = optional($quote->createdByUser)->name ?? 'Unknown User';
        $departmentName = optional($quote->createdByUser)->department ?? $quote->department ?? 'General Department';
        $dateRequested = optional($quote->date_requested)->format('d M Y') ?: optional($quote->created_at)->format('d M Y');
        $dateNeeded = optional($quote->date_needed)->format('d M Y') ?: '-';
        $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
        $statusLabels = \App\Models\Quote::statusOptions();
        $statusLabel = $statusLabels[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus));
    @endphp

    <div class="d-flex jc-between ai-start mg-b-25">
        <div class="fg-1">
            <div class="fs-24 fw-bold">KHALEEF NET SDN BHD</div>
            <div class="mg-t-8 fs-14">
                LOT 133 BLOCK P, 1ST FLOOR, LORONG PLAZA PERMAI 2<br>
                ALAMESRA<br>
                SULAMAN COASTAL HIGHWAY<br>
                KOTA KINABALU Sabah 88450<br>
                Malaysia<br>
                account@khaleefnet.com
            </div>
        </div>

        <div class="quote-header-right">
            <div class="fs-30 fw-bold quote-title">PURCHASE REQUEST</div>
            <div class="mg-t-8 mg-b-20 fs-14 fw-bold"># {{ $quote->quote_number }}</div>

            <div class="quote-meta-grid">
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Requested By :</div>
                    <div class="quote-meta-value">{{ $requesterName }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Department :</div>
                    <div class="quote-meta-value">{{ $departmentName }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Date Requested :</div>
                    <div class="quote-meta-value">{{ $dateRequested }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Date Needed :</div>
                    <div class="quote-meta-value">{{ $dateNeeded }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label" style="white-space: nowrap;">Purchasing Dept Use Only :</div>
                    <div class="quote-meta-value quote-meta-value-compact">{{ $statusLabel }}</div>
                </div>
            </div>
        </div>
    </div>

    <table class="quote-items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>UNIT PRICE</th>
                <th>QTY</th>
                <th>AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineItemCount = 0;
            @endphp
            @forelse(($companySections ?? collect()) as $section)
                <tr class="quote-company-row">
                    <td colspan="4">
                        Company: {{ $section['company_name'] ?? 'General' }}
                    </td>
                </tr>
                @foreach($section['items'] as $item)
                    @php
                        $lineItemCount++;
                        $lineSubtotal = (float) $item->quantity * (float) $item->unit_price;
                        $lineDiscount = max(0, $lineSubtotal - (float) $item->line_total);
                        $discountPercent = (float) ($item->discount_percent ?? 0);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $item->component->component_name ?? 'Component' }}</div>
                            @if($discountPercent > 0 || $lineDiscount > 0)
                                <div class="fs-12 clr-grey1">Discount: {{ number_format($discountPercent, 2) }}% (RM{{ number_format($lineDiscount, 2) }})</div>
                            @endif
                            @if(!empty($item->component->description))
                                <div class="fs-12 clr-grey1">{{ $item->component->description }}</div>
                            @endif
                        </td>
                        <td>RM{{ number_format((float) $item->unit_price, 2) }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>RM{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td>No line items</td>
                    <td>RM0.00</td>
                    <td>-</td>
                    <td>RM0.00</td>
                </tr>
            @endforelse

            @for($i = $lineItemCount; $i < 4; $i++)
                <tr class="quote-empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>
            @endfor

        </tbody>
    </table>

    <div class="d-flex jc-end mg-t-15">
        <table class="quote-summary-table">
            <thead style="display:none;">
                <tr>
                    <th>Label</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tr>
                <td>Subtotal</td>
                <td>RM{{ number_format((float) $quote->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Taxable</td>
                <td>RM{{ number_format((float) ($quote->subtotal - $quote->discount_total), 2) }}</td>
            </tr>
            <tr>
                <td>Tax Rate</td>
                <td>{{ number_format((float) $quote->tax_rate, 2) }}%</td>
            </tr>
            <tr>
                <td>Tax Amount</td>
                <td>RM{{ number_format((float) $quote->tax_amount, 2) }}</td>
            </tr>
            <tr class="quote-summary-total">
                <td>TOTAL</td>
                <td>RM{{ number_format((float) $quote->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

</div>

@endsection
