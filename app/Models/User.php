<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use LogsActivity;
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

    /**
     * Every real user of this single-panel IMS needs some role to do
     * anything useful, so panel access is gated on having at least one —
     * this also keeps a freshly self-registered Jetstream account (Jetstream
     * registration is enabled) out of the panel until Admin assigns a role.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->roles()->exists();
    }

    /**
     * Deliberately just name/email — password (even hashed) and the 2FA
     * secret/recovery-code columns are excluded, there's no value in
     * showing hash diffs and every reason not to.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email'])->logOnlyDirty()->dontLogEmptyChanges();
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
