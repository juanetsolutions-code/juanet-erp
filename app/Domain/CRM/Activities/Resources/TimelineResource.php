<?php

namespace App\Domain\CRM\Activities\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'source' => $this['source'],
            'type' => $this['type'],
            'subject' => $this['subject'],
            'description' => $this['description'],
            'user_name' => $this['user_name'],
            'user_id' => $this['user_id'],
            'properties' => $this['properties'] ?? [],
            'timestamp' => $this['timestamp'] instanceof \Carbon\Carbon ? $this['timestamp']->toIso8601String() : $this['timestamp'],
            'due_at' => isset($this['due_at']) && $this['due_at'] instanceof \Carbon\Carbon ? $this['due_at']->toIso8601String() : ($this['due_at'] ?? null),
            'completed_at' => isset($this['completed_at']) && $this['completed_at'] instanceof \Carbon\Carbon ? $this['completed_at']->toIso8601String() : ($this['completed_at'] ?? null),
            'is_completed' => (bool) ($this['is_completed'] ?? false),
            'priority' => $this['priority'] ?? null,
            'attachments' => $this['attachments'] ?? [],
            'notes_count' => $this['notes_count'] ?? 0,
        ];
    }
}
