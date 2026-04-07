<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_request_id',
        'project_id',
        'po_number',
        'company_name',
        'supplier_id',
        'vendor_name',
        'vendor_address',
        'vendor_phone',
        'subtotal',
        'total_amount',
        'status',
        'created_by',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(Quote::class, 'purchase_request_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
