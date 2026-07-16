<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ItemGroup;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Realistic demo data for a convincing walkthrough — NOT run by default via
 * `db:seed` (DatabaseSeeder only seeds what every real deployment needs:
 * roles, permissions, the admin account, the threshold setting). Run this
 * explicitly:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Requires DatabaseSeeder to have already run (roles must exist). Every
 * demo user's password is "password".
 *
 * Deliberately does NOT use WithoutModelEvents (Laravel's make:seeder
 * scaffolds it on by default) — this seeder's whole point is a populated,
 * walkthrough-ready demo, and that trait silences every Eloquent model
 * event for its entire run, including the ones spatie/laravel-activitylog
 * hooks into. With it on, every product/category/request/approval this
 * seeder creates was invisible to the Phase 7 Audit Log — found via a real
 * browser walkthrough (it showed "No Activities" despite dozens of rows
 * having just been created), not something any automated test caught,
 * since none of them seed through this class and then assert on the log.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        [$categories, $units] = $this->seedCategoriesAndUnits();
        $itemGroups = $this->seedItemGroups();
        $products = $this->seedProducts($categories, $units, $itemGroups);
        $users = $this->seedUsersAndGroups($itemGroups);
        $this->seedStockRequests($users, $products);
    }

    /**
     * @return array{0: array<string, Category>, 1: array<string, Unit>}
     */
    private function seedCategoriesAndUnits(): array
    {
        $categoryNames = ['Stationery', 'PPE', 'Hygiene', 'IT Supplies', 'Electrical'];
        $categories = collect($categoryNames)->mapWithKeys(
            fn (string $name) => [$name => Category::firstOrCreate(['name' => $name], ['slug' => str($name)->slug()])]
        );

        $unitSpecs = [
            'Piece' => 'pcs',
            'Box' => 'box',
            'Ream' => 'ream',
            'Bottle' => 'bottle',
            'Pack' => 'pack',
        ];
        $units = collect($unitSpecs)->mapWithKeys(
            fn (string $symbol, string $name) => [$name => Unit::firstOrCreate(['symbol' => $symbol], ['name' => $name])]
        );

        return [$categories->all(), $units->all()];
    }

    /**
     * @return array<string, ItemGroup>
     */
    private function seedItemGroups(): array
    {
        $names = ['Facilities Orderable', 'IT Orderable'];

        return collect($names)->mapWithKeys(
            fn (string $name) => [$name => ItemGroup::firstOrCreate(['name' => $name], ['slug' => str($name)->slug()])]
        )->all();
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Unit>  $units
     * @param  array<string, ItemGroup>  $itemGroups
     * @return array<string, Product>
     */
    private function seedProducts(array $categories, array $units, array $itemGroups): array
    {
        // [name, sku, category, unit, stock, itemGroup|null]
        $specs = [
            ['Safety Gloves (Box of 12)', 'PPE-GLV-001', 'PPE', 'Box', 3, 'Facilities Orderable'],
            ['A4 Paper Ream', 'STA-PPR-014', 'Stationery', 'Ream', 6, 'Facilities Orderable'],
            ['Hand Sanitizer 500ml', 'HYG-SAN-002', 'Hygiene', 'Bottle', 9, null],
            ['Printer Toner (Black)', 'ITS-TNR-007', 'IT Supplies', 'Piece', 10, 'IT Orderable'],
            ['Whiteboard Markers', 'STA-MRK-009', 'Stationery', 'Piece', 10, null],
            ['Stapler', 'STA-STP-003', 'Stationery', 'Piece', 42, null],
            ['Extension Cord (5m)', 'ELE-EXC-011', 'Electrical', 'Piece', 28, 'IT Orderable'],
            ['Sticky Notes (Pack)', 'STA-STN-006', 'Stationery', 'Pack', 65, null],
            ['Face Masks (Box of 50)', 'PPE-MSK-004', 'PPE', 'Box', 31, 'Facilities Orderable'],
            ['Cleaning Spray 750ml', 'HYG-CLN-005', 'Hygiene', 'Bottle', 54, null],
            ['USB-C Cables', 'ITS-USB-012', 'IT Supplies', 'Piece', 5, 'IT Orderable'],
            ['Disinfectant Wipes', 'HYG-WIP-008', 'Hygiene', 'Pack', 8, null],
        ];

        $products = [];

        foreach ($specs as [$name, $sku, $categoryName, $unitName, $stock, $itemGroupName]) {
            $product = Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'category_id' => $categories[$categoryName]->id,
                    'unit_id' => $units[$unitName]->id,
                    'current_stock' => $stock,
                ]
            );

            if ($itemGroupName) {
                $product->itemGroups()->syncWithoutDetaching([$itemGroups[$itemGroupName]->id]);
            }

            $products[$name] = $product;
        }

        return $products;
    }

    /**
     * @param  array<string, ItemGroup>  $itemGroups
     * @return array<string, User>
     */
    private function seedUsersAndGroups(array $itemGroups): array
    {
        $people = [
            'Jane Whitfield' => ['jane.whitfield@example.com', 'Approver'],
            'Miguel Torres' => ['miguel.torres@example.com', 'Storekeeper'],
            'Sarah Kim' => ['sarah.kim@example.com', 'Demander'],
            'David Lee' => ['david.lee@example.com', 'Demander'],
            'Fatima Noor' => ['fatima.noor@example.com', 'Demander'],
            'Global Supplies Ltd.' => ['contact@globalsupplies.example.com', 'Supplier'],
        ];

        $users = collect($people)->mapWithKeys(function (array $spec, string $name) {
            [$email, $role] = $spec;
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
            ]);
            $user->assignRole($role);

            return [$name => $user];
        });

        $facilitiesGroup = UserGroup::firstOrCreate(['name' => 'Facilities Team']);
        $facilitiesGroup->grantItemGroup($itemGroups['Facilities Orderable']);
        $facilitiesGroup->users()->syncWithoutDetaching([
            $users['Sarah Kim']->id,
            $users['David Lee']->id,
        ]);

        $itGroup = UserGroup::firstOrCreate(['name' => 'IT Department']);
        $itGroup->grantItemGroup($itemGroups['IT Orderable']);
        $itGroup->users()->syncWithoutDetaching([
            $users['Fatima Noor']->id,
        ]);

        return $users->all();
    }

    /**
     * Recreates a realistic multi-item request narrative — one item fully
     * issued, one partially issued because stock ran short, one rejected —
     * so the "View Trail" / request-detail view has something worth looking
     * at immediately after seeding, not just empty tables.
     *
     * @param  array<string, User>  $users
     * @param  array<string, Product>  $products
     */
    private function seedStockRequests(array $users, array $products): void
    {
        $approver = $users['Jane Whitfield'];
        $storekeeper = $users['Miguel Torres'];

        // David Lee is in the Facilities Team group, which is granted the
        // "Facilities Orderable" item-group — Safety Gloves and Face Masks
        // are both classified into it, A4 Paper Ream and Sticky Notes are
        // unclassified (open to everyone), so this whole narrative is a
        // request his group is actually permitted to make.
        $demander = $users['David Lee'];

        $request = $demander->stockRequests()->firstOrCreate(
            ['notes' => 'Restocking the front desk and 2nd floor print station ahead of the quarterly audit.'],
        );

        if ($request->items()->count() === 0) {
            // Fully approved and fully issued.
            $paperItem = $request->addItem($products['A4 Paper Ream'], 15);
            $paperItem->approve(15, $approver, 'Approved in full.');
            $paperItem->issue(15, $storekeeper);

            // Approved for less than requested, then issued less than
            // approved because stock ran short — the "partial" story.
            $glovesItem = $request->addItem($products['Safety Gloves (Box of 12)'], 10);
            $glovesItem->approve(6, $approver, "Reduced to match this month's PPE budget.");
            $glovesItem->issue(6, $storekeeper, 'Only 3 boxes in stock at time of issuance — remainder pending restock.');

            // Rejected outright.
            $stickyNotesItem = $request->addItem($products['Sticky Notes (Pack)'], 8);
            $stickyNotesItem->reject($approver, 'Already issued 20 packs to Admin Dept last week — please confirm need before resubmitting.');

            $request->recomputeStatus();
        }

        // A second, still-pending request awaiting approval, so the
        // Approver's queue isn't empty either.
        $pendingRequest = $users['Sarah Kim']->stockRequests()->firstOrCreate(
            ['notes' => 'Need these for the front desk.'],
        );
        if ($pendingRequest->items()->count() === 0) {
            $pendingRequest->addItem($products['Whiteboard Markers'], 15);
            $pendingRequest->addItem($products['Hand Sanitizer 500ml'], 12);
        }

        // Fatima Noor (IT Department group, "IT Orderable" item-group) gets
        // her own request, demonstrating a different demander only being
        // able to reach the products her group actually permits.
        $itRequest = $users['Fatima Noor']->stockRequests()->firstOrCreate(
            ['notes' => 'New laptops arriving next week — need cables and a spare toner on hand.'],
        );
        if ($itRequest->items()->count() === 0) {
            $itRequest->addItem($products['USB-C Cables'], 6);
            $itRequest->addItem($products['Printer Toner (Black)'], 2);
        }
    }
}
