<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'amount',
        'platform_fee',
        'net_amount',
        'transaction_type',
        'status',
        'description',
        'metadata',
        'stripe_payment_intent_id',
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(TransactionFee::class);
    }
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeTips($query)
    {
        return $query->where('transaction_type', 'tip');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('sender_id', $userId)
              ->orWhere('receiver_id', $userId);
        });
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2) . ' ETB';
    }

    public function getFormattedNetAmountAttribute(): string
    {
        return number_format($this->net_amount, 2) . ' ETB';
    }

    public function getFormattedPlatformFeeAttribute(): string
    {
        return number_format($this->platform_fee, 2) . ' ETB';
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            default => 'Unknown',
        };
    }

    public function calculateFees(): array
    {
        $amount = $this->amount;
        
        $platformFeePercentage = 0.05;
        $platformFee = $amount * $platformFeePercentage;
        $platformFee = max(5, min(100, $platformFee));
        
        $processingFeePercentage = 0.02;
        $processingFee = $amount * $processingFeePercentage;
        $processingFee = max(2, $processingFee);
        
        $totalFees = $platformFee + $processingFee;
        $netAmount = $amount - $totalFees;
        
        return [
            'platform_fee' => round($platformFee, 2),
            'processing_fee' => round($processingFee, 2),
            'total_fees' => round($totalFees, 2),
            'net_amount' => round($netAmount, 2),
        ];
    }

    public function process(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        try {
            \DB::transaction(function () {
                $this->update([
                    'status' => 'processing',
                    'processed_at' => now(),
                ]);

                $fees = $this->calculateFees();
                $this->update([
                    'platform_fee' => $fees['platform_fee'],
                    'net_amount' => $fees['net_amount'],
                ]);

                $this->sender->decrement('balance', $this->amount);
                $this->receiver->increment('balance', $this->net_amount);

                $this->fees()->create([
                    'fee_type' => 'platform',
                    'amount' => $fees['platform_fee'],
                    'percentage' => 5.00,
                    'description' => 'Platform fee (5%)',
                ]);

                $this->fees()->create([
                    'fee_type' => 'processing',
                    'amount' => $fees['processing_fee'],
                    'percentage' => 2.00,
                    'description' => 'Processing fee (2%)',
                ]);

                $this->update(['status' => 'completed']);
            });

            return true;
        } catch (\Exception $e) {
            $this->update(['status' => 'failed']);
            return false;
        }
    }

    public function refund(): bool
    {
        if (!$this->isRefundable()) {
            return false;
        }

        try {
            \DB::transaction(function () {
                $this->sender->increment('balance', $this->amount);
                $this->receiver->decrement('balance', $this->net_amount);

                $this->update([
                    'status' => 'refunded',
                    'processed_at' => now(),
                ]);
            });

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function isRefundable(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        if (!$this->processed_at) {
            return false;
        }

        return $this->processed_at->diffInHours(now()) <= 24;
    }

    public function isTip(): bool
    {
        return $this->transaction_type === 'tip';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
