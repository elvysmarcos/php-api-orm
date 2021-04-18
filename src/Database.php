<?php

namespace APIORM;

use APIORM\Enums\DBOperationTypeEnum;
use APIORM\Enums\ResponseTypeEnum;
use APIORM\Resources\Content;
use APIORM\Resources\ExtractFunctionArgs;

class Database
{
    //<editor-fold desc="[ Parameters ]">
    private IDatabaseDrive $drive;
    public $debug = false;
    public $limit = null;
    private $resultModel = null;
    private $defaultTable = null;
    private $maps = array();
    private $vars = array();
    private $varsContent = array();
    private $colums = array();
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
    private ?ILogConfig $log = null;

    //</editor-fold>

    public function __construct()
    {
        if ($_ENV['DB_DRIVE'] == 'mysql') {
            $this->drive = new MysqlDatabase();
        } else {
            $this->drive = new SqlDataBase();
        }

        $this->debug = $this->drive->debug;

        if (isset($_ENV['DB_LOG'])
            && $_ENV['DB_LOG'] && class_exists($_ENV['DB_LOG'])
        ) {
            $this->log = new $_ENV['DB_LOG'];
        }
    }

    private function Reset()
    {
        $this->limit = null;
        $this->resultModel = null;
        $this->defaultTable = null;
        $this->maps = array();
        $this->vars = array();
        $this->varsContent = array();
        $this->colums = array();
        $this->joins = array();
        $this->conditions = array();
        $this->orderBy = array();
        $this->response = array(
            'current' => 0,
            'limit' => false,
            'total' => 0,
            'items' => array()
        );
        $this->saveChanges = null;
        $this->saveChangesEntity = null;
        $this->log = null;
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

                if (defined("{$entityName}::_id") && key_exists($key, $entityName::_id) && $entityName::_id[$key]) {
                    continue;
                }

                $fields[] = $this->drive->GetFormattedInsertColumns($key);

                $typeValue = gettype($value);

                if ($typeValue === 'integer' || $typeValue === 'double') {
                    $values .= $values === null ? $value : ",{$value}";
                } else if ($typeValue === 'boolean') {
                    $values .= $values === null ? $value : ',' . +$value;
                } else if ($value === null) {
                    $values .= $values === null ? $value : ', NULL';
                } else {
                    $values .= $values === null ? "'{$value}'" : ",'{$value}'";
                }
            }
        }

        $query = "INSERT INTO " . $entity::_table . " (" . implode(',', $fields) . ") VALUES({$values}); ";

        if ($this->debug) {
            debugSql($query, 2);
        }


        $execute = $this->drive->Execute($query) or die($this->drive->DBReport($query));

        if ($execute) {
            if (defined("{$entityName}::_id")) {

                $recentId = $this->drive->GetRecentId();

                $DB = new Database();
                $DB->From($e = new $entityName);

                foreach ($entityName::_id as $key => $autoIncrement) {
                    if ($autoIncrement && $recentId) {
                        $DB->Where($key, '=', $recentId, null, '$e');
                    } else if (key_exists($key, $properties)) {
                        $DB->Where($key, '=', $properties[$key], null, '$e');
                    }
                }

                $entity = $DB->First();
            }

            $this->Log($entity);
        }

        $this->Reset();

        return $entity;
    }

    public function Update(IEntity $entity)
    {
        $this->saveChanges = 'update';
        $this->saveChangesEntity = $entity;

        return $this;
    }

    public function From(IEntity $entity = null)
    {
        $entityClass = get_class($entity);

        $this->defaultTable = $path = str_replace('\\', '_', $entityClass);

        $param = ExtractFunctionArgs::Get();

        $var = explode('=', $param);
        $var = trim($var[0]);

        $this->vars[$var] = $path;

        $this->GetEntities($entityClass, $path, (array)$entity);

        return $this;
    }

    public function Where($defaultColumn, string $condition, $compare = null, string $agroup = null, string $defaultEntityVar = null)
    {
        $param = ExtractFunctionArgs::Get();
        $this->AddConditions($param, $defaultColumn, $condition, $compare, 'AND', $agroup, $defaultEntityVar);

        return $this;
    }

    public function And($defaultColumn, string $condition, $compare = null, string $agroup = null, string $defaultEntityVar = null)
    {
        $param = ExtractFunctionArgs::Get();
        $this->AddConditions($param, $defaultColumn, $condition, $compare, 'AND', $agroup, $defaultEntityVar);

        return $this;
    }

    public function Or($defaultColumn, string $condition, $compare = null, string $agroup = null, string $defaultEntityVar = null)
    {
        $param = ExtractFunctionArgs::Get();
        $this->AddConditions($param, $defaultColumn, $condition, $compare, 'OR', $agroup, $defaultEntityVar);

        return $this;
    }

    public function Join(IEntity $entity, $of, $to, $required = true)
    {
        $params = ExtractFunctionArgs::Get();
        $params = explode(',', $params);

        $entityVar = explode('=', $params[0]);
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

        return $this;
    }

    public function Select(array $columns)
    {
        $columnsTipe = gettype($columns);

        $args = ExtractFunctionArgs::Get();

        $args = str_replace('\'', '"', $args);
        $args = str_replace('=>', '":"', $args);
        $args = str_replace('""', '"', $args);
        $args = str_replace('[', '{"', $args);
        $args = str_replace(']', '"}', $args);
        $args = str_replace('{"', '{">>x<<":"', $args);
        $args = str_replace(',', '",">>x<<":"', $args);
        $args = str_replace('">>x<<":""', '"', "{$args}\"");
        $args = str_replace('}"', '}', $args);
        $args = str_replace('"{', '{', $args);

        $keys = explode('":', $args);
        $keysNumber = count($keys);

        if ($keysNumber) {
            for ($i = 0; $i < $keysNumber; $i++) {
                $keys[$i] = str_replace('>>x<<', $i, $keys[$i]);
            }
        }

        $args = implode('":', $keys);
        $args = json_decode($args, true);

        if (!is_array($args)) {
            new ApiCustomException('Something didn\'t happen as expected');
        }

        $this->resultModel = $this->NormalizeColumnsResult($args);

        return $this;
    }

    public function OrderBy($column, $direction = 'DESC')
    {
        $param = ExtractFunctionArgs::Get();

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

        return $this;
    }

    public function PageResponse($current = 0, $limit = 25)
    {
        $this->response['current'] = $current;
        $this->response['limit'] = $limit;

        return $this;
    }

    public function First()
    {
        $this->limit = 1;
        $data = $this->AuxSelect();

        return isset($data[0]) ? $data[0] : null;
    }

    public function ToList(): ?array
    {
        return $this->AuxSelect();
    }

    public function Delete(IEntity $entity)
    {
        $this->saveChanges = 'delete';
        $this->saveChangesEntity = $entity;

        return $this;
    }

    public function SaveChanges()
    {
        if ($this->saveChanges === 'update') {
            return $this->AuxUpdate($this->saveChangesEntity);
        } else if ($this->saveChanges === 'delete') {
            return $this->AuxDelete($this->saveChangesEntity);
        } else {
            Response::Show(ResponseTypeEnum::BadRequest, 'Incomplete arguments for this action');
            return false;
        }
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

    public function CloseConnection(bool $rollback = false)
    {
        $this->drive->DBClose($rollback);
    }

    private function GetConditions()
    {
        $query = null;

        if (count($this->conditions)) {
            foreach ($this->conditions as $key => $value) {

                $compareType = gettype($value['compare']);

                if ($compareType === 'array' && count($value['compare']) === 0) {
                    continue;
                } else if ($compareType === 'array') {
                    $value['compare'] = '(' . implode(',', $value['compare']) . ')';
                }

                if (isset($value['agroup']) and $value['agroup'] === '(') {
                    $query .= " {$value['operator']} (";

                    $value['operator'] = null;
                }

                $value['condition'] = str_replace('==', '=', $value['condition']);

                $query .= $this->drive->GetFormattedConditions($value['operator'], $value['entity'], $value['column'], $value['condition'], $value['compare']);

                if (isset($value['agroup']) and $value['agroup'] === ')') {
                    $query .= ')';
                }
            }
        }

        return $query;
    }

    private function AuxSelect()
    {
        //<editor-fold desc=" [ Compile Columns ] ">
        if ($this->resultModel === null) {
            $this->resultModel = $this->NormalizeColumnsResult(array_keys($this->vars));
        }

        $columns = [];

        foreach ($this->colums as $key => $value) {
            $pathKey = explode('.', $value);
            $columns[] = $this->drive->GetFormattedSelectColumns($pathKey[0], $pathKey[1]);
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

                $model = $this->resultModel;

                $resultMap = $this->FixMaps($model, $res);

                if (count($this->varsContent) === 1) {
                    $resultMap = array_shift($resultMap);
                } else {
                    $resultMap = (object)$resultMap;
                }

                $content[] = $resultMap;
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

    private function JoinAux($joinPath, $of, $to = null, $toJoinPath = null, $required = true)
    {
        $type = $required ? null : 'LEFT ';

        $this->joins[$joinPath] = array('of' => $of, 'to' => $to, 'type' => $type, 'toEntity' => $toJoinPath);
    }

    private function GetPathCollumByString($value): array
    {
        $paths = explode('->', $value);
        $isVar = strripos($paths[0], '$');

        if (!$isVar === false) {
            new ApiCustomException('Invalid Result Map');
        }

        $arrows = count($paths);
        $runFor = $arrows;

        if ($runFor > 1) {
            $runFor = ($runFor - 1);
        }

        $path = null;

        for ($i = 0; $i < $runFor; $i++) {
            if ($i === 0 && key_exists($paths[$i], $this->vars)) {
                $path .= "{$this->vars[$paths[$i]]}";
            } else {
                $path .= "_{$paths[$i]}";
            }
        }

        $mainVarContent = null;

        $varString = $paths[0];

        $existInVars = key_exists($varString, $this->vars);

        if ($existInVars && !key_exists($varString, $this->varsContent)) {
            $entiyName = str_replace('_', '\\', $this->vars[$paths[0]]);
            $this->varsContent[$varString] = $mainVarContent = new  $entiyName();
        } else if ($existInVars) {
            $mainVarContent = $this->varsContent[$varString];
        }

        eval('' . $varString . '= $mainVarContent;');

        $content = null;

        eval('$content = ' . $value . ';');

        $typeContent = gettype($content);

        if ($arrows > 1) {
            $end = $typeContent === 'object' ? '_' : '.';

            $path .= "{$end}{$paths[($arrows - 1)]}";
        }

        $key = str_replace('$', null, $paths[$arrows - 1]);

        return ['exploded' => $paths, 'key' => $key, 'column' => $path, 'type' => $typeContent, 'content' => $content];
    }

    private function GetConditionsPrimaryKeys($entityName, ?array $properties)
    {
        $query = null;

        if (!count($this->conditions) && defined("{$entityName}::_id")) {
            foreach ($entityName::_id as $key => $autoIncrement) {
                if (key_exists($key, $properties)) {

                    $value = "= '{$properties[$key]}'";

                    if ($properties[$key] === null) {
                        $value = "IS NULL";
                    }

                    $query .= $this->drive->GetFormattedConditionsPrimaryKeys($key, $value);
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
                    $this->JoinAux("{$path}_{$key}", $setting->of, $setting->to, $path, $setting->required);

                    $this->GetEntities($setting->from, "{$path}_{$key}", (array)$value);
                }
            }
        }
    }

    private function FixMaps($model, $result)
    {
        $typeModel = gettype($model);

        foreach ($model as $key => $value) {
            if (gettype($value) === 'object') {

                $fixed = $this->FixMaps(clone($value), $result);

                if ($typeModel === 'object') {
                    $model->$key = $fixed;
                } else {
                    $model[$key] = $fixed;
                }

            } else {

                if (key_exists($value, $result)) {

                    if ($typeModel === 'object') {
                        $model->$key = Content::Fix($result[$value]);

                        if (method_exists($model, $key)) {
                            $model->$key = $model::$key($model->$key);
                        }
                    } else {
                        $model[$key] = Content::Fix($result[$value]);
                    }
                }
            }
        }

        return $model;
    }

    private function NormalizeColumnsResult($args, string $entityString = null)
    {
        $new = [];

        $argsType = gettype($args);

        if ($argsType === 'object') {
            $new = $args;
        }

        foreach ($args as $key => $value) {
            $keyType = gettype($key);
            $valueType = gettype($value);

            if ($valueType === 'string') {
                $path = $this->GetPathCollumByString($value);
                $pathType = $path['type'];
                $pathKey = $path['key'];
                $pathColumn = $path['column'];
                $pathContent = $path['content'];

                if ($pathType === 'object') {
                    $new[$pathKey] = $this->NormalizeColumnsResult($pathContent, $pathColumn);
                } else {
                    $new[$pathKey] = $pathColumn;

                    if (!in_array($pathColumn, $this->colums)) {
                        $this->colums[] = $pathColumn;
                    }
                }
            } else if ($keyType === 'string' && $valueType === 'object') {
                $new->$key = $this->NormalizeColumnsResult($value, "{$entityString}_$key");
            } else if ($keyType === 'string' && $valueType === 'array') {
                $new[$key] = (object)$this->NormalizeColumnsResult($value);
            } else if ($keyType === 'string' && $valueType === 'NULL') {
                $pathColumn = "{$entityString}.$key";

                if ($argsType === 'object') {
                    $new->$key = $pathColumn;
                } else {
                    $new[$key] = $pathColumn;
                }

                if (!in_array($pathColumn, $this->colums)) {
                    $this->colums[] = $pathColumn;
                }
            }
        }

        return $new;
    }

    private function AddConditions($param, $defaultColumn, $condition, $compare, string $operator, $agroup, string $defaultEntityVar = null)
    {
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

        $countVar = count($var);

        if ($entity === null && $countVar >= 2) {
            new ApiCustomException("Limit child classes reached for condition  {$defaultVar}");
        } else if ($entity === null && $countVar === 1) {
            $subClass = $var[0];
            $nameClassMap = get_class($this->saveChangesEntity);
            $settings = $nameClassMap::$subClass();
            $column = $settings->to;
        } else if ($entity !== null && $countVar) {
            $entity .= '_' . implode('_', $var);
        }

        $this->conditions[] = array('entity' => $entity, 'column' => $column, 'condition' => $condition, 'compare' => $compare, 'operator' => $operator, 'agroup' => $agroup);
    }

    private function AuxUpdate(IEntity $entity)
    {
        $entity->ImportData(null, true);
        $entityName = get_class($entity);
        $properties = (array)$entity;

        $fields = array();

        foreach ($properties as $key => $value) {

            if (defined("{$entityName}::_id") && key_exists($key, $entityName::_id)) {
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
            new ApiCustomException('No parameters to continue with the change = Update');
        }

        $query = "UPDATE " . $entity::_table . " SET {$fields} {$this->drive->GetFormattedConditionStart()} {$query}";

        if ($this->debug) {
            debugSql($query);
        }

        $result = null;

        $execute = $this->drive->Execute($query) or die($this->drive->DBReport($query));

        if ($execute) {
            if (defined("{$entityName}::_id")) {

                $DB = new Database();
                $DB->From($e = new $entityName);

                foreach ($entityName::_id as $key => $autoIncrement) {
                    $condition = '=';

                    if ($properties[$key] === null) {
                        $condition = 'IS NULL';
                    }

                    if (key_exists($key, $properties)) {
                        $DB->Where($key, $condition, $properties[$key], null, '$e');
                    }
                }

                $result = $DB->First();

            } else {
                $result = $entity;
            }

            $this->Log($result);
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
            new ApiCustomException('No parameters to continue with the change = Update');
        }

        $items = null;

        if ($this->log) {
            $queryList = "SELECT * FROM " . $entityName::_table . " {$this->drive->GetFormattedConditionStart()} {$query}";
            $items = $this->drive->Execute($queryList) or die($this->drive->DBReport($queryList));
        }

        $query = "DELETE FROM " . $entityName::_table . " {$this->drive->GetFormattedConditionStart()} {$query}";

        if ($this->debug) {
            debugSql($query);
        }

        $execute = $this->drive->Execute($query) or die($this->drive->DBReport($query));

        if ($items !== null && $execute && $this->drive->GetNumRows($items)) {
            while ($res = $this->drive->FetchArray($items)) {
                $entity->ImportData($res);
                $this->Log($entity);
            }
        }

        $this->Reset();

        return $execute ? true : false;
    }

    private function Log(IEntity $entity)
    {
        if ($this->log) {
            $entityName = get_class($entity);
            $fullClass = str_replace('\\', '_', $entityName);

            $config = $this->log->GetTypeLog($fullClass);

            $typeOperation = DBOperationTypeEnum::Insert;
            $func = debug_backtrace()[1]['function'];

            if ($func === 'AuxUpdate') {
                $typeOperation = DBOperationTypeEnum::Update;
            } else if ($func === 'AuxDelete') {
                $typeOperation = DBOperationTypeEnum::Delete;
            }

            if ($config instanceof LogEntityConfig
                && isset($config->type)
                && isset($config->filterAuthor)
                && key_exists($typeOperation, $config->filterAuthor)
            ) {
                $type = $config->type;

                $reference = [];

                if (defined("{$entityName}::_id")) {
                    foreach ($entityName::_id as $key => $autoIncrement) {
                        $reference[] = $entity->$key;
                    }
                }

                $references = count($reference);

                if ($references == 1) {
                    $reference = $reference[0];
                } else if ($references > 1) {
                    $reference = implode(',', $reference);
                } else {
                    $reference = null;
                }

                $table = $this->log->GetTableName();

                $author = null;

                if ($config->filterAuthor[$typeOperation]) {
                    $author = $this->log->GetAuthor($fullClass);
                }

                $data = json_encode($entity);

                $query = $this->drive->GetFormattedLogQuery(
                    $table,
                    $typeOperation,
                    $author,
                    $type,
                    $reference,
                    $data);

                if ($this->debug) {
                    debugSql($query);
                }

                $this->drive->Execute($query) or die($this->drive->DBReport($query));
            }
        }
    }
}
