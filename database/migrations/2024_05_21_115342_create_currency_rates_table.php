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
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->char('from_code', 3);
            $table->char('to_code', 3);
            $table->decimal('buying_rate', 13, 2);
            $table->decimal('selling_rate', 13, 2);
            $table->date('source_updated_at_local');
            $table->dateTime('refreshed_at');
            $table->timestamps();

            $table->index('from_code');
            $table->index('to_code');
            $table->index('source_updated_at_local', 'at_local_index');
            $table->index('refreshed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
