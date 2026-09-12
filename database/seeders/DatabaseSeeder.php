<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed System Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@barbar.local'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('Admin123456!'),
                'role' => User::ROLE_SYSTEM_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'system.initialized',
            'description' => 'System admin seed account provisioned and foundation initialized.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder/CLI',
        ]);

        // 2. Demo Company 1 - Active
        $manager1 = User::firstOrCreate(
            ['email' => 'ahmet@grandbarber.local'],
            [
                'name' => 'Ahmet Yılmaz',
                'password' => Hash::make('Manager123!'),
                'role' => User::ROLE_COMPANY_MANAGER,
                'email_verified_at' => now(),
            ]
        );

        $company1 = Company::firstOrCreate(
            ['code' => 'SLN-G8K2P'],
            [
                'name' => 'Grand Barber Studio',
                'manager_id' => $manager1->id,
                'phone' => '+90 532 100 2030',
                'status' => Company::STATUS_ACTIVE,
            ]
        );

        AuditLog::create([
            'user_id' => $manager1->id,
            'company_id' => $company1->id,
            'action' => 'company.registered',
            'description' => 'Company Grand Barber Studio registered successfully.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder/CLI',
        ]);

        // 3. Demo Company 2 - Active
        $manager2 = User::firstOrCreate(
            ['email' => 'mehmet@elitecuts.local'],
            [
                'name' => 'Mehmet Demir',
                'password' => Hash::make('Manager123!'),
                'role' => User::ROLE_COMPANY_MANAGER,
                'email_verified_at' => now(),
            ]
        );

        $company2 = Company::firstOrCreate(
            ['code' => 'SLN-E4M9X'],
            [
                'name' => 'Elite Cuts Lounge',
                'manager_id' => $manager2->id,
                'phone' => '+90 533 200 3040',
                'status' => Company::STATUS_ACTIVE,
            ]
        );

        AuditLog::create([
            'user_id' => $manager2->id,
            'company_id' => $company2->id,
            'action' => 'company.registered',
            'description' => 'Company Elite Cuts Lounge registered successfully.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder/CLI',
        ]);

        // 4. Demo Company 3 - Pending
        $manager3 = User::firstOrCreate(
            ['email' => 'burak@vintageblade.local'],
            [
                'name' => 'Burak Kaya',
                'password' => Hash::make('Manager123!'),
                'role' => User::ROLE_COMPANY_MANAGER,
                'email_verified_at' => now(),
            ]
        );

        $company3 = Company::firstOrCreate(
            ['code' => 'SLN-V1Z7Q'],
            [
                'name' => 'Vintage Blade Club',
                'manager_id' => $manager3->id,
                'phone' => '+90 535 300 4050',
                'status' => Company::STATUS_PENDING,
            ]
        );

        AuditLog::create([
            'user_id' => $manager3->id,
            'company_id' => $company3->id,
            'action' => 'company.registered',
            'description' => 'Company Vintage Blade Club registered awaiting activation.',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Seeder/CLI',
        ]);
    }
}
