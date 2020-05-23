<?php

namespace Core;

use Core\Enums\TypeResponseEnum;
use Core\Resources\Content;

class Database
{
    //<editor-fold desc="[ Parameters ]">
    private IDatabaseDrive $drive;
    public $debug = false;
    public $limit = null;
    private $defaultTable = null;
    private $entities = array();
    private $maps = array();
    private $vars = array();
    private $joins = array();
    private $conditions = array();
    private $orderBy = array();
    private $response = array(
        'current' => 0,
        'limit' => false,
        'total' => 0,
        'items' => array()
    );
    private $saveChanges;
    private $saveChangesEntity = null;

    //</editor-fold>

    public function __construct()
    {
        if (DB_DRIVE == 'mysql') {
            $this->drive = new MysqlDatabase();
        } else {
            $this->drive = new SqlDataBase();
        }

        $this->debug = $this->drive->debug;
    }

    private function Reset()
    {
        $this->defaultTable = null;
        $this->entities = array();
        $this->maps = array();
        $this->vars = array();
        $this->joins = array();
        $this->conditions = array();
        $this->orderBy = array();
        $this->response = array(
            'current' => 0,
            'limit' => false,
            'total' => 0,
            'items' => array()
        );
        $this->limit = null;
        $this->saveChanges = null;
        $this->saveChangesEntity = null;
    }

    public function Join($entity, $of, $to, $required = true)
    {
        $params = $this->GetParametersConditions(__FUNCTION__);
        $params = explode(',', $params);

        $entityVar = explode(' = ', $params[0]);
        $entityVar = trim($entityVar[0]);
        $entityClass = get_class($entity);

        $this->vars[$entityVar] = str_replace('\\', '_', $entityClass);
        $this->GetEntities($entityClass, $this->vars[$entityVar], (array)$entity);

        $of = explode('->', trim($params[1]));
        $columnOf = array_pop($of);
        $entityOf = array_shift($of);
        $entityOf = $this->vars[$entityOf];

        if (count($of)) {
            $entityOf .= '_' . implode('_', $of);
        }

        $to = explode('->', trim($params[2]));
        $columnTo = array_pop($to);
        $entityTo = array_shift($to);
        $entityTo = $this->vars[$entityTo];

        if (count($to)) {
            $entityTo .= '_' . implode('_', $to);
        }

        $this->JoinAux($entityOf, $columnOf, $columnTo, $entityTo, $required);
    }

    private function JoinAux($joinPath, $of, $to = null, $toJoinPath = null, $required = true)
    {
        $type = $required ? null : 'LEFT ';

        $this->joins[$joinPath] = array('of' => $of, 'to' => $to, 'type' => $type, 'toEntity' => $toJoinPath);
    }

    public function OrderBy($column, $direction = 'DESC')
    {
        $param = $this->GetParametersConditions(__FUNCTION__);

        $var = explode(',', $param)[0];
        $var = explode('->', $var);
        $column = array_pop($var);
        $entity = array_shift($var);
        $entity = $this->vars[$entity];

        if (count($var)) {
            $entity .= '_' . implode('_', $var);
        }

        $this->orderBy[] = array(
            'columnEntity' => $entity,
            'column' => $column,
            'direction' => $direction
        );
    }

    public function PageResponse($current = 0, $limit = 25)
    {
        $this->response['current'] = $current;
        $this->response['limit'] = $limit;
    }

    public function ToList($params = null): ?array
    {
        return $this->AuxSelect($params);
    }

