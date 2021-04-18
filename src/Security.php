<?php

namespace APIORM;

use APIORM\Enums\ResponseTypeEnum;
use DateTime;
use SAWToolkit\Resources\Dates;
use SAWToolkit\Resources\FormatDate;

class Security
{
    public $device;

    private $token;
    private $iat;
    private $header = [];
    public $payload = [];
    private $signature;

    public function __construct(string $path = 'Authorization')
    {
        $headers = apache_request_headers();

        if (isset($headers[$path]) and $headers[$path]) {
            $this->token = $headers[$path];
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
            $this->header = json_decode(Encryption::Base64Decode($parameters[0]), true);
            $this->payload = json_decode(Encryption::Base64Decode($parameters[1]), true);
            $this->signature = str_replace('+', null, $parameters[2]);
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
            $now = Dates::Date(FormatDate::Full);

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

        if (key_exists('SESSION_CACHE_SECRET', $_ENV)) {
            $payload['cache'] = md5("{$_ENV['SESSION_CACHE_SECRET']}{$this->iat}");
        }

        if (is_array($customPayload) and count($customPayload)) {
            $payload = array_merge($payload, $customPayload);
        }

        $raw = Encryption::Base64Encode(json_encode($header)) . '.' . Encryption::Base64Encode(json_encode($payload));

        $signature = Encryption::Base64Encode(hash_hmac('sha256', $raw, $secret, true));

        return "{$raw}.{$signature}";
    }

    private function CheckSignature($secret)
    {
        if (count($this->header) && count($this->payload)) {

            $raw = Encryption::Base64Encode(json_encode($this->header))
                . '.'
                . Encryption::Base64Encode(json_encode($this->payload));

            $signature = Encryption::Base64Encode(hash_hmac('sha256', $raw, $secret, true));
            $signature = str_replace(['+', ' '], '', $signature);

            if ($this->signature == $signature) {
                return true;
            }
        }

        return false;
    }

    public function AuthenticatedRegion(ISession $session)
    {
        if ($this->device) {

            $owner = $this->ExtractDataDevice($this->device);

            if (!$session->GetStatusIgnoreCache() && key_exists('SESSION_CACHE_SECRET', $_ENV) && key_exists('cache', $this->payload)) {
                $cache = md5("{$_ENV['SESSION_CACHE_SECRET']}{$this->payload['iat']}");

                if (
                    $cache === $this->payload['cache']
                    && $owner
                ) {
                    $session->RenewByCache($owner, $this->device);
                    return true;
                }
            }

            $session->Destroy();

            $owner = $this->ExtractDataDevice($this->device);

            $secret = $session->GetSecret($this->device);

            $signature = $this->CheckSignature($secret);

            if ($signature) {
                $session->Renew($owner, $this->device);
                return true;
            }
        }

        $session->Destroy();

        Response::Show(ResponseTypeEnum::Unauthorized, $session->GetUnauthorizedText());
    }

    private function CheckSessionByProvider(string $url)
    {
        $session = curl_init($url);

        curl_exec($session);

        if (curl_error($session)) {
            curl_close($session);
            return false;
        }

        curl_close($session);
        return true;
    }
}