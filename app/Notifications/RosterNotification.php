<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * One notification shape for everything Roster tells people: a title, one sentence, and where to act.
 * Stored for the in-app bell and sent by email; web push joins in once the person subscribes.
 */
class RosterNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $kind,
        public string $title,
        public string $body,
        public string $url,
        public int $companyId,
        public bool $mail = true,
        /** @var array<string, mixed> */
        public array $extra = [],
    ) {}

    /** @return list<string|class-string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($this->mail && filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }
        if (class_exists(WebPushChannel::class) && method_exists($notifiable, 'pushSubscriptions') && $notifiable->pushSubscriptions()->exists()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'title' => $this->title, 'body' => $this->body, 'url' => $this->url, 'company_id' => $this->companyId, ...$this->extra];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->line($this->body)->action('Open Roster', url($this->url))
            ->line('You get this email because of your role in Roster.');
    }

    public function toWebPush(object $notifiable, mixed $notification): WebPushMessage
    {
        return (new WebPushMessage)->title($this->title)->body($this->body)->icon('/icons/icon-192.png')
            ->data(['url' => $this->url])->tag($this->kind);
    }
}
