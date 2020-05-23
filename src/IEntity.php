<?php


namespace Core;


interface IEntity
{
    function ImportData($data = null, bool $extractFK = false);
}