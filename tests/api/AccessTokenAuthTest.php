<?php

declare(strict_types=1);

namespace mon\auth\tests\api;

use PHPUnit\Framework\TestCase;
use mon\auth\api\AccessTokenAuth;
use mon\auth\exception\APIException;

/**
 * AccessTokenAuth 全链路测试（ArrayDao数据源）
 */
class AccessTokenAuthTest extends TestCase
{
    protected function auth(): AccessTokenAuth
    {
        return new AccessTokenAuth([
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
        ]);
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

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::ACCESS_TOKEN_INVALID);
        $auth->getTokenData($token, 'APP001', '10.0.0.1');
    }

    public function testUnknownAppRejected(): void
    {
        $auth = $this->auth();
        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::APPID_NOT_FOUND);
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

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::APPID_STATUS_ERROR);
        $auth->createToken('APP001', '127.0.0.1');
    }
}
