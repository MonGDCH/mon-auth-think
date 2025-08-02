<?php

declare(strict_types=1);

namespace support\auth;

use mon\env\Config;
use mon\util\Instance;
use mon\auth\api\dao\DatabaseDao;
use mon\auth\api\SignatureAuth as Auth;

/**
 * signature权限控制服务
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class SignatureService
{
    use Instance;

    /**
     * 服务对象
     *
     * @var Auth
     */
    protected $service;

    /**
     * 获取权限服务
     *
     * @return Auth
     */
    public function getService(): Auth
    {
        if (!$this->service) {
            $config = Config::instance()->get('auth.signature', []);
            $this->service = new Auth($this->parseConfig($config));
        }
        return $this->service;
    }

    /**
     * 注册配置信息
     *
     * @param array $config 配置信息
     * @return SignatureService
     */
    public function register(array $config): SignatureService
    {
        $config = $this->parseConfig($config);
        $this->getService()->init($config);
        return $this;
    }

    /**
     * 获取应用信息
     *
     * @param string $app_id    应用ID
     * @throws \mon\auth\exception\APIException
     * @return array
     */
    public function getAppInfo(string $app_id): array
    {
        return $this->getService()->getAppInfo($app_id);
    }

    /**
     * 创建签名请求数据
     *
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param array $data       需要签名的数据
     * @throws \mon\auth\exception\APIException
     * @return array
     */
    public function create(string $app_id, string $secret, array $data = []): array
    {
        return $this->getService()->create($app_id, $secret, $data);
    }

    /**
     * 结合Dao数据创建API签名
     *
     * @param string $app_id    应用ID
     * @param array $data       需要签名的数据
     * @throws \mon\auth\exception\APIException
     * @return array
     */
    public function createToken(string $app_id, array $data = []): array
    {
        return $this->getService()->createToken($app_id, $data);
    }

    /**
     * 验证签名
     *
     * @param string $secret    应用秘钥
     * @param array $data       签名数据
     * @throws \mon\auth\exception\APIException
     * @return boolean
     */
    public function check(string $secret, array $data): bool
    {
        return $this->getService()->check($secret, $data);;
    }

    /**
     * 验证签名
     *
     * @param array $data   签名数据
     * @throws \mon\auth\exception\APIException
     * @return boolean
     */
    public function checkToken(array $data): bool
    {
        return $this->getService()->checkToken($data);;
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
