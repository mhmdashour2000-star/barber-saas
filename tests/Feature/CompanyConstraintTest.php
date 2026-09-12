<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_cannot_be_assigned_to_multiple_companies(): void
    {
        $manager = User::factory()->create([
            'role' => User::ROLE_COMPANY_MANAGER,
        ]);

        Company::create([
            'code' => 'SLN-FIRST',
            'name' => 'First Salon',
            'manager_id' => $manager->id,
            'phone' => '+90 555 111 2233',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        // Attempting to assign the same manager to a second company must fail at database level
        Company::create([
            'code' => 'SLN-SECND',
            'name' => 'Second Salon',
            'manager_id' => $manager->id,
            'phone' => '+90 555 222 3344',
            'status' => Company::STATUS_ACTIVE,
        ]);
    }

    public function test_company_code_must_be_unique(): void
    {
        $manager1 = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $manager2 = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);

        Company::create([
            'code' => 'SLN-DUP01',
            'name' => 'First Salon',
            'manager_id' => $manager1->id,
            'phone' => '+90 555 111 2233',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $this->expectException(QueryException::class);

        Company::create([
            'code' => 'SLN-DUP01',
            'name' => 'Second Salon',
            'manager_id' => $manager2->id,
            'phone' => '+90 555 222 3344',
            'status' => Company::STATUS_ACTIVE,
        ]);
    }
}
