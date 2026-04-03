<?php

namespace App\Http\Controllers;

use App\Models\ProjectComponent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects - PUBLIC
     */
    public function index()
    {
        $projects = Project::withCount('components')
            ->latest()
            ->paginate(10);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $projects
            ]);
        }

        return view($this->roleView('projects.index'), compact('projects'));
    }

    /**
     * Store a newly created project - PUBLIC
     */
    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'project_type' => 'required|in:new,retrofit,expansion'
        ]);

        // Use a default user ID or make it optional
        $project = Project::create([
            'user_id' => 1, // Default user ID
            'project_name' => $request->project_name,
            'location' => $request->location,
            'project_type' => $request->project_type,
            'status' => 'draft'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully',
            'data' => $project
        ], 201);
    }

    /**
     * Display the specified project - PUBLIC
     */
    public function show($id)
    {
        $project = Project::with('components.component')
            ->findOrFail($id);

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $project
            ]);
        }

        return view($this->roleView('projects.show'), compact('project'));
    }

    /**
     * Update the specified project - PUBLIC
     */
    public function update(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $request->validate([
            'project_name' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'project_type' => 'required|in:new,retrofit,expansion',
        ]);

        $project->update([
            'project_name' => $request->project_name,
            'location' => $request->location,
            'project_type' => $request->project_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'data' => $project,
        ]);
    }

    /**
     * Remove the specified project - PUBLIC
     */
    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $project->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
        ]);
    }

    /**
     * Update a project component assignment
     */
    public function updateComponent(Request $request, $id)
    {
        $component = ProjectComponent::findOrFail($id);

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'custom_price' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|in:0,5,10,15',
        ]);

        $existingNotes = [];
        if (!empty($component->notes)) {
            $decodedNotes = json_decode($component->notes, true);
            if (is_array($decodedNotes)) {
                $existingNotes = $decodedNotes;
            }
        }

        if (array_key_exists('discount_percent', $validated)) {
            $existingNotes['discount_percent'] = (int) $validated['discount_percent'];
        }

        $component->update([
            'quantity' => $validated['quantity'],
            'custom_price' => $validated['custom_price'] ?? null,
            'notes' => empty($existingNotes) ? null : json_encode($existingNotes),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project component updated successfully',
            'data' => $component,
        ]);
    }

    /**
     * Remove a component assignment from a project
     */
    public function destroyComponent($id)
    {
        $component = ProjectComponent::findOrFail($id);
        $component->delete();

        return response()->json([
            'success' => true,
            'message' => 'Project component deleted successfully',
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
