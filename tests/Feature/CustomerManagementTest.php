<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\CustomerViolation;
use App\Models\User;
use App\Services\CustomerRestrictionService;
use App\Support\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerA;
    protected Company $companyA;
    protected Customer $customerA;

    protected User $managerB;
    protected Company $companyB;
    protected Customer $customerB;

    protected CustomerRestrictionService $restrictionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restrictionService = new CustomerRestrictionService();

        // Salon A
        $this->managerA = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->companyA = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Salon Alpha',
            'manager_id' => $this->managerA->id,
            'phone' => '+905551112233',
            'status' => Company::STATUS_ACTIVE,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);
        $this->customerA = $this->companyA->customers()->create([
            'name' => 'John Doe',
            'phone' => '0555 123 45 67', // Will be normalized to +905551234567
            'active' => true,
        ]);

        // Salon B
        $this->managerB = User::factory()->create(['role' => User::ROLE_COMPANY_MANAGER]);
        $this->companyB = Company::create([
            'code' => Company::generateUniqueCode(),
            'name' => 'Salon Beta',
            'manager_id' => $this->managerB->id,
            'phone' => '+905554445566',
            'status' => Company::STATUS_ACTIVE,
            'violation_limit' => 3,
            'block_duration_days' => 7,
        ]);
        $this->customerB = $this->companyB->customers()->create([
            'name' => 'Jane Smith',
            'phone' => '+905559876543',
            'active' => true,
        ]);
    }

    /**
     * 1. Same phone number can exist in different companies as separate customer records.
     * 2. Phone normalization formats consistently to E.164 (+90...).
     */
    public function test_same_phone_allowed_in_different_companies_and_normalized(): void
    {
        $rawPhone = '0532 999 88 77';
        $normalizedExpected = '+905329998877';

        $customer1 = $this->companyA->customers()->create([
            'name' => 'Shared Phone Alpha',
            'phone' => $rawPhone,
        ]);

        $customer2 = $this->companyB->customers()->create([
            'name' => 'Shared Phone Beta',
            'phone' => $rawPhone,
        ]);

        $this->assertEquals($normalizedExpected, $customer1->phone);
        $this->assertEquals($normalizedExpected, $customer2->phone);
        $this->assertNotEquals($customer1->id, $customer2->id);
        $this->assertNotEquals($customer1->company_id, $customer2->company_id);
    }

    /**
     * 3. Duplicate phone in the same company is rejected by unique constraint.
     */
    public function test_duplicate_phone_in_same_company_is_rejected(): void
    {
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        $this->companyA->customers()->create([
            'name' => 'Duplicate Attempt',
            'phone' => $this->customerA->phone,
        ]);
    }

    /**
     * 4. findOrCreateCustomer helper normalizes and finds existing customer safely.
     */
    public function test_find_or_create_customer_upsert_service(): void
    {
        // Finding existing customer with slightly different formatting
        $resolved = $this->restrictionService->findOrCreateCustomer(
            $this->companyA,
            '+90 555 123 45 67',
            'John Doe Updated'
        );

        $this->assertEquals($this->customerA->id, $resolved->id);

        // Creating brand new customer
        $newCustomer = $this->restrictionService->findOrCreateCustomer(
            $this->companyA,
            '0544 111 22 33',
            'New Client'
        );

        $this->assertDatabaseHas('customers', [
            'id' => $newCustomer->id,
            'company_id' => $this->companyA->id,
            'phone' => '+905441112233',
        ]);
    }

    /**
     * 5. Manager can view own customers and details.
     * 6. Tenant isolation prevents manager from viewing or modifying another company's customer.
     */
    public function test_tenant_isolation_on_customer_directory(): void
    {
        // Manager A viewing own directory
        $response = $this->actingAs($this->managerA)->get(route('company.customers.index'));
        $response->assertOk();
        $response->assertSee($this->customerA->name);
        $response->assertDontSee($this->customerB->name);

        // Manager A viewing own customer show page
        $responseShowOwn = $this->actingAs($this->managerA)->get(route('company.customers.show', $this->customerA->id));
        $responseShowOwn->assertOk();

        // Manager A attempting to view Salon B customer
        $responseShowOther = $this->actingAs($this->managerA)->get(route('company.customers.show', $this->customerB->id));
        $responseShowOther->assertNotFound();

        // Manager A attempting to update Salon B customer
        $responseUpdateOther = $this->actingAs($this->managerA)->put(route('company.customers.update', $this->customerB->id), [
            'notes' => 'Hacked Notes',
        ]);
        $responseUpdateOther->assertNotFound();
    }

    /**
     * 7. Threshold below limit does not block customer.
     * 8. Reaching violation_limit creates automatic block for block_duration_days.
     * 9. Blocked customer reports isBlocked = true and canBook = false.
     */
    public function test_automatic_blocking_upon_reaching_violation_limit(): void
    {
        $this->assertFalse($this->customerA->isBlocked());
        $this->assertTrue($this->customerA->canBook());

        // Violation 1
        $this->restrictionService->recordViolation(
            $this->customerA,
            CustomerViolation::TYPE_LATE_CANCELLATION,
            'Late cancel test 1',
            null,
            $this->managerA->id
        );
        $this->assertFalse($this->customerA->fresh()->isBlocked());

        // Violation 2
        $this->restrictionService->recordViolation(
            $this->customerA,
            CustomerViolation::TYPE_NO_SHOW,
            'No show test 2',
            null,
            $this->managerA->id
        );
        $this->assertFalse($this->customerA->fresh()->isBlocked());

        // Violation 3 (Threshold of 3 reached!)
        $this->restrictionService->recordViolation(
            $this->customerA,
            CustomerViolation::TYPE_LATE_CANCELLATION,
            'Late cancel test 3',
            null,
            $this->managerA->id
        );

        $this->customerA->refresh();
        $this->assertTrue($this->customerA->isBlocked());
        $this->assertFalse($this->customerA->canBook());

        // Verify block details
        $activeBlock = $this->customerA->activeBlock();
        $this->assertNotNull($activeBlock);
        $this->assertEquals(CustomerBlock::SOURCE_AUTOMATIC, $activeBlock->source);
        $this->assertEquals(
            Carbon::now()->addDays(7)->format('Y-m-d'),
            $activeBlock->ends_at->format('Y-m-d')
        );
    }

    /**
     * 10. Once an automatic block expires, customer can book again.
     * 11. Old violations do NOT immediately re-block customer after block expiration.
     * 12. Customer must accumulate a fresh cycle of violations to be blocked again.
     */
    public function test_expired_block_allows_booking_and_requires_fresh_violation_cycle(): void
    {
        // Trigger initial block (3 violations)
        for ($i = 1; $i <= 3; $i++) {
            $this->restrictionService->recordViolation($this->customerA, CustomerViolation::TYPE_NO_SHOW, "Violation {$i}");
        }

        $this->assertTrue($this->customerA->fresh()->isBlocked());

        // Simulate time travel: backdate both the block and the old violations
        $this->customerA->violations()->update(['occurred_at' => Carbon::now()->subDays(9)]);

        $block = $this->customerA->activeBlock();
        $block->update([
            'starts_at' => Carbon::now()->subDays(8),
            'ends_at' => Carbon::now()->subDays(1),
        ]);

        $this->customerA->refresh();

        // Customer is no longer blocked
        $this->assertFalse($this->customerA->isBlocked());
        $this->assertTrue($this->customerA->canBook());

        // Active violation count for new cycle should be 0
        $this->assertEquals(0, $this->restrictionService->getActiveViolationCount($this->customerA));

        // Adding 1 new violation does NOT trigger a block because old 3 are consumed
        $this->restrictionService->recordViolation($this->customerA, CustomerViolation::TYPE_NO_SHOW, 'Fresh Cycle 1');
        $this->assertFalse($this->customerA->fresh()->isBlocked());
        $this->assertEquals(1, $this->restrictionService->getActiveViolationCount($this->customerA));
    }

    /**
     * 13. Manual block and manual unblock.
     * 14. Unblock preserves block record with lifted_at.
     */
    public function test_manual_block_and_unblock_functionality(): void
    {
        // Manager manually blocks customer
        $responseBlock = $this->actingAs($this->managerA)->post(route('company.customers.block', $this->customerA->id), [
            'reason' => 'Verbal abuse in salon',
            'duration_days' => 14,
        ]);

        $responseBlock->assertRedirect(route('company.customers.show', $this->customerA->id));

        $this->customerA->refresh();
        $this->assertTrue($this->customerA->isBlocked());
        $block = $this->customerA->activeBlock();
        $this->assertEquals(CustomerBlock::SOURCE_MANUAL, $block->source);
        $this->assertEquals('Verbal abuse in salon', $block->reason);

        // Manager manually unblocks customer
        $responseUnblock = $this->actingAs($this->managerA)->post(route('company.customers.unblock', $this->customerA->id));
        $responseUnblock->assertRedirect(route('company.customers.show', $this->customerA->id));

        $this->customerA->refresh();
        $this->assertFalse($this->customerA->isBlocked());

        // Block record is preserved with lifted_at
        $block->refresh();
        $this->assertNotNull($block->lifted_at);
        $this->assertEquals($this->managerA->id, $block->lifted_by_user_id);
        $this->assertEquals('LIFTED', $block->status);
    }

    /**
     * 15. Suspended company cannot block, unblock, or update customer notes.
     */
    public function test_suspended_company_cannot_modify_customer_restrictions(): void
    {
        $this->companyA->update(['status' => Company::STATUS_SUSPENDED]);

        $this->actingAs($this->managerA)->post(route('company.customers.block', $this->customerA->id), [
            'reason' => 'Testing suspension block',
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post(route('company.customers.unblock', $this->customerA->id))->assertForbidden();

        $this->actingAs($this->managerA)->put(route('company.customers.update', $this->customerA->id), [
            'notes' => 'Testing suspension notes',
        ])->assertForbidden();
    }

    /**
     * 16. Audit events are recorded for customer creation, update, blocking, and unblocking.
     */
    public function test_customer_audit_events_recorded(): void
    {
        // Update notes
        $this->actingAs($this->managerA)->put(route('company.customers.update', $this->customerA->id), [
            'notes' => 'VIP customer',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'customer.updated',
        ]);

        // Block
        $this->actingAs($this->managerA)->post(route('company.customers.block', $this->customerA->id), [
            'reason' => 'Audit test block',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'customer.blocked',
        ]);

        // Unblock
        $this->actingAs($this->managerA)->post(route('company.customers.unblock', $this->customerA->id));

        $this->assertDatabaseHas('audit_logs', [
            'company_id' => $this->companyA->id,
            'action' => 'customer.unblocked',
        ]);
    }
}
