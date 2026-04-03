<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Quote;
use App\Models\Component;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Show the dashboard
     */
    public function index()
    {
        // Get stats for dashboard
        $stats = [
            'total_projects' => Project::count(),
            'active_quotes' => Quote::whereIn('status', [
                Quote::STATUS_DRAFT,
                Quote::STATUS_IN_PROGRESS,
                Quote::STATUS_NEGOTIATION,
            ])->count(),
            'total_value' => Quote::sum('total_amount'),
            'total_components' => Component::count(),
            'recent_projects' => Project::withCount('components')
                ->latest()
                ->take(5)
                ->get()
        ];

        // Get all projects for dropdown
        $projects = Project::all();

        return view($this->roleView('dashboard.index'), compact('stats', 'projects'));
    }

    private function roleView(string $view): string
    {
        $role = request()->user()?->normalizedRole();
        if (in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true) && view()->exists("admin.{$view}")) {
            return "admin.{$view}";
        }

        if (view()->exists("staff.{$view}")) {
            return "staff.{$view}";
        }

        abort(404, "View not found for role: {$view}");
    }
}
