<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Permission grant: a row existing means the user_group IS allowed to
        // order products in the item_group. No separate boolean column —
        // presence in this table is the grant (see PLAN.md §3a).
        Schema::create('item_group_user_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['item_group_id', 'user_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_group_user_group');
    }
};
