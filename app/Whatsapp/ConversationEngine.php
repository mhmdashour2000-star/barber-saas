<?php

namespace App\Whatsapp;

use App\Enums\AppointmentStatus;
use App\Enums\ConversationState as State;
use App\Models\{Company, Customer, Service, WhatsappConversation};
use App\Services\{AppointmentService, AvailabilityService, CustomerRestrictionService};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

/** Called under the processor's conversation mutex. No provider transport or HTTP concerns. */
class ConversationEngine
{
    public function __construct(
        private AppointmentService $appointments,
        private AvailabilityService $availability,
        private CustomerRestrictionService $restrictions,
    ) {}

    public function handle(WhatsappConversation $conversation, Customer $customer, Company $company, InboundMessage $message): ConversationResponse
    {
        $state = State::tryFrom($conversation->state);
        if (!$state || !is_array($conversation->context) || $conversation->expires_at?->lte(now())) {
            $conversation->state = State::START->value;
            $conversation->context = [];
        }
        $conversation->last_message_at = now();
        $conversation->expires_at = now()->addHours(max(1, (int) config('whatsapp.conversation_idle_hours', 24)));
        $conversation->save();
        $state = State::from($conversation->state);
        $text = trim($message->payload);
        if ($state === State::START || in_array(mb_strtolower($text), ['menu', 'restart'], true)) {
            if (!$customer->name || $customer->name === 'Guest Client') {
                return $this->respond($conversation, State::AWAITING_NAME, 'Welcome! What name should we use for your bookings?');
            }
            return $this->menu($conversation);
        }
        if ($state === State::AWAITING_NAME) {
            if ($message->messageType !== 'text' || mb_strlen($text) < 2 || mb_strlen($text) > 100
                || !preg_match('/\p{L}/u', $text) || preg_match('/[\x00-\x1F]/', $text)) {
                return $this->respond($conversation, State::AWAITING_NAME, 'Please enter your name (2–100 characters).');
            }
            $customer->update(['name' => $text]);
            return $this->menu($conversation);
        }

        $action = $this->selectedAction($conversation, $message);
        if (!$company->accepting_new_bookings && ($action === 'book' || in_array($state, [
            State::SELECT_SERVICE, State::SELECT_BARBER, State::SELECT_DAY, State::SELECT_TIME, State::CONFIRM_BOOKING,
        ], true))) {
            return $this->menu($conversation, 'This salon is not accepting new bookings. You can still view or cancel existing appointments.');
        }
        try {
            if ($action === null) {
                if ($state === State::SELECT_TIME) {
                    $context = $this->context($conversation);
                    return $this->times($conversation, $this->service($company, $context), $context,
                        'Please choose a current available time, or send menu to restart. ');
                }
                return $this->repeat($conversation, 'Please choose one of the current options, or send menu to restart.');
            }
            return match ($state) {
                State::MAIN_MENU, State::INQUIRY_MENU, State::BOOKING_COMPLETE => $this->mainAction($conversation, $customer, $company, $action),
                State::SELECT_SERVICE => $this->selectService($conversation, $company, $action),
                State::SELECT_BARBER => $this->selectBarber($conversation, $company, $action),
                State::SELECT_DAY => $this->selectDay($conversation, $company, $action),
                State::SELECT_TIME => $this->selectTime($conversation, $customer, $company, $action),
                State::CONFIRM_BOOKING => $this->confirmBooking($conversation, $customer, $company, $action),
                State::CANCEL_SELECT => $this->selectCancellation($conversation, $customer, $company, $action),
                State::CANCEL_CONFIRM => $this->confirmCancellation($conversation, $customer, $company, $action),
                default => $this->menu($conversation),
            };
        } catch (InvalidArgumentException|ModelNotFoundException $exception) {
            // Expected stale/invalid selections reset safely; infrastructure exceptions roll back in the processor.
            return $this->menu($conversation, 'That choice is no longer available. Please start again.');
        }
    }

    private function menu(WhatsappConversation $c, string $text = 'How can we help?'): ConversationResponse
    {
        return $this->respond($c, State::MAIN_MENU, $text, $this->menuChoices());
    }

