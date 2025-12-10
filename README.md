# EzeePlusPay PHP SDK

### Lightweight, Framework-Independent PHP Library (PHP 7 Compatible)

A simple PHP SDK to integrate **EzeePlusPay Payment Gateway** into **any
PHP 7+ project**.\
No Laravel dependency. Works with plain PHP + Composer.

------------------------------------------------------------------------

## 📦 Installation

### Install via Composer

``` bash
composer require ezeepluspay/sdk
```

------------------------------------------------------------------------

## 🛠 Requirements

  Component    Version
  ------------ --------------
  PHP          **7.0+**
  Extensions   cURL, JSON
  Server       Apache/Nginx

------------------------------------------------------------------------

# 🚀 Quick Start

## 1. Initialize Client

``` php
<?php
require 'vendor/autoload.php';

use Travansoft\EzeePlusPay\Client;

$client = new Client([
    'api_key' => 'YOUR_MERCHANT_ID',
    'secret'     => 'YOUR_SECRET_KEY',
    'base_url'    => 'https://secure.ezeepluspay.in/api/'
]);
```

------------------------------------------------------------------------

# 💰 Create a Payment Request

``` php
$orderId = "ORD" . time();

$payment = $client->createPayment([
    'order_id'   => $orderId,
    'amount'     => 49900,
    'currency'   => 'INR',
    'customer'   => [
        'name'   => 'John Doe',
        'mobile' => '9876543210',
        'email'  => 'john@example.com'
    ],
    'redirect_url' => "https://yourdomain.com/payment/redirect.php"
]);

echo "Redirect user to: " . $payment['payment_url'];
```

------------------------------------------------------------------------

# 🌐 Redirect the User

``` php
header("Location: " . $payment['payment_url']);
exit;
```

------------------------------------------------------------------------

# 🔄 Handling Redirect Response Auto mode (recommended):

``` php
 
$result = $client->processPaymentCallback();

if (!$result->isValid()) {
    http_response_code(400);
    exit("Callback Error: " . $result->getError());
}

$data = $result->getData();

```
# 🔄 Handling Redirect Response Manual mode (if merchant wants control):
``` php

$result = $client->handleCallback($signedPayload, $signature);

if ($result->isValid()) {
    $data = $result->getData();
} else {
    echo $result->getError();
}
```

------------------------------------------------------------------------

# 📬 Server-to-Server Callback (Webhook)

``` php
<?php
require 'vendor/autoload.php';

use EzeePlusPay\Client;

$client = new Client([
    'merchant_id' => 'YOUR_MERCHANT_ID',
    'api_key'     => 'YOUR_SECRET_KEY'
]);

$payload = json_decode(file_get_contents("php://input"), true);

if (!$client->verifySignature($payload)) {
    http_response_code(400);
    echo "Invalid Signature";
    exit;
}

$orderId = $payload['order_id'];
$status  = $payload['status'];

http_response_code(200);
echo "OK";
```

------------------------------------------------------------------------

# 🔐 Signature Logic

``` php
public function generateSignature(array $data)
{
    ksort($data);
    $query = urldecode(http_build_query($data));
    return base64_encode(hash_hmac('sha256', $query, $this->api_key, true));
}
```

------------------------------------------------------------------------

# ✔ Signature Verification

``` php
if ($client->verifySignature($_GET)) {
    echo "Signature Verified";
} else {
    echo "Signature Invalid";
}
```

------------------------------------------------------------------------

# 🧪 Demo Project

A complete demo is included in:

    /demo

------------------------------------------------------------------------

# 📁 Example Folder Structure

    project/
    │── vendor/
    │── demo/
    │   ├── index.php
    │   ├── redirect.php
    │   ├── callback.php
    │── composer.json
    │── README.md

------------------------------------------------------------------------

# 📞 Support

For API credentials or support, contact EzeePlusPay.

------------------------------------------------------------------------

# 📝 License

MIT License
