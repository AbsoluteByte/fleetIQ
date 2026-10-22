<?php

namespace App\Mail;

use App\Models\Car;
use App\Models\InsuranceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

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
        $senderName = is_array($this->sender) && filled($this->sender['name'] ?? null)
            ? (string) $this->sender['name']
            : null;

        if ($senderAddress !== '') {
            if ($this->useCustomFrom) {
                $message->from($senderAddress, $senderName);
            } elseif ($senderName !== null) {
                $message->from((string) config('mail.from.address'), $senderName);
            }
        }

        $replyTo = $this->replyToAddress($senderAddress, $senderName);
        if ($replyTo !== null) {
            $message->replyTo($replyTo['address'], $replyTo['name']);
        }

        $message->withSymfonyMessage(function (Email $email) use ($replyTo) {
            $email->getHeaders()->addTextHeader('X-MC-PreserveRecipients', 'true');

            if ($replyTo === null) {
                return;
            }

            $email->getHeaders()->remove('reply-to');
            $email->replyTo(new Address($replyTo['address'], $replyTo['name'] ?? ''));
        });

        return $message;
    }

    /**
     * @return array{address: string, name: ?string}|null
     */
    private function replyToAddress(string $senderAddress, ?string $senderName): ?array
    {
        $company = $this->car->company;
        $companyEmail = trim((string) ($company?->email ?? ''));

        if ($companyEmail !== '' && filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
            $companyName = trim((string) ($company?->name ?? ''));

            return [
                'address' => $companyEmail,
                'name' => $companyName !== '' ? $companyName : null,
            ];
        }

        if ($senderAddress === '' || filter_var($senderAddress, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return [
            'address' => $senderAddress,
            'name' => $senderName,
        ];
    }
}
