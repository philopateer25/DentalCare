<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Notifications\TestPushNotification;
use Filament\Notifications\Notification;

class TestPushNotificationWidget extends Widget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string $view = 'filament.widgets.test-push-notification-widget';
    protected int | string | array $columnSpan = 'full';

    public function testPushAction(): Action
    {
        return Action::make('testPushAction')
            ->label('🚀 Send Test Notification')
            ->color('primary')
            ->action(function () {
                $user = auth()->user();
                
                if ($user->pushSubscriptions()->count() === 0) {
                    Notification::make()
                        ->title('No Devices Found')
                        ->body('You have not subscribed to push notifications on any device yet.')
                        ->danger()
                        ->send();
                    return;
                }

                $user->notify(new TestPushNotification());

                Notification::make()
                    ->title('Push Sent!')
                    ->body('The notification has been dispatched to your devices.')
                    ->success()
                    ->send();
            });
    }
}
