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
        Schema::create('manual_payment_confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('payment_id');
            $table->string('update_actor_id')->nullable(true);
            $table->string('update_payment_status')->nullable(true);
            $table->string('update_gateway_transaction_no')->nullable(true);
            $table->string('update_gateway_response_code')->nullable(true);
            $table->string('update_gateway_response_status')->nullable(true);
            $table->string('update_gateway_response_message')->nullable(true);
            $table->dateTime('update_paid_at')->nullable(true);
            $table->dateTime('manually_updated_at')->nullable(true);
            $table->text('update_remarks')->nullable(true);
            $table->timestamps();

            $table->foreign('payment_id', 'fk_payment_id')->references('id')->on('payments');

            $table->index('update_actor_id', 'mpc_actor_id_index');
            $table->index('update_payment_status', 'mpc_payment_status_index');
            $table->index('update_gateway_transaction_no', 'mpc_gateway_transaction_no_index');
            $table->index('update_gateway_response_code', 'mpc_gateway_response_code_index');
            $table->index('update_gateway_response_status', 'mpc_gateway_response_status_index');
            $table->index('update_gateway_response_message', 'mpc_gateway_response_message_index');
            $table->index('update_paid_at', 'mpc_paid_at_index');
            $table->index('manually_updated_at', 'mpc_updated_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_payment_updates');
    }
};
