# PawPayments — PHP SDK

Official PHP SDK for the [PawPayments](https://pawpayments.com) Native V2 API.
Pure cURL, no framework dependencies. Requires PHP **7.4+** and the `curl` + `json` extensions.

## Install

```bash
composer require pawpayments/sdk
```

## Quickstart

```php
use PawPayments\Sdk\PawPaymentsClient;

$paw = new PawPaymentsClient(apiKey: $_ENV['PAW_API_KEY']);

$invoice = $paw->createInvoice([
    'amount'       => 25,
    'fiat_currency'=> 'USD',
    'billing_type' => 'STATIC',
    'asset'        => 'usdt_tron',
    'description'  => 'Pro plan, 1 month',
    'notify_url'   => 'https://example.com/paw/webhook',
]);

echo $invoice['payment_url'];
```

## Methods

| Method | Description |
|--------|-------------|
| `createInvoice(array $params)` | Create a new invoice |
| `getInvoice(string $orderId)` | Get invoice by order ID |
| `createPermanentAddress(array $params)` | Get-or-create a permanent deposit address |
| `getPermanentAddress(string $addressId)` | Get permanent address by ID |
| `listPermanentAddresses(array $params = [])` | List permanent addresses |
| `deactivatePermanentAddress(string $addressId)` | Deactivate a permanent address |

`createPermanentAddress` accepts `user_id` (required) plus either `family` (`evm`, `bitcoin`, `litecoin`, `bitcoincash`, `tron`, `solana`, `xrp`, `ton`) or `asset` (e.g. `usdt_trc20`), and optional `notify_url` / `metadata`.

## Webhook verification

```php
use PawPayments\Sdk\Webhook;

$rawBody = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_X_PAW_SIGNATURE'] ?? '';

if (!Webhook::verifyRawBody($rawBody, $sig, $_ENV['PAW_API_KEY'])) {
    http_response_code(401);
    exit;
}

$payload = Webhook::parsePayload($rawBody);
// …handle invoice update
```

## Errors

All API errors (HTTP 4xx/5xx, network, invalid JSON) throw `PawPaymentsApiException`:

```php
use PawPayments\Sdk\Exception\PawPaymentsApiException;

try {
    $paw->createInvoice([/* ... */]);
} catch (PawPaymentsApiException $e) {
    echo $e->getErrorCode();   // e.g. "INVALID_PARAMS"
    echo $e->getHttpStatus();  // e.g. 400
    echo $e->getMessage();
}
```

## Testing

```bash
composer install
vendor/bin/phpunit --colors=always
```

## License

MIT
