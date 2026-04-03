<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComponentSupplier extends Model
{
    use HasFactory;

    protected $table = 'component_suppliers';

    protected $fillable = [
        'component_id',
        'supplier_id',
        'category_id',
        'component_code',
        'component_name',
        'description',
        'unit',
        'price',
        'currency',
        'min_quantity',
        'max_quantity',
        'is_smart_component',
        'requires_license',
        'license_type',
        'subscription_period',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_smart_component' => 'boolean',
        'requires_license' => 'boolean',
    ];

    public $timestamps = false;

    public function component()
    {
        return $this->belongsTo(Component::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
