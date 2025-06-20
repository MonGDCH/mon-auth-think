<?php

declare(strict_types=1);

namespace support\auth;

use mon\env\Config;
use mon\util\Instance;
use mon\auth\rbac\Auth;

/**
 * RBAC权限控制服务
 * 
 * @method array getAuthIds(integer|string $uid) 获取角色权限节点对应权限
 * @method array getAuthList(integer|string $uid) 获取用户权限规则列表
 * @method array getRule(integer|string $uid) 获取权限规则
 * @method mixed dao(string $name, boolean $cache = true) 获取权限操作Dao模型
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class RbacService
{
    use Instance;

    /**
     * 缓存服务对象
     *
     * @var Auth
     */
    protected $service;

    /**
     * 私有构造方法
     */
    protected function __construct()
    {
        $config = Config::instance()->get('auth.rbac', []);
        $this->service = new Auth($$config);
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
     * @return RbacService
     */
    public function register(array $config): RbacService
    {
        $this->getService()->init($config);
        return $this;
    }

    /**
     * 校验权限，重载优化Auth类的check方法
     *
     * @param  string|array     $name     需要验证的规则列表,支持字符串的单个权限规则或索引数组多个权限规则
     * @param  integer|string   $uid      认证用户的id
     * @param  boolean 		    $relation 如果为 true 表示满足任一条规则即通过验证;如果为 false 则表示需满足所有规则才能通过验证
     * @throws \mon\auth\exception\RbacException
     * @return boolean           	  成功返回true，失败返回false
     */
    public function check($name, $uid, bool $relation = true): bool
    {
        return $this->getService()->check($name, $uid, $relation);
    }

    /**
     * 回调服务
     *
     * @param string $name      方法名
     * @param mixed $arguments 参数列表
     * @return mixed
     */
    public function __call(string $name, $arguments)
    {
        return call_user_func_array([$this->getService(), $name], (array) $arguments);
    }
}
