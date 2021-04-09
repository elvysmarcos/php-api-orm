<?php


namespace APIORM;


interface ISession
{
    static function GetPathAuthorization(): ?string;

    static function GetOwner(): ?int;

    static function GetApp(): ?int;

    public function GetSecret(string $device): ?string;

    public function Renew(string $device): \DateTime;

    public function Destroy();

    public function GetUnauthorizedText(): string;
}