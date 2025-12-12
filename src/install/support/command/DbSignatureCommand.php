<?php

declare(strict_types=1);

namespace support\auth\command;

use mon\env\Config;
use mon\thinkORM\Db;
use mon\console\Input;
use mon\console\Output;
use mon\console\Command;

/**
 * Signature数据库表发布
 *
 * @author Mon <98555883@qq.com>
 * @version 1.0.0
 */
class DbSignatureCommand extends Command
{
  /**
   * 指令名
   *
   * @var string
   */
  protected static $defaultName = 'dbSignature:publish';

  /**
   * 指令描述
   *
   * @var string
   */
  protected static $defaultDescription = 'Publish the signature database.';

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
    $content = <<<SQL
CREATE TABLE IF NOT EXISTS `%s` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用ID',
  `secret` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用秘钥',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '应用名称',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '描述',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '1:有效,0:无效',
  `expired_time` datetime DEFAULT NULL COMMENT '过期时间，空则不过期',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日期',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建日期',
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_id`(`app_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ApiSignature授权表';
SQL;
    // 表名
    $table = Config::instance()->get('auth.signature.dao.construct.table', 'api_signs');
    // 建表sql
    $sql = sprintf($content, $table);
    // 建表
    Db::setConfig(Config::instance()->get('database', []));
    Db::execute($sql);
    return $out->block('Create Table `' . $table . '`', 'SUCCESS');
  }
}
