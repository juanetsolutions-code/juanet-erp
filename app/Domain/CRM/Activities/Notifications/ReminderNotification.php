<?php

namespace App\Domain\CRM\Activities\Notifications;

use App\Domain\CRM\Activities\Models\ActivityReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ActivityReminder $reminder) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->reminder->method === 'email') {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("CRM Reminder: {$this->reminder->title}")
            ->line("This is a reminder for your upcoming CRM activity.")
            ->line("**Title:** {$this->reminder->title}")
            ->line("**Description:** {$this->reminder->description}")
            ->line("**Remind Time:** {$this->reminder->remind_at->toDayDateTimeString()}")
            ->action('View Activity', url("/crm/activities/" . ($this->reminder->activity_id ?? '')))
            ->line('Thank you for using JUANET Enterprise Platform!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'reminder_id' => $this->reminder->id,
            'activity_id' => $this->reminder->activity_id,
            'title' => $this->reminder->title,
            'description' => $this->reminder->description,
            'remind_at' => $this->reminder->remind_at->toIso8601String(),
        ];
    }
}
