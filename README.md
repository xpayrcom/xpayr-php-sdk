# XPayr PHP SDK

[![CI](https://github.com/xpayrcom/xpayr-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/xpayrcom/xpayr-php-sdk/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-0f766e.svg)](LICENSE)

Official PHP SDK for XPayr payment sessions, merchant operations, webhook verification, and server-side crypto checkout integrations.

> **Status:** Developer preview · v0.1

## Purpose

A PHP 8.1+ client for XPayr Merchant API v1 with cURL transport, structured exceptions, and secure webhook verification.

## Included

- Payment, merchant, network, and webhook API methods
- PSR-4 package layout and Composer metadata
- Constant-time HMAC-SHA256 webhook verification

## Quick start

```bash
composer install
composer test
```

Install the published package:

```bash
composer require xpayr/xpayr-php:^0.1
```

For repository development, run `composer install`, `composer lint`, and `composer test`.

## Usage

```php
<?php

use XPayr\Webhook;
use XPayr\XPayrClient;

$xpayr = new XPayrClient($_ENV['XPAYR_SECRET_KEY']);
$session = $xpayr->createPayment([
    'amount' => '49.90',
    'currency' => 'USDC',
    'network' => 'bsc-testnet',
    'order_id' => 'ORDER-1001',
]);

// In the webhook route, verify the untouched request body.
$event = Webhook::constructEvent(
    file_get_contents('php://input'),
    $_SERVER['HTTP_X_XPAYR_SIGNATURE'] ?? '',
    $_ENV['XPAYR_WEBHOOK_SECRET'],
);
```

Use an XPayr test key before live credentials. Never expose `sk_test_*`, `sk_live_*`, agent keys, webhook secrets, or wallet private keys in browser code or commits.

## Documentation

- [Developer Hub](https://xpayr.com/developers)
- [Merchant API documentation](https://xpayr.com/doc-api)
- [Testnet checkout guide](https://xpayr.com/developers/testnet-checkout-api)
- [Webhook signature guide](https://xpayr.com/developers/webhook-signature-guide)

## Security

Read [SECURITY.md](SECURITY.md) before reporting a vulnerability. Payment completion must be based on verified XPayr webhook/API state and canonical on-chain evidence, not browser callbacks alone.

## License

MIT. See [LICENSE](LICENSE).
