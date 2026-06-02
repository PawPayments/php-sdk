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
| `listAssets()` | List supported assets |
| `getRates(array $params = [])` | Current exchange rates (`base`, `assets`) |
| `getBalance()` | Merchant balance summary in USD |
| `listLedger(array $params = [])` | Unified income/payout/refund feed (paginated) |
| `createInvoice(array $params)` | Create a new invoice |
| `getInvoice(string $orderId)` | Get invoice by order ID |
| `listInvoices(array $params = [])` | List invoices (paginated) |
| `notifyInvoice(string $orderId)` | Resend the merchant webhook for an invoice |
| `createPayout(array $params, ?string $uniqId = null)` | Create a single payout |
| `createPayoutBatch(array $items, ?string $uniqId = null)` | Create up to 200 payouts at once |
| `getPayout(string $payoutId)` | Get payout by ID |
| `listPayouts(array $params = [])` | List payouts (paginated) |
| `listNotifications(array $params = [])` | List webhook delivery attempts (paginated) |
| `testNotification(?string $url = null)` | Send a test webhook |
| `createPermanentAddress(array $params)` | Get-or-create a permanent deposit address |
| `getPermanentAddress(string $addressId)` | Get permanent address by ID |
| `listPermanentAddresses(array $params = [])` | List permanent addresses |
| `deactivatePermanentAddress(string $addressId)` | Deactivate a permanent address |

`createPermanentAddress` accepts `user_id` (required) plus either `family` (`evm`, `bitcoin`, `litecoin`, `bitcoincash`, `tron`, `solana`, `xrp`, `ton`) or `asset` (e.g. `usdt_trc20`), and optional `notify_url` / `metadata`.

List methods (`listInvoices`, `listPayouts`, `listLedger`, `listNotifications`)
return `['items' => [...], 'pagination' => ['page', 'per_page', 'total', 'pages']]`.

## Payouts

`createPayout` and `createPayoutBatch` send an `x-uniq-id` header (UUIDv4) for
idempotency — one is generated automatically when you don't pass `$uniqId`. The
same `uniqId` re-used within 2 hours yields a 409.

Each payout (and each batch item) accepts an optional `fee_bearer` of `merchant`
or `client` to choose who covers the network fee — defaults to `merchant`:

```php
$payout = $paw->createPayout([
    'address'       => 'T…',
    'amount'        => 50,
    'fiat_currency' => 'USD',
    'asset'         => 'usdt_tron',
    'fee_bearer'    => 'client',
]);

$batch = $paw->createPayoutBatch([
    ['address' => 'T…', 'amount' => 10, 'fiat_currency' => 'USD', 'asset' => 'usdt_tron'],
    ['address' => '0x…', 'amount' => 20, 'fiat_currency' => 'USD', 'asset' => 'usdt_erc20', 'fee_bearer' => 'client'],
]);
```

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

`Webhook::verify($rawBody, $sig, $payload, $apiKey)` is also available as a
documentation-compatible alias for `verifyRawBody`.

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
