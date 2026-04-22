@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/purchase-orders-create.css') }}">

<div class="d-flex jc-between ai-center mg-b-20 purchase-order-toolbar" style="gap:12px; flex-wrap:wrap;">
    <div class="d-flex ai-center" style="gap:10px; flex-wrap:wrap;">
        <a href="{{ route('purchase-orders.index') }}" class="fs-12 clr-blue txt-none">Back</a>
        <a href="{{ route('rfqs.show', $quote->id) }}" class="fs-12 clr-blue txt-none">Back to RFQ {{ $quote->quote_number }}</a>
    </div>
    <div class="d-flex ai-center" style="gap:10px; flex-wrap:wrap;">
        <button type="button" class="bg-blue clr-white pd-10 br-5 cursor-pointer" style="border:0;" data-print-trigger="true" data-print-quote-id="{{ $quote->quote_number }}" data-print-project-name="{{ $quote->project->project_name ?? 'project' }}">Print</button>
    </div>
</div>

<div id="purchase-orders-create-page" class="bg-white pd-30 br-10 box-shadow-basic" style="max-width: 980px; margin: 0 auto; border:0; background:#ffffff;" data-po-page="true" data-pr-id="{{ $quote->id }}" data-pr-number="{{ $quote->quote_number }}" data-pr-status="{{ $quote->status }}" data-po-download="{{ !empty($shouldDownload) ? '1' : '0' }}" data-po-status-badge>
    <div class="d-flex jc-between ai-start mg-b-25" style="gap:24px;">
        <div style="flex:1;">
            <div class="fs-18 fw-bold">KHALEEF NET SDN BHD</div>
            <div class="mg-t-8 fs-14">
                LOT 133 BLOCK P, 1ST FLOOR, LORONG PLAZA PERMAI 2<br>
                ALAMESRA<br>
                SULAMAN COASTAL HIGHWAY<br>
                KOTA KINABALU Sabah 88450<br>
                Malaysia<br>
                account@khaleefnet.com
            </div>

            <div class="mg-t-25">
                <div class="fw-bold mg-b-8">Vendor Address</div>
                <div class="fs-14">
                    <div class="fw-bold">{{ $vendor['name'] ?? 'Vendor Name' }}</div>
                    @foreach(($vendor['address_lines'] ?? []) as $line)
                        <div>{{ $line }}</div>
                    @endforeach
                    @if(!empty($vendor['phone']))
                        <div>{{ $vendor['phone'] }}</div>
                    @endif
                </div>
            </div>

            <div class="mg-t-20">
                <div class="fw-bold mg-b-8">Deliver To</div>
                <div class="fs-14">
                    <div class="fw-bold">KHALEEF NET SDN BHD</div>
                    <div>{{ $quote->project->project_name ?? 'Project Name' }}</div>
                    <div>LOT 133 BLOCK P, 1ST FLOOR,</div>
                    <div>LORONG PLAZA PERMAI 2</div>
                    <div>ALAMESRA</div>
                    <div>SULAMAN COASTAL HIGHWAY</div>
                    <div>KOTA KINABALU Sabah 88450</div>
                    <div>Malaysia</div>
                    <div>jupran@khaleefnet.com</div>
                </div>
            </div>
        </div>

        <div style="text-align:right; width:300px; margin-left:auto; align-self:stretch; display:flex; flex-direction:column;">
            <div class="mg-t-16 fs-30 fw-bold" style="line-height:1; letter-spacing: 1px;">PURCHASE ORDER</div>
            <div class="mg-t-8 fs-14 fw-bold">#{{ $poNumber }}</div>

            <div style="margin-top:auto; width:100%; font-size:14px;">
                <div style="display:grid; grid-template-columns:72px 1fr; column-gap:8px; align-items:start; margin-bottom:8px;">
                    <div style="text-align:left;">Date :</div>
                    <div style="text-align:right;">{{ optional(now())->format('d M Y') }}</div>
                </div>
                <div style="display:grid; grid-template-columns:72px 1fr; column-gap:8px; align-items:start;">
                    <div style="text-align:left;">Project :</div>
                    <div style="text-align:right; line-height:1.25;">{{ $quote->project->project_name ?? '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    <table style="width:100%; border-collapse: collapse; margin-top:10px;">
        <thead>
            <tr>
                <th style="border:1px solid #222; padding:10px; text-align:center; width:36px; background:#3a3a3a; color:#fff;">#</th>
                <th style="border:1px solid #222; padding:10px; text-align:left; background:#3a3a3a; color:#fff;">Item &amp; Description</th>
                <th style="border:1px solid #222; padding:10px; width:90px; text-align:right; background:#3a3a3a; color:#fff;">Qty</th>
                <th style="border:1px solid #222; padding:10px; width:130px; text-align:right; background:#3a3a3a; color:#fff;">Rate</th>
                <th style="border:1px solid #222; padding:10px; width:156px; text-align:right; background:#3a3a3a; color:#fff;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $index => $item)
                <tr>
                    <td style="border:1px solid #222; padding:10px; text-align:center; vertical-align:top;">{{ $index + 1 }}</td>
                    <td style="border:1px solid #222; padding:10px; vertical-align:top;">
                        <div class="fw-bold">{{ $item->component->component_name ?? 'Item' }}</div>
                        <div class="mg-t-4" style="white-space: pre-wrap;">{{ $item->component->description ?? '-' }}</div>
                        @php
                            $discountPercent = (float) ($item->discount_percent ?? 0);
                            $discountAmount = ((float) ($item->quantity ?? 0) * (float) ($item->unit_price ?? 0)) * ($discountPercent / 100);
                        @endphp
                        @if($discountPercent > 0)
                            <div class="mg-t-4" style="font-size:12px; color:#444; display:flex; justify-content:space-between; gap:8px;">
                                <span>Discount: {{ number_format($discountPercent, 2) }}%</span>
                                <span>RM {{ number_format($discountAmount, 2) }}</span>
                            </div>
                        @endif
                    </td>
                    <td style="border:1px solid #222; padding:10px; text-align:right; vertical-align:top;">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td style="border:1px solid #222; padding:10px; text-align:right; vertical-align:top;">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td style="border:1px solid #222; padding:10px; text-align:right; vertical-align:top;">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td style="border:1px solid #222; padding:10px; text-align:center;">-</td>
                    <td style="border:1px solid #222; padding:10px;">No items available for this supplier.</td>
                    <td style="border:1px solid #222; padding:10px; text-align:right;">0.00</td>
                    <td style="border:1px solid #222; padding:10px; text-align:right;">0.00</td>
                    <td style="border:1px solid #222; padding:10px; text-align:right;">0.00</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="d-flex jc-end mg-t-15">
        <table style="width:320px; border-collapse: collapse; font-size:14px;">
            <thead style="display:none;"><tr><th>Label</th><th>Amount</th></tr></thead>
            <tr>
                <td style="border:1px solid #222; padding:8px 10px; text-align:right;">Sub Total</td>
                <td style="border:1px solid #222; padding:8px 10px; text-align:right;">{{ number_format($subtotal, 2) }}</td>
            </tr>
            <tr>
                <td style="border:1px solid #222; padding:8px 10px; text-align:right;">Tax ({{ number_format((float) ($taxRate ?? 0), 2) }}%)</td>
                <td style="border:1px solid #222; padding:8px 10px; text-align:right;">{{ number_format((float) ($taxAmount ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td style="border:1px solid #222; padding:10px; text-align:right; font-weight:800; background:#ffffff;">Total</td>
                <td style="border:1px solid #222; padding:10px; text-align:right; font-weight:800; background:#ffffff;">RM{{ number_format((float) ($totalAmount ?? $subtotal), 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="mg-t-20 fs-13">
        <div class="fw-bold mg-b-6">Notes</div>
        <div>1. Supplier is required to acknowledge receipt and acceptance of this purchase order within 3 working days.</div>
        <div>2. Supplier may email confirmation to {{ $deliverTo['email'] ?? 'account@company.com' }}.</div>
    </div>

    <div class="mg-t-15 fs-13">
        <div class="fw-bold mg-b-6">Terms &amp; Conditions</div>
        <div>1. Please send copies of your invoice.</div>
        <div>2. Follow the prices, terms, delivery method, and specifications listed above.</div>
        <div>3. Notify us immediately if you are unable to deliver as specified.</div>
    </div>

    <div class="d-flex jc-end mg-t-25">
        <div style="width:340px; text-align:right; padding-top:12px; border-top:1px solid #d9d9d9;">
            <div class="fw-bold mg-b-6" style="font-size:13px; letter-spacing:0.3px;">PO Approval</div>
            <div style="display:grid; grid-template-columns:128px 1fr; column-gap:10px; row-gap:6px; font-size:13px; align-items:start;">
                <div style="text-align:left;">Approval Date :</div>
                <div style="text-align:right;">{{ !empty($approvalDetails['approval_date']) ? $approvalDetails['approval_date']->format('d M Y') : '-' }}</div>

                <div style="text-align:left;">Approved By :</div>
                <div style="text-align:right; line-height:1.35;">
                    <div class="fw-bold">{{ $approvalDetails['approved_by_name'] ?? '-' }}</div>
                    @if(!empty($approvalDetails['approved_by_department']))
                        <div>{{ $approvalDetails['approved_by_department'] }}</div>
                    @endif
                    @if(!empty($approvalDetails['approved_by_email']))
                        <div>{{ $approvalDetails['approved_by_email'] }}</div>
                    @endif
                    @if(!empty($approvalDetails['approved_by_phone']))
                        <div>{{ $approvalDetails['approved_by_phone'] }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

