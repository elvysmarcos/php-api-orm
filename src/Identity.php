<?php


namespace APIORM;


use DateTime;

class Identity
{
    public string $owner;
    public string $secret;
    public DateTime $expire;

    public function __construct(string $owner = null, string $secret = null, DateTime $expire = null)
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