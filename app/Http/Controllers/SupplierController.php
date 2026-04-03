<?php

namespace App\Http\Controllers;

use App\Models\Component;
use App\Models\ComponentSupplier;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function page()
    {
        return view($this->roleView('suppliers.index'));
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Supplier::with('componentSuppliers.component')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $supplier = Supplier::create([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $supplier,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:suppliers,name,' . $supplier->id,
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
        ]);

        $supplier->update([
            'name' => $validated['name'],
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $supplier,
        ]);
    }

    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);
        $assignedCount = ComponentSupplier::where('supplier_id', $supplier->id)->count();

        if ($assignedCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete supplier with assigned components',
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function assign(Request $request)
    {
        $validated = $request->validate([
            'component_id' => 'required|exists:components,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:component_categories,id',
            'component_code' => 'nullable|string|max:100',
            'component_name' => 'nullable|string|max:255',
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

        $assignment = ComponentSupplier::updateOrCreate(
            [
                'component_id' => $validated['component_id'],
                'supplier_id' => $validated['supplier_id'],
            ],
            [
                'price' => $validated['price'],
                'category_id' => $validated['category_id'] ?? null,
                'component_code' => $validated['component_code'] ?? null,
                'component_name' => $validated['component_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'unit' => $validated['unit'] ?? null,
                'currency' => $validated['currency'] ?? 'RM',
                'min_quantity' => $validated['min_quantity'] ?? null,
                'max_quantity' => $validated['max_quantity'] ?? null,
                'is_smart_component' => (bool) ($validated['is_smart_component'] ?? false),
                'requires_license' => (bool) ($validated['requires_license'] ?? false),
                'license_type' => $validated['license_type'] ?? null,
                'subscription_period' => $validated['subscription_period'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    public function getByComponent($componentId)
    {
        Component::findOrFail($componentId);

        $assignments = ComponentSupplier::with([
            'supplier:id,name,address,phone',
            'component:id,component_name,component_code,min_quantity,max_quantity,subscription_period',
        ])
            ->where('component_id', $componentId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function destroyAssignment($id)
    {
        $assignment = ComponentSupplier::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'success' => true,
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
