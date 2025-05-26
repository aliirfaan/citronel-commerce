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
        Schema::create('order_fulfillment_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('order_fulfillment_id');
            $table->uuid('payment_refund_id');
            $table->string('return_actor_id')->nullable(true);
            $table->string('return_status')->nullable(true);
            $table->dateTime('returned_at')->nullable(true);
            $table->decimal('refund_amount', 13, 2)->nullable(true);
            $table->timestamps();

            $table->foreign('order_fulfillment_id')->references('id')->on('order_fulfillments');
            $table->foreign('payment_refund_id')->references('id')->on('payment_refunds');

            $table->index('return_actor_id');
            $table->index('return_status');
            $table->index('returned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_fulfillment_refunds');
    }
};
