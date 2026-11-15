<?php

declare(strict_types=1);

namespace mon\auth\api\driver;

use mon\util\Event;
use mon\util\Instance;
use mon\auth\api\contract\Driver;
use mon\auth\api\concern\SaltGuard;
use mon\auth\exception\ApiException;

/**
 * 接口 Signature 签名验证
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Signature implements Driver
{
    use Instance;

    /**
     * 加密盐，必须显式传入，未配置时构造将抛出异常
     *
     * @var string
     */
    protected $encrypt_salt = '';

    /**
     * 签名算法：md5（历史默认，保持向后兼容）| hmac-sha256（推荐）
     *
     * @var string
     */
    protected $alg = 'md5';

    /**
     * 校验时是否兼容旧版md5签名（alg为hmac-sha256时生效）
     *
     * @var boolean
     */
    protected $accept_legacy = true;

    /**
     * 字段名映射
     *
     * @var array
     */
    protected $field_map = [
        // app_id字段名
        'app_id'    => 'app_id',
        // 签名字段名
        'signature' => 'signature',
        // 签名时间字段名
        'timestamp' => 'timestamp',
        // 随机字符串字段名
        'noncestr'  => 'noncestr',
        // secret key名
        'secret'    => 'key'
    ];

    /**
     * 构造方法
     *
     * @param string $salt      加密盐
     * @param array $field_map  字段名映射
     * @param array $options    扩展配置：alg(md5|hmac-sha256，默认md5保持向后兼容)、accept_legacy(是否兼容校验旧版md5签名)
     */
    public function __construct(string $salt = '', array $field_map = [], array $options = [])
    {
        // 盐为空或仍为配置模板占位符时直接拒绝初始化，防止无盐/弱密钥签名上线
        SaltGuard::assert($salt, 'Signature');
        $this->encrypt_salt = $salt;
        $this->field_map = array_merge($this->field_map, $field_map);
        if (isset($options['alg'])) {
            $this->alg = strtolower((string) $options['alg']) === 'hmac-sha256' ? 'hmac-sha256' : 'md5';
        }
        if (isset($options['accept_legacy'])) {
            $this->accept_legacy = (bool) $options['accept_legacy'];
        }
    }

    /**
     * 获取单例实例
     *
     * 带参数调用时返回新实例：加密盐是实例级状态，若沿用单例会静默忽略后续参数，
     * 常驻进程内就会一直使用首次传入的盐。
     *
     * @param mixed ...$args 构造参数(salt, field_map, options)
     * @return static
     */
    public static function instance(...$args)
    {
        if (!empty($args)) {
            return new static(...$args);
        }

        return static::createInstance([]);
    }

    /**
     * 获取加密秘钥
     *
     * @param string $secret
     * @return string
     */
    public function getSecret(string $secret): string
    {
        return $this->encrypt_salt . $secret;
    }

    /**
     * 获取映射的字段值
     *
     * @param string $key   映射名
     * @return string   字段值
     */
    public function getField(string $key): string
    {
        return $this->field_map[$key];
    }

    /**
     * 设置字段映射
     *
     * @param array $field  映射的规则
     * @return Signature
     */
    public function setField(array $field): Signature
    {
        $this->field_map = array_merge($this->field_map, $field);
        return $this;
    }

    /**
     * 创建签名请求数据
     *
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param array $data       需要签名的数据
     * @return array
     */
    public function create(string $app_id, string $secret, array $data = []): array
    {
        $signData = $this->getSignData($app_id, $data);
        $signData[$this->getField('signature')] = $this->getSign($signData, $secret);
        return $signData;
    }

    /**
     * 验证请求参数签名
     *
     * @param string $secret    应用秘钥
     * @param array $data       请求参数
     * @return boolean
     */
    public function check(string $secret, array $data): bool
    {
        $sign = $data[$this->getField('signature')] ?? '';
        if (empty($sign)) {
            throw new ApiException('API签名不存在', ApiException::SIGN_NOT_FOUND);
        }
        unset($data[$this->getField('signature')]);

        // 验证签名有效性（恒时比较，防止时序攻击），签名不匹配直接拒绝
        $verified = hash_equals($this->getSign($data, $secret), (string) $sign);
        if (!$verified && $this->alg === 'hmac-sha256' && $this->accept_legacy) {
            // 客户端未升级时兼容校验旧版md5签名，全部客户端升级后建议关闭
            $verified = hash_equals($this->getSignMd5($data, $secret), (string) $sign);
        }
        if (!$verified) {
            throw new ApiException('API签名错误', ApiException::SIGN_VERIFY_FAIL);
        }

        // 触发AccessToken验证事件，回调方法可通过 throw ApiException 增加自定义的验证方式
        Event::instance()->trigger('sign_check', $data);

        return true;
    }

    /**
     * 获取用来生成签名的数据
     *
     * @param string $app_id    app_id
     * @param array $data       签名的原数据
     * @return array
     */
    public function getSignData(string $app_id, array $data = []): array
    {
        $data[$this->getField('app_id')] = $app_id;
        // 创建时间，用于验证有效期
        $data[$this->getField('timestamp')] = time();
        // 随机字符串，用于保障随机性
        $data[$this->getField('noncestr')] = bin2hex(random_bytes(16));
        return $data;
    }

    /**
     * 生成签名
     *
     * @param array $data   生成签名用的数据
     * @param string $secret   签名最后的key值
     * @param string $key   签名的key名
     * @return string
     */
    public function getSign(array $data, string $secret, string $key = ''): string
    {
        // 获取key名
        $key = $key ?: $this->getField('secret');
        // 签名步骤一：按字典序排序数组参数
        ksort($data);
        $string = http_build_query($data);
        // 签名步骤二：在string后加入KEY
        $string = $string . "&{$key}=" . $this->getSecret($secret);

        if ($this->alg === 'hmac-sha256') {
            // HMAC-SHA256：密钥不参与拼接，避免弱摘要问题
            return strtoupper(hash_hmac('sha256', $string, $this->getSecret($secret)));
        }

        // 签名步骤三：md5加密
        $string = md5($string);
        // 签名步骤四：所有字符转为大写
        return strtoupper($string);
    }

    /**
     * 生成旧版md5签名（仅用于兼容校验）
     *
     * @param array $data     生成签名用的数据
     * @param string $secret  应用秘钥
     * @param string $key     签名的key名
     * @return string
     */
    protected function getSignMd5(array $data, string $secret, string $key = ''): string
    {
        $key = $key ?: $this->getField('secret');
        ksort($data);
        $string = http_build_query($data);
        $string = $string . "&{$key}=" . $this->getSecret($secret);

        return strtoupper(md5($string));
    }
}
