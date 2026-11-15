<?php

declare(strict_types=1);

namespace mon\auth\tests\api;

use PHPUnit\Framework\TestCase;
use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\ApiException;

/**
 * AccessTokenAuth 全链路测试（ArrayDao数据源）
 */
class AccessTokenAuthTest extends TestCase
{
    protected function auth(array $extra = []): AccessTokenAuth
    {
        $config = array_merge([
            'salt'  => 'test_salt',
            'expire'    => 60,
            'dao' => [
                'driver'    => \mon\auth\api\dao\ArrayDao::class,
                'construct' => [
                    'data' => [
                        ['app_id' => 'APP001', 'secret' => 'secret456', 'name' => 't', 'status' => 1, 'expired_time' => ''],
                    ],
                ],
            ],
        ], $extra);
        return new AccessTokenAuth($config);
    }

    public function testCreateAndGetTokenDataRoundTrip(): void
    {
        $auth = $this->auth();
        $token = $auth->createToken('APP001', '127.0.0.1', ['scope' => 'api']);
        $data = $auth->getTokenData($token, 'APP001', '127.0.0.1');

        $this->assertSame('APP001', $data['app_id']);
        // 扩展数据为平铺结构
        $this->assertSame('api', $data['scope']);
    }

    public function testIpMismatchRejected(): void
    {
        $auth = $this->auth();
        $token = $auth->createToken('APP001', '127.0.0.1');

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_INVALID);
        $auth->getTokenData($token, 'APP001', '10.0.0.1');
    }

    public function testUnknownAppRejected(): void
    {
        $auth = $this->auth();
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::APPID_NOT_FOUND);
        $auth->createToken('APP404', '127.0.0.1');
    }

    public function testDisabledAppRejected(): void
    {
        $auth = new AccessTokenAuth([
            'salt'  => 'test_salt',
            'expire'    => 60,
            'dao' => [
                'driver'    => \mon\auth\api\dao\ArrayDao::class,
                'construct' => [
                    'data' => [
                        ['app_id' => 'APP001', 'secret' => 'secret456', 'name' => 't', 'status' => 0, 'expired_time' => ''],
                    ],
                ],
            ],
        ]);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::APPID_STATUS_ERROR);
        $auth->createToken('APP001', '127.0.0.1');
    }

    public function testDefaultIssueFormatIsGcm(): void
    {
        $auth = $this->auth();
        $token = $auth->createToken('APP001', '127.0.0.1');
        $this->assertStringStartsWith(\mon\auth\api\driver\AccessToken::TOKEN_PREFIX, $token);
    }

    public function testLegacyFormatConfigEndToEnd(): void
    {
        $auth = new AccessTokenAuth([
            'salt'  => 'test_salt',
            'expire'    => 60,
            'token_format'  => 'nbed64',
            'legacy_parse'  => true,
            'dao' => [
                'driver'    => \mon\auth\api\dao\ArrayDao::class,
                'construct' => [
                    'data' => [
                        ['app_id' => 'APP001', 'secret' => 'secret456', 'name' => 't', 'status' => 1, 'expired_time' => ''],
                    ],
                ],
            ],
        ]);

        $token = $auth->createToken('APP001', '127.0.0.1');
        $this->assertStringStartsNotWith(\mon\auth\api\driver\AccessToken::TOKEN_PREFIX, $token);
        $data = $auth->getTokenData($token, 'APP001', '127.0.0.1');
        $this->assertSame('APP001', $data['app_id']);
    }

    public function testGcmConfigEndToEnd(): void
    {
        $auth = new AccessTokenAuth([
            'salt'  => 'test_salt',
            'expire'    => 60,
            'token_format'  => 'gcm',
            'legacy_parse'  => true,
            'dao' => [
                'driver'    => \mon\auth\api\dao\ArrayDao::class,
                'construct' => [
                    'data' => [
                        ['app_id' => 'APP001', 'secret' => 'secret456', 'name' => 't', 'status' => 1, 'expired_time' => ''],
                    ],
                ],
            ],
        ]);

        $token = $auth->createToken('APP001', '127.0.0.1', ['scope' => 'api']);
        $data = $auth->getTokenData($token, 'APP001', '127.0.0.1');
        $this->assertSame('api', $data['scope']);
    }

    public function testEmptySaltRejected(): void
    {
        // 盐必须显式配置，默认空值时fail-closed拒绝初始化
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::AUTH_INIT_ERROR);
        $this->auth(['salt' => '']);
    }
}