    private function menuChoices(): array
    {
        return ['book' => 'New appointment', 'inquiry' => 'My appointments', 'cancel' => 'Cancel appointment'];
    }

    private function mainAction(WhatsappConversation $c, Customer $customer, Company $company, string $action): ConversationResponse
    {
        if ($action === 'book') {
            try {
                $this->restrictions->assertCanBook($customer);
            } catch (InvalidArgumentException) {
                return $this->menu($c, 'You cannot book while inactive or blocked. You can still view or cancel your appointments.');
            }
            if ($company->status !== Company::STATUS_ACTIVE) {
                return $this->menu($c, 'This salon is not accepting new bookings. You can still view or cancel existing appointments.');
            }
            $choices = $company->services()->where('active', true)->orderBy('id')->get()
                ->mapWithKeys(fn ($s) => ['service:'.$s->id => $s->name])->all();
            return $choices ? $this->respond($c, State::SELECT_SERVICE, 'Choose a service.', $choices)
                : $this->menu($c, 'No services are available right now.');
        }
        $appointments = $this->upcoming($company, $customer)->orderBy('starts_at')->orderBy('id')->get();
        if ($action === 'inquiry') {
            $data = $appointments->map(fn ($a) => $this->appointmentSummary($a))->all();
            return $this->respond($c, State::INQUIRY_MENU, $data ? 'Your upcoming appointments (Istanbul time).' : 'You have no upcoming confirmed appointments.',
                $this->menuChoices(), [], ['appointments' => $data]);
        }
        if ($action === 'cancel') {
            $choices = $appointments->mapWithKeys(fn ($a) => ['appointment:'.$a->id => $a->booking_code.' — '.$a->starts_at->copy()->setTimezone(AvailabilityService::TIMEZONE)->format('d M H:i')])->all();
            return $choices ? $this->respond($c, State::CANCEL_SELECT, 'Which appointment would you like to cancel?', $choices)
                : $this->menu($c, 'You have no upcoming appointments to cancel.');
        }
        return $this->menu($c);
    }

    private function selectService(WhatsappConversation $c, Company $company, string $action): ConversationResponse
    {
        $service = $company->services()->where('active', true)->findOrFail($this->id($action, 'service'));
        $employees = $service->employees()->where('employees.company_id', $company->id)->where('employees.active', true)->orderBy('employees.id')->get();
        if ($employees->isEmpty()) {
            return $this->menu($c, 'No barbers are available for that service.');
        }
        $choices = ['any' => 'Any Available Barber'] + $employees->mapWithKeys(fn ($e) => ['employee:'.$e->id => $e->name])->all();
        return $this->respond($c, State::SELECT_BARBER, 'Choose a barber.', $choices, ['service_id' => $service->id]);
    }

    private function selectBarber(WhatsappConversation $c, Company $company, string $action): ConversationResponse
    {
        $context = $this->context($c);
        $context['employee_id'] = $action === 'any' ? null : $this->id($action, 'employee');
        $service = $this->service($company, $context);
        return $this->days($c, $service, $context);
    }

    private function days(WhatsappConversation $c, Service $service, array $context, string $prefix = ''): ConversationResponse
    {
        $choices = [];
        $today = Carbon::now(AvailabilityService::TIMEZONE)->startOfDay();
        for ($offset = 0; $offset <= $service->company->booking_days_ahead; $offset++) {
            $day = $today->copy()->addDays($offset);
            if ($this->availability->getBookableSlots($service, $day, $context['employee_id']) !== []) {
                $choices['day:'.$day->toDateString()] = $day->format('D d M');
            }
        }
        unset($context['day'], $context['time'], $context['time_page'], $context['offer']);
        return $choices ? $this->respond($c, State::SELECT_DAY, $prefix.'Choose a day (Istanbul time).', $choices, $context)
            : $this->menu($c, $prefix.'No available times within this salon’s booking horizon.');
    }

