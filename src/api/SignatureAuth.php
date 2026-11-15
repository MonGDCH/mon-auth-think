<?php

declare(strict_types=1);

namespace mon\auth\api;

use mon\util\Instance;
use mon\auth\api\driver\Signature;
use mon\auth\exception\ApiException;
use mon\auth\api\contract\NonceStore;

/**
 * API签名权限控制
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class SignatureAuth extends ApiAuth
{
    use Instance;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 加密盐，必须显式配置，未配置时初始化将抛出异常
        'salt'  => '',
        // 签名算法：md5（历史默认，保持向后兼容）| hmac-sha256（推荐，新接入方建议使用）
        'alg'   => 'md5',
        // alg为hmac-sha256时，是否兼容校验旧版md5签名，客户端全部升级后建议关闭
        'accept_legacy' => true,
        // 字段映射
        'field' => [
            // app_id字段名
            'app_id'    => 'app_id',
            // 签名字段名
            'signature' => 'signature',
            // 签名时间字段名
            'timestamp' => 'timestamp',
            // 随机字符串字段名
            'noncestr'  => 'noncestr',
            // secret key名
            'secret'    => 'key'
        ],
        // 有效时间，单位秒
        'expire'    => 3600,
        // 签名nonce防重放存储，null=关闭（兼容旧行为），需实现 mon\auth\api\contract\NonceStore
        'nonce_store'   => null,
        // 数据源配置
        'dao'      => [
            // 驱动，默认数组驱动
            'driver'    => \mon\auth\api\dao\ArrayDao::class,
            // 构造方法传入参数
            'construct'    => [
                // 数组驱动APP应用数据列表，驱动为 ArrayDao 时有效
                'data'  => [
                    // [
                    //     // 应用ID
                    //     'app_id'    => 'TEST123456789',
                    //     // 应用秘钥
                    //     'secret'    => 'klasjhghaalskfjqwpetoijhxc',
                    //     // 应用名称
                    //     'name'      => '测试',
                    //     // 应用状态，1有效 0无效
                    //     'status'    => 1,
                    //     // 应用过期时间
                    //     'expired_time'  => 0,
                    // ]
                ],
                // 数据库驱动操作表，驱动为 DatabaseDao 时有效
                'table'     => 'api_sign'
            ]
        ]
    ];

    /**
     * 获取驱动实例
     *
     * @return Signature
     */
    public function getDriver(): Signature
    {
        return $this->driver;
    }

    /**
     * 创建签名请求数据
     *
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param array $data       需要签名的数据
     * @return array
     */
    public function create(string $app_id, string $secret, array $data = []): array
    {
        if (!$this->isInit()) {
            throw new ApiException('未初始化权限控制', ApiException::AUTH_INIT_ERROR);
        }

        return $this->getDriver()->create($app_id, $secret, $data);
    }

    /**
     * 结合Dao数据创建API签名
     *
     * @param string $app_id    应用ID
     * @param array $data       需要签名的数据
     * @throws ApiException
     * @return array
     */
    public function createToken(string $app_id, array $data = []): array
    {
        // 获取应用信息
        $info = $this->getAppInfo($app_id);
        // 创建签名
        return $this->create($app_id, $info['secret'], $data);
    }

    /**
     * 验证签名
     *
     * @param string $secret    应用秘钥
     * @param array $data       签名数据
     * @return boolean
     */
    public function check(string $secret, array $data): bool
    {
        if (!$this->isInit()) {
            throw new ApiException('未初始化权限控制', ApiException::AUTH_INIT_ERROR);
        }

        // 验证签名（签名不匹配时驱动会直接抛出异常）
        $this->getDriver()->check($secret, $data);

        // 验证签名有效期（双向窗口：既拒绝过期请求，也拒绝超前的时间戳，防止未来时间戳长期有效）
        $field = $this->getConfig('field');
        $timestamp = isset($data[$field['timestamp']]) ? intval($data[$field['timestamp']]) : 0;
        if ($timestamp <= 0 || abs(time() - $timestamp) > $this->getConfig('expire')) {
            throw new ApiException('API签名已过期', ApiException::SIGN_TIME_INVALID);
        }

        // 验证nonce防重放（配置了nonce_store时启用），顺序：先验签和有效期，再占位nonce，避免非法请求灌爆存储
        $store = $this->getConfig('nonce_store');
        if (!is_null($store)) {
            if (!$store instanceof NonceStore) {
                throw new ApiException('nonce_store配置必须实现 NonceStore 契约', ApiException::DAO_NOT_SUPPORT);
            }
            $nonce = isset($data[$field['noncestr']]) ? strval($data[$field['noncestr']]) : '';
            if ($nonce === '' || !$store->put(strval($data[$field['app_id']]), $nonce, $this->getConfig('expire'))) {
                throw new ApiException('API签名重复提交', ApiException::SIGN_REPLAY);
            }
        }

        return true;
    }

    /**
     * 验证签名
     *
     * @param array $data   签名数据
     * @return boolean
     */
    public function checkToken(array $data): bool
    {
        $field = $this->getConfig('field');
        $app_id = $data[$field['app_id']] ?? null;
        if (empty($app_id)) {
            throw new ApiException('无效签名', ApiException::APPID_PARAMS_FAILD);
        }
        // 获取应用信息
        $info = $this->getAppInfo($app_id);
        // 验证签名
        return $this->check($info['secret'], $data);
    }

    /**
     * 初始化API驱动
     *
     * @return void
     */
    protected function initDriver()
    {
        // 盐必须显式配置，未配置（默认空值）时直接拒绝初始化
        $salt = (string) $this->getConfig('salt');
        if ($salt === '') {
            throw new ApiException('未配置有效的Signature加密盐', ApiException::AUTH_INIT_ERROR);
        }
        // 获取Signature实例
        $this->driver = new Signature($this->getConfig('salt'), $this->getConfig('field'), [
            'alg'           => (string) $this->getConfig('alg'),
            'accept_legacy' => (bool) $this->getConfig('accept_legacy'),
        ]);
    }
}