    private function AuxSelect($params = null)
    {
        //<editor-fold desc=" [ Compile Columns ] ">
        $columns = array();

        if (is_array($params) && count($params)) {
            $params = $this->FixCustomMap($params);
        }

        foreach ($this->entities as $key => $value) {
            foreach ($value as $keyColumns => $valueColumns) {
                $columns[] = $this->drive->GetFormattedSelectColumns($key, $keyColumns);
            }
        }

        $columns = implode(', ', $columns);
        //</editor-fold>

        $query = $this->drive->GetFormattedSelectQuery($this->maps[$this->defaultTable], $this->defaultTable, $columns);

        //<editor-fold desc="[ Compile Joins ]">
        if (count($this->joins)) {

            $joins = null;
            $hasNotRequired = false;

            foreach ($this->joins as $key => $value) {

                if ($value['type']) {
                    $hasNotRequired = true;
                }

                if ($hasNotRequired) {
                    $value['type'] = 'LEFT ';
                }

                $conditions = null;

                if (isset($value['and'])) {
                    $conditions = array();

                    foreach ($value['and'] as $keyCond => $valueCond) {

                        $conditions[] = $this->drive->GetFormattedEqualityComparison($key, $valueCond['of'], $value['toEntity'], $valueCond['to']);
                    }

                    $conditions = implode(' AND ', $conditions);
                } else {
                    $conditions = $this->drive->GetFormattedEqualityComparison($key, $value['of'], $value['toEntity'], $value['to']);
                }

                $joins .= $this->drive->GetFormattedJoinQuery($value['type'], $this->maps[$key], $key, $conditions);
            }

            $query .= $joins;
        }
        //</editor-fold>

        //<editor-fold desc="[ Compile Where]">
        $query .= $this->drive->GetFormattedConditionStart();

        $query .= $this->GetConditions();
        //</editor-fold>

        //<editor-fold desc=" [ Compile Order By ] ">
        if (count($this->orderBy)) {
            $orders = array();

            foreach ($this->orderBy as $key => $value) {

                $orders[] = $this->drive->GetFormattedOrders($value['columnEntity'], $value['column'], $value['direction']);
            }

            $query .= ' ORDER BY' . implode(' ,', $orders);
        }
        //</editor-fold>

        //<editor-fold desc=" [ Get Total ] ">
        $response = $this->response;
        $total = null;

        if ($response['limit'] !== false && $response['limit'] > 0) {
            $result_total = $this->drive->Execute($query) or die($this->drive->DBReport($query));
            $total = $this->drive->GetNumRows($result_total);
        }
        //</editor-fold>

        //<editor-fold desc=" [ Compile Limit ] ">
        $queryLimit = null;

        if ($response['limit'] !== false) {
            $queryLimit = $this->drive->GetFormattedFullLimit($response['current'], $response['limit']);
        } else if ($this->limit !== null) {
            $queryLimit = $this->drive->GetFormattedBasicLimit($this->limit);
        }

        $query = $this->drive->GetFormattedLimit($query, $queryLimit);
        //</editor-fold>

        //<editor-fold desc="[ Result ]">
        if ($this->debug) {
            debugSql($query);
        }

        $result = $this->drive->Execute($query) or die($this->drive->DBReport($query, 2));

        $hasRows = $this->drive->GetNumRows($result);

        if ($response['total'] > 1) {
            $total = $hasRows;
        }

        if (!$hasRows) {
            $content = array();

            if ($total !== null) {
                $response['total'] = $total;
                $response['items'] = $content;
                $content = $response;
            }

            $this->Reset();

            return $content;
        } else {
            $content = array();

            while ($res = $this->drive->FetchArray($result)) {

                $fix = [];

                if ($params && count($params)) {
                    foreach ($params as $key => $value) {
                        $fix[str_replace('$', null, $key)] = $this->FixMaps($this->vars[$key], $value, $res);
                    }
                } else {
                    foreach ($this->vars as $key => $value) {
                        $nameClassMap = str_replace('_', '\\', $value);
                        $resultMap = new $nameClassMap;
                        $fix[str_replace('$', null, $key)] = $this->FixMaps($value, $resultMap, $res);
                    }
                }

                if (count($fix) > 1) {
                    $content[] = (object)$fix;
                } else {
                    $content[] = array_shift($fix);
                }
            }

            if ($total !== null) {
                $response['total'] = $total;
                $response['items'] = $content;
                $content = $response;
            }

            $this->Reset();

            return $content;
        }
        //</editor-fold>
    }

    private function FixCustomMap($map)
    {
        $new = array();

        foreach ($map as $key => $value) {
            if (is_array($value)) {
                $new[$key] = (object)$this->FixCustomMap($value);
            } else {
                $new[$value] = null;
            }
        }

        return $new;
    }

    private function GetConditionsPrimaryKeys($entityName, ?array $properties)
    {
        $query = null;

        if (!count($this->conditions) && defined("$entityName::_id")) {
            foreach ($entityName::_id as $key => $autoIncrement) {
                if (key_exists($key, $properties)) {

                    $value = "= {$properties[$key]}";

                    if ($properties[$key] === null) {
                        $value = "IS NULL";
                    }

                    $query .= $this->drive->GetFormattedConditionsPrimaryKeys($key, $value);
                }
            }
        }

        return $query;
    }

    private function GetParametersConditions(string $part)
    {
        $bt = debug_backtrace();
        $file = file($bt[1]['file']);
        $src = $file[$bt[1]['line'] - 1];
        $part = '#(.*)' . $part . ' *?\( *?(.*) *?\)(.*)([\n])#i';
        return $var = preg_replace($part, '$2', $src);
    }

