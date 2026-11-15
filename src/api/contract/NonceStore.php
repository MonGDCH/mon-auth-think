<?php

declare(strict_types=1);

namespace mon\auth\api\contract;

/**
 * 签名nonce一次性存储契约
 *
 * 用于API签名防重放：同一个 (app_id, nonce) 在签名有效期内只允许被接受一次。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface NonceStore
{
    /**
     * 占位一个nonce
     *
     * 实现必须是原子操作（如Redis的 SET NX EX），返回 false 表示nonce已存在，应判定为重放请求。
     *
     * @param  string  $app_id 应用ID
     * @param  string  $nonce  随机字符串
     * @param  integer $ttl    存活时间，应等于签名有效期
     * @throws \mon\auth\exception\ApiException 存储不可用时由实现自行决定抛出异常或返回结果
     * @return boolean true=占位成功；false=nonce已存在（重放）
     */
    public function put(string $app_id, string $nonce, int $ttl): bool;
}
