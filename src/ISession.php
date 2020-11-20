<?php


namespace APIORM;


interface ISession
{
    static function GetPathAuthorization(): ?string;

    static function GetPerson(): ?int;

    static function GetApp(): ?int;

    public function GetSecret(string $device): ?string;

    public function Update(string $device): \DateTime;

    public function Destroy();

    public function GetUnauthorizedText(): string;
}