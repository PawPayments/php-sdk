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
}
