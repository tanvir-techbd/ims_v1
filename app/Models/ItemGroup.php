<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A permission-scoping classification for products — separate from Category,
 * which is the browsing/search taxonomy. See PLAN.md §3a: a product's
 * category and item-group(s) are independent; item-groups exist only to
 * gate which UserGroups may order a product.
 */
class ItemGroup extends Model
{
    /** @use HasFactory<\Database\Factories\ItemGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    ];

    protected static function booted(): void
    {
        static::creating(function (ItemGroup $itemGroup): void {
            if (blank($itemGroup->slug)) {
                $itemGroup->slug = Str::slug($itemGroup->name);
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'item_group_product');
    }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'item_group_user_group')
            ->withPivot('granted_by')
            ->withTimestamps();
    }
}
