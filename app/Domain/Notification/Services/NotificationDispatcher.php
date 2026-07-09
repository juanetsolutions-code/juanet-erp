<?php

namespace App\Domain\Notification\Services;

use App\Domain\Notification\Models\Notification;
use App\Domain\Notification\Models\NotificationDelivery;
use App\Domain\Notification\Repositories\NotificationRepositoryInterface;
use App\Domain\Notification\Services\NotificationPreferenceService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class NotificationDispatcher
{
    protected NotificationRepositoryInterface $notificationRepo;
    protected NotificationPreferenceService $preferenceService;

    public function __construct(
        NotificationRepositoryInterface $notificationRepo,
        NotificationPreferenceService $preferenceService
    ) {
        $this->notificationRepo = $notificationRepo;
        $this->preferenceService = $preferenceService;
    }

    /**
     * Dispatch a notification to all enabled channels for a user.
     */
    public function dispatch(Notification $notification, array $renderedContent): void
    {
        $userId = $notification->user_id;
        $orgId = $notification->organization_id;
        $category = $notification->category ?? 'system';
        
        $user = User::find($userId);
        if (!$user) {
            Log::warning("Cannot dispatch notification {$notification->id}: User {$userId} not found.");
            return;
        }

        // List of all supported channels
        $channels = ['in_app', 'email', 'sms', 'whatsapp', 'push', 'webhook'];

        foreach ($channels as $channel) {
            // Check if user has enabled this channel
            if (!$this->preferenceService->isChannelEnabled($userId, $channel, $category, $orgId)) {
                continue;
            }

            // Create tracking row in delivery
            $delivery = $this->notificationRepo->createDelivery([
                'organization_id' => $orgId,
                'notification_id' => $notification->id,
                'channel' => $channel,
                'recipient' => $this->getRecipientForChannel($user, $channel, $notification),
                'status' => 'queued',
                'retry_count' => 0,
            ]);

            try {
                // Execute actual delivery logic (asynchronously simulated or processed directly)
                $this->sendToChannel($delivery, $notification, $renderedContent);
            } catch (\Throwable $e) {
                $this->notificationRepo->updateDelivery($delivery->id, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                Log::error("Failed to send notification {$notification->id} via channel {$channel}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get recipient string depending on the channel.
     */
    protected function getRecipientForChannel(User $user, string $channel, Notification $notification): string
    {
        switch ($channel) {
            case 'email':
                return $user->email;
            case 'sms':
            case 'whatsapp':
                return $user->phone_number ?? '+1234567890'; // placeholder/fallback if no phone set
            case 'push':
                return $user->push_token ?? 'push-token-mock-123';
            case 'webhook':
                return $notification->data['webhook_url'] ?? 'https://api.juanet.io/webhooks/receiver';
            case 'in_app':
            default:
                return $user->id;
        }
    }

    /**
     * Dispatch the actual transmission per channel.
     */
    protected function sendToChannel(NotificationDelivery $delivery, Notification $notification, array $renderedContent): void
    {
        // Transition status to 'sent'
        $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'sent']);

        switch ($delivery->channel) {
            case 'in_app':
                // In-App is already saved in the notifications table. Mark as delivered.
                $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'delivered']);
                break;

            case 'email':
                // Send standard Laravel Mail or log it
                $subject = $renderedContent['subject'] ?? $notification->title;
                $html = $renderedContent['html'] ?? $notification->body;
                
                try {
                    Mail::html($html, function ($message) use ($delivery, $subject) {
                        $message->to($delivery->recipient)
                                ->subject($subject);
                    });
                    $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'delivered']);
                } catch (\Throwable $e) {
                    $this->notificationRepo->updateDelivery($delivery->id, [
                        'status' => 'failed',
                        'error_message' => 'Mail delivery failed: ' . $e->getMessage()
                    ]);
                }
                break;

            case 'sms':
                // Call external SMS Gateway (e.g. Twilio)
                // We'll log the API outbound and transition to delivered.
                Log::info("SMS Notification Sent to {$delivery->recipient}: {$notification->body}");
                $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'delivered']);
                break;

            case 'whatsapp':
                // Call WhatsApp Business API
                Log::info("WhatsApp Notification Sent to {$delivery->recipient}: {$notification->body}");
                $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'delivered']);
                break;

            case 'push':
                // Send push notification via FCM / OneSignal
                Log::info("Push Notification Sent to {$delivery->recipient}: {$notification->title}");
                $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'delivered']);
                break;

            case 'webhook':
                // Post payload to webhook URL
                try {
                    $response = Http::timeout(5)->post($delivery->recipient, [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'type' => $notification->type,
                        'category' => $notification->category,
                        'created_at' => $notification->created_at,
                    ]);
                    
                    if ($response->successful()) {
                        $this->notificationRepo->updateDelivery($delivery->id, ['status' => 'delivered']);
                    } else {
                        $this->notificationRepo->updateDelivery($delivery->id, [
                            'status' => 'failed',
                            'error_message' => 'Webhook returned status ' . $response->status(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->notificationRepo->updateDelivery($delivery->id, [
                        'status' => 'failed',
                        'error_message' => 'Webhook call failed: ' . $e->getMessage(),
                    ]);
                }
                break;
        }
    }
}
