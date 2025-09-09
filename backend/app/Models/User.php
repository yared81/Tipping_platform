<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification; 


class User extends Authenticatable  implements MustVerifyEmailContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens,HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',      // tipper, creator, admin
        'avatar',
        'bio',
        'balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            //'password' => 'hashed', 
        ];
    }

    public function sendPasswordResetNotification($token)
    {
        $url = config('app.frontend_url') . '/reset-password?token=' . $token . '&email=' . $this->email;

        $this->notify(new ResetPasswordNotification($url));
    }

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute(): string
    {
        if (!$this->avatar) {
            return asset('images/avatar-placeholder.png');
        }

        // If the stored value looks like a remote URL, return as-is
        if (preg_match('#^(https?:)?//#i', $this->avatar)) {
            return $this->avatar;
        }

        // Otherwise treat it as a local path in the public disk
        return asset('storage/' . ltrim($this->avatar, '/'));
    }

    /**
     * Helper: is the current avatar a locally stored file?
     */
    public function avatarIsLocal(): bool
    {
        return $this->avatar
            && !preg_match('#^(https?:)?//#i', $this->avatar); // not http/https or protocol-relative
    }

    public function sentTransactions()
    {
        return $this->hasMany(Transaction::class, 'sender_id');
    }

    public function receivedTransactions()
    {
        return $this->hasMany(Transaction::class, 'receiver_id');
    }

    public function allTransactions()
    {
        return Transaction::forUser($this->id);
    }

    public function canSendTip($amount): bool
    {
        return $this->balance >= $amount;
    }

    public function isCreator(): bool
    {
        return $this->role === 'creator';
    }

    public function getTotalTipsSent(): float
    {
        return $this->sentTransactions()
            ->tips()
            ->completed()
            ->sum('amount');
    }

    public function getTotalTipsReceived(): float
    {
        return $this->receivedTransactions()
            ->tips()
            ->completed()
            ->sum('net_amount');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return number_format($this->balance, 2) . ' ETB';
    }

}
