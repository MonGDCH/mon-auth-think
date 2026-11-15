<?php

declare(strict_types=1);

namespace mon\auth\tests\api;

use PHPUnit\Framework\TestCase;
use mon\auth\api\nonce\RedisNonceStore;
use mon\auth\exception\ApiException;

/**
 * 可模拟Redis客户端
 */
class FakeRedisClient
{
    /**
     * 已存在的key => [value, ttl]
     *
     * @var array
     */
    public $data = [];

    public function set($key, $value, $options = [])
    {
        $isNx = in_array('nx', $options, true) || (isset($options['nx']) && $options['nx']);
        if ($isNx && isset($this->data[$key])) {
            return false;
        }
        $ttl = $options['ex'] ?? 0;
        $this->data[$key] = [$value, $ttl];

        return true;
    }
}

/**
 * 会抛异常的Redis客户端（模拟Redis不可用）
 */
class BrokenRedisClient
{
    public function set($key, $value, $options = [])
    {
        throw new \RuntimeException('connection refused');
    }
}

/**
 * RedisNonceStore测试
 */
class RedisNonceStoreTest extends TestCase
{
    public function testInvalidClientRejected(): void
    {
        $this->expectException(ApiException::class);
        new RedisNonceStore('not an object');
    }

    public function testClientWithoutSetMethodRejected(): void
    {
        $this->expectException(ApiException::class);
        new RedisNonceStore(new \stdClass());
    }

    public function testPutFirstTimeSucceeds(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisNonceStore($client);

        $this->assertTrue($store->put('APP001', 'nonce1', 7200));
        $this->assertArrayHasKey('auth:nonce:APP001:nonce1', $client->data);
        $this->assertSame(7200, $client->data['auth:nonce:APP001:nonce1'][1]);
    }

    public function testPutSameNonceTwiceReturnsFalse(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisNonceStore($client);

        $this->assertTrue($store->put('APP001', 'nonce1', 7200));
        $this->assertFalse($store->put('APP001', 'nonce1', 7200));
    }

    public function testSameNonceDifferentAppIsolated(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisNonceStore($client);

        $this->assertTrue($store->put('APP001', 'nonce1', 7200));
        $this->assertTrue($store->put('APP002', 'nonce1', 7200));
    }

    public function testCustomPrefix(): void
    {
        $client = new FakeRedisClient();
        $store = new RedisNonceStore($client, 'custom:');

        $this->assertTrue($store->put('APP001', 'n1', 60));
        $this->assertArrayHasKey('custom:APP001:n1', $client->data);
    }

    public function testRedisFailureThrows(): void
    {
        $store = new RedisNonceStore(new BrokenRedisClient());

        $this->expectException(ApiException::class);
        $store->put('APP001', 'n1', 60);
    }
}
