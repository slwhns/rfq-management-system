@extends('layouts.app')

@section('content')
<div class="bg-white5 pd-15 bdr-bottom-22 mg-b-20">
    <div class="d-flex jc-between ai-center">
        <div class="fs-15 fw-bold">Edit Request for Quotation (RFQ) - {{ $quote->quote_number }}</div>
        <a href="{{ route('rfqs.show', $quote->id) }}" class="fs-12 clr-white txt-underline">Back to RFQ</a>
    </div>
</div>

@if($errors->any())
    <div class="bg-white5 pd-15 br-10 mg-b-15" style="border:1px solid #f0b3b3;">
        @foreach($errors->all() as $error)
            <div class="fs-12" style="color:#b33;">{{ $error }}</div>
        @endforeach
    </div>
@endif

<form id="rfq-edit-page" method="POST" action="{{ route('rfqs.update', $quote->id) }}" class="bg-white5 pd-20 br-10 box-shadow-basic">
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

    <div class="mg-b-20">
        <div class="fs-12 mg-b-6">Client Note</div>
        <textarea name="client_note" class="pd-10 bdr-all-22 br-5" style="width:100%; min-height:90px; resize:vertical;" placeholder="Add your note/comment for this RFQ...">{{ old('client_note', $quote->staff_response) }}</textarea>
    </div>

    <div class="fw-bold mg-b-10">RFQ Items</div>
    <table style="width:100%; border-collapse: collapse;">
        <thead>
            <tr style="border-bottom:1px solid #d8d8d8;">
                <th style="text-align:left; padding:10px 8px;">Item</th>
                <th style="text-align:right; padding:10px 8px; width:120px;">Quantity</th>
                <th style="text-align:right; padding:10px 8px; width:160px;">Unit Price</th>
                <th style="text-align:right; padding:10px 8px; width:160px;">Total</th>
                <th style="text-align:center; padding:10px 8px; width:90px;">Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->items as $index => $item)
                @php
                    $unitPrice = (float) ($item->unit_price ?? 0);
                    $quantity = (float) ($item->quantity ?? 0);
                    $itemTotal = $quantity * $unitPrice;
                @endphp
                <tr data-rfq-item-row="true" style="border-bottom:1px solid #ececec;">
                    <td style="padding:10px 8px;">
                        <div>{{ $item->component->component_name ?? '-' }}</div>
                        <div class="fs-11 clr-grey1">SKU: {{ $item->component->component_code ?? '-' }}</div>
                        <input type="hidden" name="items[{{ $index }}][id]" value="{{ $item->id }}">
                    </td>
                    <td style="padding:10px 8px; text-align:right;">
                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', $item->quantity) }}" min="1" step="1" class="pd-8 bdr-all-22 br-5" style="width:100px; text-align:right;" data-rfq-quantity>
                    </td>
                    <td style="padding:10px 8px; text-align:right;">
                        <div class="pd-8" style="min-width:140px; display:inline-block; text-align:right;" data-rfq-unit-price="{{ $unitPrice }}">RM {{ number_format($unitPrice, 2) }}</div>
                    </td>
                    <td style="padding:10px 8px; text-align:right;">
                        <div class="pd-8 fw-bold" style="min-width:140px; display:inline-block; text-align:right;" data-rfq-item-total>RM {{ number_format($itemTotal, 2) }}</div>
                    </td>
                    <td style="padding:10px 8px; text-align:center;">
                        <button type="button" class="pd-8 br-5 cursor-pointer" style="border:1px solid #bf2f2f; background:#fff; color:#bf2f2f; min-width:72px;" data-rfq-remove-item>Remove</button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="d-flex jc-end mg-t-20" style="gap:10px;">
        <a href="{{ route('rfqs.index') }}" class="pd-10 br-5 txt-none" style="border:1px solid #d0d0d0; color:white;">Cancel</a>
        <button type="submit" class="bg-blue clr-white pd-10 br-5 cursor-pointer" style="border:0;">Save Changes</button>
    </div>
</form>

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('rfq-edit-page');
        const rows = () => Array.from(document.querySelectorAll('#rfq-edit-page tr[data-rfq-item-row]'));

        const formatMoney = (value) => `RM ${Number(value || 0).toFixed(2)}`;

        const updateTotals = () => {
            rows().forEach((row) => updateRowTotal(row));
        };

        const updateRowTotal = (row) => {
            const quantityInput = row.querySelector('[data-rfq-quantity]');
            const unitPriceElement = row.querySelector('[data-rfq-unit-price]');
            const totalElement = row.querySelector('[data-rfq-item-total]');

            if (!quantityInput || !unitPriceElement || !totalElement) {
                return;
            }

            const quantity = Number(quantityInput.value || 0);
            const unitPrice = Number(unitPriceElement.dataset.rfqUnitPrice || 0);
            totalElement.textContent = formatMoney(quantity * unitPrice);
        };

        rows().forEach((row) => {
            const quantityInput = row.querySelector('[data-rfq-quantity]');
            const removeButton = row.querySelector('[data-rfq-remove-item]');

            if (!quantityInput) {
                return;
            }

            updateRowTotal(row);
            quantityInput.addEventListener('input', () => updateRowTotal(row));
            quantityInput.addEventListener('change', () => updateRowTotal(row));

            removeButton?.addEventListener('click', () => {
                const currentRows = rows();
                if (currentRows.length <= 1) {
                    globalThis.show_popup_temp?.('error', 'Cannot Remove Item', ['At least one RFQ item must remain.']);
                    return;
                }

                row.remove();
                updateTotals();
            });
        });
    }());
</script>
@endpush
@endsection

