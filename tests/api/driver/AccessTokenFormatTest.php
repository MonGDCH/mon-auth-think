<?php

declare(strict_types=1);

namespace mon\auth\tests\api\driver;

use PHPUnit\Framework\TestCase;
use mon\auth\api\driver\AccessToken;
use mon\auth\exception\APIException;

/**
 * AccessToken AES-256-GCM 与旧格式双读迁移测试
 */
class AccessTokenFormatTest extends TestCase
{
    protected $salt = 'fmt_test_salt';
    protected $secret = 'secret456';

    protected function driver(array $options = []): AccessToken
    {
        return new AccessToken($this->salt, [], $options);
    }

    public function testDefaultFormatIsGcm(): void
    {
        $driver = $this->driver();
        $this->assertSame(AccessToken::FORMAT_GCM, $driver->getFormat());
        $this->assertTrue($driver->getLegacyParse());
    }

    public function testUnsupportedFormatRejected(): void
    {
        $this->expectException(APIException::class);
        $this->driver(['format' => 'md5']);
    }

    public function testGcmTokenHasV2Prefix(): void
    {
        $token = $this->driver()->create('APP001', $this->secret, [], '127.0.0.1', 60);
        $this->assertStringStartsWith(AccessToken::TOKEN_PREFIX, $token);
    }

    public function testGcmRoundTrip(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', $this->secret, ['scope' => 'api', '中文' => '数据'], '127.0.0.1', 60);
        $data = $driver->parse($token, $this->secret);

        $this->assertSame('APP001', $data['app_id']);
        $this->assertSame('api', $data['scope']);
        $this->assertSame('数据', $data['中文']);
        $this->assertSame('127.0.0.1', $data['ip']);
    }

    public function testGcmIvUniquePerCreate(): void
    {
        $driver = $this->driver();
        $a = $driver->create('APP001', $this->secret, [], '127.0.0.1', 60);
        $b = $driver->create('APP001', $this->secret, [], '127.0.0.1', 60);
        // 相同数据每次加密产生不同密文（随机IV）
        $this->assertNotSame($a, $b);
    }

    public function testGcmTamperRejectedByTag(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', $this->secret, [], '127.0.0.1', 60);

        // GCM的tag校验：body内任意字符被篡改必须解密失败
        $body = substr($token, strlen(AccessToken::TOKEN_PREFIX));
        $pos = intdiv(strlen($body), 2);
        $char = $body[$pos] === 'A' ? 'B' : 'A';
        $tampered = AccessToken::TOKEN_PREFIX . substr($body, 0, $pos) . $char . substr($body, $pos + 1);

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::ACCESS_TOKEN_ERROR);
        $driver->parse($tampered, $this->secret);
    }

    public function testGcmTailAppendRejected(): void
    {
        // 对比旧版Nbed64容忍尾部垃圾字符，GCM格式结构中附加数据也会导致解析失败
        $driver = $this->driver();
        $token = $driver->create('APP001', $this->secret, [], '127.0.0.1', 60);

        $this->expectException(APIException::class);
        $driver->parse($token . 'zzz', $this->secret);
    }

    public function testGcmWrongSecretRejected(): void
    {
        $driver = $this->driver();
        $token = $driver->create('APP001', $this->secret, [], '127.0.0.1', 60);

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::ACCESS_TOKEN_ERROR);
        $driver->parse($token, 'wrong_secret');
    }

    public function testGcmTruncatedStructureRejected(): void
    {
        // 构造v2.前缀但body长度不足iv+tag的结构
        $driver = $this->driver();
        $token = AccessToken::TOKEN_PREFIX . 'aGVsbG8=';

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::ACCESS_TOKEN_ERROR);
        $driver->parse($token, $this->secret);
    }

    /**
     * 双读迁移：旧格式签发的token，默认配置（legacy_parse=true）可解析
     */
    public function testLegacyTokenReadableByDefaultDriver(): void
    {
        $legacy = $this->driver(['format' => AccessToken::FORMAT_LEGACY]);
        $token = $legacy->create('APP001', $this->secret, ['scope' => 'api'], '127.0.0.1', 60);
        $this->assertStringStartsNotWith(AccessToken::TOKEN_PREFIX, $token);

        // 默认驱动（签发GCM）可解析旧格式token
        $data = $this->driver()->parse($token, $this->secret);
        $this->assertSame('APP001', $data['app_id']);
        $this->assertSame('api', $data['scope']);
    }

    /**
     * 关闭双读后，旧格式token被明确拒绝
     */
    public function testLegacyTokenRejectedWhenLegacyParseDisabled(): void
    {
        $legacy = $this->driver(['format' => AccessToken::FORMAT_LEGACY]);
        $token = $legacy->create('APP001', $this->secret, [], '127.0.0.1', 60);

        $strict = $this->driver(['legacy_parse' => false]);
        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::ACCESS_TOKEN_ERROR);
        $strict->parse($token, $this->secret);
    }

    public function testLegacyFormatStillWorksEndToEnd(): void
    {
        $legacy = $this->driver(['format' => AccessToken::FORMAT_LEGACY]);
        $token = $legacy->create('APP001', $this->secret, [], '127.0.0.1', 60);
        $data = $legacy->parse($token, $this->secret);
        $this->assertSame('APP001', $data['app_id']);
    }
}
