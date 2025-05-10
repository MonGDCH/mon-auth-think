<?php

declare(strict_types=1);

namespace mon\auth\rbac;

use Throwable;
use mon\util\Tree;
use mon\thinkORM\Db;
use mon\auth\exception\RbacException;

/**
 * 批量更新所有后代数据服务
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class UpdateChildrenService
{
    /**
     * ID字段名
     *
     * @var string
     */
    protected $id = 'id';

    /**
     * pid字段名
     *
     * @var string
     */
    protected $pid = 'pid';

    /**
     * pid链表字段名
     *
     * @var string
     */
    protected $pids = 'pids';

    /**
     * 操作表名
     *
     * @var string
     */
    protected $table = '';

    /**
     * 构造方法
     *
     * @param string $table 操作表名
     * @param string $id ID字段名
     * @param string $pid pid字段名
     * @param string $pids pid链表字段名
     */
    public function __construct(string $table, string $id = 'id', string $pid = 'pid', string $pids = 'pids')
    {
        $this->table = $table;
        $this->id = $id;
        $this->pid = $pid;
        $this->pids = $pids;
    }

    /**
     * 更新节点及其后代的 pids 字段
     *
     * @param integer $nodeId 被更新节点的ID
     * @param integer $newParentId 新的父节点ID
     * @throws RbacException
     * @return boolean
     */
    public function updatePids(int $nodeId, int $newParentId): bool
    {
        Db::startTrans();
        try {
            // 获取父节点的 pids
            $parent = Db::table($this->table)->field([$this->id, $this->pid, $this->pids])->where($this->id, $newParentId)->find();
            if (!$parent) {
                throw new RbacException("父节点ID({$newParentId})不存在");
            }
            $parentPids = $parent[$this->pids] . ',' . $newParentId;

            // 更新当前节点的 pid 和 pids
            $save = Db::table($this->table)->where($this->id, $nodeId)->update([
                $this->pid => $newParentId,
                $this->pids => $parentPids
            ]);
            if (!$save) {
                throw new RbacException("更新节点 {$nodeId} 的 {$this->pids} 字段失败");
            }

            // 递归获取所有后代节点并更新其 pids
            $this->updateChildrenPids($nodeId, $parentPids);

            Db::commit();
            return true;
        } catch (Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 更新指定节点后代pids值
     *
     * @param integer $nodeId    节点ID
     * @param string $pids       节点的pids值
     * @throws RbacException
     * @return boolean
     */
    public function updateChildrenPids(int $nodeId, string $pids): bool
    {
        // 递归获取所有后代节点并更新其 pids
        $children = $this->getChildrenTree($nodeId);
        $descendants = $this->getDescendantList($children, $pids);
        // 批量更新后代节点的 pids
        if (!empty($descendants)) {
            $sql = $this->batchUpdateSql($descendants);
            $save = Db::execute($sql);
            if (!$save) {
                throw new RbacException("批量更新后代节点的 {$this->pids} 字段失败");
            }
        }

        return true;
    }

    /**
     * 更新指定节点后代pids值
     *
     * @param integer $nodeId    节点ID
     * @param string $pids       节点的pids值
     * @param integer $status    状态值
     * @throws RbacException
     * @return boolean
     */
    public function updateChildrenPidsAndStatus(int $nodeId, string $pids, int $status): bool
    {
        // 递归获取所有后代节点并更新其 pids
        $children = $this->getChildrenTree($nodeId);
        $descendants = $this->getDescendantList($children, $pids);
        foreach ($descendants as &$descendant) {
            $descendant['status'] = $status;
        }
        // 批量更新后代节点的 pids
        if (!empty($descendants)) {
            $sql = $this->batchUpdateSql($descendants);
            $save = Db::execute($sql);
            if (!$save) {
                throw new RbacException("批量更新后代节点失败");
            }
        }

        return true;
    }

    /**
     * 获取树结构对象
     *
     * @return Tree
     */
    public function getTree(): Tree
    {
        static $tree = null;
        if ($tree === null) {
            $data = Db::table($this->table)->select()->toArray();
            $sdk = new Tree(['id' => $this->id, 'pid' => $this->pid]);
            $tree = $sdk->data($data);
        }

        return $tree;
    }

    /**
     * 获取后代树结果数据
     *
     * @param integer $nodeId 节点id
     * @return array
     */
    public function getChildrenTree(int $nodeId): array
    {
        $children = $this->getTree()->getChildren($nodeId);
        return (new Tree(['id' => $this->id, 'pid' => $this->pid, 'root' => $nodeId]))->data($children)->getTree();
    }

    /**
     * 将 getChildrenTree 返回的树结构数据转换为包含 id 和 pids 的数组，并处理 pids 字段值
     *
     * @param array $data           树结构数据
     * @param string $parentPids    父节点的 pids
     * @param string $mark          后代标识
     * @return array
     */
    public function getDescendantList(array $data, string $parentPids, string $mark = 'children'): array
    {
        $result = [];
        foreach ($data as $v) {
            $childPids = $parentPids . ',' . $v[$this->pid];
            $result[] = [
                $this->id => $v[$this->id],
                $this->pids => $childPids
            ];
            $children = isset($v[$mark]) ? $v[$mark] : [];
            unset($v[$mark]);
            if ($children) {
                $result = array_merge($result, $this->getDescendantList($children, $childPids, $mark));
            }
        }
        return $result;
    }

    /**
     * 批量更新后代节点的任意字段
     *
     * @param array $data 包含 id 和需要更新字段的数组
     * @throws RbacException
     */
    public function batchUpdateSql(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        // 按 ID 分组数据，每个 ID 只保留最后一条记录
        $uniqueData = [];
        foreach ($data as $item) {
            $id = (int)$item['id'];
            $uniqueData[$id] = $item;
        }

        // 收集所有需要更新的字段（排除 id）
        $updateFields = [];
        foreach ($uniqueData as $item) {
            foreach (array_keys($item) as $field) {
                if ($field !== 'id') {
                    $updateFields[$field] = true;
                }
            }
        }

        $sql = "UPDATE `{$this->table}` SET ";
        $idList = array_keys($uniqueData);
        // 为每个需要更新的字段生成 CASE WHEN 语句
        foreach ($updateFields as $field => $_) {
            $sql .= "`{$field}` = CASE `id` ";
            foreach ($uniqueData as $id => $item) {
                if (array_key_exists($field, $item)) {
                    // 有该字段的值，使用提供的值
                    $value = $item[$field];
                    if (is_string($value)) {
                        // 字符串值需要转义
                        $value = addslashes($value);
                        $sql .= "WHEN {$id} THEN '{$value}' ";
                    } elseif (is_int($value) || is_float($value)) {
                        // 数字值直接使用
                        $sql .= "WHEN {$id} THEN {$value} ";
                    } elseif (is_bool($value)) {
                        // 布尔值转换为 0/1
                        $sql .= "WHEN {$id} THEN " . ($value ? 1 : 0) . " ";
                    } else {
                        // 其他类型（如 null）
                        $sql .= "WHEN {$id} THEN NULL ";
                    }
                } else {
                    // 没有该字段的值，保持原值（这种情况不会发生，因为已经处理过）
                    $sql .= "WHEN {$id} THEN `{$field}` ";
                }
            }
            $sql .= "END, ";
        }
        // 移除最后的逗号
        $sql = rtrim($sql, ', ') . " ";
        // 添加 WHERE 子句
        $sql .= "WHERE `id` IN (" . implode(',', $idList) . ")";
        return $sql;
    }
}
