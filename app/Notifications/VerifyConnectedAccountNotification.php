<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Models\ConnectedAccount;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyConnectedAccountNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $connected_account_id
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
        $connected_account = ConnectedAccount::find($this->connected_account_id);
        $provider = config("services.$connected_account->provider.name");

        $url = url()->signedRoute('oauth.verify_connected_account', ['connected_account' => $this->connected_account_id], now()->days($days = 7));

        return (new MailMessage)
                    ->greeting('Welcome back!')
                    ->subject("Connect your $provider account with Investbrain")
                    ->line("You recently attempted to log into an existing Investbrain account using $provider. To safeguard your Investbrain account, please confirm this was you by pressing the 'Connect $provider' button below:")
                    ->action("Connect $provider", $url)
                    ->line("If you do not recognize this activity, we recommend [changing your password](".route('profile.show').") as soon as possible. Otherwise, you can disregard this message. This link will expire in {$days} days.");
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
