<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
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
        ];
    }

    public function stockRequests(): HasMany
    {
        return $this->hasMany(StockRequest::class, 'requester_id');
    }

    public function approvalsMade(): HasMany
    {
        return $this->hasMany(RequestApproval::class, 'approver_id');
    }

    public function issuancesMade(): HasMany
    {
        return $this->hasMany(StockIssuance::class, 'storekeeper_id');
    }

    public function stockMovementsRecorded(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'created_by');
    }

    /**
     * Groups this user belongs to, for the ordering-permission layer —
     * independent of Role. See PLAN.md §3a.
     */
    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_user_group');
    }

    /**
     * Union of item-groups permitted across all of this user's user-groups.
     */
    public function permittedItemGroupIds(): Collection
    {
        return $this->userGroups()
            ->with('itemGroups:id')
            ->get()
            ->pluck('itemGroups')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values();
    }

    /**
     * Whether this user may order the given product. Admin always can
     * (mirrors super_admin bypassing Shield permissions). A product with no
     * item-groups is unrestricted. Otherwise the product must share at
     * least one item-group with this user's permitted set.
     */
    public function canOrderProduct(Product $product): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        $productItemGroupIds = $product->itemGroups()->pluck('item_groups.id');

        if ($productItemGroupIds->isEmpty()) {
            return true;
        }

        return $productItemGroupIds->intersect($this->permittedItemGroupIds())->isNotEmpty();
    }
}
