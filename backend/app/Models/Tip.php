<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tip extends Model
{
    //
    protected $fillable = [
        'tipper_id',
        'creator_id',
        'amount',
        'currency',
        'status',
        'message',
        'tx_ref',
        'gateway_response',
        'anonymous'
    ];

    protected $casts = [
        'gateway_response' => 'array',
        'amount' => 'decimal:2',
        'anonymous' => 'boolean',
    ];

    public function tipper()
    {
        return $this->belongsTo(User::class, 'tipper_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
