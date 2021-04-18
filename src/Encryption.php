<?php

namespace APIORM;

class Encryption
{
    static function BasicEncrypt(array $data): string
    {
        return Encryption::Base64Encode(strrev(Encryption::Base64Encode(json_encode($data))));
    }

    static function BasicDecrypting(string $data): array
    {
        return json_decode(Encryption::Base64Decode(strrev(Encryption::Base64Decode($data))), true);
    }

    static function Base64Encode(string $data): string
    {
        return base64_encode($data);
    }

    static function Base64Decode(string $data): string
    {
        return base64_decode($data);
    }
}