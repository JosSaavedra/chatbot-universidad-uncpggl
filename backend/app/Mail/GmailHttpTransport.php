<?php

namespace App\Mail;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

final class GmailHttpTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new TransportException('OAuth de Gmail no configurado.');
        }

        $oauth = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(8)
            ->withOptions(['allow_redirects' => false])
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ]);

        $accessToken = $oauth->json('access_token');
        if (! $oauth->successful() || ! is_string($accessToken) || $accessToken === '') {
            throw new TransportException('Gmail no autorizó el envío.');
        }

        $raw = rtrim(strtr(base64_encode($message->getMessage()->toString()), '+/', '-_'), '=');
        $response = Http::withToken($accessToken)->acceptJson()->connectTimeout(3)->timeout(8)
            ->withOptions(['allow_redirects' => false])
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', ['raw' => $raw]);

        if (! $response->successful() || ! is_string($response->json('id'))) {
            throw new TransportException('Gmail rechazó el mensaje.');
        }
    }

    public function __toString(): string
    {
        return 'gmail-https';
    }
}
