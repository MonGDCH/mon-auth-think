<?php

declare(strict_types=1);

namespace mon\auth\tests\jwt;

use PHPUnit\Framework\TestCase;
use mon\auth\jwt\driver\Payload;
use mon\auth\exception\JwtException;

/**
 * JWT Payload测试：exp/nbf偏移计算与空数据拒绝
 */
class PayloadTest extends TestCase
{
    public function testEmptyDataThrows(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_PAYLOAD_NOT_EMPTY);
        (new Payload())->getData();
    }

    public function testExpOffsetFromIat(): void
    {
        $payload = new Payload();
        $payload->setAud(1);
        $payload->setExp(60);
        $data = $payload->getData();

        $this->assertGreaterThanOrEqual(59, $data['exp'] - $data['iat']);
        $this->assertLessThanOrEqual(61, $data['exp'] - $data['iat']);
    }

    public function testNbfOffsetFromIat(): void
    {
        $payload = new Payload();
        $payload->setAud(1);
        $payload->setNbf(300);
        $data = $payload->getData();

        $this->assertGreaterThanOrEqual(299, $data['nbf'] - $data['iat']);
        $this->assertLessThanOrEqual(301, $data['nbf'] - $data['iat']);
    }

    public function testNbfAndExpOffsetCombined(): void
    {
        $payload = new Payload();
        $payload->setAud(1);
        $payload->setNbf(300);
        $payload->setExp(60);
        $data = $payload->getData();

        // exp基于nbf计算：exp = nbf + exp偏移，且整体为 nbf偏移 + exp偏移
        $this->assertGreaterThanOrEqual(59, $data['exp'] - $data['nbf']);
        $this->assertLessThanOrEqual(61, $data['exp'] - $data['nbf']);
        $this->assertGreaterThanOrEqual(359, $data['exp'] - $data['iat']);
        $this->assertLessThanOrEqual(361, $data['exp'] - $data['iat']);
    }

    public function testZeroExpNotSet(): void
    {
        $payload = new Payload();
        $payload->setAud(1);
        $payload->setExp(0);
        $data = $payload->getData();

        $this->assertArrayNotHasKey('exp', $data);
        $this->assertArrayNotHasKey('nbf', $data);
    }

    public function testJtiOnlySetWhenGiven(): void
    {
        $with = new Payload();
        $with->setAud(1);
        $with->setJti('abc123');
        $this->assertSame('abc123', $with->getData()['jti']);

        $without = new Payload();
        $without->setAud(1);
        $this->assertArrayNotHasKey('jti', $without->getData());
    }
}
