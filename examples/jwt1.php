<?php

/**
 * JWT 驱动级用法（HS256）
 */

require __DIR__ . '/../vendor/autoload.php';

use mon\auth\jwt\driver\Token;
use mon\auth\jwt\driver\Payload;
use mon\auth\exception\JwtException;

try {
    // 加密密钥，请使用足够随机的长字符串
    $key = 'please-change-me-to-a-random-long-key';
    // 加密算法
    $alg = 'HS256';

    $token = new Token();
    $payload = (new Payload())->setIss('abc')->setSub('def')->setExt(['a' => '123'])->setExp(3600)->setAud('127.0.0.1');

    // 创建jwt
    $jwt = $token->create($payload, $key, $alg);
    var_dump($jwt);

    // 解析并校验签名，返回paylaod数据
    $data = $token->getData($jwt, $key, $alg);
    var_dump($data);

    // 校验有效期（exp / nbf / iat）
    var_dump($token->verify($data));
} catch (JwtException $e) {
    var_dump('Msg: ' . $e->getMessage(), 'Line: ' . $e->getLine(), 'Code: ' . $e->getCode());
}
