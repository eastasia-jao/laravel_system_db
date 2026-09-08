<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    // In app/Models/User.php
    protected $fillable = [
        'name', 'email', 'password', 'employee_id', 'role', 'sales_channels', 'mobile_number', 'hub_id', 'status', 'username',
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
            'password' => 'hashed',
            'sales_channels' => 'array',
        ];
    }

    /**
     * The rest of the app was written against a `store_hub_id` attribute
     * that never existed as a column (the real column is `hub_id`). This
     * accessor makes `$user->store_hub_id` resolve correctly everywhere
     * it's already used, instead of silently returning null and disabling
     * every hub-scoping / authorization check in the app.
     */
    protected function storeHubId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->hub_id,
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function canAccessHub(int $hubId): bool
    {
        return $this->isAdmin()
            || $this->role === 'inventory_staff'
            || (int) $this->hub_id === $hubId;
    }

    public function hasSalesChannel(string $channel): bool
    {
        return $this->isAdmin() || in_array($channel, $this->sales_channels ?? [], true);
    }

    public function storeHub()
    {
        return $this->belongsTo(StoreHub::class, 'hub_id');
    }
}
