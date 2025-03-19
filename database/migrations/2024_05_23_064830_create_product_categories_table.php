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
        Schema::create('product_categories', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('parent_id')->nullable(true);
            $table->string('title');
            $table->text('description')->nullable(true);
            $table->tinyInteger('active')->default(1);
            $table->integer('sort_order')->nullable(true);
            $table->text('logo')->nullable(true);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