    private function selectDay(WhatsappConversation $c, Company $company, string $action): ConversationResponse
    {
        $context = $this->context($c);
        $context['day'] = str_starts_with($action, 'day:') ? substr($action, 4) : '';
        $context['time_page'] = 0;
        $service = $this->service($company, $context);
        $this->day($context);
        return $this->times($c, $service, $context);
    }

    private function times(WhatsappConversation $c, Service $service, array $context, string $prefix = ''): ConversationResponse
    {
        $slots = $this->availability->getBookableSlots($service, $this->day($context), $context['employee_id']);
        unset($context['time'], $context['offer']);
        if ($slots === []) {
            return $this->days($c, $service, $context, $prefix);
        }
        $page = $context['time_page'] ?? 0;
        if (!is_int($page) || $page < 0) throw new InvalidArgumentException('Invalid time page.');
        // A small, provider-neutral choice page; reserve two choices for navigation when needed.
        $pageSize = count($slots) <= 10 ? 10 : 8;
        $pageCount = (int) ceil(count($slots) / $pageSize);
        $page = min($page, $pageCount - 1);
        $context['time_page'] = $page;
        $visible = array_slice($slots, $page * $pageSize, $pageSize);
        $choices = array_combine(array_map(fn ($s) => 'time:'.$s, $visible), $visible);
        if ($page > 0) $choices['time_page:previous'] = 'Previous times';
        if ($page + 1 < $pageCount) $choices['time_page:next'] = 'More times';
        $pageLabel = $pageCount > 1 ? ' Page '.($page + 1).' of '.$pageCount.'.' : '';
        return $this->respond($c, State::SELECT_TIME, $prefix.'Choose a time (Europe/Istanbul).'.$pageLabel,
            $choices, $context, ['title' => 'Available times'], 'list');
    }

    private function selectTime(WhatsappConversation $c, Customer $customer, Company $company, string $action): ConversationResponse
    {
        $context = $this->context($c);
        $service = $this->service($company, $context);
        if (in_array($action, ['time_page:next', 'time_page:previous'], true)) {
            $page = $context['time_page'] ?? 0;
            if (!is_int($page) || $page < 0) throw new InvalidArgumentException('Invalid time page.');
            $context['time_page'] = max(0, $page + ($action === 'time_page:next' ? 1 : -1));
            return $this->times($c, $service, $context);
        }
        $time = str_starts_with($action, 'time:') ? substr($action, 5) : '';
        if (!in_array($time, $this->availability->getBookableSlots($service, $this->day($context), $context['employee_id']), true)) {
            return $this->times($c, $service, $context, 'The selected time is no longer available. ');
        }
        $context['time'] = $time;
        $summary = ['customer_name' => $customer->name, 'service' => $service->name,
            'barber' => $context['employee_id'] === null ? 'Any Available Barber' : $company->employees()->findOrFail($context['employee_id'])->name,
            'barber_assignment' => $context['employee_id'] === null ? 'on_confirmation' : 'selected',
            'date' => $context['day'], 'time' => $time, 'timezone' => AvailabilityService::TIMEZONE,
            'duration_minutes' => $service->duration_minutes, 'price_minor_units' => $service->price_minor_units];
        // Do not silently book a changed price/duration/name after the customer saw this confirmation.
        $context['offer'] = $summary;
        return $this->respond($c, State::CONFIRM_BOOKING, 'Confirm this appointment?', ['confirm' => 'Confirm booking', 'decline' => 'Cancel'],
            $context, ['summary' => $summary], 'confirmation');
    }

