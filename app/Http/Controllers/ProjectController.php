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
        $query = Project::withCount('components')->latest();
        $currentUser = request()->user();
        
        if ($currentUser && in_array($currentUser->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
            $query->where('user_id', $currentUser->id);
        }
        
        $projects = $query->paginate(10);

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
            'project_title' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'project_type' => 'required|in:new,retrofit,expansion',
            'tax_rate' => 'nullable|numeric|min:0|max:100'
        ]);

        // Assign the logged in user as the owner, or default to 1 if not logged in
        $project = Project::create([
            'user_id' => request()->user()?->id ?? 1,
            'project_name' => $request->project_name,
            'project_title' => $request->input('project_title') ?: $request->project_name,
            'location' => $request->location,
            'project_type' => $request->project_type,
            'status' => 'draft',
            'tax_rate' => $request->input('tax_rate', 10)
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
            
        $this->enforceOwnProjectForClientStaff(request()->user(), $project);

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
        $this->enforceOwnProjectForClientStaff($request->user(), $project);

        $request->validate([
            'project_name' => 'required|string|max:255',
            'project_title' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'project_type' => 'required|in:new,retrofit,expansion',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        $project->update([
            'project_name' => $request->project_name,
            'project_title' => $request->input('project_title') ?: $request->project_name,
            'location' => $request->location,
            'project_type' => $request->project_type,
            'tax_rate' => $request->input('tax_rate', $project->tax_rate ?? 10),
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
        $this->enforceOwnProjectForClientStaff(request()->user(), $project);
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
        if ($component->project) {
            $this->enforceOwnProjectForClientStaff($request->user(), $component->project);
        }

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
        if ($component->project) {
            $this->enforceOwnProjectForClientStaff(request()->user(), $component->project);
        }
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

    private function enforceOwnProjectForClientStaff(?User $currentUser, Project $project): void
    {
        if (! $currentUser) {
            return;
        }

        if (in_array($currentUser->normalizedRole(), [User::ROLE_CLIENT, User::ROLE_STAFF], true)) {
            abort_unless((int) $project->user_id === (int) $currentUser->id, 403, 'You can only access your own project.');
        }
    }
}
