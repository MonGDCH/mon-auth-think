<?php

declare(strict_types=1);

namespace mon\auth\rbac\dao;

use mon\thinkORM\Dao;
use mon\auth\rbac\Auth;
use mon\auth\rbac\Validate;
use mon\auth\exception\RbacException;

/**
 * 用户-角色关联模型
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.1   优化代码
 */
class Access extends Dao
{
    /**
     * Auth实例
     *
     * @var Auth
     */
    protected $auth;

    /**
     * 验证器
     *
     * @var Validate
     */
    protected $validate = Validate::class;

    /**
     * 自动写入时间戳
     *
     * @var boolean
     */
    protected $autoWriteTimestamp = true;

    /**
     * 自动写入时间戳格式，空则直接写入时间戳
     *
     * @var string
     */
    protected $autoTimeFormat = '';

    /**
     * 构造方法
     *
     * @param Auth $auth Auth实例
     */
    public function __construct(Auth $auth)
    {
        if (!$auth->isInit()) {
            throw new RbacException('权限服务未初始化', RbacException::RBAC_AUTH_INIT_ERROR);
        }
        $this->auth = $auth;
        $this->table = $this->auth->getConfig('auth_role_access');
        $this->autoTimeFormat = $this->auth->getConfig('time_format');
    }

    /**
     * 获取用户所在角色
     *
     * @param string|integer $uid  用户ID
     * @return array
     */
    public function getUserRole($uid): array
    {
        return $this->alias('a')->join($this->auth->getConfig('auth_role') . ' b', 'a.gid=b.id')
            ->field(['a.uid', 'a.gid', 'b.id', 'b.pid', 'b.title', 'b.rules'])
            ->where('a.uid', $uid)->where('b.status', $this->auth->getConfig('effective_status'))->all();
    }

    /**
     * 创建角色用户关联
     *
     * @param array $option 请求参数
     * @param array $ext    扩展写入字段
     * @return boolean
     */
    public function bind(array $option, array $ext = []): bool
    {
        $check = $this->validate()->scope('access_bind')->data($option)->check();
        if (!$check) {
            $this->error = $this->validate()->getError();
            return false;
        }

        if ($this->where('gid', $option['gid'])->where('uid', $option['uid'])->get()) {
            $this->error = '用户已关联角色，请勿重复关联';
            return false;
        }

        $info = array_merge($ext, ['uid' => $option['uid'], 'gid' => $option['gid']]);
        $save = $this->save($info, true);
        if (!$save) {
            $this->error = '关联用户角色失败';
            return false;
        }

        return true;
    }

    /**
     * 解除角色组绑定
     *
     * @see 此操作为删除操作，请谨慎使用
     * @param array $option 请求参数
     * @return boolean
     */
    public function unbind(array $option): bool
    {
        $check = $this->validate()->scope('access_unbind')->data($option)->check();
        if (!$check) {
            $this->error = $this->validate()->getError();
            return false;
        }

        $info = $this->where('gid', $option['gid'])->where('uid', $option['uid'])->get();
        if (!$info) {
            $this->error = '用户未绑定角色';
            return false;
        }

        $del = $this->where('gid', $option['gid'])->where('uid', $option['uid'])->limit(1)->delete();
        if (!$del) {
            $this->error = '解除角色组绑定失败';
            return false;
        }

        return true;
    }

    /**
     * 修改角色用户关联
     *
     * @param array $option 请求参数
     * @param array $ext    扩展写入字段
     * @return boolean
     */
    public function modify(array $option, array $ext = []): bool
    {
        $check = $this->validate()->scope('access_modify')->data($option)->check();
        if (!$check) {
            $this->error = $this->validate()->getError();
            return false;
        }
        if ($option['new_gid'] == $option['gid']) {
            $this->error = '新角色与旧角色相同';
            return false;
        }

        $info = $this->where('uid', $option['uid'])->where('gid', $option['gid'])->get();
        if (!$info) {
            $this->error = '用户未绑定原角色';
            return false;
        }
        $exists = $this->where('uid', $option['uid'])->where('gid', $option['new_gid'])->get();
        if ($exists) {
            $this->error = '用户已绑定新角色，请勿重复绑定';
            return false;
        }

        $info = array_merge($ext, ['uid' => $option['uid'], 'gid' => $option['new_gid']]);
        $save = $this->where(['uid' => $option['uid'], 'gid' => $option['gid']])->save($info);
        if (!$save) {
            $this->error = '修改用户角色失败';
            return false;
        }

        return true;
    }
}
