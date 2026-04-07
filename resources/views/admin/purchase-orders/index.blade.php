@extends('layouts.app')

@section('content')
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div>
            <div class="fs-15 fw-bold">Purchase Order (PO)</div>
        </div>
    </div>
</div>

<div id="purchase-orders-page" class="bg-white5 pd-20 br-10 box-shadow-basic" data-po-page="true" data-po-template-status="pending">
    <div class="d-flex jc-between ai-center mg-b-15" style="gap: 12px; flex-wrap: wrap;">
        <div>
            <div class="fw-bold">Purchase Orders List</div>
        </div>
    </div>

    @if(($purchaseOrders ?? collect())->count() === 0)
        <div class="pd-20 br-10" style="border:1px dashed #c7d4f3; background:#f7faff;">
            <div class="fw-bold mg-b-8">No PO created yet</div>
            <div class="fs-13 clr-grey1">
                Approve a PR, open it, and click Create PO. The record will appear here.
            </div>
        </div>
    @else
        <div class="of-auto" style="max-height:520px; overflow-y:auto;">
            <table style="width:100%; border-collapse:collapse; min-width:760px;">
                <thead>
                    <tr style="border-bottom:1px solid #d8d8d8;">
                        <th style="text-align:left; padding:12px 8px;">PO</th>
                        <th style="text-align:left; padding:12px 8px;">PR</th>
                        <th style="text-align:left; padding:12px 8px;">Project</th>
                        <th style="text-align:left; padding:12px 8px;">Company</th>
                        <th style="text-align:right; padding:12px 8px;">Total</th>
                        <th style="text-align:center; padding:12px 8px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseOrders as $po)
                        <tr style="border-bottom:1px solid #ececec;">
                            <td style="padding:10px 8px;">{{ $po->po_number }}</td>
                            <td style="padding:10px 8px;">{{ $po->purchaseRequest->quote_number ?? '-' }}</td>
                            <td style="padding:10px 8px;">{{ $po->purchaseRequest->project->project_name ?? '-' }}</td>
                            <td style="padding:10px 8px;">{{ $po->company_name }}</td>
                            <td style="padding:10px 8px; text-align:right;">RM{{ number_format((float) $po->total_amount, 2) }}</td>
                            <td style="padding:10px 8px; text-align:center;">
                                <div class="d-flex ai-center jc-center" style="gap:10px; flex-wrap:wrap;">
                                    <a href="{{ route('purchase-orders.show', $po->id) }}" class="txt-none" style="color:#2f55c7;">Open</a>
                                    <a href="{{ route('purchase-orders.show', ['purchaseOrder' => $po->id, 'download' => 1]) }}" class="txt-none" style="color:#177a3e;">Download PDF</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mg-t-15">
            {{ $purchaseOrders->links('vendor.pagination.qs') }}
        </div>
    @endif
</div>
@endsection
