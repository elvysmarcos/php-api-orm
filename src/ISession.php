<?php


namespace APIORM;


interface ISession
{
    static function GetPerson(): ?int;

    static function GetApp(): ?int;

    public function GetIat(string $device): ?int;

    public function GetSecret(string $device): ?string;

    public function Update(string $device): \DateTime;

    public function Destroy();

    public function GetUnauthorizedText(): string;
}