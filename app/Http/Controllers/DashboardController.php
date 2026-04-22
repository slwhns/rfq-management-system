<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Quote;
use App\Models\QuoteStatusHistory;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    /**
     * Show the dashboard
     */
    public function index()
    {
        $currentUser = request()->user();
        $role = $currentUser?->normalizedRole();

        $viewData = array_merge(
            ['role' => $role],
            $this->buildDashboardData($currentUser, $role)
        );

        return view($this->roleView('dashboard.index'), $viewData);
    }

    private function buildDashboardData(?User $currentUser, ?string $role): array
    {
        if (in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)) {
            return $this->buildAdminDashboardData($role);
        }

        return $this->buildClientDashboardData($currentUser);
    }

    private function buildAdminDashboardData(?string $role): array
    {
        $pendingReviewPrs = Quote::with(['project', 'createdByUser'])
            ->whereIn('status', [
                Quote::STATUS_SENT,
                'in_progress',
                'negotiation',
                'viewed',
            ])
            ->orderByRaw('CASE WHEN date_needed IS NULL THEN 1 ELSE 0 END')
            ->orderBy('date_needed')
            ->orderBy('created_at')
            ->take(20)
            ->get();

        $recentAdminActivities = QuoteStatusHistory::with(['quote.project', 'changedByUser'])
            ->whereHas('changedByUser', function ($userQuery) {
                $userQuery->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERADMIN]);
            })
            ->latest()
            ->take(12)
            ->get();

        $approvedQuoteCount = Quote::query()
            ->whereIn('status', [Quote::STATUS_APPROVED, 'accepted'])
            ->count();

        $data = [
            'pendingReviewPrs' => $pendingReviewPrs,
            'pendingReviewCount' => $pendingReviewPrs->count(),
            'recentAdminActivities' => $recentAdminActivities,
            'approvedQuoteCount' => $approvedQuoteCount,
        ];

        if ($role === User::ROLE_SUPERADMIN) {
            $data['staffUsers'] = User::query()
                ->select(['id', 'name', 'role'])
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user) => $user->normalizedRole() !== User::ROLE_SUPERADMIN)
                ->values();
        }

        return $data;
    }

    private function buildClientDashboardData(?User $currentUser): array
    {
        $userId = $currentUser?->id;

        $clientProjects = Project::query()
            ->withCount('quotes')
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $clientQuotes = Quote::with([
            'project',
            'adminNotesUpdatedBy',
            'createdByUser',
            'statusHistories' => function ($historyQuery) {
                $historyQuery->with('changedByUser')->latest();
            },
        ])
            ->where('created_by', $userId)
            ->latest()
            ->take(12)
            ->get();

        $quoteStateSummary = $this->buildQuoteStateSummary($userId);
        $recentProjectActivities = $this->buildRecentProjectActivities($clientProjects);
        $clientQuotesForActivity = $clientQuotes->map(fn (Quote $quote) => $this->buildQuoteActivity($quote));

        $recentActivities = $recentProjectActivities
            ->concat($clientQuotesForActivity)
            ->filter(fn ($activity) => !empty($activity['timestamp']))
            ->sortByDesc(fn ($activity) => $activity['timestamp'])
            ->values()
            ->take(8);

        return [
            'clientQuotes' => $clientQuotes,
            'clientProjects' => $clientProjects,
            'quoteStateSummary' => $quoteStateSummary,
            'recentActivities' => $recentActivities,
            'totalClientQuotes' => Quote::where('created_by', $userId)->count(),
            'draftClientQuotes' => Quote::where('created_by', $userId)->where('status', Quote::STATUS_DRAFT)->count(),
            'sentClientQuotes' => Quote::where('created_by', $userId)->where('status', Quote::STATUS_SENT)->count(),
            'approvedClientQuotes' => Quote::where('created_by', $userId)->where('status', Quote::STATUS_APPROVED)->count(),
            'declinedClientQuotes' => Quote::where('created_by', $userId)->where('status', Quote::STATUS_DECLINED)->count(),
            'totalClientProjects' => $clientProjects->count(),
        ];
    }

    private function buildQuoteStateSummary(?int $userId): Collection
    {
        $quoteStateOrder = [
            Quote::STATUS_DRAFT,
            Quote::STATUS_SENT,
            Quote::STATUS_APPROVED,
            Quote::STATUS_DECLINED,
        ];

        $quoteStateCounts = Quote::query()
            ->where('created_by', $userId)
            ->pluck('status')
            ->map(fn ($status) => Quote::normalizeStatus((string) $status))
            ->filter(fn ($status) => in_array($status, $quoteStateOrder, true))
            ->countBy();

        return collect($quoteStateOrder)->map(function (string $status) use ($quoteStateCounts) {
            return [
                'status' => $status,
                'label' => Quote::statusOptions()[$status] ?? ucfirst(str_replace('_', ' ', $status)),
                'count' => (int) ($quoteStateCounts[$status] ?? 0),
            ];
        })->values();
    }

    private function buildRecentProjectActivities(Collection $clientProjects): Collection
    {
        return $clientProjects->take(5)->map(function (Project $project) {
            $projectStatus = Quote::normalizeStatus((string) ($project->status ?? Quote::STATUS_DRAFT));

            return [
                'title' => $project->project_name ?? 'Project',
                'description' => 'Project ' . (Quote::statusOptions()[$projectStatus] ?? ucfirst(str_replace('_', ' ', $projectStatus))),
                'timestamp' => $project->updated_at ?? $project->created_at,
                'type' => 'project',
            ];
        });
    }

    private function buildQuoteActivity(Quote $quote): array
    {
        $normalizedStatus = Quote::normalizeStatus($quote->status);
        $latestHistory = $quote->statusHistories->first();
        $latestHistoryBy = $latestHistory?->changedByUser?->normalizedRole();
        $latestHistoryTo = Quote::normalizeStatus((string) ($latestHistory?->to_status ?? ''));
        $latestHistoryAt = $latestHistory?->created_at;

        $timestamp = collect([
            $quote->admin_notes_updated_at,
            $quote->quotation_sent_at,
            $latestHistoryAt,
            $quote->updated_at,
            $quote->created_at,
        ])->filter()->sortDesc()->first();

        $result = $this->resolveQuoteActivityMeta(
            $quote,
            $normalizedStatus,
            $latestHistory,
            $latestHistoryBy,
            $latestHistoryTo,
            $latestHistoryAt,
            $timestamp
        );

        return [
            'title' => $quote->quote_number,
            'description' => $result['description'],
            'timestamp' => $result['timestamp'],
            'type' => 'rfq',
            'quote_id' => $quote->id,
            'project_name' => $quote->project->project_name ?? '-',
            'details' => $result['details'],
            'is_admin_activity' => $result['is_admin_activity'],
        ];
    }

    private function resolveQuoteActivityMeta(
        Quote $quote,
        string $normalizedStatus,
        $latestHistory,
        ?string $latestHistoryBy,
        string $latestHistoryTo,
        $latestHistoryAt,
        $fallbackTimestamp
    ): array {
        $description = 'RFQ updated';
        $details = null;
        $timestamp = $fallbackTimestamp;
        $isAdminActivity = false;

        if ($this->isAdminFinalReissueResponse($quote, $latestHistoryBy, $latestHistoryTo)) {
            $description = 'Admin responded to re-issue: ' . (Quote::statusOptions()[$latestHistoryTo] ?? ucfirst($latestHistoryTo));
            $details = $latestHistory?->status_note ?: null;
            $timestamp = $latestHistoryAt ?: $fallbackTimestamp;
            $isAdminActivity = true;
        } elseif ($this->hasPreferredAdminNoteUpdate($quote)) {
            $description = 'Admin updated note';
            $details = $quote->admin_notes ?: null;
            $timestamp = $quote->admin_notes_updated_at;
            $isAdminActivity = true;
        } elseif ($this->hasAdminShare($quote)) {
            $description = 'Admin shared RFQ update';
            $timestamp = $quote->quotation_sent_at;
            $isAdminActivity = true;
        } elseif ($this->isAdminStatusUpdate($latestHistoryBy)) {
            $description = 'Admin changed status to ' . (Quote::statusOptions()[$latestHistoryTo] ?? ucfirst(str_replace('_', ' ', $latestHistoryTo)));
            $details = $latestHistory?->status_note ?: null;
            $timestamp = $latestHistoryAt ?: $fallbackTimestamp;
            $isAdminActivity = true;
        } elseif ($this->isFinalClientState($normalizedStatus)) {
            $description = 'RFQ is ' . (Quote::statusOptions()[$normalizedStatus] ?? ucfirst($normalizedStatus));
        }

        return [
            'description' => $description,
            'details' => $details,
            'timestamp' => $timestamp,
            'is_admin_activity' => $isAdminActivity,
        ];
    }

    private function isAdminFinalReissueResponse(Quote $quote, ?string $latestHistoryBy, string $latestHistoryTo): bool
    {
        return $this->isAdminStatusUpdate($latestHistoryBy)
            && $this->isReissueQuote($quote)
            && in_array($latestHistoryTo, [Quote::STATUS_APPROVED, Quote::STATUS_DECLINED], true);
    }

    private function hasPreferredAdminNoteUpdate(Quote $quote): bool
    {
        return (bool) $quote->admin_notes_updated_at
            && (! $quote->quotation_sent_at || $quote->admin_notes_updated_at->gte($quote->quotation_sent_at));
    }

    private function hasAdminShare(Quote $quote): bool
    {
        return (bool) $quote->quotation_sent_at;
    }

    private function isAdminStatusUpdate(?string $latestHistoryBy): bool
    {
        return in_array($latestHistoryBy, [User::ROLE_ADMIN, User::ROLE_SUPERADMIN], true);
    }

    private function isFinalClientState(string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, [Quote::STATUS_APPROVED, Quote::STATUS_DECLINED], true);
    }

    private function isReissueQuote(Quote $quote): bool
    {
        return ((int) ($quote->version ?? 1) > 1)
            || preg_match('/-V\d+$/i', (string) $quote->quote_number) === 1;
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
