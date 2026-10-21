<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use Modules\Auth\Models\AuditLog;
use Modules\Auth\Models\User;

/** Writes the audit trail. Never records a password, token or any other secret. */
final class AuditTrail
{
    /** @param  array<string, scalar|null>  $details */
    public function record(?User $actor, string $action, ?User $subject = null, array $details = []): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? 'user' : null,
            'subject_id' => $subject?->id,
            // Kept apart from the id so the entry still reads after the account is deleted.
            'subject_label' => $subject ? mb_substr("{$subject->name} <{$subject->email}>", 0, 190) : null,
            'details' => $details ?: null,
            'created_at' => now(),
        ]);
    }
}
