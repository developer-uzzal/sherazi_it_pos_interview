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
        Schema::table('products', function (Blueprint $table) {
            // Add index to name for faster LIKE searches
            $table->index('name');
            // Add index to sold_count for efficient orderByDesc queries
            $table->index('sold_count');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Add index to status to optimize filtering by status
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['sold_count']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });
    }
};
