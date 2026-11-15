<?php

declare(strict_types=1);

namespace mon\auth\tests\api;

use PHPUnit\Framework\TestCase;
use mon\auth\api\SignatureAuth;
use mon\auth\api\AccessTokenAuth;
use mon\auth\api\driver\Signature;
use mon\auth\api\driver\AccessToken;
use mon\auth\api\dao\ArrayDao;
use mon\auth\exception\ApiException;

/**
 * 常驻进程（Workerman/Swoole）环境下的状态隔离与安全回归
 *
 * 覆盖：配置重建不累积、嵌套配置深合并、占位符盐拦截、app_id 严格比较、IP 绑定语义、编码失败防护
 */
class ResidentProcessTest extends TestCase
{
    /**
     * 构造 Signature 配置
     *
     * @param string $salt
     * @param string $appId
     * @return array
     */
    protected function signatureConfig(string $salt = 'signature_test_salt', string $appId = 'APP001'): array
    {
        return [
            'salt' => $salt,
            'dao' => [
                'driver' => ArrayDao::class,
                'construct' => [
                    'data' => [
                        ['app_id' => $appId, 'secret' => 'secret456', 'status' => 1, 'expired_time' => ''],
                    ],
                ],
            ],
        ];
    }

    /**
     * 构造 AccessToken 配置
     *
     * @param string $salt
     * @param string $appId
     * @return array
     */
    protected function accessTokenConfig(string $salt = 'access_token_test_salt', string $appId = 'APP001'): array
    {
        return [
            'salt' => $salt,
            'dao' => [
                'driver' => ArrayDao::class,
                'construct' => [
                    'data' => [
                        ['app_id' => $appId, 'secret' => 'secret456', 'status' => 1, 'expired_time' => ''],
                    ],
                ],
            ],
        ];
    }

    /**
     * init() 以默认配置为基准重建，不累积上一次的配置
     *
     * @return void
     */
    public function testInitDoesNotAccumulateConfig(): void
    {
        $config = $this->signatureConfig();
        $config['expire'] = 60;

        $auth = new SignatureAuth($config);
        $this->assertSame(60, $auth->getConfig('expire'));

        // 第二次初始化未指定 expire，应回到默认值而不是沿用 60
        $auth->init($this->signatureConfig());
        $this->assertSame(3600, $auth->getConfig('expire'));
    }

    /**
     * setConfig() 为增量设置，保留当前配置
     *
     * @return void
     */
    public function testSetConfigMergesIncrementally(): void
    {
        $auth = new SignatureAuth($this->signatureConfig());
        $auth->setConfig(['expire' => 120]);

        $this->assertSame(120, $auth->getConfig('expire'));
        $this->assertSame('signature_test_salt', $auth->getConfig('salt'));
        $this->assertInstanceOf(ArrayDao::class, $auth->getDao());
    }

