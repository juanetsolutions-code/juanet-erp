<?php

namespace App\Notifications;

use App\Models\User;
use App\Repositories\NotificationRepositoryInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification as LaravelNotification;

class EnterpriseNotification extends LaravelNotification implements ShouldQueue
{
    use Queueable;

    public string $title;
    public string $body;
    public string $type;
    public string $category;
    public string $priority;
    public ?string $organizationId;
    public array $extraData;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        string $title,
        string $body,
        string $type = 'info',
        string $category = 'system',
        string $priority = 'normal',
        ?string $organizationId = null,
        array $extraData = []
    ) {
        $this->title = $title;
        $this->body = $body;
        $this->type = $type;
        $this->category = $category;
        $this->priority = $priority;
        $this->organizationId = $organizationId;
        $this->extraData = $extraData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (!$notifiable instanceof User) {
            return ['database'];
        }

        $repository = app(NotificationRepositoryInterface::class);
        $prefs = $repository->getPreferences($notifiable->id, $this->organizationId);

        // Check if this category is enabled by the user
        $categoryEnabled = $prefs->categories[$this->category] ?? true;
        if (!$categoryEnabled) {
            return [];
        }

        $channels = [];

        // Check channel preferences
        if ($prefs->channels['database'] ?? true) {
            $channels[] = 'database';
        }

        if ($prefs->channels['email'] ?? true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $priorityPrefix = '';
        if ($this->priority === 'urgent' || $this->priority === 'high') {
            $priorityPrefix = '[' . strtoupper($this->priority) . '] ';
        }

        $mail = (new MailMessage)
            ->subject($priorityPrefix . $this->title)
            ->line($this->body);

        if (isset($this->extraData['action_url'])) {
            $mail->action($this->extraData['action_text'] ?? 'View Action', $this->extraData['action_url']);
        }

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     * This will be stored in our custom table by our NotificationService or standard system.
     * Note: Laravel's standard 'database' driver stores in a 'notifications' table using custom JSON schema.
     * But we want to store it in our highly-isolated enterprise custom 'notifications' table.
     * To do this perfectly, we will write custom storage logic in our NotificationService,
     * or we can let 'toDatabase' store a payload, but since we created a custom Notification model,
     * it is cleaner to let our NotificationService handle sending & saving, or customize the database channel!
     * Let's define `toDatabase` returning the clean attributes.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'organization_id' => $this->organizationId,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'category' => $this->category,
            'priority' => $this->priority,
            'data' => $this->extraData,
        ];
    }
}
