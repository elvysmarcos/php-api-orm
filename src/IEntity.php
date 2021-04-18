<?php


namespace APIORM;


interface IEntity
{
    function ImportData($data = null, bool $extractFK = false): void;

    function ExportData($target);

    function Clone(self $entity): void;
}