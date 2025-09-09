<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'fee_type',
        'amount',
        'percentage',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ETB';
    }

    public function getFormattedPercentageAttribute(): string
    {
        return $this->percentage ? number_format($this->percentage, 2) . '%' : 'N/A';
    }

    public function scopePlatformFees($query)
    {
        return $query->where('fee_type', 'platform');
    }

    public function scopeProcessingFees($query)
    {
        return $query->where('fee_type', 'processing');
    }

    public function scopeStripeFees($query)
    {
        return $query->where('fee_type', 'stripe');
    }
}
