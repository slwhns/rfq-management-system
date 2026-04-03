<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
    ];

    const UPDATED_AT = null;

    public function componentSuppliers()
    {
        return $this->hasMany(ComponentSupplier::class);
    }

    public function components()
    {
        return $this->belongsToMany(Component::class, 'component_suppliers')
            ->withPivot(['price']);
    }
}
