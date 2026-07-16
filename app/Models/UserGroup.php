<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * How Admin organizes users (typically Demanders) for the purpose of
 * granting ordering permission over ItemGroups. Not tied to Roles — a
 * UserGroup is purely about which item-groups its members may order from.
 * See PLAN.md §3a.
 */
class UserGroup extends Model
{
    /** @use HasFactory<\Database\Factories\UserGroupFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'description',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly($this->fillable)->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_user_group');
    }

    public function itemGroups(): BelongsToMany
    {
        return $this->belongsToMany(ItemGroup::class, 'item_group_user_group')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function grantItemGroup(ItemGroup $itemGroup, ?User $grantedBy = null): void
    {
        $this->itemGroups()->syncWithoutDetaching([
            $itemGroup->id => ['granted_by' => $grantedBy?->id],
        ]);
    }

    public function revokeItemGroup(ItemGroup $itemGroup): void
    {
        $this->itemGroups()->detach($itemGroup->id);
    }
}
