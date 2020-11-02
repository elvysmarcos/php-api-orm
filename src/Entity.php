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

    static function Find($id = null)
    {
        $class = get_called_class();

        $values = [];

        if (gettype($id) === 'integer') {
            foreach ($class::_id as $key => $autoIncrement) {
                $values[$key] = $id;
            }
        } else if (is_array($id) && count($id) === count($class::_id)) {
            $index = 0;
            foreach ($class::_id as $key => $autoIncrement) {
                $values[$key] = $id[$index];
                $index++;
            }
        } else {
            new ApiCustomException('Invalid method call, you need send a correct value');
        }

        $db = new Database();
        $db->Select($e = new $class);

        foreach ($class::_id as $key => $autoIncrement) {

            $condition = '=';

            if ($values[$key] === null) {
                $condition = 'IS NULL';
            }

            $db->Where($key, $condition, $values[$key], null, '$e');
        }

        $result = $db->First();

        return $result;
    }

    static function All()
    {
        $class = get_called_class();

        $db = new Database();
        $db->Select($e = new $class);
        $result = $db->ToList();

        return $result;
    }

    static function Paginate(int $current, int $limit)
    {
        $class = get_called_class();

        $db = new Database();
        $db->Select($e = new $class);
        $db->PageResponse($current, $limit);
        $result = $db->ToList();

        return $result;
    }

    function Insert()
    {
        $db = new Database();
        $result = $db->Insert(clone($this));

        foreach ($result as $key => $value) {
            $this->$key = $result->$key;
        }
    }

    function Update()
    {
        $db = new Database();
        $db->Update(clone($this));
        $result = $db->SaveChanges();

        foreach ($result as $key => $value) {
            $this->$key = $result->$key;
        }
    }

    static function Delete($id = null)
    {
        $class = get_called_class();

        $values = [];

        if (gettype($id) === 'integer') {
            foreach ($class::_id as $key => $autoIncrement) {
                $values[$key] = $id;
            }
        } else if (is_array($id) && count($id) === count($class::_id)) {
            $index = 0;
            foreach ($class::_id as $key => $autoIncrement) {
                $values[$key] = $id[$index];
                $index++;
            }
        } else {
            new ApiCustomException('Invalid method call, you need send a correct value');
        }

        $db = new Database();
        $db->Delete($e = new $class);

        foreach ($class::_id as $key => $autoIncrement) {
            $condition = '=';

            if ($values[$key] === null) {
                $condition = 'IS NULL';
            }

            $db->Where($key, $condition, $values[$key], null, '$e');
        }

        $result = $db->SaveChanges();

        return $result;
    }
}
