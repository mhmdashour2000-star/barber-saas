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
    /** Booking eligibility belongs here; appointment callers must supply a tenant-scoped customer. */
    public function assertCanBook(Customer $customer): void
    {
        DB::transaction(function () use ($customer) {
            $customer = $this->lockCustomer($customer);
            // A locking read sees committed blocks even inside an older MySQL snapshot.
            if (!$customer->active || $this->activeBlocks($customer)->first()) {
                throw new \InvalidArgumentException('This customer is inactive or currently blocked and cannot book appointments.');
            }
        }, 3);
    }

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
        }, 3);
    }

    /**
     * Get the count of qualifying violations for the current cycle.
     * New blocks use a violation ID watermark; historical blocks retain their timestamp cutoff.
     * If no automatic block exists yet, all historical violations count.
     */
    public function getActiveViolationCount(Customer $customer): int
    {
        $latestAutoBlock = $customer->blocks()
            ->where('source', CustomerBlock::SOURCE_AUTOMATIC)
            ->latest('starts_at')->latest('id')
            ->lockForUpdate()->first();

        $query = $customer->violations();

        if ($latestAutoBlock) {
            if ($latestAutoBlock->violation_cursor_id !== null) {
                $query->where('id', '>', $latestAutoBlock->violation_cursor_id);
            } else {
                // Preserve the cutoff for historical blocks created before the cursor existed.
                $query->where('occurred_at', '>', $latestAutoBlock->starts_at);
            }
        }

        return $query->lockForUpdate()->get(['id'])->count();
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
            $customer = $this->lockCustomer($customer);
            if ($appointmentId !== null) {
                $company->appointments()->where('customer_id', $customer->id)->findOrFail($appointmentId);
                $existing = $customer->violations()->where('appointment_id', $appointmentId)
                    ->where('type', $type)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }
            }
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
            if ($currentCount >= $limit && !$this->activeBlocks($customer)->first()) {
                $this->createAutomaticBlock($customer, $currentCount, $userId);
            }

            return $violation;
        }, 3);
    }

    /**
     * Automatically create a block when threshold is reached.
     */
    public function createAutomaticBlock(Customer $customer, int $violationCount, ?int $userId = null): CustomerBlock
    {
        return DB::transaction(function () use ($customer, $userId) {
            $customer = $this->lockCustomer($customer);
            if ($existing = $this->activeBlocks($customer)->first()) {
                return $existing;
            }
            $violationCount = $this->getActiveViolationCount($customer);
            if ($violationCount < ($customer->company->violation_limit ?? 3)) {
                throw new \InvalidArgumentException('The automatic block threshold has not been reached.');
            }
            $company = $customer->company;
            $now = Carbon::now();
            $durationDays = $company->block_duration_days ?? 7;
            $endsAt = $now->copy()->addDays($durationDays);

            /** @var CustomerBlock $block */
            $block = $company->customerBlocks()->create([
                'customer_id' => $customer->id,
                'reason' => "Automatic block: reached limit of {$company->violation_limit} violations ({$violationCount} recorded in current cycle).",
                'source' => CustomerBlock::SOURCE_AUTOMATIC,
                'violation_cursor_id' => $customer->violations()->lockForUpdate()->orderByDesc('id')->value('id'),
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
        }, 3);
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
            $customer = $this->lockCustomer($customer);
            // Deterministic policy: retain the existing active block and its original expiry/reason.
            if ($existing = $this->activeBlocks($customer)->first()) {
                return $existing;
            }
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
        }, 3);
    }

    /**
     * Manually lift an active customer block.
     */
    public function manualUnblock(Customer $customer, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($customer, $userId) {
            $customer = $this->lockCustomer($customer);
            $blocks = $this->activeBlocks($customer)->get();
            if ($blocks->isEmpty()) {
                return false;
            }
            foreach ($blocks as $block) {
                $block->update(['lifted_at' => Carbon::now(), 'lifted_by_user_id' => $userId]);
            }
            AuditLog::record('customer.unblocked', "All active blocks for customer '{$customer->name}' were lifted.",
                $userId, $customer->company_id);

            return true;
        }, 3);
    }

    private function lockCustomer(Customer $customer): Customer
    {
        return $customer->company->customers()->lockForUpdate()->findOrFail($customer->id);
    }

    private function activeBlocks(Customer $customer)
    {
        return $customer->blocks()->whereNull('lifted_at')->where('starts_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('starts_at')->orderByDesc('id')->lockForUpdate();
    }
}
