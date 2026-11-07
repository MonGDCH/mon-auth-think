<?php

declare(strict_types=1);

namespace mon\auth\tests\jwt;

use PHPUnit\Framework\TestCase;
use mon\auth\jwt\driver\Token;
use mon\auth\jwt\driver\Payload;
use mon\auth\exception\JwtException;

/**
 * JWT Token驱动测试：urlsafe编码、签名验签、格式错误拒绝
 */
class TokenTest extends TestCase
{
    protected $key = 'token_test_key';

    public function testCreateTokenStructure(): void
    {
        $payload = new Payload();
        $payload->setAud(1);
        $payload->setSub('t');
        $token = Token::instance()->create($payload, $this->key, 'HS256');

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
        $header = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[0])), true);
        $this->assertSame('JWT', $header['typ']);
        $this->assertSame('HS256', $header['alg']);
    }

    public function testGetDataRoundTrip(): void
    {
        $payload = new Payload();
        $payload->setAud(7);
        $payload->setExt(['k' => 'v']);
        $token = Token::instance()->create($payload, $this->key, 'HS256');

        $data = Token::instance()->getData($token, $this->key, 'HS256');
        $this->assertSame(7, $data['aud']);
        $this->assertSame('v', $data['ext']['k']);
    }

    public function testInvalidFormatRejected(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_TOKEN_FORMAT_ERROR);
        Token::instance()->getData('not.a', $this->key, 'HS256');
    }

    public function testUnsupportedAlgRejected(): void
    {
        $token = Token::instance()->create((new Payload())->setAud(1), $this->key, 'HS256');
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_ALG_NOT_SUPPORT);
        Token::instance()->getData($token, $this->key, 'RS256');
    }

    public function testNbfFutureRejected(): void
    {
        $payload = new Payload();
        $payload->setAud(1);
        $payload->setNbf(600); // 600秒后才生效
        $token = Token::instance()->create($payload, $this->key, 'HS256');

        // Token::getData只负责解析验签，时间校验由verify完成（Auth::getTokenData内部同样先getData再verify）
        $data = Token::instance()->getData($token, $this->key, 'HS256');
        $this->assertSame(1, $data['aud']);

        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_TOKEN_NBF_ERROR);
        Token::instance()->verify($data);
    }
}
