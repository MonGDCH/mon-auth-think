<?php

declare(strict_types=1);

namespace mon\auth\rbac;

/**
 * RBAC验证器
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.1   优化代码
 */
class Validate extends \mon\util\Validate
{
    /**
     * 验证规则
     *
     * @var array
     */
    public $rule = [
        'id'        => ['required', 'id'],
        'uid'       => ['required', 'id'],
        'gid'       => ['required', 'id'],
        'new_gid'   => ['required', 'id'],
        'pid'       => ['required', 'int', 'min:0'],
        'rules'     => ['isset', 'arr', 'rules'],
        'rule'      => ['required', 'str', 'maxLength:250'],
        'title'     => ['required', 'str', 'maxLength:50'],
        'remark'    => ['isset', 'str', 'maxLength:250'],
        'status'    => ['required', 'int', 'min:0'],
    ];

    /**
     * 错误提示信息
     *
     * @var array
     */
    public $message = [
        'id'        => 'ID参数错误',
        'uid'       => '用户ID参数错误',
        'gid'       => '角色ID参数错误',
        'new_gid'   => '新角色ID参数错误',
        'pid'       => '上级ID参数错误',
        'status'    => '状态参数错误',
        'rules'     => [
            'isset'     => '角色规则列表参数错误',
            'arr'       => '角色规则列表必须为数组',
            'rules'     => '角色规则列表格式错误'
        ],
        'rule'      => [
            'required'  => '规则参数错误',
            'str'       => '规则格式必须为字符串',
            'maxLength' => '规则长度不能超过250个字符'
        ],
        'title'     => [
            'required'  => '名称参数错误',
            'str'       => '名称格式必须为字符串',
            'maxLength' => '名称长度不能超过50个字符'
        ],
        'remark'    => [
            'isset'     => '备注描述参数错误',
            'str'       => '备注描述格式必须为字符串',
            'maxLength' => '备注描述长度不能超过50个字符'
        ]
    ];

    /**
     * 验证场景
     *
     * @var array
     */
    public $scope = [
        // 绑定用户组
        'access_bind'   => ['uid', 'gid'],
        // 解除绑定角色组
        'access_unbind' => ['uid', 'gid'],
        // 修改组别用户关联
        'access_modify' => ['uid', 'gid', 'new_gid'],
        // 添加角色组别
        'role_add'      => ['pid', 'title', 'rules'],
        // 修改角色组别信息
        'role_modify'   => ['pid', 'title', 'rules', 'status', 'id'],
        // 增加规则
        'rule_add'      => ['title', 'pid', 'rule', 'remark'],
        // 修改规则
        'rule_modify'   => ['title', 'pid', 'rule', 'remark', 'status', 'id'],
    ];

    /**
     * 验证规则组数据
     *
     * @param array $value
     * @return boolean
     */
    public function rules(array $value): bool
    {
        foreach ($value as $rule) {
            if (!$this->int($rule)) {
                return false;
            }
        }

        return true;
    }
}