    /**
     * 单例带配置调用会重新初始化，不会沿用首次传入的盐与Dao
     *
     * @return void
     */
    public function testInstanceReinitializesWithNewConfig(): void
    {
        $first = SignatureAuth::instance($this->signatureConfig('salt_first', 'APP_FIRST'));
        $this->assertSame('salt_first', $first->getConfig('salt'));

        $second = SignatureAuth::instance($this->signatureConfig('salt_second', 'APP_SECOND'));
        $this->assertSame('salt_second', $second->getConfig('salt'));
        // 同一单例对象，但配置已被重建
        $this->assertSame($first, $second);
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::APPID_NOT_FOUND);
        $second->createToken('APP_FIRST');
    }

    /**
     * newInstance() 返回互不影响的实例
     *
     * @return void
     */
    public function testNewInstanceIsIndependent(): void
    {
        $a = SignatureAuth::newInstance($this->signatureConfig('salt_a', 'APP_A'));
        $b = SignatureAuth::newInstance($this->signatureConfig('salt_b', 'APP_B'));

        $this->assertNotSame($a, $b);
        $this->assertSame('salt_a', $a->getConfig('salt'));
        $this->assertSame('salt_b', $b->getConfig('salt'));

        // 同一份数据，不同盐签发的签名不能互验
        $data = $a->create('APP_A', 'secret456', ['x' => 1]);
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::SIGN_VERIFY_FAIL);
        $b->check('secret456', $data);
    }

    /**
     * 局部 field 配置不应覆盖整组默认字段映射（PHPUnit 会把 Undefined array key 警告转为异常）
     *
     * @return void
     */
    public function testPartialFieldConfigKeepsDefaults(): void
    {
        $config = $this->signatureConfig();
        $config['field'] = ['app_id' => 'aid'];

        $auth = new SignatureAuth($config);
        $data = $auth->create('APP001', 'secret456', ['a' => 1]);
        $this->assertArrayHasKey('aid', $data);

        $this->assertTrue($auth->check('secret456', $data));
    }

    /**
     * 局部 dao 配置不应丢失 construct 默认值
     *
     * @return void
     */
    public function testPartialDaoConfigKeepsConstruct(): void
    {
        $auth = new SignatureAuth([
            'salt' => 'signature_test_salt',
            'dao' => ['driver' => ArrayDao::class],
        ]);

        $this->assertInstanceOf(ArrayDao::class, $auth->getDao());
        // 默认 construct 里 data 为空数组
        $this->assertSame([], $auth->getDao()->getList());
    }

    /**
     * 配置模板占位符盐必须被拒绝
     *
     * @return void
     */
    public function testPlaceholderSaltRejected(): void
    {
        $cases = [
            function () {
                new Signature('%s');
            },
            function () {
                new AccessToken('%s');
            },
            function () {
                new SignatureAuth(['salt' => '%s']);
            },
            function () {
                new AccessTokenAuth(['salt' => '%s']);
            },
        ];

        foreach ($cases as $case) {
            try {
                $case();
                $this->fail('占位符盐未被拒绝');
            } catch (ApiException $e) {
                $this->assertSame(ApiException::AUTH_INIT_ERROR, $e->getCode());
            }
        }
    }

    /**
     * 初始化失败时不应继续以旧配置对外服务
     *
     * @return void
     */
    public function testFailedInitMarksUninitialized(): void
    {
        $auth = new SignatureAuth($this->signatureConfig());
        $this->assertTrue($auth->isInit());

        try {
            $auth->init(['salt' => '']);
            $this->fail('空盐未被拒绝');
        } catch (ApiException $e) {
            $this->assertSame(ApiException::AUTH_INIT_ERROR, $e->getCode());
        }
        $this->assertFalse($auth->isInit());

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::AUTH_INIT_ERROR);
        $auth->createToken('APP001');
    }

    /**
     * 未绑定IP（签发时ip为空）的token应可在任意IP校验
     *
     * @return void
     */
    public function testUnboundIpTokenAccepted(): void
    {
        $auth = new AccessTokenAuth($this->accessTokenConfig());
        $token = $auth->create('APP001', 'secret456');

        $data = $auth->getData($token, 'APP001', 'secret456', '10.0.0.9');
        $this->assertSame('APP001', $data['app_id']);
        $this->assertSame('', $data['ip']);
    }

    /**
     * 绑定了IP的token仍然严格校验
     *
     * @return void
     */
    public function testBoundIpStillEnforced(): void
    {
        $auth = new AccessTokenAuth($this->accessTokenConfig());
        $token = $auth->create('APP001', 'secret456', '127.0.0.1');

        $this->assertSame('127.0.0.1', $auth->getData($token, 'APP001', 'secret456', '127.0.0.1')['ip']);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_INVALID);
        $auth->getData($token, 'APP001', 'secret456', '10.0.0.1');
    }

    /**
     * app_id 必须严格比较，避免数字字符串松散比较导致跨应用通过
     *
     * @return void
     */
    public function testAppIdStrictComparison(): void
    {
        $auth = new AccessTokenAuth($this->accessTokenConfig('access_token_test_salt', '0e111111'));
        $token = $auth->create('0e111111', 'secret456', '1.2.3.4');

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_FAILD);
        $auth->getData($token, '0e222222', 'secret456', '1.2.3.4');
    }

    /**
     * ArrayDao 查找应用同样严格比较
     *
     * @return void
     */
    public function testArrayDaoStrictAppIdLookup(): void
    {
        $dao = new ArrayDao(['data' => [
            ['app_id' => '0e111111', 'secret' => 's', 'status' => 1, 'expired_time' => ''],
        ]]);

        $this->assertSame('s', $dao->getInfo('0e111111')['secret']);
        $this->assertSame([], $dao->getInfo('0e222222'));
    }

    /**
     * 扩展数据含非法UTF-8时抛出可读异常，而不是 TypeError
     *
     * @return void
     */
    public function testInvalidUtf8ExtendData(): void
    {
        $auth = new AccessTokenAuth($this->accessTokenConfig());

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(ApiException::ACCESS_TOKEN_ENCODE_ERROR);
        $auth->create('APP001', 'secret456', '1.1.1.1', ['nick' => "\xB1\xE0"]);
    }

    /**
     * Dao 缺少 status / expired_time 字段时不产生警告，且按 fail-closed 处理
     *
     * @return void
     */
    public function testDaoFieldFallback(): void
    {
        $dao = new ArrayDao(['data' => []]);
        $this->assertFalse($dao->effect([]));
        $this->assertTrue($dao->expire([]));
        // 无法解析的过期时间按已过期处理
        $this->assertFalse($dao->expire(['expired_time' => 'not-a-date']));
        // 整型时间戳同样支持
        $this->assertTrue($dao->expire(['expired_time' => time() + 60]));
        $this->assertFalse($dao->expire(['expired_time' => time() - 60]));
    }
}
