<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerBlock;
use App\Models\CustomerViolation;
use App\Support\PhoneHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerRestrictionService
{
    /**
     * Reusable upsert method for customer identity by phone within a company.
     * Prevents duplicates and normalizes phone numbers.
     */
    public function findOrCreateCustomer(Company $company, string $phone, ?string $name = null): Customer
    {
        $normalizedPhone = PhoneHelper::normalize($phone);

        return DB::transaction(function () use ($company, $normalizedPhone, $name) {
            $customer = $company->customers()
                ->where('phone', $normalizedPhone)
                ->first();

            if (!$customer) {
                $customer = $company->customers()->create([
                    'name' => $name ?: 'Guest Client',
                    'phone' => $normalizedPhone,
                    'active' => true,
                ]);

                AuditLog::record(
                    'customer.created',
                    "Customer '{$customer->name}' ({$customer->phone}) registered.",
                    auth()->id(),
                    $company->id
                );
            } elseif ($name && $customer->name === 'Guest Client') {
                $customer->update(['name' => $name]);
            }

            return $customer;
        });
    }

    /**
     * Get the count of qualifying violations for the current cycle.
     * Semantics: Violations that occurred AFTER the most recent automatic block was created.
     * If no automatic block exists yet, all historical violations count.
     */
    public function getActiveViolationCount(Customer $customer): int
    {
        $latestAutoBlock = $customer->blocks()
            ->where('source', CustomerBlock::SOURCE_AUTOMATIC)
            ->latest('starts_at')
            ->first();

        $query = $customer->violations();

        if ($latestAutoBlock) {
            $query->where('occurred_at', '>', $latestAutoBlock->starts_at);
        }

        return $query->count();
    }

    /**
     * Record a behavioral violation for a customer and evaluate the automatic blocking threshold.
     */
    public function recordViolation(
        Customer $customer,
        string $type,
        ?string $reason = null,
        ?int $appointmentId = null,
        ?int $userId = null
    ): CustomerViolation {
        $company = $customer->company;
        $now = Carbon::now();

        return DB::transaction(function () use ($company, $customer, $type, $reason, $appointmentId, $userId, $now) {
            /** @var CustomerViolation $violation */
            $violation = $company->customerViolations()->create([
                'customer_id' => $customer->id,
                'type' => $type,
                'reason' => $reason,
                'appointment_id' => $appointmentId,
                'occurred_at' => $now,
                'created_by_user_id' => $userId,
            ]);

            AuditLog::record(
                'customer.violation.created',
                "Violation '{$type}' recorded for customer '{$customer->name}'.",
                $userId,
                $company->id
            );

            // Evaluate violation count in current cycle
            $currentCount = $this->getActiveViolationCount($customer);
            $limit = $company->violation_limit ?? 3;

            // If threshold reached and customer does not already have an active block, create automatic block
            if ($currentCount >= $limit && !$customer->isBlocked()) {
                $this->createAutomaticBlock($customer, $currentCount, $userId);
            }

            return $violation;
        });
    }

    /**
     * Automatically create a block when threshold is reached.
     */
    public function createAutomaticBlock(Customer $customer, int $violationCount, ?int $userId = null): CustomerBlock
    {
        $company = $customer->company;
        $now = Carbon::now();
        $durationDays = $company->block_duration_days ?? 7;
        $endsAt = $now->copy()->addDays($durationDays);

        /** @var CustomerBlock $block */
        $block = $company->customerBlocks()->create([
            'customer_id' => $customer->id,
            'reason' => "Automatic block: reached limit of {$company->violation_limit} violations ({$violationCount} recorded in current cycle).",
            'source' => CustomerBlock::SOURCE_AUTOMATIC,
            'starts_at' => $now,
            'ends_at' => $endsAt,
            'created_by_user_id' => $userId,
        ]);

        AuditLog::record(
            'customer.automatic_block.created',
            "Customer '{$customer->name}' automatically blocked until {$endsAt->format('Y-m-d H:i')}.",
            $userId,
            $company->id
        );

        return $block;
    }

    /**
     * Manually block a customer with a specified reason and duration (in days, or null for indefinite).
     */
    public function manualBlock(Customer $customer, string $reason, ?int $durationDays = null, ?int $userId = null): CustomerBlock
    {
        $company = $customer->company;
        $now = Carbon::now();
        $endsAt = $durationDays ? $now->copy()->addDays($durationDays) : null;

        return DB::transaction(function () use ($company, $customer, $reason, $endsAt, $now, $userId) {
            /** @var CustomerBlock $block */
            $block = $company->customerBlocks()->create([
                'customer_id' => $customer->id,
                'reason' => $reason,
                'source' => CustomerBlock::SOURCE_MANUAL,
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'created_by_user_id' => $userId,
            ]);

            AuditLog::record(
                'customer.blocked',
                "Customer '{$customer->name}' manually blocked." . ($endsAt ? " Expires at {$endsAt->format('Y-m-d')}." : ' Indefinite block.'),
                $userId,
                $company->id
            );

            return $block;
        });
    }

    /**
     * Manually lift an active customer block.
     */
    public function manualUnblock(Customer $customer, ?int $userId = null): bool
    {
        $company = $customer->company;
        $activeBlock = $customer->activeBlock();

        if (!$activeBlock) {
            return false;
        }

        $activeBlock->update([
            'lifted_at' => Carbon::now(),
            'lifted_by_user_id' => $userId,
        ]);

        AuditLog::record(
            'customer.unblocked',
            "Customer '{$customer->name}' manual block was lifted.",
            $userId,
            $company->id
        );

        return true;
    }
}
