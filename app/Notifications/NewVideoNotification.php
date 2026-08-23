<?php

namespace App\Notifications;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class NewVideoNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Video $video
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
            'video_id' => $this->video->id,
            'title' => $this->video->title,
            'slug' => $this->video->slug,
            'thumbnail_url' => $this->video->thumbnail_url,
//            'uploaded_by_user_id' => $this->video->user_id,
            'message' => 'A new video was added: '.$this->video->title,
        ];
    }
}
