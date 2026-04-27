<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    use HasFactory;

    protected $table = 'purchase_request_items';

    protected $fillable = [
        'quote_id',
        'component_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_type',
        'discount_value',
        'line_total'
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function component()
    {
        return $this->belongsTo(Component::class);
    }
}
