<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  list<array{sheet: string|null, row: int|null, column: string|null, value: string|null, message: string}>  $errors
     */
    public function __construct(
        public string $errorMessage,
        public array $errors = [],
        public int $errorCount = 0,
    ) {}

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
        $mail = (new MailMessage)
            ->greeting('Oh no!')
            ->subject('Your Investbrain import failed!')
            ->line('Heads up, your Investbrain import was unable to successfully complete. Nothing was imported, so you can fix the problems below and upload the file again.')
            ->action('Try again?', route('import-export'));

        if (empty($this->errors)) {

            return $mail->line($this->errorMessage);
        }

        $mail->line('**'.$this->errorMessage.'**');

        foreach ($this->errors as $error) {
            $mail->line(self::describe($error));
        }

        if ($this->errorCount > count($this->errors)) {
            $mail->line('...and '.($this->errorCount - count($this->errors)).' more. The full list is on the import page.');
        }

        return $mail;
    }

    /**
     * Describe where a problem is, e.g. "Transactions row 4 (Quantity): ..."
     *
     * @param  array{sheet: string|null, row: int|null, column: string|null, value: string|null, message: string}  $error
     */
    public static function describe(array $error): string
    {
        $location = trim(implode(' ', array_filter([
            $error['sheet'] ?? null,
            isset($error['row']) ? 'row '.$error['row'] : null,
            isset($error['column']) ? '('.$error['column'].')' : null,
        ])));

        return ($location ? $location.': ' : '').$error['message'];
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
