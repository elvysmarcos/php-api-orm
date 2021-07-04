<?php


namespace APIORM;

class SqlDataBase implements IDatabaseDrive
{
    public $debug = false;

    public function __construct()
    {
        (isset($_ENV['DB_DEBUG']) && $_ENV['DB_DEBUG']) ? $this->debug = true : null;
    }

    public function GetFormattedSelectColumns(string $entity, string $column)
    {
        return "[{$entity}].[{$column}] AS '{$entity}.{$column}'";
    }

    public function GetFormattedSelectQuery(string $table, string $entity, string $columns)
    {
        return "SELECT TOP(?) {$columns} FROM {$table} [{$entity}] ";
    }

    public function GetFormattedInsertColumns(string $column)
    {
        return "[{$column}]";
    }

    public function GetFormattedUpdateColumns(string $column, $value)
    {
        $fields = null;

        $valueType = gettype($value);

        if ($valueType === 'integer' || $valueType === 'double') {
            $fields = "[{$column}] = {$value}";
        } else if ($valueType === 'boolean') {
            $fields = "[{$column}] = " . +$value;
        } else if ($value === null) {
            $fields = "[{$column}] = NULL";
        } else {
            $fields = "[{$column}] = '" . addslashes($value) . "'";
        }

        return $fields;
    }

    public function GetFormattedJoinQuery(?string $type, string $table, string $entity, string $conditions)
    {
        return "{$type}JOIN {$table} [{$entity}] ON {$conditions}";
    }

    public function GetFormattedFullLimit(int $current, int $limit)
    {
        return " OFFSET " . ($current * $limit) . "  ROWS FETCH NEXT {$limit} ROWS ONLY ";
    }

    public function GetFormattedBasicLimit(int $limit)
    {
        return "TOP({$limit})";
    }

    public function GetFormattedLimit(string $query, ?string $queryLimit)
    {
        return str_replace('TOP(?)', $queryLimit, $query);
    }

    public function GetFormattedEqualityComparison(string $ofEntity, string $ofColumn, string $toEntity, string $toColumn)
    {
        return "[{$ofEntity}].[{$ofColumn}] = [{$toEntity}].[{$toColumn}]";
    }

    public function GetFormattedConditionStart()
    {
        return ' WHERE 1=1';
    }

    public function GetFormattedConditions(?string $operator, ?string $entity, string $column, string $condition, ?string $compare)
    {
        $query = null;

        $operator = $operator ? " {$operator} " : null;

        $entity = $entity ? " [{$entity}]." : null;

        $condition = strtoupper(trim($condition));

        if ($compare === null && $condition === '=') {
            $condition = 'IS NULL';
        }

        switch ($condition) {
            case '=':
            case '!=':
            case '>':
            case '<':
            case '>=':
            case '<=':
                $query = "{$operator} {$entity}[{$column}] {$condition} '{$compare}'";
                break;
            case 'LIKE':
                $query = "{$operator} {$entity}[{$column}] LIKE '{$compare}'";
                break;
            case 'IN':
            case 'NOT IN':
                $query = "{$operator} {$entity}[{$column}] {$condition} {$compare}";
                break;
            case 'IS NULL':
            case 'IS NOT NULL':
                $query = "{$operator} {$entity}[{$column}] {$condition}";
                break;
        }

        return $query;
    }

    public function GetFormattedConditionsPrimaryKeys(string $column, $value)
    {
        return " AND [{$column}] {$value}";
    }

    public function GetFormattedOrders(string $entity, string $column, string $direction)
    {
        return " [{$entity}].[{$column}] " . strtoupper($direction);
    }

    public function GetFormattedLogQuery(string $table, int $typeOperation, ?int $author, int $type, ?string $reference, string $data)
    {
        $reference = $reference ? "'{$reference}'" : 'NULL';
        $author = $author ? "{$author}" : 'NULL';

        return "INSERT INTO `{$table}` (`date`,`typeOperation`,`author`,`type`,`reference`,`data`) VALUES (GETDATE(),{$typeOperation},{$author},{$type},{$reference},'{$data}')";
    }

    public function Execute($query)
    {
        return sqlsrv_query($this->DBLink(), $query);
    }

    public function GetNumRows($result)
    {
        return !sqlsrv_has_rows($result) ? sqlsrv_num_rows($result) : 0;
    }

    public function GetRecentId()
    {
        $lastQuery = sqlsrv_query($this->DBLink(), 'SELECT SCOPE_IDENTITY() as Id');
        $recent = sqlsrv_fetch_array($lastQuery, SQLSRV_FETCH_ASSOC);
        return ($recent && isset($recent['Id'])) ? $recent['Id'] : null;
    }

    public function FetchArray($result)
    {
        return sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC);
    }

    public function CustomQuery($query)
    {
        $execute = $this->Execute($query) or die($this->DBReport($query));

        $rowsAffected = sqlsrv_rows_affected($execute);

        $result = null;

        if ($rowsAffected) {

            while ($res = sqlsrv_fetch_array($execute, SQLSRV_FETCH_ASSOC)) {
                $result[] = (object)$res;
            }

            if ($result == null) {
                $next_result = sqlsrv_next_result($execute);
                if ($next_result) {
                    while ($res = sqlsrv_fetch_array($execute, SQLSRV_FETCH_ASSOC)) {
                        $result[] = (object)$res;
                    }
                }
            }
        }

        return $result;
    }

    public function DBLink()
    {
        if (!isset($_SESSION['link'])) {
            $_SESSION['link'] = sqlsrv_connect($_ENV['DB_HOST'], array("Database" => $_ENV['DB_DATABASE'], "UID" => $_ENV['DB_USER'], "PWD" => base64_decode($_ENV['DB_PASS']), "CharacterSet" => $_ENV['DB_CHARSET']));

            if ($_SESSION['link'] === false) {
                unset($_SESSION['link']);

                $error = sqlsrv_errors();

                debugSql($error[0]['message']);

                throw new ApiCustomException('Something didn\'t happen as expected');
            }
        }

        return $_SESSION['link'];
    }

    public function DBClose(bool $rollback = false)
    {
        if (isset($_SESSION['link'])) {
            if ($rollback) {
                \sqlsrv_rollback($_SESSION['link']);
            }
            \sqlsrv_close($_SESSION['link']);
            unset($_SESSION['link']);
        }
    }

    public function DBReport($query = null, $line = 3)
    {
        $error = sqlsrv_errors();

        debugSql(str_replace('', '\"', "\rError: " . $error[0]['message'] . "\rQuery: {
                        $query}"), null, $line);

        throw new ApiCustomException('Something didn\'t happen as expected');
    }
}
