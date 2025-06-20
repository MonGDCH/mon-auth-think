<?php

declare(strict_types=1);

namespace support\auth;

use mon\env\Config;
use mon\util\Instance;
use mon\auth\api\dao\DatabaseDao;
use mon\auth\api\AccessTokenAuth as Auth;

/**
 * AccessToken权限控制服务
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class AccessTokenService
{
    use Instance;

    /**
     * 缓存服务对象
     *
     * @var Auth
     */
    protected $service;

    /**
     * 构造方法
     */
    protected function __construct()
    {
        $config = Config::instance()->get('auth.accesstoken', []);
        $this->service = new Auth($this->parseConfig($config));
    }

    /**
     * 获取权限服务
     *
     * @return Auth
     */
    public function getService(): Auth
    {
        return $this->service;
    }

    /**
     * 注册配置信息
     *
     * @param array $config 配置信息
     * @return AccessTokenService
     */
    public function register(array $config): AccessTokenService
    {
        $config = $this->parseConfig($config);
        $this->getService()->init($config);
        return $this;
    }

    /**
     * 创建AccessToken
     *
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param string $ip        签发ip地址
     * @param array $extend     扩展数据
     * @throws \mon\auth\exception\APIException
     * @return string
     */
    public function create(string $app_id, string $secret,  string $ip = '', array $extend = []): string
    {
        return $this->getService()->create($app_id, $secret, $ip, $extend);
    }

    /**
     * 结合Dao数据创建AccessToken
     *
     * @param string $app_id    应用ID
     * @param string $ip        签发ip地址
     * @param array $extend     扩展数据
     * @throws \mon\auth\exception\APIException
     * @return string
     */
    public function createToken(string $app_id, string $ip = '', array $extend = []): string
    {
        return $this->getService()->createToken($app_id, $ip, $extend);
    }

    /**
     * 校验获取AccessToken数据
     *
     * @param string $token     Token
     * @param string $app_id    应用ID
     * @param string $ip        请求ip地址
     * @param string $secret    应用秘钥
     * @throws \mon\auth\exception\APIException
     * @return array
     */
    public function getData(string $token, string $app_id, string $secret, string $ip = ''): array
    {
        return $this->getService()->getData($token, $app_id, $secret, $ip);
    }

    /**
     * 校验获取AccessToken数据
     *
     * @param string $token     Token
     * @param string $ip        请求ip地址
     * @param string $app_id    应用ID
     * @throws \mon\auth\exception\APIException
     * @return array
     */
    public function getTokenData(string $token, string $app_id, string $ip = ''): array
    {
        return $this->getService()->getTokenData($token, $app_id, $ip);
    }

    /**
     * 解析完善配置信息
     *
     * @param array $config 配置信息
     * @return array
     */
    protected function parseConfig(array $config): array
    {
        if ($config['dao']['driver'] == DatabaseDao::class && is_string($config['dao']['construct']['config'])) {
            // 数据库dao驱动，字符串类型的数据库链接配置
            $dbconfig = Config::instance()->get('database.' . $config['dao']['construct']['config'], []);
            $config['dao']['construct']['config'] = $dbconfig;
        }

        return $config;
    }
}
