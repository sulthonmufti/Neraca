<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //Tabel Departments (Untuk kantin atau koperasi perusahaan)
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name', 100);
                $table->boolean('is_active')->default(true);
                $table->timestampsTz();

                $table->unique(['tenant_id', 'code']);
            });
        }

        //Tabel Member Types (Umum, Karyawan, Anggota Koperasi, VIP)
        if (!Schema::hasTable('member_types')) {
            Schema::create('member_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('name', 100);
                $table->unsignedBigInteger('price_list_id')->nullable();
                $table->decimal('discount_percent', 5, 2)->default(0);
                $table->bigInteger('default_credit_limit')->default(0);
                $table->decimal('points_rate', 5, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestampsTz();

                $table->index(['tenant_id', 'is_active']);
            });
        }

        //Tabel Members
        if (!Schema::hasTable('members')) {
            Schema::create('members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->string('member_no', 50);
                $table->foreignId('member_type_id')->constrained('member_types')->restrictOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->string('employee_no', 50)->nullable();
                $table->string('name', 150);
                $table->string('phone', 30)->nullable();
                $table->string('email', 100)->nullable();
                $table->string('card_code', 100); // Barcode / QR kartu member
                $table->string('status', 20)->default('active'); // active, inactive, blocked
                $table->bigInteger('credit_limit')->default(0);
                $table->timestampTz('joined_at')->useCurrent();
                $table->text('note')->nullable();
                $table->timestampsTz();
                $table->softDeletesTz();

                $table->unique(['tenant_id', 'member_no']);
                $table->unique(['tenant_id', 'card_code']);
                $table->index(['tenant_id', 'phone']);
                $table->index(['tenant_id', 'status']);
            });
        }

        //Tabel Cache Saldo Member (member_wallets) - Siap untuk Fase 2
        if (!Schema::hasTable('member_wallets')) {
            Schema::create('member_wallets', function (Blueprint $table) {
                $table->foreignId('member_id')->primary()->constrained('members')->cascadeOnDelete();
                $table->bigInteger('balance')->default(0);
                $table->bigInteger('credit_used')->default(0);
                $table->timestampsTz();
            });

            DB::statement('ALTER TABLE member_wallets ADD CONSTRAINT chk_member_wallets_balance_non_negative CHECK (balance >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_wallets');
        Schema::dropIfExists('members');
        Schema::dropIfExists('member_types');
        Schema::dropIfExists('departments');
    }
};