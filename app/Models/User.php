<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    public const BUILT_IN_ROLES = [
        'admin' => 'Admin',
        'inventory_staff' => 'Inventory Staff',
        'sales_associate' => 'Sales Associate',
        'sales_marketing_staff' => 'Sales/Marketing Staff',
    ];

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

    public function hasBuiltInRole(): bool
    {
        return array_key_exists($this->role, self::BUILT_IN_ROLES);
    }

    public function canAccessHub(int $hubId): bool
    {
        return $this->isAdmin()
            || $this->role === 'inventory_staff'
            || (int) $this->hub_id === $hubId
            || ($this->role === 'sales_associate'
                && $this->assignedStoreHubs()->whereKey($hubId)->exists());
    }

    public function assignedStoreHubs(): BelongsToMany
    {
        return $this->belongsToMany(StoreHub::class, 'sales_associate_store_hubs')
            ->withTimestamps();
    }

    public function accessibleStoreHubIds(): array
    {
        if ($this->role !== 'sales_associate') {
            return $this->hub_id ? [(int) $this->hub_id] : [];
        }

        return collect([(int) $this->hub_id])
            ->merge($this->assignedStoreHubs()->pluck('store_hubs.id')->map(fn ($id) => (int) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasSalesChannel(string $channel): bool
    {
        return $this->isAdmin() || in_array($channel, $this->sales_channels ?? [], true);
    }

    public function usesAssignedSalesChannels(): bool
    {
        return in_array($this->role, ['inventory_staff', 'sales_marketing_staff'], true);
    }

    public function canRecordChannelSales(): bool
    {
        return $this->isAdmin()
            || $this->role === 'sales_associate'
            || ($this->usesAssignedSalesChannels() && ! empty($this->sales_channels));
    }

    public function storeHub()
    {
        return $this->belongsTo(StoreHub::class, 'hub_id');
    }
}
