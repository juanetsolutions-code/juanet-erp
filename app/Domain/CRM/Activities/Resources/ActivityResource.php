<?php

namespace App\Domain\CRM\Activities\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'loggable_type' => $this->loggable_type,
            'loggable_id' => $this->loggable_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'subject' => $this->subject,
            'description' => $this->description,
            'properties' => $this->properties,
            'due_at' => $this->due_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_completed' => (bool)$this->is_completed,
            'priority' => $this->priority,
            'is_recurring' => (bool)$this->is_recurring,
            'recurring_rules' => $this->recurring_rules,
            'last_reminder_sent_at' => $this->last_reminder_sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'assignee' => $this->relationLoaded('assignee') ? [
                'id' => $this->assignee?->id,
                'name' => $this->assignee?->name,
                'email' => $this->assignee?->email,
            ] : null,
            'attachments' => $this->relationLoaded('attachments') ? $this->attachments->map(fn($att) => [
                'id' => $att->id,
                'stored_file_id' => $att->stored_file_id,
                'name' => $att->file?->name,
                'size' => $att->file?->size,
                'mime_type' => $att->file?->mime_type,
            ]) : [],
            'notes_count' => $this->relationLoaded('notes') ? $this->notes->count() : 0,
        ];
    }
}
