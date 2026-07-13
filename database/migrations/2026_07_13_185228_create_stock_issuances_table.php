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
        Schema::create('stock_issuances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_request_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storekeeper_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('issued_qty');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_issuances');
    }
};
