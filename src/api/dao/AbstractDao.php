<?php

declare(strict_types=1);

namespace mon\auth\api\dao;

use mon\auth\api\contract\Dao;

/**
 * Dao公共实现
 *
 * 各数据源驱动共用「应用是否有效 / 是否过期」的判定逻辑：
 * 1. 缺少 status 字段按无效处理（fail-closed），不再抛出未定义数组键警告；
 * 2. expired_time 同时兼容 datetime 字符串与整型时间戳，无法解析时按已过期处理。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
abstract class AbstractDao implements Dao
{
    /**
     * 是否有效
     *
     * @param array $info   应用信息
     * @return boolean
     */
    public function effect(array $info): bool
    {
        return isset($info['status']) && (int) $info['status'] === 1;
    }

    /**
     * 是否在有效期内
     *
     * @param array $info   应用信息
     * @return boolean
     */
    public function expire(array $info): bool
    {
        $expired = $info['expired_time'] ?? '';
        // 空值表示永久有效
        if ($expired === '' || $expired === null || $expired === 0 || $expired === '0') {
            return true;
        }
        // 整型时间戳
        if (is_numeric($expired)) {
            return (int) $expired > time();
        }
        // 日期字符串，解析失败按已过期处理
        $timestamp = strtotime((string) $expired);
        return $timestamp !== false && $timestamp > time();
    }
}
