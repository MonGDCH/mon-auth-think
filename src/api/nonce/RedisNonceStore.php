<?php

declare(strict_types=1);

namespace mon\auth\api\nonce;

use mon\auth\api\contract\NonceStore;
use mon\auth\exception\APIException;

/**
 * 基于Redis的签名nonce防重放存储
 *
 * 兼容 phpredis（\Redis）与 Predis\Client 等支持 set($key, $value, ['nx', 'ex' => $ttl]) 语法的客户端。
 * 注意：必须先完成验签后再调用 put，避免非法请求灌爆存储。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class RedisNonceStore implements NonceStore
{
    /**
     * Redis客户端实例
     *
     * @var mixed
     */
    protected $client;

    /**
     * key前缀
     *
     * @var string
     */
    protected $prefix;

    /**
     * 构造方法
     *
     * @param mixed  $client 支持set方法的Redis客户端实例（如\Redis或\Predis\Client）
     * @param string $prefix key前缀
     */
    public function __construct($client, string $prefix = 'auth:nonce:')
    {
        if (!is_object($client) || !method_exists($client, 'set')) {
            throw new APIException('RedisNonceStore需要注入支持set方法的Redis客户端实例', APIException::DAO_NOT_SUPPORT);
        }
        $this->client = $client;
        $this->prefix = $prefix;
    }

    /**
     * 占位一个nonce
     *
     * @param  string  $app_id 应用ID
     * @param  string  $nonce  随机字符串
     * @param  integer $ttl    存活时间
     * @throws APIException Redis操作失败时抛出
     * @return boolean true=占位成功；false=nonce已存在（重放）
     */
    public function put(string $app_id, string $nonce, int $ttl): bool
    {
        $key = $this->prefix . $app_id . ':' . $nonce;
        try {
            // SET key value NX EX ttl 原子操作：返回false即已存在（重放）
            $result = $this->client->set($key, '1', ['nx', 'ex' => $ttl]);
        } catch (\Throwable $e) {
            throw new APIException('nonce存储写入失败：' . $e->getMessage(), APIException::SIGN_REPLAY);
        }

        return (bool) $result;
    }
}
