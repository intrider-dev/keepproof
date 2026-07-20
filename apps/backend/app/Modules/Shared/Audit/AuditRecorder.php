<?php

declare(strict_types=1);

namespace App\Modules\Shared\Audit;

use App\Modules\Accounts\Models\User;
use App\Modules\Households\Models\Household;
use App\Modules\Shared\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final readonly class AuditRecorder
{
    public function __construct(private Request $request) {}

    /** @param array<string, bool|int|string|null> $metadata */
    public function record(
        string $action,
        ?User $actor = null,
        ?Household $household = null,
        ?Model $subject = null,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'household_id' => $household?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'correlation_id' => $this->request->attributes->get('correlation_id'),
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
