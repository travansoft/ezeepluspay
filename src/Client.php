<?php

namespace Travansoft\EzeePlusPay;

use Travansoft\EzeePlusPay\Exceptions\EzeePlusPayException;

class Client
{
    /** @var string */
    private $apiKey;

    /** @var string */
    private $secret;

    /** @var string */
    private $baseUrl;

    /**
     * PHP 7 compatible constructor
     *
     * @param array $config
     */


    public function __construct(array $config = [])
    {
        if (!isset($config['api_key']) || !isset($config['secret']) || !isset($config['base_url'])) {
            throw new \InvalidArgumentException("api_key and secret are required.");
        }

        $this->apiKey  = $config['api_key'];
        $this->secret  = $config['secret'];
        $this->baseUrl = $config['base_url'];
    }



    /**
     * Create a payment order
     */
    public function createPayment(string $merchantTid, float $amount, string $callbackUrl, array $metadata = []): array
    {
        $payload = [
            'merchant_tid' => $merchantTid,
            'amount'       => $amount,
            'callback_url' => $callbackUrl,
            'metadata'     => $metadata,
        ];

        return $this->post('/api/transaction/create', $payload);
    }

    /**
     * Auto-handles callback from $_POST
     * Returns CallbackResponse object
     */
    public function processPaymentCallback(): CallbackResponse
    {
        if (empty($_POST['signed_payload']) || empty($_POST['signature'])) {
            return new CallbackResponse(false, null, 'Missing callback parameters');
        }

        return $this->handleCallback($_POST['signed_payload'], $_POST['signature']);
    }

    /**
     * -------------------------------------------------------------
     * WEBHOOK PROCESSING (JSON BODY WEBHOOKS)
     * -------------------------------------------------------------
     * Reads php://input → validates → returns CallbackResponse
     */
    public function processWebhook(): CallbackResponse
    {
        $raw = file_get_contents('php://input');

        if (!$raw) {
            return new CallbackResponse(false, null, 'Webhook payload is empty');
        }

        $json = json_decode($raw, true);

        if (!is_array($json)) {
            return new CallbackResponse(false, null, 'Invalid webhook JSON');
        }

        if (!isset($json['signed_payload']) || !isset($json['signature'])) {
            return new CallbackResponse(false, null, 'Webhook missing signed_payload or signature');
        }

        return $this->handleCallback(
            $json['signed_payload'],
            $json['signature']
        );
    }

    /**
     * Manual handling of callback
     */
    public function handleCallback(string $signedPayload, string $receivedSignature): CallbackResponse
    {
        $expected = $this->generateSignature($signedPayload);

        if (!hash_equals($expected, $receivedSignature)) {
            return new CallbackResponse(false, null, 'Invalid signature');
        }

        try {
            $decoded = $this->decodeSignedPayloadInternal($signedPayload);
        } catch (\Exception $e) {
            return new CallbackResponse(false, null, $e->getMessage());
        }

        return new CallbackResponse(true, $decoded, null);
    }

    private function decodeSignedPayloadInternal(string $signedPayload): array
    {
        $json = base64_decode($signedPayload, true);

        if ($json === false) {
            throw new EzeePlusPayException('Invalid signed payload (Base64 decode failed).');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new EzeePlusPayException('Invalid signed payload (JSON decode failed).');
        }

        return $data;
    }

    private function generateSignature(string $signedPayload): string
    {
        return hash_hmac('sha256', $signedPayload, $this->secret);
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
                'X-API-KEY: ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new EzeePlusPayException("cURL error: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 500) {
            throw new EzeePlusPayException("EzeePlusPay server error: HTTP {$status}");
        }

        $decoded = json_decode($response, true);

        if ($decoded === null) {
            throw new EzeePlusPayException("Invalid JSON response.");
        }

        return $decoded;
    }
}
