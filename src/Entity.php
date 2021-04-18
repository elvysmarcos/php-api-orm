<?php

namespace APIORM;

use APIORM\Resources\Content;

class Entity implements IEntity
{
    function ImportData($data = null, $extractFK = false): void
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
                $of = $settings->of;
                $to = $settings->to;

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
        $targetType = $targetType;

        foreach ($this as $key => $value) {

            $valueType = gettype($value);

            if ($targetType === 'object' && $valueType !== 'object' && property_exists($target, $key)) {
                $target->$key = $this->$key;
            } else if ($targetType === 'array' && $valueType !== 'object' && key_exists($key, $target)) {
                $target[$key] = $this->$key;
            } else if ($targetType === 'object' && property_exists($target, $key)) {
                $target->$key = $this->$key->ExportData($value);
            } else if ($targetType === 'array' && key_exists($key, $target)) {
                $target[$key] = $this->$key->ExportData((array)$value);
            }
        }

        return $target;
    }

    function Clone(IEntity $entity): void
    {
        foreach ($entity as $key => $value) {
            $this->$key = $entity->$key;
        }
    }
}
