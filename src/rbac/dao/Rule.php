<?php

namespace mon\auth\rbac\dao;

use Throwable;
use mon\thinkORM\Dao;
use mon\auth\rbac\Auth;
use mon\auth\rbac\Validate;
use mon\auth\exception\RbacException;
use mon\auth\rbac\UpdateChildrenService;

/**
 * 权限规则表
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.1   优化代码
 */
class Rule extends Dao
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
        $this->table = $this->auth->getConfig('auth_rule');
        $this->autoTimeFormat = $this->auth->getConfig('time_format');
    }

    /**
     * 新增规则
     *
     * @param array $option 规则参数
     * @param array $ext    扩展写入字段
     * @throws RbacException
     * @return integer
     */
    public function add(array $option, array $ext = []): int
    {
        $check = $this->validate()->scope('rule_add')->data($option)->check();
        if (!$check) {
            throw new RbacException('新增权限规则参数错误：' . $this->validate()->getError());
        }

        $status = $option['status'];
        $pid = $option['pid'];
        $pids = '0';
        // 存在父级规则，判断父级状态及获取父级pids
        if ($pid > 0) {
            $parentInfo = $this->where('id', $pid)->field(['id', 'pid', 'pids', 'status'])->get();
            if (!$parentInfo) {
                throw new RbacException('新增权限规则失败： 父级权限规则不存在');
            }
            // 比较状态
            $invalid_status = $this->auth->getConfig('invalid_status');
            if ($parentInfo['status'] == $invalid_status && $status != $invalid_status) {
                throw new RbacException('新增权限规则失败： 父级权限规则为不可用状态下，子级规则必须为不可用状态');
            }

            $pids = $parentInfo['pids'] . ',' . $pid;
        }

        $info = array_merge($ext, [
            'pid'       => $pid,
            'pids'      => $pids,
            'title'     => $option['title'],
            'rule'      => $option['rule'],
            'remark'    => $option['remark'] ?? '',
            'status'    => $status
        ]);
        $rule_id = $this->save($info, true, true);
        if (!$rule_id) {
            throw new RbacException('新增权限规则失败： 新增权限规则失败');
        }

        return intval($rule_id);
    }

    /**
     * 修改规则
     *
     * @param array $option 规则参数
     * @param array $ext    扩展写入字段
     * @return boolean
     */
    public function modify(array $option, array $ext = []): bool
    {
        $check = $this->validate()->scope('rule_modify')->data($option)->check();
        if (!$check) {
            throw new RbacException('修改权限规则参数错误：' . $this->validate()->getError());
        }

        $idx = $option['id'];
        $ruleInfo = $this->where(['id' => $idx])->field(['id', 'pid', 'pids', 'status'])->get();
        if (!$ruleInfo) {
            throw new RbacException('修改权限规则失败： 权限规则不存在');
        }

        $status = $option['status'];
        $pid = $option['pid'];
        $pids = $pid > 0 ? $ruleInfo['pids'] : $pid;
        // 是否需要更新后代状态为无效
        $updateChildrenInvalidStatus = $ruleInfo['status'] != $status && $status == $this->auth->getConfig('invalid_status');
        // 是否需要更新pids
        $updatePids = $ruleInfo['pid'] != $pid;
        // 存在父级规则，并且修改了父级规则，判断父级状态及获取父级pids
        if ($pid > 0 && $updatePids) {
            $parentInfo = $this->where('id', $pid)->field(['id', 'pid', 'pids', 'status'])->get();
            if (!$parentInfo) {
                throw new RbacException('修改权限规则失败： 父级权限规则不存在');
            }
            // 比较状态
            $invalid_status = $this->auth->getConfig('invalid_status');
            if ($parentInfo['status'] == $invalid_status && $status != $invalid_status) {
                throw new RbacException('修改权限规则失败： 父级权限规则为不可用状态下，子级规则必须为不可用状态');
            }

            $pids = $parentInfo['pids'] . ',' . $pid;
        }

        // 更新数据
        $this->startTrans();
        try {
            // 修改规则信息
            $modifyInfo = array_merge($ext, [
                'pid'       => $pid,
                'pids'      => $pids,
                'title'     => $option['title'],
                'rule'      => $option['rule'],
                'remark'    => $option['remark'] ?? '',
                'status'    => $status
            ]);
            $save = $this->where(['id' => $idx])->save($modifyInfo);
            if (!$save) {
                throw new RbacException('修改权限规则失败： 修改规则信息失败');
            }

            // 更新后代信息
            if ($updateChildrenInvalidStatus || $updatePids) {
                // 更新pid，需要更新所有后代的pids
                if ($updatePids) {
                    $sdk = new UpdateChildrenService($this->table);
                    // 判断是否需要更新状态为无效，更新后代pids信息
                    $save = $updateChildrenInvalidStatus ? $sdk->updateChildrenPidsAndStatus($idx, $pids, $status) : $sdk->updateChildrenPids($idx, $pids);
                } else {
                    // 只更新后代状态，使用 FIND_IN_SET 直接修改后代状态
                    $hasChildren = $this->where('FIND_IN_SET(' . $idx . ', pids)')->get();
                    $save = $hasChildren ? $this->where('FIND_IN_SET(' . $idx . ', pids)')->save(['status' => $status]) : true;
                }
                if (!$save) {
                    throw new RbacException('修改权限规则失败： 批量更新后代节点数据失败');
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
}
