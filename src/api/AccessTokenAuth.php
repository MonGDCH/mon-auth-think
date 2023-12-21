<?php

declare(strict_types=1);

namespace mon\auth\api;

use mon\util\Instance;
use mon\auth\api\driver\AccessToken;
use mon\auth\exception\APIException;

/**
 * AccessToken签名API权限控制
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class AccessTokenAuth extends ApiAuth
{
    use Instance;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [
        // 字段映射
        'field'     => [
            // app_id字段名
            'app_id'    => 'app_id',
            // 有效时间字段名
            'expire'    => 'expire',
            // 签发的IP
            'ip'        => 'ip',
        ],
        // 有效时间，单位秒
        'expire'    => 7200,
        // 默认加密盐
        'salt'      => 'a!khg#-$%iu_ow1.08',
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
                    //     // 应用过期时间戳
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
     * @return AccessToken
     */
    public function getDriver(): AccessToken
    {
        return $this->driver;
    }

    /**
     * 创建AccessToken
     *
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param array $extend     扩展数据
     * @throws APIException
     * @return string
     */
    public function create(string $app_id, string $secret, array $extend = []): string
    {
        if (!$this->isInit()) {
            throw new APIException('未初始化权限控制', APIException::AUTH_INIT_ERROR);
        }

        return $this->getDriver()->create($app_id, $secret, $extend, $this->getConfig('expire'));
    }

    /**
     * 结合Dao数据创建AccessToken
     *
     * @param string $app_id    应用ID
     * @param array $extend     扩展数据
     * @throws APIException
     * @return string
     */
    public function createToken(string $app_id, array $extend = []): string
    {
        if (!$this->isInit()) {
            throw new APIException('未初始化权限控制', APIException::AUTH_INIT_ERROR);
        }
        // 获取应用信息
        $info = $this->getAppInfo($app_id);

        // 创建token
        return $this->create($app_id, $info['secret'], $extend);
    }

    /**
     * 校验AccessToken
     *
     * @param string $token token
     * @param string $app_id  应用ID
     * @param string $secret  应用秘钥
     * @throws APIException
     * @return array    token数据
     */
    public function check(string $token, string $app_id, string $secret): array
    {
        if (!$this->isInit()) {
            throw new APIException('未初始化权限控制', APIException::AUTH_INIT_ERROR);
        }

        return $this->getDriver()->check($token, $app_id, $secret);
    }

    /**
     * 校验AccessToken
     *
     * @param string $token token
     * @param string $app_id 应用ID
     * @return array    token数据
     */
    public function checkToken(string $token, string $app_id): array
    {
        if (!$this->isInit()) {
            throw new APIException('未初始化权限控制', APIException::AUTH_INIT_ERROR);
        }

        // 获取应用信息
        $info = $this->getAppInfo($app_id);

        return $this->check($token, $app_id, $info['secret']);
    }

    /**
     * 初始化API驱动
     *
     * @return void
     */
    protected function initDriver()
    {
        // 获取AccessToken实例
        $this->driver = new AccessToken($this->getConfig('salt'), $this->getConfig('field'));
    }
}
