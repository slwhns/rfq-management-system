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
     * Generate a single purchase request from a project.
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

            $defaultCompany = trim((string) optional($project->user)->company_name);
            if ($defaultCompany === '') {
                $defaultCompany = 'QS';
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
                'quote_number' => $this->generateQuoteNumberForCompanyName($defaultCompany),
                'version' => 1,
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'tax_rate' => round($taxRate, 2),
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($totalAmount, 2),
                'status' => Quote::STATUS_DRAFT,
                'created_by' => $createdBy,
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
    protected function generateQuoteNumberForCompanyName(string $companyName)
    {
        $prefix = $this->generatePrefixFromCompanyName($companyName);

        $lastQuote = Quote::where('quote_number', 'like', $prefix . '-%')
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastQuote && preg_match('/^[A-Z]+-(\d+)$/', $lastQuote->quote_number, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%03d', $prefix, $nextNumber);
    }

    /**
     * Prefix rules:
     * - If first token is a short acronym (<=3 chars), use it (e.g. "TS").
     * - Otherwise use first letters of first two significant words (e.g. "Samajaya Electrical" => "SE").
     */
    protected function generatePrefixFromCompanyName(string $companyName)
    {
        $prefix = 'QS';
        $cleaned = Str::upper(preg_replace('/[^A-Za-z0-9\s]+/', ' ', $companyName));
        $tokens = array_values(array_filter(preg_split('/\s+/', $cleaned ?: '')));

        if (!empty($tokens)) {
            $ignored = ['SDN', 'BHD', 'LTD', 'LLP', 'PLC'];
            $significant = array_values(array_filter($tokens, function ($token) use ($ignored) {
                return !in_array($token, $ignored, true);
            }));

            if (!empty($significant)) {
                $first = $significant[0];
                if (strlen($first) <= 3) {
                    $prefix = substr($first, 0, 3);
                } else {
                    $prefix = substr($first, 0, 1);
                    if (isset($significant[1])) {
                        $prefix .= substr($significant[1], 0, 1);
                    }
                }
            }
        }

        return $prefix;
    }
}
