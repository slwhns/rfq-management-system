@php
    $showPurchasingStatus = (bool) ($showPurchasingStatus ?? false);
    $showResubmittedBadge = (bool) ($showResubmittedBadge ?? false);

    $requesterName = optional($quote->createdByUser)->name ?? 'Unknown User';
    $companyName = optional($quote->createdByUser)->company_name ?? 'RFQ Management System';
    $dateRequested = optional($quote->date_requested)->format('d M Y') ?: optional($quote->created_at)->format('d M Y');
    $dateNeeded = optional($quote->date_needed)->format('d M Y') ?: '-';

    $normalizedStatus = \App\Models\Quote::normalizeStatus($quote->status);
    $statusLabels = \App\Models\Quote::statusOptions();
    $statusLabel = $statusLabels[$normalizedStatus] ?? ucfirst(str_replace('_', ' ', $normalizedStatus));

    $hasAdminNotes = !empty(trim((string) ($quote->admin_notes ?? '')));
    $hasClientNotes = !empty(trim((string) ($quote->staff_response ?? '')));
    $isReissuedRfq = (int) ($quote->version ?? 1) > 1
        || preg_match('/-V\d+$/i', (string) ($quote->quote_number ?? '')) === 1;
    $shouldShowTemplateNotes = ($hasAdminNotes || $hasClientNotes) && ! $isReissuedRfq;
@endphp

<div id="quote-template" class="bg-white pd-30 br-10 box-shadow-basic quote-template-card">
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
            <div class="fs-30 fw-bold quote-title">REQUEST FOR QUOTATION</div>
            <div class="mg-t-8 mg-b-20 fs-14 fw-bold"># {{ $quote->quote_number }}</div>

            @if($showResubmittedBadge)
                @php
                    $isResubmitted = $quote->statusHistories()
                        ->where('from_status', 'declined')
                        ->where('to_status', 'sent')
                        ->exists();
                @endphp
                @if($isResubmitted)
                    <div style="margin-bottom:12px; padding: 6px 10px; background-color: #fff3cd; color: #856404; border-radius: 4px; font-size: 12px; font-weight: 600; border: 1px solid #ffeaa7; display: inline-block;">
                        RESUBMITTED
                    </div>
                    <div style="margin-bottom:20px;"></div>
                @endif
            @endif

            <div class="quote-meta-grid">
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Requested By :</div>
                    <div class="quote-meta-value">{{ $requesterName }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Company :</div>
                    <div class="quote-meta-value">{{ $companyName }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Project :</div>
                    <div class="quote-meta-value">{{ $quote->project->project_name ?? '-' }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Date Requested :</div>
                    <div class="quote-meta-value">{{ $dateRequested }}</div>
                </div>
                <div class="quote-meta-row">
                    <div class="quote-meta-label">Date Needed :</div>
                    <div class="quote-meta-value">{{ $dateNeeded }}</div>
                </div>
                @if($showPurchasingStatus)
                    <div class="quote-meta-row">
                        <div class="quote-meta-label" style="white-space: nowrap;">Purchasing Dept Use Only :</div>
                        <div class="quote-meta-value quote-meta-value-compact">{{ $statusLabel }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <table class="quote-items-table">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Description</th>
                <th>QTY</th>
                <th>UOM</th>
                <th>Price</th>
                <th>AMOUNT</th>
            </tr>
        </thead>
        <tbody>
            @php
                $lineItemCount = 0;
            @endphp
            @forelse($quote->items as $item)
                @php
                    $lineItemCount++;
                    $component = $item->component;
                    $itemSku = trim((string) ($component->component_code ?? ''));
                    $itemUnit = trim((string) ($component->unit ?? ''));
                    $uomLabel = $itemUnit !== '' ? ucfirst(strtolower($itemUnit)) : '-';
                    $lineSubtotal = (float) $item->quantity * (float) $item->unit_price;
                    $lineDiscount = max(0, $lineSubtotal - (float) $item->line_total);
                    $discountPercent = (float) ($item->discount_percent ?? 0);
                @endphp
                <tr>
                    <td>
                        <div class="fw-bold">{{ $itemSku !== '' ? $itemSku : '-' }}</div>
                    </td>
                    <td>
                        <div class="fw-bold">{{ $component->component_name ?? 'Item' }}</div>
                        @if($discountPercent > 0 || $lineDiscount > 0)
                            <div class="fs-12 clr-grey1">
                                Discount: 
                                @if($quote->discount_type === 'percent')
                                    {{ number_format($discountPercent, 2) }}%
                                @else
                                    RM{{ number_format($lineDiscount, 2) }}
                                @endif
                            </div>
                        @endif
                        @if(!empty($component->description))
                            <div class="fs-12 clr-grey1">{{ $component->description }}</div>
                        @endif
                    </td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $uomLabel }}</td>
                    <td>RM{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td>RM{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">No line items</td>
                </tr>
            @endforelse

            @for($i = $lineItemCount; $i < 4; $i++)
                <tr class="quote-empty-row">
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
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
            @if($quote->discount_total > 0)
            <tr>
                <td>
                    @if($quote->discount_scope === 'lumpsum')
                        Lump Sum Discount
                        @if($quote->discount_type === 'percent')
                            ({{ number_format((float) $quote->discount_value, 2) }}%)
                        @endif
                    @else
                        Total Discount
                    @endif
                </td>
                <td style="color:#bf2f2f;">-RM{{ number_format((float) $quote->discount_total, 2) }}</td>
            </tr>
            @endif
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

    @if($shouldShowTemplateNotes)
        <div class="quote-template-notes mg-t-10">
            @if($hasAdminNotes)
                <div><strong>Admin Notes:</strong> {{ $quote->admin_notes }}</div>
            @endif
            @if($hasClientNotes)
                <div><strong>Client Notes:</strong> {{ $quote->staff_response }}</div>
            @endif
        </div>
    @endif
</div>
