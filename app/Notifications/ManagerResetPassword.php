<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class ManagerResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable)
    {
        // Use the configured canonical origin, never an untrusted request Host header.
        return rtrim(config('app.url'), '/').route('manager.password.reset', [
            'token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
    }
}
