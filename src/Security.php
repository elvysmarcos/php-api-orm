<?php

namespace APIORM;

use APIORM\Enums\TypeResponseEnum;
use DateTime;

class Security
{
    public $token;
    public $device;

    public function __construct($id = null)
    {
        $headers = apache_request_headers();

        if (isset($headers['Authorization']) and $headers['Authorization']) {
            $this->token = $headers['Authorization'];
            list($parameters, $header, $payload, $signature) = $this->GetDataToken($this->token);

            $this->device = $payload['key'];
        } else if ($id) {
            $this->device = $this->DeviceGenerate($id);
            $this->token = $this->TokenGenerate($this->device);
        }
    }

    public function GetDataToken(string $token): ?array
    {
        $parameters = explode('.', str_replace('Bearer ', null, $token));

        $header = null;
        $payload = null;
        $signature = null;

        if (count($parameters) === 3) {
            $header = json_decode(Encryption::Base64UrlDecode($parameters[0]), true);
            $payload = json_decode(Encryption::Base64UrlDecode($parameters[1]), true);

            $signature = Encryption::Base64UrlEncode(
                hash_hmac(
                    'sha256',
                    Encryption::Base64UrlEncode(json_encode($header)) . '.' . Encryption::Base64UrlEncode(json_encode($payload)),
                    $payload['key'],
                    true
                )
            );
        }

        return array($parameters, $header, $payload, $signature);
    }

    private function DeviceGenerate(int $id): string
    {
        $characters = 'abcdxywzABCDZYWZ0123456789';

        $max = mb_strlen($characters) - 1;

        $code = null;

        for ($i = 0; $i < 10; $i++) {
            $code .= $characters[mt_rand(0, $max)];
        }

        return Encryption::BasicEncrypt(array($code, $id, TIME));
    }

    public function TokenGenerate(string $device, ?array $setPayload = null): string
    {
        $header = array(
            'alg' => 'HS256',
            'typ' => 'JWT'
        );

        $payload = array(
            'iat' => TIME,
            'key' => $device
        );

        if (is_array($setPayload) and count($setPayload)) {
            $payload = array_merge($payload, $setPayload);
        }

        $raw = Encryption::Base64UrlEncode(json_encode($header)) . '.' . Encryption::Base64UrlEncode(json_encode($payload));


        $signature = Encryption::Base64UrlEncode(hash_hmac('sha256', $raw, $device, true));

        return "{$raw}.{$signature}";
    }

    public function AuthenticatedRegion(ISession $SessionBusiness)
    {
        $SessionBusiness->Destroy();

        $dataDate = $SessionBusiness->Get($this->device);

        if ($dataDate) {
            $date = new DateTime();
            $dateCheck = $date->modify('+30 minutes');
            $now = new DateTime();

            if ($dateCheck > $now) {
                $SessionBusiness->Update($this->device);
                return true;
            }
        }

        Response::show(TypeResponseEnum::Unauthorized, 'Sessão não autênticada');
    }
}