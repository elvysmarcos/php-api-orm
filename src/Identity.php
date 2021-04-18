<?php


namespace APIORM;


class Identity
{
    public string $owner;
    public string $secret;
    public string $expire;

    public function __construct($owner = null, string $secret = null, string $expire = null)
    {
        if ($owner) {
            $this->owner = $owner;
        }

        if ($secret) {
            $this->secret = $secret;
        }

        if ($expire) {
            $this->expire = $expire;
        }
    }
}