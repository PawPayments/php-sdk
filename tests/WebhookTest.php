<?php

namespace PawPayments\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use PawPayments\Sdk\Webhook;

class WebhookTest extends TestCase
{
    private string $apiKey = 'test_api_key_abc123';

    public function testVerifyRawBodyValid(): void
    {
        $body = '{"order_id":"abc","status":"success"}';
        $sig = hash_hmac('sha256', $body, $this->apiKey);

        $this->assertTrue(Webhook::verifyRawBody($body, $sig, $this->apiKey));
    }

    public function testVerifyAliasValid(): void
    {
        $body = '{"order_id":"abc","status":"success"}';
        $sig = hash_hmac('sha256', $body, $this->apiKey);

        $this->assertTrue(Webhook::verify($body, $sig, ['order_id' => 'abc'], $this->apiKey));
    }

    public function testVerifyRawBodyInvalid(): void
    {
        $body = '{"order_id":"abc","status":"success"}';

        $this->assertFalse(Webhook::verifyRawBody($body, 'badsignature', $this->apiKey));
    }

    public function testVerifyRawBodyTampered(): void
    {
        $body = '{"order_id":"abc","status":"success"}';
        $sig = hash_hmac('sha256', $body, $this->apiKey);
        $tampered = '{"order_id":"abc","status":"cancelled"}';

        $this->assertFalse(Webhook::verifyRawBody($tampered, $sig, $this->apiKey));
    }

    public function testVerifyRawBodyEmptyKeyRejected(): void
    {
        $body = '{"order_id":"abc","status":"success"}';
        $sig = hash_hmac('sha256', $body, '');

        $this->assertFalse(Webhook::verifyRawBody($body, $sig, ''));
    }

    public function testVerifyRawBodyUppercaseHexAccepted(): void
    {
        $body = '{"order_id":"abc","status":"success"}';
        $sig = strtoupper(hash_hmac('sha256', $body, $this->apiKey));

        $this->assertTrue(Webhook::verifyRawBody($body, $sig, $this->apiKey));
    }

    public function testVerifyRawBodyMalformedHeaderRejected(): void
    {
        $body = '{"order_id":"abc","status":"success"}';
        $sig = hash_hmac('sha256', $body, $this->apiKey);

        $this->assertFalse(Webhook::verifyRawBody($body, '', $this->apiKey));
        $this->assertFalse(Webhook::verifyRawBody($body, substr($sig, 0, 63), $this->apiKey));
        $this->assertFalse(Webhook::verifyRawBody($body, $sig . '0', $this->apiKey));
        $this->assertFalse(Webhook::verifyRawBody($body, $sig . "\n", $this->apiKey));
        $this->assertFalse(Webhook::verifyRawBody($body, str_repeat('g', 64), $this->apiKey));
    }

    public function testParsePayload(): void
    {
        $body = '{"order_id":"abc","status":"success","amount":100.5}';
        $payload = Webhook::parsePayload($body);

        $this->assertSame('abc', $payload['order_id']);
        $this->assertSame('success', $payload['status']);
        $this->assertSame(100.5, $payload['amount']);
    }

    public function testParsePayloadInvalidJson(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Webhook::parsePayload('not json');
    }

    public function testCoversAmountWhenTheInvoiceWasIssuedForTheTotal(): void
    {
        $payload = ['initial_fiat_amount' => 100.0, 'fiat_amount' => 100.0, 'fiat_currency' => 'USD'];

        $this->assertTrue(Webhook::coversAmount($payload, 'USD', 100));
        $this->assertTrue(Webhook::coversAmount($payload, 'usd', '100.00'));
        $this->assertTrue(Webhook::coversAmount(['fiat_currency' => 'usd'] + $payload, 'USD', 100));
    }

    public function testCoversAmountTrustsTheGatewaysOwnTolerance(): void
    {
        // A merchant may accept 20% short; the gateway then sends `success` with
        // 80 received, and the receiver must not second-guess it.
        $payload = ['initial_fiat_amount' => '100', 'fiat_amount' => '80', 'fiat_currency' => 'EUR'];

        $this->assertTrue(Webhook::coversAmount($payload, 'EUR', 100));
    }

    public function testCoversAmountRefusesAWebhookForACheaperInvoice(): void
    {
        // A genuinely signed webhook for a 1.00 invoice whose `extra` names a
        // 500.00 order: paid in full, for the wrong amount.
        $payload = ['initial_fiat_amount' => 1.0, 'fiat_amount' => 1.0, 'fiat_currency' => 'USD'];

        $this->assertFalse(Webhook::coversAmount($payload, 'USD', 500));
    }

    public function testCoversAmountAllowsOnlyACentOfRounding(): void
    {
        $this->assertTrue(Webhook::coversAmount(['initial_fiat_amount' => 99.99, 'fiat_currency' => 'USD'], 'USD', 100));
        $this->assertFalse(Webhook::coversAmount(['initial_fiat_amount' => 99.98, 'fiat_currency' => 'USD'], 'USD', 100));
        $this->assertTrue(Webhook::coversAmount(['initial_fiat_amount' => 120, 'fiat_currency' => 'USD'], 'USD', 100));
    }

    public function testCoversAmountRefusesAnotherCurrency(): void
    {
        $payload = ['initial_fiat_amount' => '100000', 'fiat_amount' => '100000', 'fiat_currency' => 'IDR'];

        $this->assertFalse(Webhook::coversAmount($payload, 'USD', 100));
        $this->assertFalse(Webhook::coversAmount(['initial_fiat_amount' => '100'], 'USD', 100));
        $this->assertFalse(Webhook::coversAmount(['initial_fiat_amount' => '100', 'fiat_currency' => 'USD'], '', 100));
        // Unknown on both sides is not a match.
        $this->assertFalse(Webhook::coversAmount(['initial_fiat_amount' => '100'], '', 100));
    }

    public function testCoversAmountRefusesAPayloadWithoutTheIssuedAmount(): void
    {
        // Every paid webhook carries it; without it nothing proves the amount.
        $this->assertFalse(Webhook::coversAmount(['fiat_amount' => 100, 'fiat_currency' => 'USD'], 'USD', 100));
        $this->assertFalse(Webhook::coversAmount(['initial_fiat_amount' => 'x', 'fiat_currency' => 'USD'], 'USD', 100));
        $this->assertFalse(Webhook::coversAmount(['initial_fiat_amount' => null, 'fiat_currency' => 'USD'], 'USD', 100));
    }

    public function testCoversAmountRefusesATotalThatIsNotANumber(): void
    {
        $payload = ['initial_fiat_amount' => 100, 'fiat_currency' => 'USD'];

        $this->assertFalse(Webhook::coversAmount($payload, 'USD', null));
        $this->assertFalse(Webhook::coversAmount($payload, 'USD', ''));
        $this->assertFalse(Webhook::coversAmount($payload, 'USD', 'abc'));
    }
}
