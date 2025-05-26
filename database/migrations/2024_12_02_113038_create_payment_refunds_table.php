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
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('order_id');
            $table->string('ticket_number')->nullable(true);
            $table->string('refund_status')->nullable(true);
            $table->text('refund_reason')->nullable(true);
            $table->decimal('refund_grand_total', 13, 2)->nullable(true);
            $table->string('create_actor_id')->nullable(true);
            $table->dateTime('refund_created_at')->nullable(true);
            $table->string('update_actor_id')->nullable(true);
            $table->dateTime('refunded_at')->nullable(true);
            $table->string('refund_transaction_no')->nullable(true);
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
            $table->index('ticket_number');
            $table->index('refund_status');
            $table->index('create_actor_id');
            $table->index('refund_created_at');
            $table->index('update_actor_id');
            $table->index('refunded_at');
            $table->index('refund_transaction_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
