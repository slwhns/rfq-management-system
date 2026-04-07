<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Quote;
use App\Models\Component;
use App\Models\ComponentCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Show the dashboard
     */
    public function index()
    {
        $currentUser = request()->user();
        $role = $currentUser?->normalizedRole();

        $viewData = [
            'role' => $role,
        ];

        if (in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            $pendingReviewPrs = Quote::with('project')
                ->whereIn('status', [Quote::STATUS_DRAFT, Quote::STATUS_IN_PROGRESS, Quote::STATUS_NEGOTIATION])
                ->latest()
                ->take(12)
                ->get();

            $approvedPoCount = PurchaseOrder::where('status', 'approved')->count();

            $viewData['pendingReviewPrs'] = $pendingReviewPrs;
            $viewData['approvedPoCount'] = $approvedPoCount;

            if ($role === User::ROLE_SUPERADMIN) {
                $staffUsers = User::query()
                    ->select(['id', 'name', 'role'])
                    ->orderBy('name')
                    ->get()
                    ->filter(fn (User $user) => $user->normalizedRole() !== User::ROLE_SUPERADMIN)
                    ->values();

                $viewData['staffUsers'] = $staffUsers;
            }
        } else {
            $quotesWithAdminComments = Quote::with(['project', 'adminNotesUpdatedBy'])
                ->whereNotNull('admin_notes')
                ->where('admin_notes', '!=', '')
                ->latest('admin_notes_updated_at')
                ->take(12)
                ->get();

            $viewData['quotesWithAdminComments'] = $quotesWithAdminComments;
            $viewData['totalItems'] = Component::count();
            $viewData['totalCategories'] = ComponentCategory::count();
            $viewData['totalSuppliers'] = Supplier::count();
        }

        return view($this->roleView('dashboard.index'), $viewData);
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
