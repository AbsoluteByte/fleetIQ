<?php

namespace App\Mail;

use App\Models\Car;
use App\Models\InsuranceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CarInsuranceFleetChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Car $car,
        public InsuranceProvider $provider,
        public string $action,
        public string $subjectLine,
        public string $bodyText,
        public ?array $sender = null,
        public bool $useCustomFrom = false,
    ) {}

    public function build()
    {
        $message = $this->subject($this->subjectLine)
            ->view('emails.car-insurance-fleet-change')
            ->with([
                'bodyText' => $this->bodyText,
                'car' => $this->car,
                'provider' => $this->provider,
                'action' => $this->action,
            ]);

        $senderAddress = is_array($this->sender) ? trim((string) ($this->sender['address'] ?? '')) : '';

        if ($senderAddress !== '') {
            $name = filled($this->sender['name'] ?? null) ? (string) $this->sender['name'] : null;

            $message->replyTo($senderAddress, $name);

            if ($this->useCustomFrom) {
                $message->from($senderAddress, $name);
            } elseif ($name !== null) {
                $message->from((string) config('mail.from.address'), $name);
            }
        }

        $this->addConfiguredReplyTo($message, $senderAddress);

        return $message;
    }

    private function addConfiguredReplyTo(self $message, string $senderAddress): void
    {
        $replyTo = trim((string) config('mail.reply_to.address'));

        if ($replyTo === '' || filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        if ($senderAddress !== '' && strcasecmp($replyTo, $senderAddress) === 0) {
            return;
        }

        $message->replyTo($replyTo);
    }
}
