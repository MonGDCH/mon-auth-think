<?php

namespace mon\auth\rbac\dao;

use Throwable;
use mon\util\Tree;
use mon\thinkORM\Dao;
use mon\auth\rbac\Auth;
use mon\auth\rbac\Validate;
use mon\auth\exception\RbacException;
use mon\auth\rbac\UpdateChildrenService;

/**
 * 角色模型
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.1   优化代码
 */
class Role extends Dao
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
        $this->table = $this->auth->getConfig('auth_role');
        $this->autoTimeFormat = $this->auth->getConfig('time_format');
    }

    /**
     * 创建角色
     *
     * @param array $option 角色参数
     * @param array $ext    扩展写入字段
     * @return integer
     */
    public function add(array $option, array $ext = []): int
    {
        $check = $this->validate()->scope('role_add')->data($option)->check();
        if (!$check) {
            $this->error = $this->validate()->getError();
            return 0;
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
                $this->error = '父级权限角色不存在';
                return false;
            }
            // 比较状态
            $invalid_status = $this->auth->getConfig('invalid_status');
            if ($parentInfo['status'] == $invalid_status && $status != $invalid_status) {
                $this->error = '父级权限角色为不可用状态下，子级角色必须为不可用状态';
                return false;
            }

            // 比对规则，判断是否越权
            $parentRules = explode(',', $parentInfo['rules']);
            sort($parentRules);
            if ($this->isUltraVires($parentRules, $rules)) {
                $this->error = '角色权限存在越权父级角色，请检查权限';
                return false;
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
            $this->error = '创建权限角色失败';
            return 0;
        }

        return intval($id);
    }

    /**
     * 修改角色信息
     *
     * @param array $option 角色参数
     * @param array $ext    扩展写入字段
     * @return boolean
     */
    public function modify(array $option, array $ext = []): bool
    {
        $check = $this->validate()->scope('role_modify')->data($option)->check();
        if (!$check) {
            $this->error = $this->validate()->getError();
            return false;
        }
        // 获取数据
        $info = $this->where(['id' => $option['id']])->get();
        if (!$info) {
            $this->error = '角色不存在';
            return false;
        }
        $status = $option['status'];
        $idx = $option['id'];
        $pid = $option['pid'];
        $pids = $pid > 0 ? $info['pids'] : $pid;
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

        // 修改父级角色。存在父级角色，子级角色权限规则必须包含在父级权限规则中
        if ($pid > 0 && $updatePids) {
            $parentInfo = $this->where('id', $pid)->field(['id', 'pid', 'pids', 'status'])->get();
            if (!$parentInfo) {
                $this->error = '父级权限角色不存在';
                return false;
            }
            // 比较状态
            $invalid_status = $this->auth->getConfig('invalid_status');
            if ($parentInfo['status'] == $invalid_status && $status != $invalid_status) {
                $this->error = '父级权限角色为不可用状态下，子级角色必须为不可用状态';
                return false;
            }

            // 判断是否修改了规则或者修改了父级
            $parentRules = explode(',', $parentInfo['rules']);
            sort($parentRules);
            // 比对规则
            if ($this->isUltraVires($parentRules, $rules)) {
                $this->error = '角色权限存在越权父级角色，请检查权限';
                return false;
            }

            $pids = $parentInfo['pids'] . ',' . $pid;
        }

        // 更新后代数据
        $updateChildrenData = [];
        $sdk = new UpdateChildrenService($this->table);
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
                    $updateChildrenData[$key]['status'] = $status;
                }
                // 判断是否修改减少了权限规则，如果减少了权限规则，需要更新子级角色的权限规则
                if ($removedPermissions) {
                    $child_rules = explode(',', $child['rules']);
                    $new_child_rules = array_diff($child_rules, $removedPermissions);
                    // 判断是否存在修改
                    if (count($new_child_rules) != count($child_rules)) {
                        sort($new_child_rules);
                        $updateChildrenData[$key]['rules'] = implode(',', $new_child_rules);
                    }
                }
            }
        }

        // 更新数据
        $this->startTrans();
        try {
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
                throw new RbacException('修改角色信息失败');
            }

            // 更新后代信息
            if (!empty($updateChildrenData)) {
                $updateSql = $sdk->batchUpdateSql($updateChildrenData);
                $save = $this->execute($updateSql);
                if (!$save) {
                    throw new RbacException("批量更新后代节点数据失败");
                }
            }

            $this->commit();
            return true;
        } catch (Throwable $e) {
            // 回滚事务
            $this->rollback();
            $this->error = '修改角色信息异常, ' . $e->getMessage();
            return false;
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
