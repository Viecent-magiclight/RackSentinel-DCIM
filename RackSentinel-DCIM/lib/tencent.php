<?php
/**
 * 腾讯云 API 3.0 TC3-HMAC-SHA256（用于 Web 测试发送）。
 */

function tencent_secret_id(): string
{
    $v = getenv('TENCENT_SECRET_ID');
    return $v !== false && $v !== '' ? $v : setting('tencent_secret_id');
}

function tencent_secret_key(): string
{
    $v = getenv('TENCENT_SECRET_KEY');
    if ($v !== false && $v !== '') {
        return $v;
    }
    return setting('tencent_secret_key');
}

function tc3_authorization(string $secretId, string $secretKey, string $service, string $host, string $payload, int $timestamp): string
{
    $algorithm = 'TC3-HMAC-SHA256';
    $date      = gmdate('Y-m-d', $timestamp);
    $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
    $signedHeaders    = 'content-type;host';
    $hashedPayload    = hash('sha256', $payload);
    $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}{$signedHeaders}\n{$hashedPayload}";
    $credentialScope  = "{$date}/{$service}/tc3_request";
    $stringToSign     = "{$algorithm}\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);
    $secretDate    = hash_hmac('sha256', $date, 'TC3' . $secretKey, true);
    $secretService = hash_hmac('sha256', $service, $secretDate, true);
    $secretSign    = hash_hmac('sha256', 'tc3_request', $secretService, true);
    $signature     = hash_hmac('sha256', $stringToSign, $secretSign);
    return "{$algorithm} Credential={$secretId}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
}

function tencent_api_post(string $service, string $host, string $action, string $version, array $body): array
{
    $sid = tencent_secret_id();
    $key = tencent_secret_key();
    if ($sid === '' || $key === '') {
        return ['ok' => false, 'error' => '未配置腾讯云 SecretId/SecretKey'];
    }
    $payload   = json_encode($body, JSON_UNESCAPED_UNICODE);
    $timestamp = time();
    $auth      = tc3_authorization($sid, $key, $service, $host, $payload, $timestamp);
    $ctx       = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => implode("\r\n", [
                'Content-Type: application/json; charset=utf-8',
                "Host: {$host}",
                "X-TC-Action: {$action}",
                "X-TC-Version: {$version}",
                "X-TC-Timestamp: {$timestamp}",
                'X-TC-Region: ap-guangzhou',
                "Authorization: {$auth}",
            ]),
            'content' => $payload,
            'timeout' => 15,
        ],
    ]);
    $raw = @file_get_contents('https://' . $host, false, $ctx);
    if ($raw === false) {
        return ['ok' => false, 'error' => '请求失败'];
    }
    return ['ok' => true, 'response' => json_decode($raw, true) ?: $raw];
}

function tencent_send_test_sms(string $phone, array $params): array
{
    return tencent_api_post('sms', 'sms.tencentcloudapi.com', 'SendSms', '2021-01-11', [
        'PhoneNumberSet'   => [$phone],
        'SmsSdkAppId'      => setting('tencent_sms_app_id'),
        'SignName'         => setting('tencent_sms_sign'),
        'TemplateId'       => setting('tencent_sms_template_id'),
        'TemplateParamSet' => $params,
    ]);
}

function tencent_send_test_voice(string $phone, array $params, string $mode = 'tts'): array
{
    $action = $mode === 'code' ? 'SendCodeVoice' : 'SendTtsVoice';
    return tencent_api_post('vms', 'vms.tencentcloudapi.com', $action, '2020-09-02', [
        'VoiceSdkAppid'    => setting('tencent_vms_app_id'),
        'TemplateId'       => setting('tencent_vms_template_id'),
        'CalledNumber'     => $phone,
        'TemplateParamSet' => $params,
    ]);
}
