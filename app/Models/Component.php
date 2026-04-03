<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Component extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'component_code',
        'component_name',
        'description',
        'unit',
        'currency',
        'min_quantity',
        'max_quantity',
        'is_smart_component',
        'requires_license',
        'license_type',
        'subscription_period',
        'is_active'
    ];

    protected $casts = [
        'is_smart_component' => 'boolean',
        'requires_license' => 'boolean',
        'is_active' => 'boolean'
    ];

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(ComponentCategory::class, 'category_id');
    }

    /**
     * Get project components
     */
    public function projectComponents()
    {
        return $this->hasMany(ProjectComponent::class);
    }

    /**
     * Get supplier-specific offers for this component.
     */
    public function supplierPrices()
    {
        return $this->hasMany(ComponentSupplier::class);
    }

    /**
     * Get suppliers that can provide this component.
     */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'component_suppliers')
            ->withPivot(['price']);
    }

    /**
     * Scope active components
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope smart components
     */
    public function scopeSmart($query)
    {
        return $query->where('is_smart_component', true);
    }
}
