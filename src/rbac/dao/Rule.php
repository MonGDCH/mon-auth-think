<?php

namespace mon\auth\rbac\dao;

use Throwable;
use mon\util\Tree;
use mon\thinkORM\Dao;
use mon\auth\rbac\Auth;
use mon\auth\rbac\Validate;
use mon\auth\exception\RbacException;

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
     * @return integer
     */
    public function add(array $option, array $ext = []): int
    {
        $check = $this->validate()->scope('rule_add')->data($option)->check();
        if (!$check) {
            $this->error = $this->validate()->getError();
            return 0;
        }

        $info = array_merge($ext, [
            'pid'       => $option['pid'],
            'title'     => $option['title'],
            'rule'      => $option['rule'],
            'remark'    => $option['remark'] ?? '',
        ]);
        $rule_id = $this->save($info, false, true);
        if (!$rule_id) {
            $this->error = '新增规则失败';
            return 0;
        }

        return $rule_id;
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
            $this->error = $this->validate()->getError();
            return false;
        }

        $idx = $option['id'];
        $status = $option['status'];
        $baseInfo = $this->where(['id' => $idx])->get();
        if (!$baseInfo) {
            $this->error = '规则信息不存在';
            return false;
        }

        if ($baseInfo['status'] != $status) {
            // 修改了状态
            $rules = $this->field(['id', 'pid', 'status', 'rule'])->all();
            if ($status == $this->auth->getConfig('effective_status')) {
                // 有效则判断当前修改父级节点及所有祖先节点是否都为有效状态。
                $parents = Tree::instance()->data($rules)->getParents($option['pid'], true);
                foreach ($parents as $v) {
                    if ($v['status'] == $this->auth->getConfig('invalid_status')) {
                        $this->error = '操作失败(祖先节点存在无效节点)';
                        return false;
                    }
                }

                // 更新
                $info = array_merge($ext, [
                    'pid'       => $option['pid'],
                    'title'     => $option['title'],
                    'rule'      => $option['rule'],
                    'remark'    => $option['remark'] ?? '',
                    'status'    => $option['status'],
                ]);
                $save = $this->where(['id' => $idx])->save($info);
                if (!$save) {
                    $this->error = '更新规则失败';
                    return false;
                }

                return true;
            } else if ($status == $this->auth->getConfig('invalid_status')) {
                // 无效，同步将所有后代节点下线
                $childrens = Tree::instance()->data($rules)->getChildrenIds($idx);
                // 更新
                $this->startTrans();
                try {
                    // 更新规则
                    $info = array_merge($ext, [
                        'pid'       => $option['pid'],
                        'title'     => $option['title'],
                        'rule'      => $option['rule'],
                        'remark'    => $option['remark'] ?? '',
                        'status'    => $option['status'],
                    ]);
                    $save = $this->where(['id' => $idx])->save($info);
                    if (!$save) {
                        $this->rollback();
                        $this->error = '更新失败';
                        return false;
                    }

                    // 下线后代
                    if (!empty($childrens)) {
                        $update_time = $this->autoTimeFormat ? date($this->autoTimeFormat, time()) : time();
                        $offline = $this->where('id', 'IN', $childrens)->update(['status' => $option['status'], 'update_time' => $update_time]);
                        if (!$offline) {
                            $this->rollback();
                            $this->error = '修改后代权限规则失败';
                            return false;
                        }
                    }

                    // 提交事务
                    $this->commit();
                    return true;
                } catch (Throwable $e) {
                    // 回滚事务
                    $this->rollback();
                    $this->error = '修改规则异常, ' . $e->getMessage();
                    return false;
                }
            }
        } else {
            // 未修改状态，直接更新
            $info = array_merge($ext, [
                'pid'       => $option['pid'],
                'title'     => $option['title'],
                'rule'      => $option['rule'],
                'remark'    => $option['remark'] ?? '',
            ]);
            $save = $this->where(['id' => $idx])->save($info);
            if (!$save) {
                $this->error = '更新失败';
                return false;
            }

            return true;
        }
    }
}
