<?php


namespace APIORM;


interface ILogConfig
{
    public static function GetTableName(): string;

    public static function GetAuthor(): ?int;

    public static function GetTypeLog(string $path): ?array;
}