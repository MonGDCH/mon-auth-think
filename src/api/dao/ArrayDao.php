<?php

declare(strict_types=1);

namespace mon\auth\api\dao;

/**
 * 从Array数据源中获取数据
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class ArrayDao extends AbstractDao
{
    /**
     * 数据源
     *
     * @var array
     */
    private $data = [];

    /**
     * 构造方法
     *
     * @param array $config   配置信息
     */
    public function __construct(array $config)
    {
        $this->data = isset($config['data']) && is_array($config['data']) ? $config['data'] : [];
    }

    /**
     * 获取所有数据
     *
     * @return array
     */
    public function getList(): array
    {
        return $this->data;
    }

    /**
     * 获取指定APP_ID数据
     *
     * @param string $app_id    应用ID
     * @return array
     */
    public function getInfo(string $app_id): array
    {
        $result = [];
        foreach ($this->data as $item) {
            if (!is_array($item) || !isset($item['app_id'])) {
                continue;
            }
            // 严格比较：松散比较下 '0e123' 与 '0e456' 会被当作数值相等，可能命中其他应用的数据
            if ((string) $item['app_id'] === $app_id) {
                $result = $item;
                break;
            }
        }

        return $result;
    }
}