    private function confirmBooking(WhatsappConversation $c, Customer $customer, Company $company, string $action): ConversationResponse
    {
        if ($action !== 'confirm') {
            return $this->menu($c, 'Booking was not created.');
        }
        $context = $this->context($c);
        $service = $this->service($company, $context);
        $this->day($context);
        if (!is_string($context['time'] ?? null) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $context['time'])
            || !is_array($context['offer'] ?? null)
            || ($context['offer']['date'] ?? null) !== $context['day']
            || ($context['offer']['time'] ?? null) !== $context['time']
            || ($context['offer']['duration_minutes'] ?? null) !== $service->duration_minutes
            || ($context['offer']['price_minor_units'] ?? null) !== $service->price_minor_units
            || ($context['offer']['service'] ?? null) !== $service->name) {
            throw new InvalidArgumentException('Invalid or changed confirmation.');
        }
        try {
            $appointment = \Illuminate\Support\Facades\DB::transaction(function () use ($context, $customer, $service, $company) {
                $created = $this->appointments->create([
                    'customer_id' => $customer->id, 'service_id' => $service->id,
                    'employee_id' => $context['employee_id'],
                    'starts_at' => Carbon::parse($context['day'].' '.$context['time'], AvailabilityService::TIMEZONE),
                ], $company, 'customer', $customer->id);
                if ($created->service_duration_minutes_snapshot !== $context['offer']['duration_minutes']
                    || $created->service_price_minor_units_snapshot !== $context['offer']['price_minor_units']
                    || $created->service_name_snapshot !== $context['offer']['service']) {
                    throw new InvalidArgumentException('The offer changed before the slot was locked.');
                }
                return $created;
            });
        } catch (InvalidArgumentException) {
            try {
                $this->restrictions->assertCanBook($customer);
            } catch (InvalidArgumentException) {
                return $this->menu($c, 'You cannot book while inactive or blocked. You can still view or cancel your appointments.');
            }
            if (!$company->fresh()->accepting_new_bookings) {
                return $this->menu($c, 'This salon is not accepting new bookings. You can still view or cancel existing appointments.');
            }
            return $this->times($c, $service, $context, 'The selected time could not be booked and may no longer be available. Please choose again. ');
        }

