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
        Schema::create('payment_method_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_method_id');
            $table->string('payment_class', 255)->nullable(true);
            $table->decimal('min_amount', 13, 2);
            $table->decimal('max_amount', 13, 2);
            $table->text('client_callback_url')->nullable(true);
            $table->text('server_callback_url')->nullable(true);
            $table->tinyInteger('debug')->default(1);
            $table->text('debugReplaceKeys')->nullable(true);
            $table->text('allowed_channels')->nullable(true);
            $table->text('custom_value_1')->nullable(true);
            $table->text('custom_value_2')->nullable(true);
            $table->text('custom_value_3')->nullable(true);
            $table->text('custom_value_4')->nullable(true);
            $table->text('custom_value_5')->nullable(true);
            $table->text('custom_value_6')->nullable(true);
            $table->text('custom_value_7')->nullable(true);
            $table->text('custom_value_8')->nullable(true);
            $table->text('custom_value_9')->nullable(true);
            $table->text('custom_value_10')->nullable(true);
            $table->timestamps();

            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_method_configurations');
    }
};