    private function GetConditions()
    {
        $query = null;

        if (count($this->conditions)) {
            foreach ($this->conditions as $key => $value) {

                if (is_array($value['compare'])) {
                    $value['compare'] = '(' . implode(',', $value['compare']) . ')';
                }

                if (isset($value['agroup']) and $value['agroup'] === true) {
                    $query .= " {$value['operator']} (";

                    $value['operator'] = null;
                }

                $value['condition'] = str_replace('==', '=', $value['condition']);

                $query .= $this->drive->GetFormattedConditions($value['operator'], $value['entity'], $value['column'], $value['condition'], $value['compare']);

                if (isset($value['agroup']) and $value['agroup'] === false) {
                    $query .= " )";
                }
            }
        }

        return $query;
    }

    private function GetEntities($entity, $path, $property)
    {
        $this->maps[$path] = $entity::_table;

        if (count($property)) {
            foreach ($property as $key => $value) {
                if (gettype($value) === 'object') {
                    $setting = $entity::$key();
                    $this->JoinAux("{$path}_{$key}", $setting['of'], $setting['to'], $path, $setting['required']);

                    $this->GetEntities($setting['class'], "{$path}_{$key}", (array)$value);
                } else {
                    $this->entities[$path][$key] = null;
                }
            }
        }
    }

    private function FixMaps($entity, $propriety, $result)
    {
        $changed = false;

        foreach ($propriety as $key => $value) {
            if (gettype($value) === 'object') {
                $propriety->$key = $this->FixMaps("{$entity}_{$key}", $value, $result);
            } else {

                if (key_exists("{$entity}.{$key}", $result)) {
                    $propriety->$key = Content::Fix($result["{$entity}.{$key}"]);

                    unset($result["{$entity}.{$key}"]);

                    $changed = true;
                }
            }
        }

        return $changed ? $propriety : null;
    }

    public function Insert(IEntity $entity)
    {
        $entity->ImportData(null, true);

        $entityName = get_class($entity);
        $properties = (array)$entity;

        $fields = [];
        $values = null;

        if (count($properties)) {
            foreach ($properties as $key => $value) {

                if (defined("$entityName::_id") && key_exists($key, $entityName::_id) && $entityName::_id[$key]) {
                    continue;
                }

                $fields[] = $this->drive->GetFormattedInsertColumns($key);

                if (gettype($value) === 'integer' || gettype($value) === 'double') {
                    $values .= $values === null ? $value : ",{$value}";
                } else if (gettype($value) === 'boolean') {
                    $values .= $values === null ? $value : "," . +$value;
                } else if ($value === null) {
                    $values .= $values === null ? $value : ", NULL";
                } else {
                    $values .= $values === null ? "'{$value}'" : ",'{$value}'";
                }
            }
        }

        $query = "INSERT INTO " . $entity::_table . " (" . implode(',', $fields) . ") VALUES({$values}); ";

        if ($this->debug) {
            debugSql($query, 2);
        }

        $result = null;

        $execute = $this->drive->Execute($query) or die($this->drive->DBReport($query));

        if ($execute) {

            if (defined("$entityName::_id")) {

                $recentId = $this->drive->GetRecentId();

                $DB = new Database();
                $DB->Select($e = new $entityName);

                foreach ($entityName::_id as $key => $autoIncrement) {
                    if ($autoIncrement && $recentId) {
                        $DB->Where($key, '=', $recentId, 'AND', null, '$e');
                    } else if (key_exists($key, $properties)) {
                        $DB->Where($key, '=', $properties[$key], 'AND', null, '$e');
                    }
                }

                $result = $DB->First();

            } else {
                $result = $entity;
            }
        }

        $this->Reset();

        return $result;
    }

    public function Select(IEntity $entity = null)
    {
        $entityClass = get_class($entity);

        $this->defaultTable = $path = str_replace('\\', '_', $entityClass);

        $param = $this->GetParametersConditions(__FUNCTION__);

        $var = explode(' = ', $param);
        $var = trim($var[0]);

        $this->vars[$var] = $path;

        $this->GetEntities($entityClass, $path, (array)$entity);
    }

