<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_name',
        'location',
        'project_type',
        'status'
    ];

    protected $casts = [
        'status' => 'string'
    ];

    /**
     * Get the owner
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get components in this project
     */
    public function components()
    {
        return $this->hasMany(ProjectComponent::class);
    }

    /**
     * Get quotes for this project
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Calculate estimated cost
     */
    public function getEstimatedCostAttribute()
    {
        return $this->components()
            ->selectRaw('SUM(project_components.quantity * COALESCE(project_components.custom_price, 0)) as total')
            ->value('total') ?? 0;
    }

    /**
     * Count components
     */
    public function getComponentCountAttribute()
    {
        return $this->components()->count();
    }

    /**
     * Count smart components
     */
    public function getSmartComponentCountAttribute()
    {
        return $this->components()
            ->whereHas('component', fn($q) => $q->where('is_smart_component', true))
            ->count();
    }
}
