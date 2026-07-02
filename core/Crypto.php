<?php

namespace App\Core;

class Crypto
{
    public static function encrypt(string $data, string $key): string
    {
        $derivedKey = hash('sha256', $key, true);
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt(string $data, string $key): ?string
    {
        $decoded = base64_decode($data);
        if (strlen($decoded) < 17) {
            return null;
        }
        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);
        $derivedKey = hash('sha256', $key, true);
        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $derivedKey, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }
}
