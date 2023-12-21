<?php

declare(strict_types=1);

namespace mon\auth\api\contract;

/**
 * API权限接口
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface ApiAuth
{
    /**
     * 初始化
     *
     * @param array $config 配置信息
     * @return ApiAuth
     */
    public function init(array $config = []): ApiAuth;

    /**
     * 是否初始化
     *
     * @return boolean
     */
    public function isInit(): bool;

    /**
     * 获取配置信息
     *
     * @return mixed
     */
    public function getConfig(string $field = '');

    /**
     * 获取驱动实例
     *
     * @return Driver
     */
    public function getDriver(): Driver;

    /**
     * 获取Dao实例
     *
     * @return Dao
     */
    public function getDao(): Dao;
}
