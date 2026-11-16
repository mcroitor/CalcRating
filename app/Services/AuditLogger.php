<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Write a domain event to the dedicated audit channel.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $event,
        User|int|null $actor,
        string $targetType,
        int|string|null $targetId,
        array $context = []
    ): void {
        $actorId = $actor instanceof User ? $actor->id : $actor;
        $requestId = Log::sharedContext()['request_id'] ?? null;

        Log::channel('audit')->info($event, [
            'event' => $event,
            'actor_id' => $actorId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'request_id' => $requestId,
            'context' => $context,
        ]);
    }
}
