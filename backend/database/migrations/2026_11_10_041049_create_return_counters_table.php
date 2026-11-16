<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('return_counters')) {
            Schema::create('return_counters', function (Blueprint $table) {
                $table->id();
                $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
                $table->date('counter_date');
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestampsTz();

                $table->unique(['outlet_id', 'counter_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_counters');
    }
};