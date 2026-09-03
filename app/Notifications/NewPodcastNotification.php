<?php

namespace App\Notifications;

use App\Models\Podcast;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewPodcastNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Podcast $podcast
    ) {
    }

    public function via($notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast($notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function toArray($notifiable): array
    {
        return $this->payload();
    }

    private function payload(): array
    {
        return [
            'podcast_id' => $this->podcast->id,
            'title' => $this->podcast->title,
            'slug' => $this->podcast->slug,
            'cover_image_url' => $this->podcast->cover_image_url,
            'audio_url' => $this->podcast->audio_url,
            'message' => 'A new podcast was added: '.$this->podcast->title,
        ];
    }
}
