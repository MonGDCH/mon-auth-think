<?php

namespace mon\auth\rbac\dao;

use Throwable;
use mon\util\Tree;
use mon\thinkORM\Dao;
use mon\auth\rbac\Auth;
use mon\auth\rbac\Validate;
use mon\auth\exception\RbacDaoException;
use mon\auth\rbac\UpdateChildrenService;
use mon\auth\rbac\dao\concern\FreshValidator;

/**
 * 角色模型
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.1   优化代码
 */
class Role extends Dao
{
    use FreshValidator;

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
    protected $autoWriteTimestamp = false;

    /**
     * 构造方法
     *
     * @param Auth $auth Auth实例
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
        $this->table = $this->auth->getConfig('auth_role');
        $this->autoWriteTimestamp = $this->auth->getConfig('write_time');
        $this->autoTimeFormat = $this->auth->getConfig('time_format');
        $this->createTime = $this->auth->getConfig('create_time');
        $this->updateTime = $this->auth->getConfig('update_time');
    }

    /**
     * 创建角色
     *
     * @param array $option 角色参数
     * @param array $ext    扩展写入字段
     * @throws RbacDaoException
     * @return integer
     */
    public function add(array $option, array $ext = []): int
    {
        $validate = $this->validate();
        $check = $validate->scope('role_add')->data($option)->check();
        if (!$check) {
            throw new RbacDaoException('新增角色参数错误：' . $validate->getError());
        }
        $pid = $option['pid'];
        $pids = '0';
        $status = $option['status'];
        $rules = array_unique($option['rules']);
        // 去除重复的规则
        sort($rules);
        // 存在父级角色，子级角色权限规则必须包含在父级权限规则中
        if ($pid > 0) {
            $parentInfo = $this->where('id', $pid)->get();
            if (!$parentInfo) {
                throw new RbacDaoException('新增角色失败： 父级权限角色不存在');
            }
            // 比较状态
            $invalid_status = $this->auth->getConfig('invalid_status');
            if ($parentInfo['status'] == $invalid_status && $status != $invalid_status) {
                throw new RbacDaoException('新增角色失败： 父级权限角色为不可用状态下，子级角色必须为不可用状态');
            }

            // 比对规则，判断是否越权
            $parentRules = explode(',', (string) $parentInfo['rules']);
            sort($parentRules);
            if ($this->isUltraVires($parentRules, $rules)) {
                throw new RbacDaoException('新增角色失败： 角色权限存在越权父级角色，请检查权限');
            }

            $pids = $parentInfo['pids'] . ',' . $pid;
        }

        // 记录角色信息
        $info = array_merge($ext, [
            'title' => $option['title'],
            'pid'   => $pid,
            'pids'  => $pids,
            'rules' => implode(',', $rules),
            'status' => $status
        ]);
        $id = $this->save($info, true, true);
        if (!$id) {
            throw new RbacDaoException('新增角色失败： 创建权限角色失败');
        }

        return intval($id);
    }

