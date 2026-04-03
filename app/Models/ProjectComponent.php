<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectComponent extends Model
{
    use HasFactory;

    protected $table = 'project_components';

    protected $fillable = [
        'project_id',
        'component_id',
        'quantity',
        'custom_price',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'custom_price' => 'decimal:2'
    ];

    /**
     * Get the project
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the component
     */
    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get effective price (custom, supplier, or 0)
     */
    public function getEffectivePriceAttribute()
    {
        // If custom price is set, use it
        if ($this->custom_price) {
            return $this->custom_price;
        }

        // Otherwise try to get from component's supplier prices
        if ($this->component) {
            $supplierPrice = $this->component->supplierPrices()->first();
            if ($supplierPrice && $supplierPrice->price) {
                return $supplierPrice->price;
            }
        }

        return 0;
    }

    /**
     * Calculate line total
     */
    public function getLineTotalAttribute()
    {
        return $this->quantity * $this->effective_price;
    }
}
