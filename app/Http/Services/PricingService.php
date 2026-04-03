<?php

namespace App\Http\Services;

use App\Models\Project;
use App\Models\PricingRule;

class PricingService
{
    /**
     * Calculate pricing for a project
     */
    public function calculate(Project $project)
    {
        // Load components with their related data
        $components = $project->components()
            ->with(['component', 'component.supplierPrices'])
            ->get();
        
        $subtotal = 0;
        $items = [];
        $totalDiscount = 0;

        foreach ($components as $projectComponent) {
            // Get the unit price - use custom price, or first supplier price, or 0
            $unitPrice = (float) ($projectComponent->custom_price ?? 0);
            
            if ($unitPrice == 0 && $projectComponent->component) {
                // Get first supplier price if custom price not set
                $supplierPrice = $projectComponent->component->supplierPrices->first();
                if ($supplierPrice) {
                    $unitPrice = (float) $supplierPrice->price;
                }
            }
            
            $quantity = (int) ($projectComponent->quantity ?? 0);
            $lineSubtotal = $unitPrice * $quantity;
            $subtotal += $lineSubtotal;

            $notes = [];
            if (!empty($projectComponent->notes)) {
                $decodedNotes = json_decode($projectComponent->notes, true);
                if (is_array($decodedNotes)) {
                    $notes = $decodedNotes;
                }
            }

            $discountPercent = (int) ($notes['discount_percent'] ?? 0);
            if (!in_array($discountPercent, [0, 5, 10, 15], true)) {
                $discountPercent = 0;
            }

            $lineDiscount = $lineSubtotal * ($discountPercent / 100);
            $lineTotalAfterDiscount = $lineSubtotal - $lineDiscount;
            $totalDiscount += $lineDiscount;

            $items[] = [
                'id' => $projectComponent->component_id,  // Return the component ID
                'component_id' => $projectComponent->component_id,
                'name' => $projectComponent->component->component_name,
                'code' => $projectComponent->component->component_code,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_subtotal' => round($lineSubtotal, 2),
                'discount_percent' => $discountPercent,
                'discount_amount' => round($lineDiscount, 2),
                'line_total' => round($lineTotalAfterDiscount, 2),
                'is_smart' => $projectComponent->component->is_smart_component,
            ];
        }

        $afterDiscount = $subtotal - $totalDiscount;
        $tax = $afterDiscount * 0.10; // 10% tax
        $total = $afterDiscount + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'discounts' => [],
            'total_discount' => round($totalDiscount, 2),
            'after_discount' => round($afterDiscount, 2),
            'tax_rate' => 10,
            'tax_amount' => round($tax, 2),
            'total' => round($total, 2),
            'items' => $items,
            'smart_count' => 0,
        ];
    }
}
