<?php

declare(strict_types=1);

namespace mon\auth\api\driver;

use mon\util\Event;
use mon\util\Nbed64;
use mon\util\Instance;
use mon\auth\api\contract\Driver;
use mon\auth\exception\ApiException;

/**
 * 接口 AccessToken 权限控制
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class AccessToken implements Driver
{
    use Instance;

    /**
     * 新版token格式标识前缀（AES-256-GCM），点号不在base64字符集内，可作为可靠的格式标识
     */
    const TOKEN_PREFIX = 'v2.';

    /**
     * Token格式：AES-256-GCM认证加密（推荐）
     */
    const FORMAT_GCM = 'gcm';

    /**
     * Token格式：旧版Nbed64自研加密
     */
    const FORMAT_LEGACY = 'nbed64';

    /**
     * 加密盐，必须显式传入，未配置时构造将抛出异常
     *
     * @var string
     */
    protected $encrypt_salt = '';

    /**
     * Token格式，默认签发AES-256-GCM格式
     *
     * @var string
     */
    protected $format = self::FORMAT_GCM;

    /**
     * 是否兼容解析旧版Nbed64格式token（迁移期双读，确认存量token全部过期后可关闭）
     *
     * @var boolean
     */
    protected $legacy_parse = true;

    /**
     * 字段名映射
     *
     * @var array
     */
    protected $field_map = [
        // app_id字段名
        'app_id'    => 'app_id',
        // 有效时间字段名
        'expire'    => 'expire',
        // 签发的IP
        'ip'        => 'ip'
    ];

    /**
     * 构造方法
     *
     * @param string $salt      加密盐
     * @param array  $field_map 字段名映射
     * @param array  $options   额外选项：format（token格式，默认gcm）、legacy_parse（兼容解析旧格式，默认true）
     */
    public function __construct(string $salt = '', array $field_map = [], array $options = [])
    {
        // 盐为空时直接拒绝初始化，防止无盐Token上线
        if ($salt === '') {
            throw new ApiException('未配置有效的AccessToken加密盐', ApiException::AUTH_INIT_ERROR);
        }
        $this->encrypt_salt = $salt;
        $this->field_map = array_merge($this->field_map, $field_map);
        if (isset($options['format'])) {
            $this->setFormat($options['format']);
        }
        if (isset($options['legacy_parse'])) {
            $this->legacy_parse = (bool) $options['legacy_parse'];
        }
    }

    /**
     * 获取Token格式
     *
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * 设置Token签发格式
     *
     * @param string $format 支持AccessToken::FORMAT_GCM、AccessToken::FORMAT_LEGACY
     * @throws ApiException 不支持的格式
     * @return AccessToken
     */
    public function setFormat(string $format): AccessToken
    {
        if (!in_array($format, [self::FORMAT_GCM, self::FORMAT_LEGACY], true)) {
            throw new ApiException('不支持的AccessToken格式', ApiException::DAO_NOT_SUPPORT);
        }
        $this->format = $format;
        return $this;
    }

    /**
    * 获取是否兼容解析旧格式token
     *
     * @return boolean
     */
    public function getLegacyParse(): bool
    {
        return $this->legacy_parse;
    }

    /**
     * 设置是否兼容解析旧格式token
     *
     * @param boolean $legacy_parse
     * @return AccessToken
     */
    public function setLegacyParse(bool $legacy_parse): AccessToken
    {
        $this->legacy_parse = $legacy_parse;
        return $this;
    }

    /**
     * 获取内置加密盐
     *
     * @return string
     */
    public function getSalt(): string
    {
        return $this->encrypt_salt;
    }

    /**
     * 设置内置加密盐
     *
     * @param string $salt  加密盐
     * @return AccessToken
     */
    public function setSalt(string $salt): AccessToken
    {
        if ($salt === '') {
            throw new ApiException('未配置有效的AccessToken加密盐', ApiException::AUTH_INIT_ERROR);
        }
        $this->encrypt_salt = $salt;
        return $this;
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
     * @return AccessToken
     */
    public function setField(array $field): AccessToken
    {
        $this->field_map = array_merge($this->field_map, $field);
        return $this;
    }

    /**
     * 创建AccessToken
     *
     * @param string $app_id    应用ID
     * @param string $secret    应用秘钥
     * @param array $extend     扩展的数据
     * @param string $ip        签发ip地址
     * @param integer $exp      有效时间
     * @return string   生成的AccessToken
     */
    public function create(string $app_id, string $secret, array $extend = [], string $ip = '', int $exp = 7200): string
    {
        // 过期时间
        $expire_time = $this->getExpireTime($exp);
        // 盐
        $encrypt_salt = $this->getEncryptSalt($secret);
        // token数据
        $data = array_merge($extend, [
            $this->getField('app_id') => $app_id,
            $this->getField('expire') => $expire_time,
            $this->getField('ip') => $ip
        ]);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        // 旧版格式
        if ($this->format === self::FORMAT_LEGACY) {
            return Nbed64::stringEncrypt($json, $encrypt_salt);
        }

        // 新版格式：AES-256-GCM认证加密
        return $this->encryptGCM($json, $encrypt_salt);
    }

    /**
     * 解析AccessToken数据
     *
     * 根据token前缀自动识别格式：v2.前缀为AES-256-GCM格式，其余按旧版Nbed64格式解析（需开启legacy_parse）
     *
     * @param string $token token
     * @param string $salt  应用秘钥
     * @throws ApiException
     * @return array    token数据
     */
    public function parse(string $token, string $secret): array
    {
        // 盐
        $encrypt_salt = $this->getEncryptSalt($secret);

        // 新版格式
        if (strpos($token, self::TOKEN_PREFIX) === 0) {
            return $this->decryptGCM($token, $encrypt_salt);
        }

        // 旧版格式
        if (!$this->legacy_parse) {
            throw new ApiException('不支持的AccessToken格式', ApiException::ACCESS_TOKEN_ERROR);
        }
        // 解析token数据
        $json = Nbed64::stringDecrypt($token, $encrypt_salt);
        // 修正解密后输出的JON格式错误：存在零宽的控制符(Control character error)
        $json = preg_replace('/[[:cntrl:]]/mu', '', $json);
        if (empty($json)) {
            throw new ApiException('无效的AccessToken!', ApiException::ACCESS_TOKEN_ERROR);
        }
        $data = json_decode($json, true);
        if (json_last_error()) {
            throw new ApiException('无效的AccessToken! ' . json_last_error_msg(), ApiException::ACCESS_TOKEN_ERROR);
        }

        return $data;
    }

    /**
     * AES-256-GCM加密token数据
     *
     * @param  string $json         json数据
     * @param  string $encrypt_salt 加密盐
     * @throws ApiException openssl失败时抛出
     * @return string v2.前缀的base64url(iv.tag.cipher)
     */
    protected function encryptGCM(string $json, string $encrypt_salt): string
    {
        // AES-256-GCM需要32字节密钥，由加盐派生
        $key = hash('sha256', $encrypt_salt, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new ApiException('AccessToken加密失败', ApiException::OPENSSL_ERROR);
        }

        return self::TOKEN_PREFIX . $this->b64urlEncode($iv . $tag . $cipher);
    }

    /**
     * AES-256-GCM解密token数据，GCM tag自动校验完整性，任何篡改直接失败
     *
     * @param  string $token        v2.前缀的token
     * @param  string $encrypt_salt 加密盐
     * @throws ApiException
     * @return array
     */
    protected function decryptGCM(string $token, string $encrypt_salt): array
    {
        $raw = $this->b64urlDecode(substr($token, strlen(self::TOKEN_PREFIX)));
        // 结构长度校验：iv(12) + tag(16) + 密文(>=1)
        if ($raw === false || strlen($raw) < 29) {
            throw new ApiException('无效的AccessToken!', ApiException::ACCESS_TOKEN_ERROR);
        }

        $key = hash('sha256', $encrypt_salt, true);
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $json = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($json === false) {
            throw new ApiException('无效的AccessToken!', ApiException::ACCESS_TOKEN_ERROR);
        }
        $data = json_decode($json, true);
        if (json_last_error()) {
            throw new ApiException('无效的AccessToken! ' . json_last_error_msg(), ApiException::ACCESS_TOKEN_ERROR);
        }

        return $data;
    }

    /**
     * base64url编码
     *
     * @param  string $data
     * @return string
     */
    protected function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * base64url解码
     *
     * @param  string $data
     * @return string|false
     */
    protected function b64urlDecode(string $data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    /**
     * 验证AccessToken
     *
     * @param string $token     Token
     * @param string $ip        请求ip地址
     * @param string $secret    应用秘钥
     * @param string $app_id    应用ID
     * @throws ApiException
     * @return array Token数据
     */
    public function getData(string $token, string $app_id, string $secret, string $ip = ''): array
    {
        // 获取token数据
        $data = $this->parse($token, $secret);
        // 校验必要字段是否存在
        $appField = $this->getField('app_id');
        $expireField = $this->getField('expire');
        $ipField = $this->getField('ip');
        if (!isset($data[$appField], $data[$expireField], $data[$ipField])) {
            throw new ApiException('无效的AccessToken数据', ApiException::ACCESS_TOKEN_ERROR);
        }
        // 校验APP_id
        if ($app_id != $data[$appField]) {
            throw new ApiException('AppID不匹配', ApiException::ACCESS_TOKEN_FAILD);
        }
        // 校验有效期
        if (time() > $data[$expireField]) {
            throw new ApiException('Token已过期', ApiException::ACCESS_TOKEN_INVALID);
        }
        // 验证授权IP
        if ($ip != $data[$ipField]) {
            throw new ApiException('Token授权IP无效', ApiException::ACCESS_TOKEN_INVALID);
        }

        // 触发AccessToken验证事件，回调方法可通过 throw ApiException 增加自定义的验证方式
        Event::instance()->trigger('access_check', $data);

        return $data;
    }

    /**
     * 获取过期时间
     *
     * @param integer $exp
     * @return integer
     */
    protected function getExpireTime(int $exp): int
    {
        return time() + $exp;
    }

    /**
     * 获取加密盐
     *
     * @param string $salt  盐
     * @return string
     */
    protected function getEncryptSalt(string $salt): string
    {
        return $this->getSalt() . $salt;
    }
}