    public function Where($defaultColumn, $condition, $compare = null, $operator = 'AND', $agroup = null, string $defaultEntityVar = null)
    {
        $param = $this->GetParametersConditions(__FUNCTION__);

        $var = explode(',', $param)[0];

        $defaultVar = $var;

        $var = explode('->', $var);
        $entity = null;

        if (count($var) === 1 && $var[0] == '$key') {
            $var = array();
            $column = $defaultColumn;

            if ($defaultEntityVar && isset($this->vars[$defaultEntityVar])) {
                $entity = $this->vars[$defaultEntityVar];
            }

        } else {
            $column = array_pop($var);
            $entity = array_shift($var);
            $entity = isset($this->vars[$entity]) ? $this->vars[$entity] : null;
        }

        if ($entity === null && count($var) >= 2) {
            new ApiCustomException("Limite classes filhas atingidas para condição {$defaultVar}");
        } else if ($entity === null && count($var) === 1) {
            $subClass = $var[0];
            $nameClassMap = get_class($this->saveChangesEntity);
            $settings = $nameClassMap::$subClass();
            $column = $settings['to'];
        } else if ($entity !== null && count($var)) {
            $entity .= '_' . implode('_', $var);
        }

        $this->conditions[] = array('entity' => $entity, 'column' => $column, 'condition' => $condition, 'compare' => $compare, 'operator' => $operator, 'agroup' => $agroup);
    }

    public function First($params = null)
    {
        $data = $this->AuxSelect($params);

        return isset($data[0]) ? $data[0] : null;
    }

    public function Update(IEntity $entity)
    {
        $this->saveChanges = 'update';
        $this->saveChangesEntity = $entity;
    }

    public function Delete(IEntity $entity)
    {
        $this->saveChanges = 'delete';
        $this->saveChangesEntity = $entity;
    }

    public function SaveChanges()
    {
        if ($this->saveChanges === 'update') {
            return $this->AuxUpdate($this->saveChangesEntity);
        } else if ($this->saveChanges === 'delete') {
            return $this->AuxDelete($this->saveChangesEntity);
        } else {
            Response::show(TypeResponseEnum::SQL, 'Argumentos incompletos para está ação');
            return false;
        }
    }

    private function AuxUpdate(IEntity $entity)
    {
        $entity->ImportData(null, true);
        $entityName = get_class($entity);
        $properties = (array)$entity;

        $fields = array();

        foreach ($properties as $key => $value) {

            if (defined("$entityName::_id") && key_exists($key, $entityName::_id)) {
                continue;
            } else {
                $fields[] = $this->drive->GetFormattedUpdateColumns($key, $value);
            }
        }

        $fields = implode(', ', $fields);

        $query = $this->GetConditionsPrimaryKeys($entityName, $properties);

        if ($query === null) {
            $query .= $this->GetConditions();
        }

        if ($query === null) {
            new ApiCustomException('Não parametros para continurar com a alteração = Update');
        }

        $query = "UPDATE " . $entity::_table . " SET {$fields} {$this->drive->GetFormattedConditionStart()} {$query}";

        if ($this->debug) {
            debugSql($query);
        }

        $result = null;

        $execute = $this->drive->Execute($query) or die($this->drive->DBReport($query));

        if ($execute) {

            if (defined("$entityName::_id")) {

                $DB = new Database();
                $DB->Select($e = new $entityName);

                foreach ($entityName::_id as $key => $autoIncrement) {
                    $condition = "=";

                    if ($properties[$key] === null) {
                        $condition = "IS NULL";
                    }

                    if (key_exists($key, $properties)) {
                        $DB->Where($key, $condition, $properties[$key], 'AND', null, '$e');
                    }
                }

                $result = $DB->First();

            } else {
                $result = $entity;
            }
        }

        $this->Reset();

        return $result;
    }

    private function AuxDelete(IEntity $entity)
    {
        $entity->ImportData(null, true);

        $entityName = get_class($entity);
        $properties = (array)$entity;

        $query = $this->GetConditionsPrimaryKeys($entityName, $properties);

        $query .= $this->GetConditions();

        if ($query === null) {
            new ApiCustomException('Não parametros para continurar com a alteração = Update');
        }

        $query = "DELETE FROM " . $entityName::_table . " {$this->drive->GetFormattedConditionStart()} {$query}";

        if ($this->debug) {
            debugSql($query);
        }

        $execute = $this->drive->Execute($query) or die($this->drive->DBReport($query));

        $this->Reset();

        return $execute ? true : false;
    }

    public function Query($query)
    {
        if ($this->debug) {
            debugSql($query);
        }

        return $this->drive->CustomQuery($query);
    }

    public function Status()
    {
        $this->drive->DBLink();

        return true;
    }
}
