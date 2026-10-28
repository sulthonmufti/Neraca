<?php

namespace App\Modules\Core\Database\Seeders;

use App\Models\User;
use App\Modules\Core\Models\Outlet;
use App\Modules\Core\Models\OutletModule;
use App\Modules\Core\Models\PaymentMethod;
use App\Modules\Core\Models\Tax;
use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Permissions sesuai blueprint
        $permissions = [
            'sale.create',
            'sale.void',
            'sale.return',
            'discount.apply',
            'shift.open',
            'shift.close',
            'catalog.manage',
            'inventory.manage',
            'member.manage',
            'report.view',
            'settings.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // 2. Buat Roles: admin, manager, supervisor, kasir
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $supervisorRole = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $kasirRole = Role::firstOrCreate(['name' => 'kasir', 'guard_name' => 'web']);

        $adminRole->syncPermissions($permissions);
        $managerRole->syncPermissions([
            'sale.create', 'sale.void', 'sale.return', 'discount.apply',
            'shift.open', 'shift.close', 'catalog.manage', 'inventory.manage',
            'member.manage', 'report.view',
        ]);
        $supervisorRole->syncPermissions([
            'sale.create', 'sale.void', 'sale.return', 'discount.apply',
            'shift.open', 'shift.close',
        ]);
        $kasirRole->syncPermissions([
            'sale.create', 'shift.open', 'shift.close',
        ]);

        // 3. Buat Tenant Demo
        $tenant = Tenant::firstOrCreate(
            ['code' => 'neraca-demo'],
            [
                'name' => 'Neraca Sejahtera Utama',
                'is_active' => true,
                'settings' => ['currency' => 'IDR', 'allow_negative_stock' => false],
            ]
        );

        // 4. Buat Outlet Demo
        $outlet = Outlet::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'OUT-001'],
            [
                'name' => 'Outlet Pusat (Kafe & Koperasi)',
                'address' => 'Jl. Pemuda No. 10',
                'phone' => '081234567890',
                'timezone' => 'Asia/Jakarta',
                'receipt_header' => 'NERACA POS - OUTLET PUSAT',
                'receipt_footer' => 'Terima kasih atas kunjungan Anda!',
                'is_active' => true,
            ]
        );

        // 5. Saklar Modul Outlet (cafe, canteen, koperasi)
        foreach (['cafe' => true, 'canteen' => false, 'koperasi' => true] as $mod => $enabled) {
            OutletModule::updateOrCreate(
                ['outlet_id' => $outlet->id, 'module_key' => $mod],
                ['is_enabled' => $enabled, 'config' => []]
            );
        }

        // 6. Pajak & Service Charge
        Tax::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'PPN 11%'],
            ['kind' => 'tax', 'rate_percent' => 11.00, 'is_inclusive' => false, 'is_active' => true]
        );

        // 7. Metode Pembayaran Standar
        $methods = [
            ['code' => 'CASH', 'name' => 'Tunai', 'type' => 'cash', 'sort_order' => 1],
            ['code' => 'QRIS', 'name' => 'QRIS Statis/Dinamis', 'type' => 'qris', 'sort_order' => 2],
            ['code' => 'TRANSFER', 'name' => 'Transfer Bank', 'type' => 'transfer', 'sort_order' => 3],
            ['code' => 'MEMBER_BAL', 'name' => 'Saldo Member', 'type' => 'member_balance', 'sort_order' => 4],
            ['code' => 'MEMBER_CRD', 'name' => 'Kasbon / Potong Gaji', 'type' => 'member_credit', 'sort_order' => 5],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $method['code']],
                array_merge($method, ['is_active' => true])
            );
        }

        // 8. Buat User Admin & Kasir (dengan PIN 123456)
        $admin = User::firstOrCreate(
            ['email' => 'admin@neraca.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Administrator Neraca',
                'password' => Hash::make('password'),
                'pin_hash' => Hash::make('111111'),
                'is_active' => true,
            ]
        );
        $admin->assignRole($adminRole);
        $admin->outlets()->syncWithoutDetaching([$outlet->id => ['is_default' => true]]);

        $kasir = User::firstOrCreate(
            ['email' => 'kasir@neraca.test'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Kasir Utama',
                'password' => Hash::make('password'),
                'pin_hash' => Hash::make('123456'),
                'is_active' => true,
            ]
        );
        $kasir->assignRole($kasirRole);
        $kasir->outlets()->syncWithoutDetaching([$outlet->id => ['is_default' => true]]);
    }
}