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
        Schema::create('manual_retries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_fulfillment_id');
            $table->string('retry_user_id')->nullable(true);
            $table->string('retry_fulfillment_status')->nullable(true);
            $table->dateTime('retried_at')->nullable(true);
            $table->timestamps();

            $table->foreign('order_fulfillment_id')->references('id')->on('order_fulfillments');

            $table->index('retry_user_id');
            $table->index('retry_fulfillment_status');
            $table->index('retried_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_retries');
    }
};
