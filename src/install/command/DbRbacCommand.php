<?php

declare(strict_types=1);

namespace support\command\auth;

use mon\util\Sql;
use mon\env\Config;
use mon\thinkORM\Db;
use mon\console\Input;
use mon\console\Output;
use mon\console\Command;

/**
 * RBAC数据库表发布
 *
 * @author Mon <98555883@qq.com>
 * @version 1.0.0
 */
class DbRbacCommand extends Command
{
    /**
     * 指令名
     *
     * @var string
     */
    protected static $defaultName = 'dbRBAC:publish';

    /**
     * 指令描述
     *
     * @var string
     */
    protected static $defaultDescription = 'Publish the RBAC database.';

    /**
     * 指令分组
     *
     * @var string
     */
    protected static $defaultGroup = 'Auth';

    /**
     * 执行指令
     *
     * @param  Input  $in  输入实例
     * @param  Output $out 输出实例
     * @return integer  exit状态码
     */
    public function execute(Input $in, Output $out)
    {
        // 读取sql文件
        $file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'rbac.sql';
        $sqls = Sql::parseFile($file);
        // 表名
        $auth_role = Config::instance()->get('auth.rbac.auth_role', 'auth_role');
        $auth_access = Config::instance()->get('auth.rbac.auth_role_access', 'auth_access');
        $auth_rule = Config::instance()->get('auth.rbac.auth_rule', 'auth_rule');

        // 角色表
        $roleSql = <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `pids` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '父级ID列表',
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '组名',
  `rules` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '规则ID列表',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1:有效,0:无效',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日期',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建日期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色表';
SQL;
        $sql = sprintf($roleSql, $auth_role);
        Db::execute($sql);
        $out->block('Create Table `' . $auth_role . '`', 'SUCCESS');

        // 角色人员关联表
        $roleSql = <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `uid` int(10) unsigned NOT NULL COMMENT '用户ID',
  `gid` int(10) unsigned NOT NULL COMMENT '角色ID',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日期',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建日期',
  UNIQUE KEY `uid_gid` (`uid`,`gid`),
  KEY `uid` (`uid`),
  KEY `gid` (`gid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色人员关联表';
SQL;
        $sql = sprintf($roleSql, $auth_access);
        Db::execute($sql);
        $out->block('Create Table `' . $auth_access . '`', 'SUCCESS');

        // 权限规则表
        $roleSql = <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '父级ID',
  `pids` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '父级ID列表',
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '规则标题',
  `rule` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '权限规则',
  `remark` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '描述信息',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1:有效,0:无效',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日期',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建日期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限规则表';
SQL;
        $sql = sprintf($roleSql, $auth_rule);
        Db::execute($sql);
        $out->block('Create Table `' . $auth_rule . '`', 'SUCCESS');
    }
}
