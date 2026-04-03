<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComponentCategory extends Model
{
    use HasFactory;

    protected $table = 'component_categories';

    protected $fillable = [
        'name',
        'description',
        'icon',
        'sort_order'
    ];

    /**
     * Get the components in this category
     */
    public function components()
    {
        return $this->hasMany(Component::class, 'category_id');
    }

    /**
     * Get count of components
     */
    public function getComponentsCountAttribute()
    {
        return $this->components()->count();
    }

    /**
     * Scope for ordering
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}