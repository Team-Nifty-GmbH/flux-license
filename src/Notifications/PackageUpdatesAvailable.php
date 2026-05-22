<?php

namespace TeamNiftyGmbH\FluxLicense\Notifications;

use FluxErp\Contracts\HasToastNotification;
use FluxErp\Notifications\Notification;
use FluxErp\Support\Notification\ToastNotification\NotificationAction;
use FluxErp\Support\Notification\ToastNotification\ToastNotification;
use FluxErp\Traits\Makeable;
use Illuminate\Bus\Queueable;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use NotificationChannels\WebPush\WebPushMessage;

class PackageUpdatesAvailable extends Notification implements HasToastNotification
{
    use Makeable, Queueable;

    public function __construct(public array $updates) {}

    public function toArray(object $notifiable): array
    {
        return $this->toToastNotification($notifiable)->toArray();
    }

    public function toToastNotification(object $notifiable): ToastNotification
    {
        $description = collect($this->updates)
            ->map(fn (array $u): string => sprintf('%s: %s → %s', $u['name'], $u['current'], $u['available']))
            ->implode('<br>');

        return ToastNotification::make()
            ->notifiable($notifiable)
            ->title(__(':count package update(s) available', ['count' => count($this->updates)]))
            ->description($description)
            ->persistent()
            ->accept(
                NotificationAction::make()
                    ->label(__('Manage plugins'))
                    ->url(route('settings', ['setting-entry' => 'settings.plugins']))
            );
    }

    public function toWebPush(object $notifiable): ?WebPushMessage
    {
        if (! method_exists($notifiable, 'pushSubscriptions') || ! $notifiable->pushSubscriptions()->exists()) {
            return null;
        }

        return $this->toToastNotification($notifiable)->toWebPush();
    }

    public function toFcm(object $notifiable): ?FcmNotification
    {
        return $this->toToastNotification($notifiable)->toFcm();
    }

    public function toFcmData(object $notifiable): array
    {
        return $this->toToastNotification($notifiable)->toFcmData();
    }
}
