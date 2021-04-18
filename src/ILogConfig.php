<?php


namespace APIORM;


interface ILogConfig
{
    public static function GetTableName(): string;

    public static function GetAuthor(string $path = null): ?int;

    public static function GetTypeLog(string $path): ?LogEntityConfig;
}