<?php

declare(strict_types=1);

namespace mon\auth\tests\api;

use PHPUnit\Framework\TestCase;
use mon\auth\api\SignatureAuth;
use mon\auth\api\contract\NonceStore;
use mon\auth\exception\APIException;

/**
 * 测试用内存nonce存储
 */
class MemoryNonceStore implements NonceStore
{
    /**
     * 已占位的nonce
     *
     * @var array
     */
    public $store = [];

    public function put(string $app_id, string $nonce, int $ttl): bool
    {
        $key = $app_id . ':' . $nonce;
        if (isset($this->store[$key])) {
            return false;
        }
        $this->store[$key] = true;

        return true;
    }
}

/**
 * SignatureAuth P0回归测试：checkToken全链路 + nonce防重放
 */
class SignatureAuthTest extends TestCase
{
    protected $config;

    protected function setUp(): void
    {
        $this->config = [
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
        ];
    }

    protected function auth(array $extra = []): SignatureAuth
    {
        return new SignatureAuth(array_merge($this->config, $extra));
    }

    public function testCreateTokenAndCheckTokenPasses(): void
    {
        $auth = $this->auth();
        $data = $auth->createToken('APP001', ['a' => 1]);
        $this->assertTrue($auth->checkToken($data));
    }

    public function testCheckTokenTamperedRejected(): void
    {
        $auth = $this->auth();
        $data = $auth->createToken('APP001', ['a' => 1]);
        $data['a'] = 2;

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_VERIFY_FAIL);
        $auth->checkToken($data);
    }

    public function testCheckTokenExpiredRejected(): void
    {
        $auth = $this->auth();
        $data = $auth->createToken('APP001');
        // 直接篡改时间戳会破坏签名，因此签名校验先拒绝
        $data['timestamp'] = time() - 99999;

        $this->expectException(APIException::class);
        $auth->checkToken($data);
    }

    /**
     * 签名覆盖全部字段，任何字段缺失/篡改都会先被签名校验拦截；
     * SIGN_TIME_INVALID的isset防护属于纵深防御（内部调用场景）
     */
    public function testCheckTokenMissingTimestampRejected(): void
    {
        $auth = $this->auth();
        $data = $auth->createToken('APP001');
        unset($data['timestamp']);

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_VERIFY_FAIL);
        $auth->checkToken($data);
    }

    public function testCheckTokenMissingAppIdRejected(): void
    {
        $auth = $this->auth();
        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::APPID_PARAMS_FAILD);
        $auth->checkToken(['signature' => 'x']);
    }

    public function testCheckTokenUnknownAppRejected(): void
    {
        $auth = $this->auth();
        $data = $auth->createToken('APP001');
        $data['app_id'] = 'APP404';

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::APPID_NOT_FOUND);
        $auth->checkToken($data);
    }

    public function testNonceDisabledByDefault(): void
    {
        // 默认不配置nonce_store，行为与旧版一致
        $auth = $this->auth();
        $data = $auth->createToken('APP001');
        $this->assertTrue($auth->checkToken($data));
        $this->assertTrue($auth->checkToken($data)); // 重放仍通过（未启用防护）
    }

    public function testNonceStoreBlocksReplay(): void
    {
        $store = new MemoryNonceStore();
        $auth = $this->auth(['nonce_store' => $store]);
        $data = $auth->createToken('APP001');

        $this->assertTrue($auth->checkToken($data));
        $this->assertCount(1, $store->store);

        // 同一签名数据重放被拒绝
        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_REPLAY);
        $auth->checkToken($data);
    }

    public function testNonceStoreRemoveNonceBreaksSignature(): void
    {
        // noncestr参与签名，移除后签名校验先拒绝；nonce空值防护属于纵深防御
        $store = new MemoryNonceStore();
        $auth = $this->auth(['nonce_store' => $store]);
        $data = $auth->createToken('APP001');
        unset($data['noncestr']);

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::SIGN_VERIFY_FAIL);
        $auth->checkToken($data);
        $this->assertCount(0, $store->store);
    }

    public function testInvalidNonceStoreConfigRejected(): void
    {
        $auth = $this->auth(['nonce_store' => new \stdClass()]);
        $data = $auth->createToken('APP001');

        $this->expectException(APIException::class);
        $this->expectExceptionCode(APIException::DAO_NOT_SUPPORT);
        $auth->checkToken($data);
    }

    public function testInvalidSignatureDoesNotConsumeNonce(): void
    {
        $store = new MemoryNonceStore();
        $auth = $this->auth(['nonce_store' => $store]);
        $data = $auth->createToken('APP001');
        $data['evil'] = 'tampered';

        try {
            $auth->checkToken($data);
            $this->fail('应抛出签名错误异常');
        } catch (APIException $e) {
            $this->assertSame(APIException::SIGN_VERIFY_FAIL, $e->getCode());
        }
        // 验签失败不应占位nonce
        $this->assertCount(0, $store->store);
    }
}
