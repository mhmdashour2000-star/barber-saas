<?php

namespace App\Whatsapp\Meta;

use App\Whatsapp\CompanyResolver;
use Illuminate\Support\Facades\DB;

class DeliveryStatuses
{
    public function handle(array $value): void
    {
        $destination = $value['metadata']['phone_number_id'] ?? null;
        if (!is_string($destination)) return;
        try { $company = app(CompanyResolver::class)->byPhoneNumberId($destination); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException) { return; }
        foreach (is_array($value['statuses'] ?? null) ? $value['statuses'] : [] as $event) {
            $id = $event['id'] ?? null; $status = $event['status'] ?? null;
            if (!is_string($id) || !in_array($status, ['sent', 'delivered', 'read', 'failed'], true)) continue;
            DB::transaction(function () use ($company, $id, $status) {
                $m = $company->whatsappOutboundMessages()->where('provider', 'meta')->where('external_message_id', $id)->lockForUpdate()->first();
                if (!$m) return;
                $rank = [null => 0, 'sent' => 1, 'failed' => 2, 'delivered' => 3, 'read' => 4];
                if (($rank[$m->delivery_status] ?? 0) >= $rank[$status]) return;
                $m->update(['delivery_status' => $status]);
                if ($status === 'failed') $m->update(['last_error_code' => 'delivery_failed', 'last_error_summary' => 'Provider reported delivery failure.']);
            }, 3);
        }
    }
}