    /**
     * 修改角色信息
     *
     * @param array $option 角色参数
     * @param array $ext    扩展写入字段
     * @throws RbacDaoException
     * @return boolean
     */
    public function modify(array $option, array $ext = []): bool
    {
        $validate = $this->validate();
        $check = $validate->scope('role_modify')->data($option)->check();
        if (!$check) {
            throw new RbacDaoException('修改角色信息参数错误：' . $validate->getError());
        }
        // 获取数据
        $info = $this->where(['id' => $option['id']])->get();
        if (!$info) {
            throw new RbacDaoException('修改角色信息参数错误：角色不存在');
        }
        $status = $option['status'];
        $idx = $option['id'];
        $pid = $option['pid'];
        $pids = strval($pid > 0 ? $info['pids'] : $pid);
        $rules = array_unique($option['rules']);
        sort($rules);

        // 判断是否减少了权限
        $old_rules = explode(',', $info['rules']);
        sort($old_rules);
        $removedPermissions = $this->getRemovedPermissions($old_rules, $rules);

        // 是否需要更新后代状态为无效
        $updateChildrenInvalidStatus = $info['status'] != $status && $status == $this->auth->getConfig('invalid_status');
        // 是否需要更新pids
        $updatePids = $info['pid'] != $pid;

        // 存在父级角色时，无论是否修改父级，均需校验：父级存在、父级状态、子级权限不可越出父级权限范围、不可形成循环层级
        if ($pid > 0) {
            $parentInfo = $this->where('id', $pid)->field(['id', 'pid', 'pids', 'rules', 'status'])->get();
            if (!$parentInfo) {
                throw new RbacDaoException('修改角色信息失败： 父级权限角色不存在');
            }
            // 禁止将角色挂到自己或自己的后代角色下，避免形成循环层级
            if ($pid == $idx || in_array($idx, explode(',', (string) $parentInfo['pids']))) {
                throw new RbacDaoException('修改角色信息失败： 父级角色不能是自己或自己的后代角色');
            }
            // 比较状态
            $invalid_status = $this->auth->getConfig('invalid_status');
            if ($parentInfo['status'] == $invalid_status && $status != $invalid_status) {
                throw new RbacDaoException('修改角色信息失败： 父级权限角色为不可用状态下，子级角色必须为不可用状态');
            }

            // 比对规则，判断是否越权
            $parentRules = explode(',', (string) $parentInfo['rules']);
            sort($parentRules);
            if ($this->isUltraVires($parentRules, $rules)) {
                throw new RbacDaoException('修改角色信息失败： 角色权限存在越权父级角色，请检查权限');
            }

            // 修改了父级角色时，需要更新pids
            if ($updatePids) {
                $pids = $parentInfo['pids'] . ',' . $pid;
            }
        }

        // 更新数据（开启事务后再读取子树快照并计算后代更新，缩小并发窗口，快照过期风险由事务内锁复核兜底）
        $this->startTrans();
        try {
            // 更新后代数据
            $updateChildrenData = [];
            // 传入Dao的连接配置，保证子树快照、加锁与批量更新在同一连接/事务内
            $sdk = new UpdateChildrenService($this->table, 'id', 'pid', 'pids', $this->getConnection());
            if ($removedPermissions || $updateChildrenInvalidStatus || $updatePids) {
                // 更新后代数据服务
                $childrens = $sdk->getTree()->getChildren($idx);
                // 修改了父级角色
                if ($updatePids) {
                    // 更新pids
                    $childrenTree = (new Tree(['root' => $idx]))->data($childrens)->getTree();
                    $descendantList = $sdk->getDescendantList($childrenTree, $pids);
                    $updateChildrenData = array_column($descendantList, null, 'id');
                }

                foreach ($childrens as $child) {
                    $key = $child['id'];
                    // 更新状态
                    if ($updateChildrenInvalidStatus) {
                        $updateChildrenData[$key]['id'] = $key;
                        $updateChildrenData[$key]['status'] = $status;
                    }
                    // 判断是否修改减少了权限规则，如果减少了权限规则，需要更新子级角色的权限规则
                    if ($removedPermissions) {
                        $child_rules = explode(',', $child['rules']);
                        $new_child_rules = array_diff($child_rules, $removedPermissions);
                        // 判断是否存在修改
                        if (count($new_child_rules) != count($child_rules)) {
                            sort($new_child_rules);
                            $updateChildrenData[$key]['id'] = $key;
                            $updateChildrenData[$key]['rules'] = implode(',', $new_child_rules);
                        }
                    }
                }
            }
            // 并发防护：事务内加锁重读并复核，前面校验阶段读取的数据可能已被并发修改
            $lockedInfo = $this->where(['id' => $idx])->lock(true)->get();
            if (!$lockedInfo
                || $lockedInfo['pid'] != $info['pid']
                || $lockedInfo['status'] != $info['status']
                || $lockedInfo['rules'] != $info['rules']
            ) {
                throw new RbacDaoException('修改角色信息失败： 角色数据已发生变更，请刷新后重试');
            }

            // 修改角色信息
            $modifyInfo = array_merge($ext, [
                'title'     => $option['title'],
                'pid'       => $pid,
                'pids'      => $pids,
                'rules'     => implode(',', $rules),
                'status'    => $status
            ]);
            $save = $this->where(['id' => $idx])->save($modifyInfo);
            if (!$save) {
                throw new RbacDaoException('修改角色信息失败： 更新角色信息失败');
            }

            // 更新后代信息
            if (!empty($updateChildrenData)) {
                sort($updateChildrenData);
                $updateSql = $sdk->batchUpdateSql($updateChildrenData);
                $save = $this->execute($updateSql);
                if (!$save) {
                    throw new RbacDaoException('修改角色信息失败： 批量更新后代节点数据失败');
                }
            }

            $this->commit();
            return true;
        } catch (Throwable $e) {
            // 回滚事务
            $this->rollback();
            throw $e;
        }
    }

    /**
     * 判断是否越权，新增了权限
     *
     * @param array $permissions        原有的权限
     * @param array $checkPermissions   校验的权限
     * @return boolean
     */
    protected function isUltraVires(array $permissions, array $checkPermissions): bool
    {
        $intersectCount = count(array_intersect($permissions, $checkPermissions));
        return count($checkPermissions) > $intersectCount;
    }

    /**
     * 获取删除的权限，用于判断是否需要更新子级权限
     *
     * @param array $permissions        原有的权限
     * @param array $checkPermissions   校验的权限
     * @return array
     */
    protected function getRemovedPermissions(array $permissions, array $checkPermissions): array
    {
        // 构建新权限的哈希表（O(n)）
        $newMap = array_flip($checkPermissions);
        $removed = [];
        foreach ($permissions as $id) {
            if (!isset($newMap[$id])) {
                $removed[] = $id;
            }
        }

        return $removed;
    }
}