        $this->auditSource($company, 'whatsapp.booking.created', $appointment->booking_code);
        return $this->respond($c, State::BOOKING_COMPLETE, 'Booking confirmed.',
            $this->menuChoices(), [], ['appointment' => $this->appointmentSummary($appointment)]);
    }

    private function selectCancellation(WhatsappConversation $c, Customer $customer, Company $company, string $action): ConversationResponse
    {
        $appointment = $this->upcoming($company, $customer)->findOrFail($this->id($action, 'appointment'));
        return $this->respond($c, State::CANCEL_CONFIRM, 'Cancel appointment '.$appointment->booking_code.'? Late cancellation rules apply.',
            ['confirm' => 'Confirm cancellation', 'decline' => 'Keep appointment'], ['appointment_id' => $appointment->id],
            ['summary' => $this->appointmentSummary($appointment)], 'confirmation');
    }

    private function confirmCancellation(WhatsappConversation $c, Customer $customer, Company $company, string $action): ConversationResponse
    {
        if ($action !== 'confirm') {
            return $this->menu($c, 'Your appointment was not cancelled.');
        }
        $context = $this->context($c);
        if (!is_int($context['appointment_id'] ?? null)) {
            throw new InvalidArgumentException('Invalid appointment selection.');
        }
        $appointment = $company->appointments()->where('customer_id', $customer->id)->lockForUpdate()->findOrFail($context['appointment_id']);
        if ($appointment->status === AppointmentStatus::CANCELLED_BY_CUSTOMER) {
            return $this->menu($c, 'This appointment is already cancelled.');
        }
        if ($appointment->status !== AppointmentStatus::CONFIRMED || $appointment->starts_at->lte(now())) {
            return $this->menu($c, 'That appointment can no longer be cancelled here.');
        }
        $appointment = $this->appointments->cancelByCustomer($appointment, $company, 'customer', $customer->id);
        $this->auditSource($company, 'whatsapp.booking.cancelled', $appointment->booking_code);
        return $this->respond($c, State::MAIN_MENU, 'Appointment '.$appointment->booking_code.' cancelled.', $this->menuChoices(), [],
            ['appointment' => $this->appointmentSummary($appointment)]);
    }

    private function upcoming(Company $company, Customer $customer)
    {
        return $company->appointments()->where('customer_id', $customer->id)->where('status', AppointmentStatus::CONFIRMED)->where('starts_at', '>', now());
    }

    private function auditSource(Company $company, string $action, string $code): void
    {
        $company->auditLogs()->create(['user_id' => null, 'action' => $action, 'description' => 'Booking '.$code]);
    }

    private function appointmentSummary($a): array
    {
        return ['booking_code' => $a->booking_code, 'service' => $a->service_name_snapshot,
            // Current assignment is operational; the original snapshot remains immutable history.
            'barber' => $a->company->employees()->whereKey($a->employee_id)->value('name') ?? 'Unavailable',
            'date' => $a->starts_at->copy()->setTimezone(AvailabilityService::TIMEZONE)->toDateString(),
            'time' => $a->starts_at->copy()->setTimezone(AvailabilityService::TIMEZONE)->format('H:i'),
            'timezone' => AvailabilityService::TIMEZONE, 'status' => $a->status->value,
            'duration_minutes' => $a->service_duration_minutes_snapshot, 'price_minor_units' => $a->service_price_minor_units_snapshot];
    }

    private function service(Company $company, array $context): Service
    {
        if (!is_int($context['service_id'] ?? null) || !array_key_exists('employee_id', $context)
            || ($context['employee_id'] !== null && !is_int($context['employee_id']))) {
            throw new InvalidArgumentException('Malformed service selection.');
        }
        $service = $company->services()->where('active', true)->findOrFail($context['service_id']);
        if ($context['employee_id'] !== null) {
            $service->employees()->where('employees.company_id', $company->id)->where('employees.active', true)->findOrFail($context['employee_id']);
        }
        return $service;
    }

    private function day(array $context): Carbon
    {
        $value = $context['day'] ?? null;
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
            || !Carbon::hasFormat($value, 'Y-m-d')) {
            throw new InvalidArgumentException('Malformed day.');
        }
        return Carbon::parse($value, AvailabilityService::TIMEZONE);
    }

    private function id(string $action, string $prefix): int
    {
        if (!preg_match('/^'.preg_quote($prefix, '/').':([1-9][0-9]*)$/D', $action, $match)) {
            throw new InvalidArgumentException('Invalid selection.');
        }
        return (int) $match[1];
    }

    private function context(WhatsappConversation $c): array
    {
        if (!is_array($c->context)) {
            throw new InvalidArgumentException('Invalid conversation context.');
        }
        return $c->context;
    }

    private function selectedAction(WhatsappConversation $c, InboundMessage $message): ?string
    {
        $choices = $c->context['choices'] ?? [];
        if (!is_array($choices)) {
            return null;
        }
        foreach (array_values($choices) as $index => $choice) {
            if (!is_array($choice) || !is_string($choice['id'] ?? null) || !is_string($choice['action'] ?? null) || !is_string($choice['label'] ?? null)) {
                continue;
            }
            if ($message->payload === $choice['id'] || ($message->messageType === 'text'
                && in_array(mb_strtolower(trim($message->payload)), [(string) ($index + 1), $choice['action'], mb_strtolower($choice['label'])], true))) {
                return $choice['action'];
            }
        }
        return null;
    }

    private function repeat(WhatsappConversation $c, string $text): ConversationResponse
    {
        $choices = $c->context['choices'] ?? [];
        if (!is_array($choices) || count(array_filter($choices, fn ($v) => is_array($v) && is_string($v['id'] ?? null) && is_string($v['label'] ?? null) && is_string($v['action'] ?? null))) !== count($choices)) {
            return $this->menu($c, 'Let’s start again.');
        }
        return new ConversationResponse('choices', $text, array_map(fn ($v) => ['id' => $v['id'], 'label' => $v['label']], $choices));
    }

    private function respond(WhatsappConversation $c, State $state, string $text, array $options = [], array $context = [], array $data = [], string $type = 'choices'): ConversationResponse
    {
        $revision = $c->revision + 1;
        $choices = [];
        foreach ($options as $action => $label) {
            $choices[] = ['id' => $c->id.':'.$revision.':'.$action, 'action' => $action, 'label' => $label];
        }
        $c->update(['state' => $state->value, 'revision' => $revision, 'context' => array_replace($context, ['choices' => $choices])]);
        return new ConversationResponse($choices ? $type : 'text', $text,
            array_map(fn ($v) => ['id' => $v['id'], 'label' => $v['label']], $choices), $data);
    }
}
