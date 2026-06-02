<?php

namespace PawPayments\Sdk\Tests;

use PHPUnit\Framework\TestCase;
use PawPayments\Sdk\PawPaymentsClient;
use ReflectionMethod;

class ClientTest extends TestCase
{
    private function invoke(string $method, array $args = [])
    {
        $client = new PawPaymentsClient('test_api_key');
        $ref = new ReflectionMethod(PawPaymentsClient::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($client, $args);
    }

    public function testBuildQueryEmpty(): void
    {
        $this->assertSame('', $this->invoke('buildQuery', [[]]));
        $this->assertSame('', $this->invoke('buildQuery', [['x' => null]]));
    }

    public function testBuildQueryJoinsArrays(): void
    {
        $query = $this->invoke('buildQuery', [['order_ids' => ['a', 'b', 'c']]]);
        $this->assertSame('?order_ids=a%2Cb%2Cc', $query);
    }

    public function testBuildQuerySerialisesBooleans(): void
    {
        $this->assertSame('?active=true', $this->invoke('buildQuery', [['active' => true]]));
        $this->assertSame('?active=false', $this->invoke('buildQuery', [['active' => false]]));
    }

    public function testBuildQuerySkipsNulls(): void
    {
        $query = $this->invoke('buildQuery', [['page' => 1, 'status' => null, 'asset' => 'usdt_tron']]);
        $this->assertSame('?page=1&asset=usdt_tron', $query);
    }

    public function testUuid4Format(): void
    {
        $uuid = $this->invoke('uuid4');
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testUuid4IsRandom(): void
    {
        $this->assertNotSame($this->invoke('uuid4'), $this->invoke('uuid4'));
    }
}
