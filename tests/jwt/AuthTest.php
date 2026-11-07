<?php

declare(strict_types=1);

namespace mon\auth\tests\jwt;

use PHPUnit\Framework\TestCase;
use mon\auth\jwt\Auth;
use mon\auth\jwt\driver\Payload;
use mon\auth\jwt\driver\Token;
use mon\auth\exception\JwtException;

/**
 * JWT Auth P0回归测试：key校验、签发/解析、篡改拒绝、过期拒绝
 */
class AuthTest extends TestCase
{
    /**
     * 有效配置
     *
     * @var array
     */
    protected $config = [
        'key'   => 'test_key_123',
        'alg'   => 'HS256',
        'exp'   => 60,
        'nbf'   => 0,
    ];

    public function testConstructWithEmptyKeyThrows(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_CONFIG_NOT_FOUND);
        new Auth();
    }

    public function testConstructWithPlaceholderKeyThrows(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_CONFIG_NOT_FOUND);
        new Auth(['key' => '%s']);
    }

    public function testInitWithEmptyKeyThrows(): void
    {
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_CONFIG_NOT_FOUND);
        (new Auth(['key' => 'temp']))->init(['key' => '']);
    }

    public function testCreateAndGetTokenDataRoundTrip(): void
    {
        $auth = new Auth($this->config);
        $token = $auth->createToken(1001, ['name' => 'mon'], 'user', 'auth-lib');
        $this->assertIsString($token);

        $data = $auth->getTokenData($token, 'user', 'auth-lib');
        $this->assertSame(1001, $data['aud']);
        $this->assertSame('mon', $data['ext']['name']);
        $this->assertArrayHasKey('exp', $data);
        $this->assertArrayHasKey('iat', $data);
    }

    public function testTamperedTokenRejected(): void
    {
        $auth = new Auth($this->config);
        $token = $auth->createToken(1001);

        // 篡改payload部分
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        $payload['ext']['role'] = 'admin';
        $parts[1] = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $tampered = implode('.', $parts);

        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_TOKEN_VERIFY_ERROR);
        $auth->getTokenData($tampered);
    }

    public function testWrongKeyRejected(): void
    {
        $auth = new Auth($this->config);
        $token = $auth->createToken(1001);

        $other = new Auth(['key' => 'another_key', 'alg' => 'HS256']);
        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_TOKEN_VERIFY_ERROR);
        $other->getTokenData($token);
    }

    public function testSubMismatchRejected(): void
    {
        $auth = new Auth($this->config + ['sub' => 'expected_sub']);
        $token = $auth->createToken(1001);

        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_PAYLOAD_SUB_ERROR);
        $auth->getTokenData($token, 'other_sub');
    }

    public function testIssMismatchRejected(): void
    {
        $auth = new Auth($this->config + ['iss' => 'expected_iss']);
        $token = $auth->createToken(1001);

        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_PAYLOAD_ISS_ERROR);
        $auth->getTokenData($token, '', 'other_iss');
    }

    public function testExpiredTokenRejected(): void
    {
        // sub/iss置空以跳过这两个校验，直达时间校验
        $auth = new Auth($this->config + ['sub' => '', 'iss' => '']);
        // 构造exp已过期的token：直接通过__set写入data中的exp，setExp为0时getData不会覆盖
        $payload = new Payload();
        $payload->setAud(1001);
        $payload->exp = time() - 100;
        $token = Token::instance()->create($payload, $this->config['key'], $this->config['alg']);

        $this->expectException(JwtException::class);
        $this->expectExceptionCode(JwtException::JWT_TOKEN_EXP_ERROR);
        $auth->getTokenData($token);
    }

    public function testGetConfigMissingKeyReturnsNull(): void
    {
        $auth = new Auth($this->config);
        $this->assertNull($auth->getConfig('not_exist_key'));
        $this->assertSame('test_key_123', $auth->getConfig('key'));
    }
}
