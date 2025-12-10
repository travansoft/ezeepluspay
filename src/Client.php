<?php

namespace Travansoft\EzeePlusPay;

use Travansoft\EzeePlusPay\Exceptions\EzeePlusPayException;

class Client
{
    private $apiKey;
    private $secret;
    private $baseUrl;

    public function __construct(string $apiKey, string $secret, string $baseUrl = 'https://api.ezeepluspay.com')
    {
        $this->apiKey = $apiKey;
        $this->secret = $secret;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function createPayment(string $merchantTid, float $amount, string $callbackUrl, array $metadata = []): array
    {
        $payload = [
            'merchant_tid' => $merchantTid,
            'amount'        => $amount,
            'callback_url'  => $callbackUrl,
            'metadata'      => $metadata
        ];

        return $this->post('/api/transaction/create', $payload);
    }

    public function validateCallback(string $signedPayload, string $receivedSignature): bool
    {
        $expected = hash_hmac('sha256', $signedPayload, $this->secret);
        return hash_equals($expected, $receivedSignature);
    }

    public function decodeSignedPayload(string $signedPayload): array
    {
        return json_decode(base64_decode($signedPayload), true);
    }

    private function post(string $path, array $payload): array
    {
        $url = $this->baseUrl . $path;

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new EzeePlusPayException(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true) ?: [];
    }
}
