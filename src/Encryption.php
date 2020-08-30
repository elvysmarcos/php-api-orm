<?php

namespace APIORM;

class Encryption
{
    static function BasicEncrypt(array $data): string
    {
        return Encryption::Base64UrlEncode(strrev(Encryption::Base64UrlEncode(json_encode($data))));
    }

    static function BasicDecrypting(string $data): array
    {
        return json_decode(Encryption::Base64UrlDecode(strrev(Encryption::Base64UrlDecode($data))), true);
    }

    static function Base64UrlEncode(string $data)
    {
        $urlSafeData = strtr(base64_encode($data), '+/', '-_');

        return rtrim($urlSafeData, '=');
    }

    static function Base64UrlDecode(string $data)
    {
        $urlUnsafeData = strtr($data, '-_', '+/');

        $paddedData = str_pad($urlUnsafeData, strlen($data) % 4, '=', STR_PAD_RIGHT);

        return base64_decode($paddedData);
    }
}