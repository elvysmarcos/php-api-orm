<?php

namespace APIORM;

use APIORM\Resources\Content;

class Entity implements IEntity
{
    function ImportData($data = null, $extractFK = false)
    {
        if ($data === null) {
            $data = clone($this);
        }

        $typeData = gettype($data);

        foreach (clone($this) as $key => $value) {
            $type = gettype($value);

            $exist = false;

            if ($typeData === 'object') {
                $exist = property_exists($data, $key);
            } else {
                $exist = key_exists($key, $data);
            }

            if ($exist && $type === 'object' && $extractFK) {
                $settings = $this::$key();
                $of = $settings['of'];
                $to = $settings['to'];

                unset($this->$key);

                if ($typeData === 'object') {
                    $this->$to = Content::Fix($data->$key->$of);
                } else if ($typeData === 'array') {
                    $this->$to = Content::Fix($data[$key][$of]);
                }
            } else if ($exist && $type === 'object') {
                if ($typeData === 'object') {
                    $this->$key->ImportData($data->$key);
                } else if ($typeData === 'array') {
                    $this->$key->ImportData($data[$key]);
                }
            } else if ($exist) {
                if ($typeData === 'object') {
                    $this->$key = Content::Fix($data->$key);
                } else if ($typeData === 'array') {
                    $this->$key = Content::Fix($data[$key]);
                }
            } else {
                unset($this->$key);
            }
        }
    }

    function ExportData($target)
    {
        foreach ($this as $key => $value) {
            if (gettype($target) === 'object' && gettype($value) !== 'object' && property_exists($target, $key)) {
                $target->$key = $this->$key;
            } else if (gettype($target) === 'array' && gettype($value) !== 'object' && key_exists($key, $target)) {
                $target[$key] = $this->$key;
            } else if (gettype($target) === 'object' && property_exists($target, $key)) {
                $target->$key = $this->$key->ExportData($value);
            } else if (gettype($target) === 'array' && key_exists($key, $target)) {
                $target[$key] = $this->$key->ExportData((array)$value);
            }
        }

        return $target;
    }
}
