<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuoteStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'purchase_request_status_histories';

    protected $fillable = [
        'quote_id',
        'from_status',
        'to_status',
        'status_note',
        'changed_by',
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
