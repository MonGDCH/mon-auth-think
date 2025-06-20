<?php

declare(strict_types=1);

namespace mon\auth\rbac\dao;

use mon\thinkORM\Dao;
use mon\auth\rbac\Auth;
use mon\auth\rbac\Validate;
use mon\auth\exception\RbacException;

/**
 * 用户-角色关联Dao模型
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
     * @throws RbacException
     * @return boolean
     */
    public function bind(array $option, array $ext = []): bool
    {
        $check = $this->validate()->scope('access_bind')->data($option)->check();
        if (!$check) {
            throw new RbacException('关联角色用户参数错误：' . $this->validate()->getError());
        }

        if ($this->where('gid', $option['gid'])->where('uid', $option['uid'])->get()) {
            throw new RbacException('关联角色用户失败： 用户已关联角色，请勿重复关联');
        }

        $info = array_merge($ext, ['uid' => $option['uid'], 'gid' => $option['gid']]);
        $save = $this->save($info, true);
        if (!$save) {
            throw new RbacException('关联角色用户失败： 关联用户角色失败');
        }

        return true;
    }

    /**
     * 解除角色组绑定
     *
     * @see 此操作为删除操作，请谨慎使用
     * @param array $option 请求参数
     * @throws RbacException
     * @return boolean
     */
    public function unbind(array $option): bool
    {
        $check = $this->validate()->scope('access_unbind')->data($option)->check();
        if (!$check) {
            throw new RbacException('解除角色用户关联参数错误：' . $this->validate()->getError());
        }

        $info = $this->where('gid', $option['gid'])->where('uid', $option['uid'])->get();
        if (!$info) {
            throw new RbacException('解除角色用户关联失败： 用户未绑定角色');
        }

        $del = $this->where('gid', $option['gid'])->where('uid', $option['uid'])->limit(1)->delete();
        if (!$del) {
            throw new RbacException('解除角色用户关联失败： 解除角色组绑定失败');
        }

        return true;
    }

    /**
     * 修改角色用户关联
     *
     * @param array $option 请求参数
     * @param array $ext    扩展写入字段
     * @throws RbacException
     * @return boolean
     */
    public function modify(array $option, array $ext = []): bool
    {
        $check = $this->validate()->scope('access_modify')->data($option)->check();
        if (!$check) {
            throw new RbacException('修改角色用户关联参数错误：' . $this->validate()->getError());
        }
        if ($option['new_gid'] == $option['gid']) {
            throw new RbacException('修改角色用户关联参数错误： 新角色与旧角色相同');
        }

        $info = $this->where('uid', $option['uid'])->where('gid', $option['gid'])->get();
        if (!$info) {
            throw new RbacException('修改角色用户关联参数错误： 用户未绑定原角色');
        }
        $exists = $this->where('uid', $option['uid'])->where('gid', $option['new_gid'])->get();
        if ($exists) {
            throw new RbacException('修改角色用户关联参数错误： 用户已绑定新角色，请勿重复绑定');
        }

        $info = array_merge($ext, ['uid' => $option['uid'], 'gid' => $option['new_gid']]);
        $save = $this->where(['uid' => $option['uid'], 'gid' => $option['gid']])->save($info);
        if (!$save) {
            throw new RbacException('修改角色用户关联失败： 修改用户角色失败');
        }

        return true;
    }
}
