<?php

namespace App\Domain\CRM\Activities\DTO;

use App\Domain\CRM\Activities\Enums\ActivityType;
use App\Domain\CRM\Activities\Enums\ActivityPriority;
use Carbon\Carbon;

class ActivityData
{
    public function __construct(
        public string $organizationId,
        public ActivityType $type,
        public string $subject,
        public ?string $loggableType = null,
        public ?string $loggableId = null,
        public ?string $userId = null,
        public ?string $description = null,
        public ?array $properties = null,
        public ?Carbon $dueAt = null,
        public ?Carbon $completedAt = null,
        public bool $isCompleted = false,
        public ActivityPriority $priority = ActivityPriority::MEDIUM,
        public bool $isRecurring = false,
        public ?array $recurringRules = null
    ) {}

    public static function fromArray(array $data, string $organizationId): self
    {
        return new self(
            organizationId: $organizationId,
            type: $data['type'] instanceof ActivityType ? $data['type'] : ActivityType::from($data['type']),
            subject: $data['subject'],
            loggableType: $data['loggable_type'] ?? null,
            loggableId: $data['loggable_id'] ?? null,
            userId: $data['user_id'] ?? auth()->id(),
            description: $data['description'] ?? null,
            properties: $data['properties'] ?? null,
            dueAt: isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
            completedAt: isset($data['completed_at']) ? Carbon::parse($data['completed_at']) : null,
            isCompleted: (bool) ($data['is_completed'] ?? false),
            priority: isset($data['priority']) 
                ? ($data['priority'] instanceof ActivityPriority ? $data['priority'] : ActivityPriority::from($data['priority'])) 
                : ActivityPriority::MEDIUM,
            isRecurring: (bool) ($data['is_recurring'] ?? false),
            recurringRules: $data['recurring_rules'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'loggable_type' => $this->loggableType,
            'loggable_id' => $this->loggableId,
            'user_id' => $this->userId,
            'type' => $this->type->value,
            'subject' => $this->subject,
            'description' => $this->description,
            'properties' => $this->properties,
            'due_at' => $this->dueAt,
            'completed_at' => $this->completedAt,
            'is_completed' => $this->isCompleted,
            'priority' => $this->priority->value,
            'is_recurring' => $this->isRecurring,
            'recurring_rules' => $this->recurringRules,
        ];
    }
}
