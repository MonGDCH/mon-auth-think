<?php

declare(strict_types=1);

namespace mon\auth\rbac;

/**
 * 树形数据安全遍历工具
 *
 * 修复点：原实现依赖 mon\util\Tree 的递归/循环遍历，当 pid 数据存在环路（例如把父级设置为自身或自身的子级）时：
 *      1. getChildren() 无限递归 -> 内存耗尽 Fatal error，常驻进程 worker 直接崩溃；
 *      2. getParents() 无出口 while 循环 -> 单核 100% CPU 空跑，worker 永久挂死。
 * 本工具全部遍历均带 visited 集合与最大深度限制，环路数据不会再拖死进程；
 * 同时不使用 Tree::instance() 单例，避免常驻进程/协程下多个请求共用同一份树数据。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 * @version 1.0.1   深度上限放宽至1000，避免超深合法树被误判为环路
 */
final class TreeGuard
{
    /**
     * 最大遍历深度，超过则认为数据异常（防止脏数据把进程拖死）
     * 环路检测/遍历均带 visited 集合，不会因深度上限而失控，
     * 上限仅用于异常数据的快速终止，正常树远低于该值
     */
    const MAX_DEPTH = 1000;

    /**
     * 构建索引
     *
     * @param array $rows   数据行
     * @param string $idKey 主键字段名
     * @param string $pidKey 父级字段名
     * @return array [map => [id => row], children => [pid => [row, ...]]]
     */
    public static function index(array $rows, string $idKey = 'id', string $pidKey = 'pid'): array
    {
        $map = [];
        $children = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_key_exists($idKey, $row)) {
                continue;
            }
            $id = (int) $row[$idKey];
            $pid = array_key_exists($pidKey, $row) ? (int) $row[$pidKey] : 0;
            $map[$id] = $row;
            $children[$pid][] = $row;
        }

        return ['map' => $map, 'children' => $children];
    }

    /**
     * 检测整表是否存在 pid 环路
     *
     * @param array $rows   数据行
     * @param string $idKey 主键字段名
     * @param string $pidKey 父级字段名
     * @return boolean
     */
    public static function hasCycle(array $rows, string $idKey = 'id', string $pidKey = 'pid'): bool
    {
        $map = static::index($rows, $idKey, $pidKey)['map'];
        foreach (array_keys($map) as $id) {
            $visited = [];
            $current = (int) $id;
            $depth = 0;
            while (isset($map[$current])) {
                if (isset($visited[$current])) {
                    // 重复访问即为环路
                    return true;
                }
                $visited[$current] = true;
                $current = (int) ($map[$current][$pidKey] ?? 0);
                if (++$depth > static::MAX_DEPTH) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 获取全部后代数据行（不含自身）
     *
     * @param array $rows   数据行
     * @param integer|string $id 节点ID
     * @param string $idKey 主键字段名
     * @param string $pidKey 父级字段名
     * @return array
     */
    public static function descendants(array $rows, $id, string $idKey = 'id', string $pidKey = 'pid'): array
    {
        $index = static::index($rows, $idKey, $pidKey);
        $children = $index['children'];
        $result = [];
        // 起点自身也标记为已访问，环路数据下不会把自身当作后代返回
        $visited = [(int) $id => true];
        $queue = [(int) $id];
        $depth = 0;
        while ($queue && ++$depth <= static::MAX_DEPTH) {
            $next = [];
            foreach ($queue as $pid) {
                if (!isset($children[$pid])) {
                    continue;
                }
                foreach ($children[$pid] as $child) {
                    $cid = (int) $child[$idKey];
                    // 环路保护：已访问过的节点不再展开
                    if (isset($visited[$cid])) {
                        continue;
                    }
                    $visited[$cid] = true;
                    $result[] = $child;
                    $next[] = $cid;
                }
            }
            $queue = $next;
        }

        return $result;
    }

    /**
     * 获取全部后代ID（不含自身）
     *
     * @param array $rows   数据行
     * @param integer|string $id 节点ID
     * @param string $idKey 主键字段名
     * @param string $pidKey 父级字段名
     * @return array
     */
    public static function descendantIds(array $rows, $id, string $idKey = 'id', string $pidKey = 'pid'): array
    {
        $result = [];
        foreach (static::descendants($rows, $id, $idKey, $pidKey) as $row) {
            $result[] = (int) $row[$idKey];
        }

        return $result;
    }

    /**
     * 获取全部祖先数据行（不含自身），根节点在前
     *
     * @param array $rows   数据行
     * @param integer|string $id 节点ID
     * @param string $idKey 主键字段名
     * @param string $pidKey 父级字段名
     * @return array
     */
    public static function ancestors(array $rows, $id, string $idKey = 'id', string $pidKey = 'pid'): array
    {
        $map = static::index($rows, $idKey, $pidKey)['map'];
        $current = (int) $id;
        if (!isset($map[$current])) {
            return [];
        }
        $result = [];
        // 起点自身标记为已访问，环路数据下不会把自身当作祖先返回
        $visited = [(int) $id => true];
        $current = (int) ($map[$current][$pidKey] ?? 0);
        $depth = 0;
        while ($current > 0 && isset($map[$current])) {
            if (isset($visited[$current]) || ++$depth > static::MAX_DEPTH) {
                // 环路或超深数据，终止遍历
                break;
            }
            $visited[$current] = true;
            array_unshift($result, $map[$current]);
            $current = (int) ($map[$current][$pidKey] ?? 0);
        }

        return $result;
    }
}
