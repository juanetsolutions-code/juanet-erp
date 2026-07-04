<?php

namespace App\Domain\CRM\Activities\Services;

use App\Domain\CRM\Activities\Contracts\ActivityRepositoryInterface;
use App\Domain\CRM\Activities\Models\Activity;
use App\Domain\CRM\Activities\Models\ActivityNote;
use App\Domain\CRM\Activities\Models\ActivityAttachment;
use App\Domain\CRM\Activities\Models\ActivityReminder;
use App\Domain\CRM\Activities\DTO\ActivityData;
use App\Domain\CRM\Activities\Events\ActivityCreated;
use App\Domain\CRM\Activities\Events\ActivityUpdated;
use App\Domain\CRM\Activities\Events\ActivityCompleted;
use App\Domain\CRM\Activities\Events\AttachmentUploaded;
use App\Domain\CRM\Activities\Events\TimelineUpdated;
use App\Domain\CRM\Activities\Events\TaskOverdue;
use App\Models\StoredFile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    public function __construct(
        protected ActivityRepositoryInterface $repository,
        protected \App\Contracts\EventBus $eventBus
    ) {}

    public function createActivity(ActivityData $data): Activity
    {
        return DB::transaction(function () use ($data) {
            $activity = $this->repository->create($data);

            $this->eventBus->dispatch(new ActivityCreated($activity));

            if ($activity->loggable_type && $activity->loggable_id) {
                $this->eventBus->dispatch(new TimelineUpdated($activity->loggable_type, $activity->loggable_id, $activity->organization_id));
            }

            return $activity;
        });
    }

    public function updateActivity(Activity $activity, ActivityData $data): Activity
    {
        return DB::transaction(function () use ($activity, $data) {
            $updated = $this->repository->update($activity, $data);

            $this->eventBus->dispatch(new ActivityUpdated($updated));

            if ($updated->loggable_type && $updated->loggable_id) {
                $this->eventBus->dispatch(new TimelineUpdated($updated->loggable_type, $updated->loggable_id, $updated->organization_id));
            }

            return $updated;
        });
    }

    public function completeActivity(Activity $activity): Activity
    {
        return DB::transaction(function () use ($activity) {
            $activity->update([
                'is_completed' => true,
                'completed_at' => Carbon::now()
            ]);

            $this->eventBus->dispatch(new ActivityCompleted($activity));

            if ($activity->loggable_type && $activity->loggable_id) {
                $this->eventBus->dispatch(new TimelineUpdated($activity->loggable_type, $activity->loggable_id, $activity->organization_id));
            }

            return $activity;
        });
    }

    /**
     * Rich Notes with version history support.
     */
    public function addNote(string $notableType, string $notableId, string $content, string $organizationId, ?string $userId = null, ?string $parentId = null): ActivityNote
    {
        return DB::transaction(function () use ($notableType, $notableId, $content, $organizationId, $userId, $parentId) {
            $note = ActivityNote::create([
                'organization_id' => $organizationId,
                'notable_type' => $notableType,
                'notable_id' => $notableId,
                'user_id' => $userId ?? auth()->id(),
                'content' => $content,
                'version' => 1,
                'parent_id' => $parentId,
            ]);

            // If attached to an activity, update timeline
            if ($notableType === Activity::class) {
                $activity = Activity::find($notableId);
                if ($activity && $activity->loggable_type && $activity->loggable_id) {
                    $this->eventBus->dispatch(new TimelineUpdated($activity->loggable_type, $activity->loggable_id, $organizationId));
                }
            } else {
                $this->eventBus->dispatch(new TimelineUpdated($notableType, $notableId, $organizationId));
            }

            return $note;
        });
    }

    public function updateNote(ActivityNote $note, string $newContent, ?string $userId = null): ActivityNote
    {
        return DB::transaction(function () use ($note, $newContent, $userId) {
            $originalNoteId = $note->original_note_id ?? $note->id;

            // Create a new note record representing the updated version
            $updatedNote = ActivityNote::create([
                'organization_id' => $note->organization_id,
                'notable_type' => $note->notable_type,
                'notable_id' => $note->notable_id,
                'user_id' => $userId ?? auth()->id(),
                'content' => $newContent,
                'version' => $note->version + 1,
                'parent_id' => $note->parent_id,
                'original_note_id' => $originalNoteId,
            ]);

            // Soft delete the previous version so only latest is retrieved by default queries
            $note->delete();

            if ($note->notable_type === Activity::class) {
                $activity = Activity::find($note->notable_id);
                if ($activity && $activity->loggable_type && $activity->loggable_id) {
                    $this->eventBus->dispatch(new TimelineUpdated($activity->loggable_type, $activity->loggable_id, $note->organization_id));
                }
            } else {
                $this->eventBus->dispatch(new TimelineUpdated($note->notable_type, $note->notable_id, $note->organization_id));
            }

            return $updatedNote;
        });
    }

    public function deleteNote(ActivityNote $note): bool
    {
        return DB::transaction(function () use ($note) {
            $deleted = (bool) $note->delete();

            if ($note->notable_type === Activity::class) {
                $activity = Activity::find($note->notable_id);
                if ($activity && $activity->loggable_type && $activity->loggable_id) {
                    $this->eventBus->dispatch(new TimelineUpdated($activity->loggable_type, $activity->loggable_id, $note->organization_id));
                }
            } else {
                $this->eventBus->dispatch(new TimelineUpdated($note->notable_type, $note->notable_id, $note->organization_id));
            }

            return $deleted;
        });
    }

    /**
     * File Attachments integration.
     */
    public function addAttachment(Activity $activity, string $storedFileId, ?string $userId = null): ActivityAttachment
    {
        return DB::transaction(function () use ($activity, $storedFileId, $userId) {
            $attachment = ActivityAttachment::create([
                'organization_id' => $activity->organization_id,
                'activity_id' => $activity->id,
                'stored_file_id' => $storedFileId,
                'user_id' => $userId ?? auth()->id(),
            ]);

            $this->eventBus->dispatch(new AttachmentUploaded($attachment));

            if ($activity->loggable_type && $activity->loggable_id) {
                $this->eventBus->dispatch(new TimelineUpdated($activity->loggable_type, $activity->loggable_id, $activity->organization_id));
            }

            return $attachment;
        });
    }

    /**
     * Reminders setup.
     */
    public function addReminder(Activity $activity, string $title, Carbon $remindAt, string $method = 'in_app', ?string $description = null, ?array $recurringRules = null, ?string $userId = null): ActivityReminder
    {
        return ActivityReminder::create([
            'organization_id' => $activity->organization_id,
            'activity_id' => $activity->id,
            'user_id' => $userId ?? $activity->user_id ?? auth()->id(),
            'title' => $title,
            'description' => $description,
            'remind_at' => $remindAt,
            'method' => $method,
            'is_sent' => false,
            'recurring_rules' => $recurringRules,
        ]);
    }

    /**
     * Overdue Tasks Scanner
     */
    public function detectAndProcessOverdueTasks(string $organizationId): Collection
    {
        $overdue = $this->repository->getOverdueTasks($organizationId);

        foreach ($overdue as $task) {
            $this->eventBus->dispatch(new TaskOverdue($task));
        }

        return $overdue;
    }
}
