<?php

namespace App\Core;

class JWT
{
    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode(string $data): ?string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
        return $decoded !== false ? $decoded : null;
    }

    public static function encode(array $payload, string $secret, int $expiry = 86400): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        if (!isset($payload['iat'])) {
            $payload['iat'] = time();
        }
        if (!isset($payload['exp'])) {
            $payload['exp'] = $payload['iat'] + $expiry;
        }

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));

        $signatureInput = $base64Header . '.' . $base64Payload;
        $signature = hash_hmac('sha256', $signatureInput, $secret, true);
        $base64Signature = self::base64UrlEncode($signature);

        return $signatureInput . '.' . $base64Signature;
    }

    public static function decode(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$base64Header, $base64Payload, $base64Signature] = $parts;

        $signatureInput = $base64Header . '.' . $base64Payload;
        $signature = self::base64UrlDecode($base64Signature);
        if ($signature === null) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $signatureInput, $secret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($base64Payload);
        if ($payloadJson === null) {
            return null;
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}
