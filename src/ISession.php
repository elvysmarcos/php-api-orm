<?php


namespace APIORM;


interface ISession
{
    static function GetPathAuthorization(): ?string;

    static function GetOwner(): ?int;

    static function GetApp(): ?int;

    public function GetSecret(string $device): ?string;

    public function GetStatusIgnoreCache(): bool;

    public function Renew(string $owner, string $device): void;

    public function RenewByCache(string $owner, string $device): void;

    public function Destroy();

    public function GetUnauthorizedText(): string;
}