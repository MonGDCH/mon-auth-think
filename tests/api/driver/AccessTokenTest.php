<?php

declare(strict_types=1);

namespace mon\auth\tests\api\driver;

use PHPUnit\Framework\TestCase;
use mon\auth\api\driver\AccessToken;
use mon\util\Nbed64;
use mon\auth\exception\ApiException;

/**
 * AccessToken驱动 P0回归测试：IP校验、缺字段防护、过期拒绝
 */
class AccessTokenTest extends TestCase
{
    protected $salt = 'at_test_salt';

    protected function driver(): AccessToken
    {
        return new AccessToken($this->salt);
    }

    public function testCreateParseRoundTrip(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', 'secret456', ['scope' => 'api'], '127.0.0.1', 60);
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // parse的secret参数传原始应用秘钥（内部自行加盐）
        $data = $driver->parse($token, 'secret456');
        $this->assertSame('APP001', $data['app_id']);
        // 扩展数据为平铺结构，无ext包装键
        $this->assertSame('api', $data['scope']);
    }

    public function testTamperedTokenRejected(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', 'secret456', [], '127.0.0.1', 60);

        // 修改token中间字符（尾部附加字符不影响解密结果，属于解码器容忍行为）
        $pos = intdiv(strlen($token), 2);
        $char = $token[$pos] === 'A' ? 'B' : 'A';
        $tampered = substr($token, 0, $pos) . $char . substr($token, $pos + 1);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_ERROR);
        $driver->parse($tampered, 'secret456');
    }

    public function testWrongSaltRejected(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', 'secret456', [], '127.0.0.1', 60);

        $other = new AccessToken('other_salt');
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_ERROR);
        $other->parse($token, 'secret456');
    }

    public function testAppIdMismatchRejected(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', 'secret456', [], '127.0.0.1', 60);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_FAILD);
        $driver->getData($token, 'APP002', 'secret456', '127.0.0.1');
    }

    public function testIpMismatchRejected(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', 'secret456', [], '127.0.0.1', 60);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_INVALID);
        $driver->getData($token, 'APP001', 'secret456', '192.168.1.1');
    }

    /**
     * P0回归：修复前缺字段直接notice
     */
    public function testMissingFieldsRejected(): void
    {
        $driver = $this->driver();
        // 合法加密但缺少expire/ip字段的数据
        $minimal = Nbed64::stringEncrypt(json_encode(['app_id' => 'APP001']), $this->salt . 'secret456');

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_ERROR);
        $driver->getData($minimal, 'APP001', 'secret456', '127.0.0.1');
    }

    /**
     * P0回归：构造已过期的AccessToken必须被拒绝
     */
    public function testExpiredTokenRejected(): void
    {
        $driver = $this->driver();
        // 手工构造expire已过期的加密数据
        $raw = json_encode([
            'app_id'    => 'APP001',
            'expire'    => time() - 10,
            'ip'        => '127.0.0.1',
            'ext'       => [],
        ]);
        $token = \mon\util\Nbed64::stringEncrypt($raw, $driver->getSalt() . 'secret456');

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_INVALID);
        $driver->getData($token, 'APP001', 'secret456', '127.0.0.1');
    }
}
