<?php

namespace APIORM;

use APIORM\Enums\TypeResponseEnum;
use DateTime;

class Security
{
    public $device;

    private $token;
    private $iat;
    private $header = [];
    private $payload = [];
    private $signature;

    public function __construct()
    {
        $headers = apache_request_headers();

        if (isset($headers['Authorization']) and $headers['Authorization']) {
            $this->token = $headers['Authorization'];
            $this->ExtractDataToken();
        }
    }

    private function ExtractDataToken()
    {
        $parameters = explode('.', str_replace('Bearer ', null, $this->token));

        $header = null;
        $payload = null;
        $signature = null;

        if (count($parameters) === 3) {
            $this->header = json_decode(Encryption::Base64UrlDecode($parameters[0]), true);
            $this->payload = json_decode(Encryption::Base64UrlDecode($parameters[1]), true);
            $this->signature = $parameters[2];
            $this->device = $this->payload['device'];
        }
    }

    public function DeviceGenerate(int $id): string
    {
        $characters = 'abcdxywzABCDZYWZ0123456789@#';

        $max = mb_strlen($characters) - 1;

        $code = null;

        for ($i = 0; $i < 10; $i++) {
            $code .= $characters[mt_rand(0, $max)];
        }

        return Encryption::BasicEncrypt(array($code, $id, time()));
    }

    public function ExtractDataDevice($device): int
    {
        $data = Encryption::BasicDecrypting($device);

        if (is_array($data) && count($data) === 3) {
            $date = new DateTime(date('Y-m-d H:i:s', $data[2]));
            $verify = $date->modify('+1 year');
            $now = new DateTime();

            if ($verify > $now) {
                return $data[1];
            }
        }

        return false;
    }

    public function TokenGenerate(string $device, string $secret, ?array $customPayload = null): string
    {
        $this->iat = time();

        $header = array(
            'alg' => 'HS256',
            'typ' => 'JWT'
        );

        $payload = array(
            'iat' => $this->iat,
            'device' => $device
        );

        if (is_array($customPayload) and count($customPayload)) {
            $payload = array_merge($payload, $customPayload);
        }

        $raw = Encryption::Base64UrlEncode(json_encode($header)) . '.' . Encryption::Base64UrlEncode(json_encode($payload));

        $signature = Encryption::Base64UrlEncode(hash_hmac('sha256', $raw, $secret, true));

        return "{$raw}.{$signature}";
    }

    private function CheckSignature($secret)
    {
        if (count($this->header) && count($this->payload)) {
            $signature = Encryption::Base64UrlEncode(
                hash_hmac(
                    'sha256',
                    Encryption::Base64UrlEncode(json_encode($this->header)) . '.' . Encryption::Base64UrlEncode(json_encode($this->payload)),
                    $secret,
                    true
                )
            );

            if ($this->signature = $signature) {
                return true;
            }
        }

        return false;
    }

    public function AuthenticatedRegion(ISession $session)
    {
        $session->Destroy();

        $secret = $session->GetSecret($this->device);

        $signature = $this->CheckSignature($secret);

        if ($signature) {
            $date = $session->Update($this->device);
            $this->iat = $date->getTimestamp();
            return true;
        }

        Response::Show(TypeResponseEnum::Unauthorized, $session->GetUnauthorizedText());
    }
}