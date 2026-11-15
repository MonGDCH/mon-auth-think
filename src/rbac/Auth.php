<?php

declare(strict_types=1);

namespace mon\auth\rbac;

use mon\util\Event;
use mon\auth\rbac\dao\Rule;
use mon\auth\rbac\dao\Role;
use mon\auth\rbac\dao\Access;
use mon\auth\exception\RbacException;

/**
 * 权限控制
 *
 * 功能特性：
 * 1，是对规则进行认证，不是对节点进行认证。用户可以把节点当作规则名称实现对节点进行认证。
 *      $auth = Auth::instance($config);  $auth->check('规则名称','用户id')
 * 2，可以同时对多条规则进行认证，并设置多条规则的关系（ true 或者 false ）
 *      $auth = Auth::instance($config);  $auth->check([规则1, 规则2], '用户id', false)
 *      第三个参数为 false 时表示，用户需要同时具有规则1和规则2的权限。 当第三个参数为 true 时，表示用户值需要具备其中一个条件即可。默认为 true
 * 3，一个用户可以属于多个用户组(auth_role_access 定义了用户所属用户组)。我们需要设置每个用户组拥有哪些规则(auth_role 定义了用户组权限)
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Auth
{
    /**
     * 缓存的模型实例
     *
     * @var array
     */
    protected $daos = [];

    /**
     * 权限DB表默认配置
     *
     * @var array
     */
    protected $config = [
        // 角色表               
        'auth_role'         => 'auth_role',
        // 用户-角色关系表     
        'auth_role_access'  => 'auth_access',
        // 权限规则表    
        'auth_rule'         => 'auth_rule',
        // 超级管理员权限标志       
        'admin_mark'        => '*',
        // 有效的状态值
        'effective_status'  => 1,
        // 无效的状态值
        'invalid_status'    => 0,
        // 是否自动写入时间戳
        'write_time'        => false,
        // 时间写入格式化
        'time_format'       => 'Y-m-d H:i:s',
        // 创建时间字段名
        'create_time'       => 'create_time',
        // 更新时间字段名
        'update_time'       => 'update_time',
        // 是否触发事件
        'trigger_event'     => false,
    ];

    /**
     * 默认配置快照
     *
     * 构造时从 $config 属性的初始值复制，作为 init() 重建配置的基准，
     * 避免常驻进程内多次初始化把上一次的配置累积进来。
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
        $this->default_config = $this->config;
        if (!empty($config)) {
            $this->init($config);
        }
    }

    /**
     * 初始化方法
     *
     * 以默认配置为基准合并入参，不继承上一次调用残留的配置
     *
     * @param array $config 配置信息
     * @return Auth
     */
    public function init(array $config = []): Auth
    {
        $this->config = array_merge($this->default_config, $config);
        // 配置可能变更了表名/时间戳字段，必须丢弃已缓存Dao
        $this->resetDao();

        return $this;
    }

    /**
     * 设置配置
     *
     * 在当前配置基础上增量合并，用于只覆盖个别配置项
     *
     * @param array $config 设置配置信息
     * @return Auth
     */
    public function setConfig(array $config): Auth
    {
        $this->config = array_merge($this->config, $config);
        $this->resetDao();

        return $this;
    }

    /**
     * 重置Dao实例缓存
     *
     * Dao在构造时快照了表名与时间戳字段，常驻进程下继续复用旧实例会读写错表
     *
     * @return void
     */
    protected function resetDao()
    {
        $this->daos = [];
    }

    /**
     * 获取配置信息
     *
     * @param string $key   配置索引
     * @return mixed
     */
    public function getConfig($key = '')
    {
        if (!empty($key)) {
            return $this->config[$key] ?? null;
        }

        return $this->config;
    }

    /**
     * 校验权限
     *
     * @param  string|array $rule       需要验证的规则列表,支持字符串的单个权限规则或索引数组多个权限规则
     * @param  integer|string   $uid    认证用户的id
     * @param  boolean  $relation       如果为 true 表示满足任一条规则即通过验证;如果为 false 则表示需满足所有规则才能通过验证
     * @throws RbacException
     * @return boolean
     */
    public function check($rule, $uid, bool $relation = true): bool
    {
        // 先规范化待验证规则：避免参数非法时也去查库，同时修复空规则列表直接放行(fail-open)的问题
        $rule = $this->normalizeRule($rule);
        if (empty($rule)) {
            // 规则列表为空属于调用方错误，按无权限处理（fail-closed）
            $this->triggerEvent($uid, $rule, 'failed', [], $relation);
            return false;
        }

        // 获取用户需要验证的所有有效规则列表
        $authList = $this->getAuthList($uid);
        if (in_array($this->config['admin_mark'], (array) $authList, true)) {
            // 触发rbac权限验证事件
            $this->triggerEvent($uid, $rule, 'admin', $authList, $relation);
            // 具备所有权限
            return true;
        }

        // 保存验证通过的规则名
        $list = [];
        // 验证权限
        foreach ($authList as $auth) {
            if (in_array($auth, $rule, true)) {
                $list[] = $auth;
            }
        }
        // 判断验证规则
        if ($relation == true && !empty($list)) {
            // 触发rbac权限验证事件
            $this->triggerEvent($uid, $rule, 'check', $list, $relation);
            return true;
        }
        $diff = array_diff($rule, $list);
        if ($relation == false && empty($diff)) {
            // 触发rbac权限验证事件
            $this->triggerEvent($uid, $rule, 'diff', $diff, $relation);
            return true;
        }

        // 触发rbac权限验证不通过事件
        $this->triggerEvent($uid, $rule, 'failed', $diff, $relation);
        return false;
    }

    /**
     * 规范化待验证的规则
     *
     * @param  string|array $rule 待验证的规则
     * @throws RbacException
     * @return array
     */
    protected function normalizeRule($rule): array
    {
        if (is_string($rule) || is_int($rule)) {
            $rule = [(string) $rule];
        } else if (!is_array($rule)) {
            throw new RbacException('不支持的规则类型，只支持string、array类型', RbacException::RBAC_RULE_NOT_SUPPORT);
        }

        $result = [];
        foreach ($rule as $item) {
            if (!is_string($item) && !is_int($item)) {
                throw new RbacException('规则列表只支持string、int类型', RbacException::RBAC_RULE_NOT_SUPPORT);
            }
            $item = strtolower(trim((string) $item));
            if ($item === '') {
                continue;
            }
            $result[$item] = $item;
        }

        return array_values($result);
    }

    /**
     * 获取角色权限节点对应权限
     *
     * @param  integer|string $uid 用户ID
     * @return array
     */
    public function getAuthIds($uid): array
    {
        // 获取规则节点
        $ids = [];
        $dao = $this->getAccessDao();
        $roles = $dao->getUserRole($uid);
        foreach ($roles as $v) {
            if (!$v || !trim((string) ($v['rules'] ?? ''), ',')) {
                continue;
            }
            $ids = array_merge($ids, array_map('trim', explode(',', trim($v['rules'], ','))));
        }

        return array_unique($ids);
    }

    /**
     * 获取用户权限规则列表
     *
     * @param  integer|string $uid 用户ID
     * @return array
     */
    public function getAuthList($uid): array
    {
        // 获取规则节点
        $ids = $this->getAuthIds($uid);
        if (empty($ids)) {
            return [];
        }
        $authList = [];
        // 判断是否拥有所有权限
        if (in_array($this->config['admin_mark'], $ids, true)) {
            $authList[] = $this->config['admin_mark'];
            return $authList;
        }
        // 获取权限规则（复用已查得的规则ID，同一请求内不再重复查询访问表）
        $rules = $this->getRule($uid, $ids);
        foreach ($rules as $rule) {
            if (!$rule) {
                continue;
            }
            $authList[] = strtolower($rule['rule']);
        }

        return array_unique($authList);
    }

    /**
     * 获取权限规则
     *
     * @param integer|string $uid  用户ID
     * @param array|null $ids     规则ID列表，传入时不再重复查询（供getAuthList复用同一请求内的查询结果）
     * @return array
     */
    public function getRule($uid, ?array $ids = null): array
    {
        // 获取规则节点
        $ids = $ids ?? $this->getAuthIds($uid);
        if (empty($ids)) {
            return [];
        }
        // 构造查询条件
        $dao = $this->getRuleDao();
        $query = $dao->field(['id', 'pid', 'rule', 'title'])->where('status', $this->config['effective_status']);
        if (!in_array($this->config['admin_mark'], $ids, true)) {
            // 规则ID统一转为整型，避免脏数据（如非数字）导致 MySQL 隐式转换告警或匹配到 id=0
            $ids = array_values(array_filter(array_map('intval', $ids), function ($id) {
                return $id > 0;
            }));
            if (empty($ids)) {
                return [];
            }
            $query->where('id', 'IN', $ids);
        }
        // 获取权限规则
        return $query->all();
    }

    /**
     * 用户-角色关联Dao模型
     *
     * @param boolean $cache    是否缓存
     * @return Access
     */
    public function getAccessDao(bool $cache = true): Access
    {
        // 获取实例
        $name = 'access';
        if ($cache && isset($this->daos[$name])) {
            return $this->daos[$name];
        }
        $this->daos[$name] = new Access($this);
        return $this->daos[$name];
    }

    /**
     * 获取角色Dao模型
     *
     * @param boolean $cache    是否缓存
     * @return Role
     */
    public function getRoleDao(bool $cache = true): Role
    {
        // 获取实例
        $name = 'role';
        if ($cache && isset($this->daos[$name])) {
            return $this->daos[$name];
        }
        $this->daos[$name] = new Role($this);
        return $this->daos[$name];
    }

    /**
     * 获取规则Dao模型
     *
     * @param boolean $cache
     * @return Rule
     */
    public function getRuleDao(bool $cache = true): Rule
    {
        // 获取实例
        $name = 'rule';
        if ($cache && isset($this->daos[$name])) {
            return $this->daos[$name];
        }
        $this->daos[$name] = new Rule($this);
        return $this->daos[$name];
    }

    /**
     * 触发验证事件
     *
     * @param string|integer $uid   用户ID
     * @param string|array   $rule  验证规则名称
     * @param string $type  验证事件类型
     * @param array $auth   权限列表
     * @param boolean $relation 是否需要满足全部规则通过才通过
     * @return void
     */
    protected function triggerEvent($uid, $rule, string $type, array $auth, bool $relation)
    {
        if ($this->config['trigger_event']) {
            Event::instance()->trigger('rbac_check', [
                'type' => $type,
                'auth' => $auth,
                'uid'  => $uid,
                'rule' => $rule,
                'relation' => $relation
            ]);
        }
    }
}
