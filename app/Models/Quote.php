<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quote extends Model
{
    use HasFactory;

    protected $table = 'purchase_requests';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_APPROVED,
        self::STATUS_DECLINED,
        self::STATUS_CANCELLED,
    ];

    public const STAFF_MUTABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CANCELLED,
    ];

    public const ADMIN_MUTABLE_STATUSES = [
        self::STATUS_SENT,
        self::STATUS_CANCELLED,
    ];

    public const ADMIN_VISIBLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_APPROVED,
        self::STATUS_DECLINED,
        self::STATUS_CANCELLED,
    ];

    public const STAFF_VISIBLE_STATUSES = self::STATUSES;

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_SENT => 'Awaiting Review',
        self::STATUS_APPROVED => 'Approved',
        self::STATUS_DECLINED => 'Rejected',
        self::STATUS_CANCELLED => 'Cancelled',
    ];

    public const STATUS_BADGE_STYLES = [
        self::STATUS_DRAFT => ['background' => '#eff1f7', 'color' => '#4a5470', 'border' => '#dbe0ee'],
        self::STATUS_SENT => ['background' => '#fff7d6', 'color' => '#9a6a00', 'border' => '#ffe08a'],
        self::STATUS_APPROVED => ['background' => '#eafaf0', 'color' => '#1f8a4c', 'border' => '#bdeacb'],
        self::STATUS_DECLINED => ['background' => '#ffefef', 'color' => '#bf2f2f', 'border' => '#f9c8c8'],
        self::STATUS_CANCELLED => ['background' => '#f5f5f5', 'color' => '#616161', 'border' => '#dddddd'],
    ];

    public const LEGACY_STATUS_MAP = [
        'in_progress' => self::STATUS_SENT,
        'negotiation' => self::STATUS_SENT,
        'viewed' => self::STATUS_SENT,
        'sent' => self::STATUS_SENT,
        'accepted' => self::STATUS_APPROVED,
        'rejected' => self::STATUS_DECLINED,
        'expired' => self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'project_id',
        'quote_number',
        'version',
        'subtotal',
        'discount_total',
        'discount_scope',
        'discount_type',
        'discount_value',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'status',
        'created_by',
        'date_needed',
        'date_requested',
        'department',
        'approved_by',
        'admin_notes',
        'admin_notes_updated_by',
        'staff_response',
        'staff_response_updated_by',
        'quotation_sent_at',
        'client_decision_note',
        'client_decision_at',
    ];

    protected $casts = [
        'date_needed' => 'datetime',
        'admin_notes_updated_at' => 'datetime',
        'staff_response_updated_at' => 'datetime',
        'date_requested' => 'datetime',
        'quotation_sent_at' => 'datetime',
        'client_decision_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(QuoteStatusHistory::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function adminNotesUpdatedBy()
    {
        return $this->belongsTo(User::class, 'admin_notes_updated_by');
    }

    public function staffResponseUpdatedBy()
    {
        return $this->belongsTo(User::class, 'staff_response_updated_by');
    }

    public static function statusOptions(): array
    {
        return self::STATUS_LABELS;
    }

    public static function statusBadgeStyles(): array
    {
        return self::STATUS_BADGE_STYLES;
    }

    public static function isValidStatus(?string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function normalizeStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return self::STATUS_DRAFT;
        }

        return self::LEGACY_STATUS_MAP[$status] ?? $status;
    }

    public static function mutableStatusesForRole(?string $role): array
    {
        return in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)
            ? self::ADMIN_MUTABLE_STATUSES
            : self::STAFF_MUTABLE_STATUSES;
    }

    public static function mutableStatusOptionsForRole(?string $role): array
    {
        $statuses = self::mutableStatusesForRole($role);
        $labels = self::statusOptions();

        return collect($statuses)
            ->filter(fn ($status) => isset($labels[$status]))
            ->mapWithKeys(fn ($status) => [$status => $labels[$status]])
            ->all();
    }

    public static function visibleStatusesForRole(?string $role): array
    {
        return in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)
            ? self::ADMIN_VISIBLE_STATUSES
            : self::STAFF_VISIBLE_STATUSES;
    }

    public static function visibleStatusOptionsForRole(?string $role): array
    {
        $statuses = self::visibleStatusesForRole($role);
        $labels = self::statusOptions();

        return collect($statuses)
            ->filter(fn ($status) => isset($labels[$status]))
            ->mapWithKeys(fn ($status) => [$status => $labels[$status]])
            ->all();
    }

    public static function canUpdateStatusForRole(?string $role, string $fromStatus, string $toStatus): bool
    {
        $isNoOpChange = $fromStatus === $toStatus;
        $isMutableTarget = in_array($toStatus, self::mutableStatusesForRole($role), true);
        $isApprovedLocked = $fromStatus === self::STATUS_APPROVED && ! $isNoOpChange;
        $isLockedFinalDecision = !in_array($role, [User::ROLE_SUPERADMIN, User::ROLE_ADMIN], true)
            && in_array($fromStatus, [self::STATUS_APPROVED, self::STATUS_DECLINED], true);

        return $isNoOpChange || ($isMutableTarget && ! $isApprovedLocked && ! $isLockedFinalDecision);
    }
}
