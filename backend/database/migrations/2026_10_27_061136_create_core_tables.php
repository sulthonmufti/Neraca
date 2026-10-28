<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        //outlets
        Schema::create('outlets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('npwp', 50)->nullable();
            $table->string('timezone', 50)->default('Asia/Jakarta');
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['tenant_id', 'code']);
        });

        //users
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->restrictOnDelete();
            $table->string('pin_hash')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('pin_hash');
            $table->timestampTz('last_login_at')->nullable()->after('is_active');
            $table->softDeletesTz();
        });

        //user outlets
        Schema::create('user_outlets', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestampsTz();

            $table->primary(['user_id', 'outlet_id']);
        });

        //outlet modules
        Schema::create('outlet_modules', function (Blueprint $table) {
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->string('module_key', 50);
            $table->boolean('is_enabled')->default(false);
            $table->jsonb('config')->nullable();
            $table->timestampsTz();

            $table->primary(['outlet_id', 'module_key']);
        });

        //settings
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->restrictOnDelete();
            $table->string('key', 100);
            $table->jsonb('value');
            $table->timestampsTz();

            $table->unique(['tenant_id', 'outlet_id', 'key']);
        });

        //taxes
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('kind', 20);
            $table->decimal('rate_percent', 5, 2);
            $table->boolean('is_inclusive')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        //payment methods
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name', 100);
            $table->string('type', 30);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->jsonb('config')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'code']);
        });

        //devices
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->restrictOnDelete();
            $table->string('name', 100);
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampTz('last_sync_at')->nullable();
            $table->timestampsTz();
        });

        //idempotency keys
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('endpoint');
            $table->smallInteger('response_code');
            $table->jsonb('response_body')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('outlet_modules');
        Schema::dropIfExists('user_outlets');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'pin_hash', 'is_active', 'last_login_at', 'deleted_at']);
        });

        Schema::dropIfExists('outlets');
    }
};