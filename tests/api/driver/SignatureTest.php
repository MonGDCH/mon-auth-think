<?php

declare(strict_types=1);

namespace mon\auth\tests\api\driver;

use PHPUnit\Framework\TestCase;
use mon\auth\api\driver\Signature;
use mon\auth\exception\APIException;

/**
 * API签名驱动 P0回归测试：P0-1 签名篡改/错误秘钥必须拒绝
 */
class SignatureTest extends TestCase
{
    protected $salt = 'test_salt';
    protected $secret = 'secret456';

    protected function driver(): Signature
    {
        return new Signature($this->salt);
    }

    public function testCreateContainsRequiredFields(): void
    {
        $data = $this->driver()->create('APP001', $this->secret, ['foo' => 'bar']);
        $this->assertArrayHasKey('app_id', $data);
        $this->assertArrayHasKey('signature', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('noncestr', $data);
        $this->assertSame('APP001', $data['app_id']);
    }

    public function testValidSignPasses(): void
    {
        $data = $this->driver()->create('APP001', $this->secret, ['foo' => 'bar']);
        $this->assertTrue($this->driver()->check($this->secret, $data));
    }

    /**
     * P0回归：修复前签名篡改不抛异常、返回值反转
     */
    public function testTamperedDataRejected(): void
    {
        $driver = $this->driver();
        $data = $driver->create('APP001', $this->secret, ['foo' => 'bar', 'amount' => 100]);
        $data['amount'] = 999999; // 篡改业务参数

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_VERIFY_FAIL);
        $driver->check($this->secret, $data);
    }

    /**
     * P0回归：修复前错误秘钥返回true
     */
    public function testWrongSecretRejected(): void
    {
        $driver = $this->driver();
        $data = $driver->create('APP001', $this->secret);

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_VERIFY_FAIL);
        $driver->check('wrong_secret', $data);
    }

    public function testMissingSignatureRejected(): void
    {
        $data = $this->driver()->create('APP001', $this->secret);
        unset($data['signature']);

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_NOT_FOUND);
        $this->driver()->check($this->secret, $data);
    }

    public function testTamperedTimestampRejected(): void
    {
        $driver = $this->driver();
        $data = $driver->create('APP001', $this->secret);
        $data['timestamp'] = time() + 999;

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_VERIFY_FAIL);
        $driver->check($this->secret, $data);
    }

    public function testNonceIsUniquePerCreate(): void
    {
        $driver = $this->driver();
        $a = $driver->create('APP001', $this->secret);
        $b = $driver->create('APP001', $this->secret);
        $this->assertNotSame($a['noncestr'], $b['noncestr']);
    }

    public function testCustomFieldMapping(): void
    {
        $driver = new Signature($this->salt, [
            'app_id'    => 'appId',
            'signature' => 'sign',
            'timestamp' => 'ts',
            'noncestr'  => 'nonce',
            'secret'    => 'key',
        ]);
        $data = $driver->create('APP001', $this->secret);
        $this->assertArrayHasKey('appId', $data);
        $this->assertArrayHasKey('sign', $data);
        $this->assertTrue($driver->check($this->secret, $data));
    }
}
