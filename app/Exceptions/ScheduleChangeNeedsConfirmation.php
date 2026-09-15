<?php

namespace App\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ScheduleChangeNeedsConfirmation extends \RuntimeException
{
    public function __construct(public Collection $appointments, public array $fields, public string $confirmation)
    {
        parent::__construct('Existing appointments are affected.');
    }

    public function render(Request $request)
    {
        return response()->view('company.availability.confirm-change', [
            'appointments' => $this->appointments, 'fields' => $this->fields,
            'confirmation' => $this->confirmation, 'action' => $request->url(), 'method' => $request->method(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
