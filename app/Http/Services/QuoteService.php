<?php

namespace App\Http\Services;

use App\Models\Project;
use App\Models\ProjectComponent;
use App\Models\Quote;
use App\Models\QuoteItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuoteService
{
    protected $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    /**
    * Generate a single RFQ from a project.
     */
    public function generateFromProject(Project $project, ?int $createdBy = null, ?string $department = null)
    {
        return DB::transaction(function () use ($project, $createdBy, $department) {
            $projectComponents = ProjectComponent::with('component')
                ->where('project_id', $project->id)
                ->get();

            if ($projectComponents->isEmpty()) {
                return collect();
            }

            $taxRate = (float) ($project->tax_rate ?? 10.0);
            $subtotal = 0.0;
            $discountTotal = 0.0;
            $items = [];

            foreach ($projectComponents as $projectComponent) {
                $unitPrice = (float) ($projectComponent->effective_price ?? 0);
                $quantity = (int) ($projectComponent->quantity ?? 0);
                $lineSubtotal = $unitPrice * $quantity;

                $notes = [];
                if (!empty($projectComponent->notes)) {
                    $decoded = json_decode((string) $projectComponent->notes, true);
                    if (is_array($decoded)) {
                        $notes = $decoded;
                    }
                }

                $discountPercent = (float) ($notes['discount_percent'] ?? 0);
                if (!in_array((int) $discountPercent, [0, 5, 10, 15], true)) {
                    $discountPercent = 0;
                }

                $lineDiscount = $lineSubtotal * ($discountPercent / 100);
                $lineTotal = $lineSubtotal - $lineDiscount;

                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;

                $items[] = [
                    'component_id' => (int) $projectComponent->component_id,
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'discount_percent' => round($discountPercent, 2),
                    'line_total' => round($lineTotal, 2),
                ];
            }

            $afterDiscount = $subtotal - $discountTotal;
            $taxAmount = $afterDiscount * ($taxRate / 100);
            $totalAmount = $afterDiscount + $taxAmount;

            $quote = Quote::create([
                'project_id' => $project->id,
                'quote_number' => $this->generateQuoteNumber(),
                'version' => 1,
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'discount_scope' => 'item',
                'discount_type' => 'percent',
                'discount_value' => null,
                'tax_rate' => round($taxRate, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($totalAmount, 2),
                'status' => Quote::STATUS_DRAFT,
                'created_by' => $createdBy,
                'date_requested' => now(),
                'date_needed' => now()->addDays(30),
                'department' => $department,
            ]);

            foreach ($items as $item) {
                QuoteItem::create([
                    'quote_id' => $quote->id,
                    'component_id' => $item['component_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'],
                    'discount_type' => 'percent',
                    'discount_value' => $item['discount_percent'],
                    'line_total' => $item['line_total']
                ]);
            }

            // Update project status
            $project->update(['status' => 'quoted']);

            return collect([$quote]);
        });
    }

    /**
     * Generate unique quote number
     */
    protected function generateQuoteNumber()
    {
        $prefix = 'QUO';

        $maxSequence = 0;
        $quoteNumbers = Quote::where('quote_number', 'like', $prefix . '-%')->pluck('quote_number');

        foreach ($quoteNumbers as $quoteNumber) {
            if (preg_match('/^' . $prefix . '-(\d+)(?:-V\d+)?$/', (string) $quoteNumber, $matches)) {
                $maxSequence = max($maxSequence, (int) $matches[1]);
            }
        }

        $nextNumber = $maxSequence + 1;
        $candidate = sprintf('%s-%03d', $prefix, $nextNumber);

        while (Quote::where('quote_number', $candidate)->exists()) {
            $nextNumber++;
            $candidate = sprintf('%s-%03d', $prefix, $nextNumber);
        }

        return $candidate;
    }
}
