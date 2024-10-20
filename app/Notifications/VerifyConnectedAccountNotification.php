<?php

namespace App\Notifications;

use App\Models\ConnectedAccountVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyConnectedAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $verification_id
    ) { }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $verification = ConnectedAccountVerification::find($this->verification_id);
        $provider = config("services.$verification->provider.name");

        return (new MailMessage)
                    ->greeting('Welcome back!')
                    ->subject("Connect your $provider account with Investbrain")
                    ->line("You recently attempted to log into an existing Investbrain account using $provider. To safeguard your Investbrain account, please confirm this was you by pressing the 'Connect $provider' button below:")
                    ->action("Connect $provider", route('verify_connected_account', ['verification_id' => $this->verification_id]))
                    ->line('If you do not recognize this activity, we recommend [changing your password]('.route('profile.show').') as soon as possible. Otherwise, you can disregard this message.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
