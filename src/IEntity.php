<?php


namespace APIORM;


interface IEntity
{
    function ImportData($data = null, bool $extractFK = false);
}