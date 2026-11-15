<?php

declare(strict_types=1);

namespace mon\auth\api;

use mon\util\Instance;
use mon\auth\api\driver\AccessToken;
use mon\auth\exception\ApiException;

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
     * 库内置的公开默认盐与发布占位符，禁止用于生产环境
     */
    const BUILTIN_SALTS = ['%s', 'a!khg#-$%iu_ow1.08', 'a!a05kh(g#-$%iu_ow1.08'];

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
        // Token签发格式：gcm=AES-256-GCM认证加密（推荐），nbed64=旧版自研加密
        'token_format'  => 'gcm',
        // 兼容解析旧版格式token（迁移期双读，确认存量token全部过期后可关闭）
        'legacy_parse'  => true,
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
     * @param string $ip        签发ip地址
     * @param array $extend     扩展数据
     * @throws ApiException
     * @return string
     */
    public function create(string $app_id, string $secret, string $ip = '', array $extend = []): string
    {
        if (!$this->isInit()) {
            throw new ApiException('未初始化权限控制', ApiException::AUTH_INIT_ERROR);
        }

        return $this->getDriver()->create($app_id, $secret, $extend, $ip, $this->getConfig('expire'));
    }

    /**
     * 结合Dao数据创建AccessToken
     *
     * @param string $app_id    应用ID
     * @param string $ip        签发ip地址
     * @param array $extend     扩展数据
     * @throws ApiException
     * @return string
     */
    public function createToken(string $app_id, string $ip = '', array $extend = []): string
    {
        if (!$this->isInit()) {
            throw new ApiException('未初始化权限控制', ApiException::AUTH_INIT_ERROR);
        }

        // 获取应用信息
        $info = $this->getAppInfo($app_id);

        // 创建token
        return $this->create($app_id, $info['secret'], $ip, $extend);
    }

    /**
     * 获取AccessToken数据
     *
     * @param string $token     Token
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param string $ip        请求ip地址
     * @throws ApiException
     * @return array
     */
    public function getData(string $token, string $app_id, string $secret, string $ip = ''): array
    {
        if (!$this->isInit()) {
            throw new ApiException('未初始化权限控制', ApiException::AUTH_INIT_ERROR);
        }

        return $this->getDriver()->getData($token, $app_id, $secret, $ip);
    }

    /**
     * 获取AccessToken数据
     *
     * @param string $token     Token
     * @param string $app_id    应用ID
     * @param string $ip        请求ip地址
     * @return array
     */
    public function getTokenData(string $token, string $app_id, string $ip = ''): array
    {
        if (!$this->isInit()) {
            throw new ApiException('未初始化权限控制', ApiException::AUTH_INIT_ERROR);
        }

        // 获取应用信息
        $info = $this->getAppInfo($app_id);

        return $this->getData($token, $app_id, $info['secret'], $ip);
    }

    /**
     * 初始化API驱动
     *
     * @return void
     */
    protected function initDriver()
    {
        // 禁止使用库内置的公开默认盐及发布占位符'%s'（跳过vendor:publish直接上线时盐为公开字面量，Token可被伪造）
        $salt = (string) $this->getConfig('salt');
        if ($salt === '' || in_array($salt, static::BUILTIN_SALTS, true)) {
            throw new ApiException('未配置有效的AccessToken加密盐（禁止使用库内置默认盐）', ApiException::AUTH_INIT_ERROR);
        }
        // 获取AccessToken实例
        $this->driver = new AccessToken($this->getConfig('salt'), $this->getConfig('field'), [
            'format'        => $this->getConfig('token_format'),
            'legacy_parse'  => $this->getConfig('legacy_parse'),
        ]);
    }
}
