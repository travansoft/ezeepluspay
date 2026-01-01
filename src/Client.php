<?php

namespace Travansoft\EzeePlusPay;

use Travansoft\EzeePlusPay\Exceptions\EzeeplusPayException;

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
        $this->baseUrl = rtrim($config['base_url'], '/');
    }
 

    /**
     * Create a payment order
     */
    public function createPayment(array $params): array
    {
        // merchant_tid required + non-empty string
        if (empty($params['merchant_tid'])) {
            throw new EzeeplusPayException("merchant_tid is required and cannot be empty.");
        }

        // amount required + numeric + greater than 0
        if (!isset($params['amount']) || !is_numeric($params['amount']) || $params['amount'] <= 0) {
            throw new EzeeplusPayException("amount is required and must be a number greater than 0.");
        }

        // callback_url required + valid URL
        if (empty($params['callback_url']) || !filter_var($params['callback_url'], FILTER_VALIDATE_URL)) {
            throw new EzeeplusPayException("callback_url is required and must be a valid URL.");
        }

        // metadata optional → must be array
        if (isset($params['metadata']) && !is_array($params['metadata'])) {
            throw new EzeeplusPayException("metadata must be an array.");
        }

        // --- BUILD PAYLOAD ---
        $payload = [
            'merchant_tid' => $params['merchant_tid'],
            'amount'       => (float)$params['amount'],
            'callback_url' => $params['callback_url'],
            'metadata'     => $params['metadata'] ?? [],
        ];

        return $this->post('/api/transaction/create', $payload);
  
    }

    /**
     * Auto-handles callback from $_POST
     * Returns CallbackResponse object
     */
    public function processPaymentCallback(): CallbackResponse
    {
        // Prefer POST (server callback)
        if (!empty($_POST['signed_payload']) && !empty($_POST['signature'])) {
            return $this->handleCallback(
                $_POST['signed_payload'],
                $_POST['signature']
            );
        }

        // Fallback to GET (browser redirect)
        if (!empty($_GET['signed_payload']) && !empty($_GET['signature'])) {
            return $this->handleCallback(
                $_GET['signed_payload'],
                $_GET['signature']
            );
        }

        return new CallbackResponse(false, null, 'Missing callback parameters');
    }


    /**
     * -------------------------------------------------------------
     * WEBHOOK PROCESSING (JSON BODY WEBHOOKS)
     * -------------------------------------------------------------
     * Reads php://input → validates → returns CallbackResponse
     */
    public function processWebhook(): CallbackResponse
    {
        $rawBody = file_get_contents('php://input');

        if (!$rawBody) {
            return new CallbackResponse(false, null, 'Webhook payload is empty');
        }

        $json = json_decode($rawBody, true);

        if (!is_array($json)) {
            return new CallbackResponse(false, null, 'Invalid webhook JSON');
        }
        if (empty($json['signed_payload']) || empty($json['signature'])) {
            return new CallbackResponse(false, null, 'Missing webhook parameters');
        }
        $receivedSignature = $json['signature'];
        if (!$receivedSignature) {
            return new CallbackResponse(false, null, 'Missing signature');
        }

        $signedPayload = $json['signed_payload'];

        // Compute expected HMAC signature
        $expectedSignature = hash_hmac('sha256', $signedPayload, $this->secret);

        // Compare signatures
        if (!hash_equals($expectedSignature, $receivedSignature)) {
            return new CallbackResponse(false, null, 'Invalid signature');
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

    /**
     * Fetch transaction status (Offline / Forced PSP reconciliation)
     *
     * @param string $aggregatorTid
     * @param bool   $force  If true, forces PSP API call
     *
     * @return array
     */
    public function fetchTransactionStatus(string $aggregatorTid, bool $force = false): array
    {
        if (empty($aggregatorTid)) {
            throw new EzeeplusPayException("aggregator_tid is required.");
        }

        $query = $force ? '?force=true' : '';
        $url   = $this->baseUrl . '/api/transaction/status/' . urlencode($aggregatorTid) . $query;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-API-KEY: ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new EzeeplusPayException("cURL error: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 500) {
            throw new EzeeplusPayException("EzeePlusPay server error: HTTP {$status}");
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new EzeeplusPayException("Invalid JSON response from status API.");
        }

        return $decoded;
    }


    private function decodeSignedPayloadInternal(string $signedPayload): array
    {
        $json = base64_decode($signedPayload, true);

        if ($json === false) {
            throw new EzeeplusPayException('Invalid signed payload (Base64 decode failed).');
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw new EzeeplusPayException('Invalid signed payload (JSON decode failed).');
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
            throw new EzeeplusPayException("cURL error: {$error}");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status >= 500) {
            throw new EzeeplusPayException("EzeePlusPay server error: HTTP {$status}");
        }

        $decoded = json_decode($response, true);

        if ($decoded === null) {
            throw new EzeeplusPayException("Invalid JSON response.");
        }

        return $decoded;
    }
}
