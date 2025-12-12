# EzeePlusPay PHP SDK

### Lightweight, Framework-Independent PHP Library (PHP 7 Compatible)

A simple PHP SDK to integrate **EzeePlusPay PSP Aggregator Gateway** into **any
PHP 7+ project**.\
Works with plain PHP + Composer.

------------------------------------------------------------------------

## 📦 Installation

### Install via Composer

``` bash
composer require travansoft/ezeepluspay
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
    'api_key' => 'YOUR_API_KEY',
    'secret'     => 'YOUR_SECRET_KEY',
    'base_url'    => 'https://secure.example.in/'
]);
```

------------------------------------------------------------------------

# 💰 Create a Payment Request

``` php
$merchantTransactionId$ = "ORD" . time();

$response = $client->createPayment([
    'merchant_tid'   => $merchantTransactionId,
    'amount'     => 49900,    
    'callback_url' => "https://yourdomain.com/payment/redirect.php",
    'metadata'   => [
        'customer'   => 'John Doe',
        'mobile' => '9876543210',
        'email'  => 'john@example.com'
    ]
]);

echo "Redirect user to: " . $response['payment_url'];
```

------------------------------------------------------------------------

# 🌐 Redirect the User

``` php
header("Location: " . $response['payment_url']);
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

$result = $client->processWebhook();

if (!$result->isValid()) {
    http_response_code(400);
    exit("Callback Error: " . $result->getError());
}

$data = $result->getData();
$merchantTransactionID = $data['merchant_tid'];
$status  = $data['status'];
```

------------------------------------------------------------------------
   
# 📞 Support

For API credentials or support, contact EzeePlusPay.

------------------------------------------------------------------------

# 📝 License

MIT License
