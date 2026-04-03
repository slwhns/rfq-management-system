<?php

namespace App\Http\Controllers;

use App\Models\ComponentCategory;
use App\Models\Component;
use App\Models\ComponentSupplier;
use App\Models\Project;
use App\Models\ProjectComponent;
use App\Http\Services\PricingService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PricingController extends Controller
{
    protected PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    public function index()
    {
        return redirect()->route('projects.index');
    }

    public function categories()
    {
        return response()->json([
            'success' => true,
            'data' => ComponentCategory::all()
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
        ]);

        $category = ComponentCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? '📦',
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    public function updateCategory(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:10',
        ]);

        $category = ComponentCategory::findOrFail($id);
        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? '📦',
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    public function destroyCategory($id)
    {
        $category = ComponentCategory::findOrFail($id);
        $componentsCount = Component::where('category_id', $category->id)->count();

        if ($componentsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing components',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function components(Request $request, $categoryId = null)
    {
        $resolvedCategoryId = $categoryId ?? $request->query('category_id');

        if ($resolvedCategoryId === null || $resolvedCategoryId === '') {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        $componentsQuery = Component::with(['supplierPrices.supplier:id,name,address,phone']);

        if ($resolvedCategoryId === 'all') {
            return response()->json([
                'success' => true,
                'data' => $componentsQuery->get(),
            ]);
        }

        if (!ComponentCategory::whereKey($resolvedCategoryId)->exists()) {
            throw ValidationException::withMessages([
                'category_id' => ['The selected category is invalid.'],
            ]);
        }

        $components = $componentsQuery
            ->where('category_id', $resolvedCategoryId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $components,
        ]);
    }

    public function storeComponent(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:component_categories,id',
            'component_code' => 'required|string|max:100',
            'component_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'min_quantity' => 'nullable|integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
            'is_smart_component' => 'nullable|boolean',
            'requires_license' => 'nullable|boolean',
            'license_type' => 'nullable|string|max:100',
            'subscription_period' => 'nullable|string|max:100',
        ]);

        $component = Component::create([
            'category_id' => $validated['category_id'],
            'component_code' => $validated['component_code'],
            'component_name' => $validated['component_name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'] ?? 'unit',
            'currency' => $validated['currency'] ?? 'RM',
            'min_quantity' => $validated['min_quantity'] ?? 1,
            'max_quantity' => $validated['max_quantity'] ?? null,
            'is_smart_component' => (bool) ($validated['is_smart_component'] ?? false),
            'requires_license' => (bool) ($validated['requires_license'] ?? false),
            'license_type' => $validated['license_type'] ?? null,
            'subscription_period' => $validated['subscription_period'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $component,
        ], 201);
    }

    public function updateItemComponent(Request $request, $id)
    {
        $validated = $request->validate([
            'component_code' => 'required|string|max:100',
            'component_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'unit' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'min_quantity' => 'nullable|integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
            'is_smart_component' => 'nullable|boolean',
            'requires_license' => 'nullable|boolean',
            'license_type' => 'nullable|string|max:100',
            'subscription_period' => 'nullable|string|max:100',
        ]);

        $component = Component::findOrFail($id);
        $component->update([
            'component_code' => $validated['component_code'],
            'component_name' => $validated['component_name'],
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'] ?? 'unit',
            'currency' => $validated['currency'] ?? 'RM',
            'min_quantity' => $validated['min_quantity'] ?? 1,
            'max_quantity' => $validated['max_quantity'] ?? null,
            'is_smart_component' => (bool) ($validated['is_smart_component'] ?? false),
            'requires_license' => (bool) ($validated['requires_license'] ?? false),
            'license_type' => $validated['license_type'] ?? null,
            'subscription_period' => $validated['subscription_period'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $component,
        ]);
    }

    public function destroyItemComponent($id)
    {
        $component = Component::findOrFail($id);

        ProjectComponent::where('component_id', $component->id)->delete();
        $component->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function addComponent(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'component_id' => 'required|exists:components,id',
            'quantity' => 'required|integer|min:1',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        $componentModel = Component::findOrFail($validated['component_id']);
        $supplierOffer = null;

        if (!empty($validated['supplier_id'])) {
            $supplierOffer = ComponentSupplier::where('component_id', $validated['component_id'])
                ->where('supplier_id', $validated['supplier_id'])
                ->with('supplier:id,name')
                ->first();

            if (!$supplierOffer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected supplier does not provide this component',
                ], 422);
            }
        }

        $effectiveMinQuantity = $supplierOffer?->min_quantity ?? $componentModel->min_quantity ?? 1;
        $effectiveMaxQuantity = $supplierOffer?->max_quantity ?? $componentModel->max_quantity;

        $isBelowMinimum = $validated['quantity'] < (int) $effectiveMinQuantity;
        $isAboveMaximum = !is_null($effectiveMaxQuantity)
            && $validated['quantity'] > (int) $effectiveMaxQuantity;

        if ($isBelowMinimum || $isAboveMaximum) {
            return response()->json([
                'success' => false,
                'message' => $isBelowMinimum
                    ? 'Quantity is below component minimum quantity'
                    : 'Quantity exceeds component maximum quantity',
            ], 422);
        }

        $customPrice = null;
        $notes = null;

        if (!empty($validated['supplier_id'])) {
            $customPrice = $supplierOffer->price;
            $notes = json_encode([
                'supplier_id' => $validated['supplier_id'],
                'supplier_name' => $supplierOffer?->supplier?->name,
                'subscription_period' => $supplierOffer?->subscription_period ?? $componentModel->subscription_period,
            ]);
        }

        $component = ProjectComponent::create([
            'project_id' => $validated['project_id'],
            'component_id' => $validated['component_id'],
            'quantity' => $validated['quantity'],
            'custom_price' => $customPrice,
            'notes' => $notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $component
        ]);
    }

    public function calculate($projectId)
    {
        $project = Project::findOrFail($projectId);
        $summary = $this->pricingService->calculate($project);

        return response()->json([
            'success' => true,
            'data' => [
                'subtotal' => $summary['subtotal'] ?? 0,
                'total_discount' => $summary['total_discount'] ?? 0,
                'after_discount' => $summary['after_discount'] ?? 0,
                'tax_amount' => $summary['tax_amount'] ?? 0,
                'tax_rate' => $summary['tax_rate'] ?? 10,
                'total' => $summary['total'] ?? 0,
            ]
        ]);
    }

    private function roleView(string $view): string
    {
        $role = request()->user()?->normalizedRole();
        if ($role === User::ROLE_ADMIN && view()->exists("admin.{$view}")) {
            return "admin.{$view}";
        }

        if (view()->exists("staff.{$view}")) {
            return "staff.{$view}";
        }

        abort(404, "View not found for role: {$view}");
    }
}
