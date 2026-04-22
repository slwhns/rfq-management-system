@extends('layouts.app')

@section('content')
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">Edit Request for Quotation (RFQ) - {{ $quote->quote_number }}</div>
        <a href="{{ route('rfqs.show', $quote->id) }}" class="fs-12 clr-blue txt-none">Back to RFQ</a>
    </div>
</div>

@if($errors->any())
    <div class="bg-white5 pd-15 br-10 mg-b-15" style="border:1px solid #f0b3b3;">
        @foreach($errors->all() as $error)
            <div class="fs-12" style="color:#b33;">{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('rfqs.update', $quote->id) }}" class="bg-white5 pd-20 br-10 box-shadow-basic">
    @csrf
    @method('PATCH')

    <div class="d-grid mg-b-25" style="grid-template-columns: 1fr 1fr; column-gap: 25px; row-gap: 14px; align-items: end;">
        <div>
            <div class="fs-12 mg-b-6">Status</div>
            <select name="status" class="pd-10 bdr-all-22 br-5" style="width:96%;">
                @foreach($statusOptions as $status => $label)
                    <option value="{{ $status }}" {{ old('status', $quote->status) === $status ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <div class="fs-12 mg-b-6">Date Requested</div>
            <input type="date" name="date_requested" value="{{ old('date_requested', optional($quote->date_requested)->format('Y-m-d')) }}" class="pd-10 bdr-all-22 br-5" style="width:90%;">
        </div>
        <div>
            <div class="fs-12 mg-b-6">Tax Rate (%)</div>
            <input type="number" name="tax_rate" value="{{ old('tax_rate', $quote->tax_rate) }}" step="0.01" min="0" max="100" class="pd-10 bdr-all-22 br-5" style="width:93%;">
        </div>
        <div>
            <div class="fs-12 mg-b-6">Date Needed</div>
            <input type="date" name="date_needed" value="{{ old('date_needed', optional($quote->date_needed)->format('Y-m-d')) }}" class="pd-10 bdr-all-22 br-5" style="width:90%;">
        </div>
    </div>

    <div class="fw-bold mg-b-10">RFQ Items</div>
    <table style="width:100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom:1px solid #d8d8d8;">
                <th style="text-align:left; padding:10px 8px;">Item</th>
                <th style="text-align:right; padding:10px 8px; width:120px;">Quantity</th>
                <th style="text-align:right; padding:10px 8px; width:160px;">Unit Price</th>
                <th style="text-align:right; padding:10px 8px; width:160px;">Discount %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $index => $item)
                <tr style="border-bottom:1px solid #ececec;">
                    <td style="padding:10px 8px;">
                        <div>{{ $item->component->component_name ?? '-' }}</div>
                        <div class="fs-11 clr-grey1">SKU: {{ $item->component->component_code ?? '-' }}</div>
                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                    </td>
                    <td style="padding:10px 8px; text-align:right;">
                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $item->quantity) }}" min="1" step="1" class="pd-8 bdr-all-22 br-5" style="width:100px; text-align:right;">
                    </td>
                    <td style="padding:10px 8px; text-align:right;">
                        <input type="number" name="items[{{ $index }}][unit_price]" value="{{ old('items.'.$index.'.unit_price', $item->unit_price) }}" min="0" step="0.01" class="pd-8 bdr-all-22 br-5" style="width:140px; text-align:right;">
                    </td>
                    <td style="padding:10px 8px; text-align:right;">
                        <input type="number" name="items[{{ $index }}][discount_percent]" value="{{ old('items.'.$index.'.discount_percent', $item->discount_percent ?? 0) }}" min="0" max="100" step="0.01" class="pd-8 bdr-all-22 br-5" style="width:140px; text-align:right;">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="d-flex jc-end mg-t-20" style="gap:10px;">
        <a href="{{ route('rfqs.index') }}" class="pd-10 br-5 txt-none" style="border:1px solid #d0d0d0; color:#555;">Cancel</a>
        <button type="submit" class="bg-blue clr-white pd-10 br-5 cursor-pointer" style="border:0;">Save Changes</button>
    </div>
</form>
@endsection

