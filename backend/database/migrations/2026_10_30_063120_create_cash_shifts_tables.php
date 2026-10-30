<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel registers
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->string('name', 100); // Contoh: Kasir 1, POS Depan
            $table->string('code', 50);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['outlet_id', 'code']);
        });

        // Tabel shifts
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->foreignId('register_id')->constrained('registers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->timestampTz('opened_at');
            $table->timestampTz('closed_at')->nullable();

            $table->bigInteger('opening_cash')->default(0);
            $table->bigInteger('expected_cash')->default(0); // Hitungan sistem (modal + tunai masuk - tunai keluar)
            $table->bigInteger('counted_cash')->nullable(); // Hitung fisik uang di laci saat tutup shift
            $table->bigInteger('diff_cash')->default(0); // Selisih (counted - expected)

            // Ringkasan pergerakan kas selama shift
            $table->bigInteger('total_cash_sales')->default(0);
            $table->bigInteger('total_non_cash_sales')->default(0);
            $table->bigInteger('total_cash_in')->default(0);
            $table->bigInteger('total_cash_out')->default(0);

            $table->string('status', 30)->default('open'); // open, closed, reconciled
            $table->text('note')->nullable();
            $table->text('close_note')->nullable();
            
            $table->timestampsTz();

            $table->index(['outlet_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        //Tabel cash_movements
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_id')->constrained('shifts')->restrictOnDelete();
            $table->string('type', 30);
            $table->bigInteger('amount');
            $table->string('category', 50)->nullable();
            $table->text('reason');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->timestampsTz();

            $table->index('shift_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('registers');
    }
};