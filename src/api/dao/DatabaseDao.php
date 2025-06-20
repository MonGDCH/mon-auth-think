<?php

declare(strict_types=1);

namespace mon\auth\api\dao;

use mon\thinkORM\Db;
use mon\auth\api\contract\Dao;

/**
 * 从database数据源中获取数据
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class DatabaseDao implements Dao
{
    /**
     * 数据表名称
     *
     * @var string
     */
    protected $table = 'api_sign';

    /**
     * 构造方法
     *
     * @param array $config 配置信息
     */
    public function __construct(array $config)
    {
        // 定义操作表
        $this->table = $config['table'];
    }

    /**
     * 获取所有数据
     *
     * @return array
     */
    public function getList(): array
    {
        return Db::table($this->table)->select()->toArray();
    }

    /**
     * 获取指定APP_ID数据
     *
     * @param string $app_id    应用ID
     * @return array
     */
    public function getInfo(string $app_id): array
    {
        return Db::table($this->table)->where('app_id', $app_id)->findOrEmpty();
    }

    /**
     * 是否有效
     *
     * @param array $info   应用信息
     * @return boolean
     */
    public function effect(array $info): bool
    {
        return $info['status'] == 1;
    }

    /**
     * 是否在有效期内
     *
     * @param array $info   应用信息
     * @return boolean
     */
    public function expire(array $info): bool
    {
        return empty($info['expired_time']) || strtotime($info['expired_time']) > time();
    }
}
