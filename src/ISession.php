<?php


namespace APIORM;


interface ISession
{
    static function getPerson();

    static function getApp();

    public function Get(string $device);

    public function Update(string $device);

    public function Destroy();
}