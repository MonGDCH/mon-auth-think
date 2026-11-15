<?php

namespace mon\auth\api;

use mon\auth\api\contract\Dao;
use mon\auth\api\contract\Driver;
use mon\auth\exception\ApiException;
use mon\auth\api\contract\ApiAuth as ApiAuthInterface;

/**
 * API鉴权服务基类
 *
 * 单例能力由子类的 mon\util\Instance trait 提供
 *
 * @method static static createInstance(array $args = [], bool $isNew = false) 创建/获取单例实例
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
abstract class ApiAuth implements ApiAuthInterface
{
    /**
     * 初始化标志
     *
     * @var boolean
     */
    protected $init = false;

    /**
     * 驱动实例
     *
     * @var Driver
     */
    protected $driver;

    /**
     * Dao实例
     *
     * @var Dao
     */
    protected $dao;

    /**
     * 配置信息
     *
     * @var array
     */
    protected $config = [];

    /**
     * 默认配置快照
     *
     * 构造时从 $config 属性的初始值复制，作为 init() 每次重建配置的基准。
     * 缺少该基准时，常驻进程（Workerman/Swoole）内多次 init() 会把上一次请求的配置累积进来。
     *
     * @var array
     */
    protected $default_config = [];

    /**
     * 构造方法
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config = [])
    {
        // 记录默认配置快照
        $this->default_config = $this->config;
        if (!empty($config)) {
            $this->init($config);
        }
    }

    /**
     * 初始化
     *
     * 以默认配置为基准合并入参后重建驱动与Dao，不继承上一次调用残留的配置，
     * 保证常驻进程内不同请求/不同应用之间互不污染。
     *
     * @param array $config 配置信息
     * @return ApiAuthInterface
     */
    public function init(array $config = []): ApiAuthInterface
    {
        $this->applyConfig($this->mergeConfig($this->default_config, $config));

        return $this;
    }

    /**
     * 增量设置配置
     *
     * 在当前生效配置的基础上合并，用于只覆盖个别配置项（如运行期注入 nonce_store）
     *
     * @param array $config 配置信息
     * @return ApiAuthInterface
     */
    public function setConfig(array $config = []): ApiAuthInterface
    {
        $this->applyConfig($this->mergeConfig($this->config, $config));

        return $this;
    }

    /**
     * 获取单例实例并应用配置
     *
     * 带配置调用时会以「默认配置 + 入参」重新初始化，不会像 mon\util\Instance 的默认行为那样
     * 静默忽略后续传入的参数（常驻进程内会表现为首个请求的盐/Dao 被固定下来）。
     *
     * 注意：子类通过 mon\util\Instance trait 提供的 instance() 会覆盖父类的同名方法，
     * 因此各子类需要在自己的 instance() 中调用本方法（见 SignatureAuth、AccessTokenAuth）。
     *
     * @param array $config 配置信息
     * @return static
     */
    protected static function resolveInstance(array $config)
    {
        $instance = static::createInstance([]);
        if (!empty($config)) {
            $instance->init($config);
        }

        return $instance;
    }

    /**
     * 创建独立的服务实例
     *
     * @param array $config 配置信息
     * @return static
     */
    public static function newInstance(array $config = [])
    {
        return new static($config);
    }

    /**
     * 应用配置并重建驱动、Dao
     *
     * @param array $config 完整配置
     * @return void
     */
    protected function applyConfig(array $config)
    {
        // 先失效当前状态：初始化失败时不应继续持有旧驱动/旧配置对外提供服务
        $this->init = false;
        $this->config = $config;
        // 初始化API驱动
        $this->initDriver();
        // 初始化数据驱动
        $this->initDao();
        // 记录标志
        $this->init = true;
    }

    /**
     * 递归合并配置
     *
     * 子配置（field、dao、dao.construct 等）逐层合并，避免调用方只传局部子项时覆盖掉整组默认值，
     * 导致后续读取到未定义的键。
     *
     * @param array $base    基准配置
     * @param array $config  待合并配置
     * @return array
     */
    protected function mergeConfig(array $base, array $config): array
    {
        foreach ($config as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = $this->mergeConfig($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * 初始化API驱动
     *
     * @return void
     */
    abstract protected function initDriver();

    /**
     * 获取驱动实例
     *
     * @return Driver
     */
    abstract public function getDriver(): Driver;

    /**
     * 初始化Dao驱动
     *
     * @throws ApiException
     * @return void
     */
    protected function initDao()
    {
        $config = $this->getConfig('dao');
        if (!is_array($config)) {
            throw new ApiException('Dao配置必须为数组', ApiException::DAO_NOT_SUPPORT);
        }
        $driver = $config['driver'] ?? '';
        if (!is_string($driver) || !class_exists($driver) || !is_subclass_of($driver, Dao::class)) {
            throw new ApiException('Dao驱动未实现接口[' . Dao::class . ']', ApiException::DAO_NOT_SUPPORT);
        }

        $construct = $config['construct'] ?? [];
        if (!is_array($construct)) {
            throw new ApiException('Dao构造参数必须为数组', ApiException::DAO_NOT_SUPPORT);
        }
        $this->dao = new $driver($construct);
    }

    /**
     * 获取Dao实例
     *
     * @return Dao
     */
    public function getDao(): Dao
    {
        return $this->dao;
    }

    /**
     * 是否初始化
     *
     * @return boolean
     */
    public function isInit(): bool
    {
        return $this->init;
    }

    /**
     * 获取配置信息
     *
     * @return mixed
     */
    public function getConfig(string $field = '')
    {
        if (!empty($field)) {
            return $this->config[$field] ?? null;
        }

        return $this->config;
    }

    /**
     * 获取应用信息
     *
     * @param string $app_id    应用ID
     * @return array
     */
    public function getAppInfo(string $app_id): array
    {
        // 获取应用信息
        $info = $this->getDao()->getInfo($app_id);
        if (!$info) {
            throw new ApiException('APPID不存在', ApiException::APPID_NOT_FOUND);
        }
        if (!$this->getDao()->effect($info)) {
            throw new ApiException('APPID无效', ApiException::APPID_STATUS_ERROR);
        }
        if (!$this->getDao()->expire($info)) {
            throw new ApiException('APPID已过期', ApiException::APPID_TIME_INVALID);
        }

        return $info;
    }
}
