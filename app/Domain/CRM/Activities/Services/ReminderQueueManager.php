<?php

namespace App\Domain\CRM\Activities\Services;

use App\Domain\CRM\Activities\Models\ActivityReminder;
use App\Domain\CRM\Activities\Events\ReminderTriggered;
use App\Domain\CRM\Activities\Notifications\ReminderNotification;
use App\Contracts\EventBus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReminderQueueManager
{
    protected EventBus $eventBus;

    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Process pending reminders.
     */
    public function processReminders(): Collection
    {
        return DB::transaction(function () {
            $now = Carbon::now();
            $reminders = ActivityReminder::where('is_sent', false)
                ->where('remind_at', '<=', $now)
                ->with(['user', 'activity'])
                ->get();

            foreach ($reminders as $reminder) {
                try {
                    $reminder->update([
                        'is_sent' => true,
                        'sent_at' => $now
                    ]);

                    // Dispatch transactional event via EventBus
                    $this->eventBus->dispatch(new ReminderTriggered($reminder));

                    // Send notifications based on method
                    $user = $reminder->user;
                    if ($user) {
                        if ($reminder->method === 'email' || $reminder->method === 'in_app') {
                            $user->notify(new ReminderNotification($reminder));
                        }
                        
                        // Future SMS abstraction trigger
                        if ($reminder->method === 'sms') {
                            Log::info("SMS Reminder abstract triggered for User {$user->id}: {$reminder->title}");
                        }
                    }

                    // Handle recurring reminder logic
                    if ($reminder->recurring_rules) {
                        $this->scheduleNextOccurrence($reminder);
                    }

                } catch (\Exception $e) {
                    Log::error("Failed to process reminder {$reminder->id}: " . $e->getMessage());
                }
            }

            return $reminders;
        });
    }

    protected function scheduleNextOccurrence(ActivityReminder $reminder): ?ActivityReminder
    {
        $rules = $reminder->recurring_rules;
        $frequency = $rules['frequency'] ?? null; // daily, weekly, monthly
        $interval = (int) ($rules['interval'] ?? 1);

        if (!$frequency) {
            return null;
        }

        $nextRemindAt = Carbon::parse($reminder->remind_at);

        switch ($frequency) {
            case 'daily':
                $nextRemindAt->addDays($interval);
                break;
            case 'weekly':
                $nextRemindAt->addWeeks($interval);
                break;
            case 'monthly':
                $nextRemindAt->addMonths($interval);
                break;
            default:
                return null;
        }

        return ActivityReminder::create([
            'organization_id' => $reminder->organization_id,
            'activity_id' => $reminder->activity_id,
            'user_id' => $reminder->user_id,
            'title' => $reminder->title,
            'description' => $reminder->description,
            'remind_at' => $nextRemindAt,
            'method' => $reminder->method,
            'is_sent' => false,
            'recurring_rules' => $rules,
        ]);
    }
}
