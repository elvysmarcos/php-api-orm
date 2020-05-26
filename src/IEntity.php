<?php


namespace APIORM;


interface IEntity
{
    function ImportData($data = null, bool $extractFK = false);

    function ExportData($target);

    static function Find($id = null);

    static function All();

    static function Paginate(int $current, int $limit);

    function Update();

    static function Delete($id = null);
}