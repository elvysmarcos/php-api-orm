<?php


namespace APIORM;

use mysqli;

class MysqlDatabase implements IDatabaseDrive
{
    public $debug = false;

    public function __construct()
    {
        $_ENV['DB_DEBUG'] ? $this->debug = true : null;
    }

    public function GetFormattedSelectColumns(string $entity, string $column)
    {
        return " `{$entity}`.`{$column}` AS '{$entity}.{$column}'";
    }

    public function GetFormattedSelectQuery(string $table, string $entity, string $columns)
    {
        return "SELECT {$columns} FROM {$table} `{$entity}` ";
    }

    public function GetFormattedInsertColumns(string $column)
    {
        return "`{$column}`";
    }

    public function GetFormattedUpdateColumns(string $column, $value)
    {
        $fields = null;

        if (gettype($value) === 'integer' || gettype($value) === 'double') {
            $fields = "`{$column}` = {$value}";
        } else if (gettype($value) === 'boolean') {
            $fields = "`{$column}` = " . +$value;
        } else if ($value === null) {
            $fields = "`{$column}` = NULL";
        } else {
            $fields = "`{$column}` = '{$value}'";
        }

        return $fields;
    }

    public function GetFormattedJoinQuery(?string $type, string $table, string $entity, string $conditions)
    {
        return " {$type}JOIN {$table} `{$entity}` ON {$conditions}";
    }

    public function GetFormattedFullLimit(int $current, int $limit)
    {
        return " LIMIT " . ($current * $limit) . ",{$limit}";
    }

    public function GetFormattedBasicLimit(int $limit)
    {
        return " LIMIT {$limit}";
    }

    public function GetFormattedLimit(string $query, ?string $queryLimit)
    {
        return $query .= $queryLimit;
    }

    public function GetFormattedEqualityComparison(string $ofEntity, string $ofColumn, string $toEntity, string $toColumn)
    {
        return " `{$ofEntity}`.`{$ofColumn}` = `{$toEntity}`.`{$toColumn}`";
    }

    public function GetFormattedConditionStart()
    {
        return ' WHERE 1';
    }

    public function GetFormattedConditions(?string $operator, ?string $entity, string $column, string $condition, ?string $compare)
    {
        $query = null;
        $entity = $entity ? " `{$entity}`." : null;

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
                $query = " {$operator} {$entity}`{$column}` {$condition} '{$compare}'";
                break;
            case 'LIKE':
                $query = " {$operator} {$entity}`{$column}` LIKE '{$compare}'";
                break;
            case 'IN':
            case 'NOT IN':
                $query = " {$operator} {$entity}`{$column}` {$condition} {$compare}";
                break;
            case 'IS NULL':
            case 'IS NOT NULL':
                $query = " {$operator} {$entity}`{$column}` {$condition}";
                break;
        }

        return $query;
    }

    public function GetFormattedConditionsPrimaryKeys(string $column, $value)
    {
        return " AND `{$column}` {$value}";
    }

    public function GetFormattedOrders(string $entity, string $column, string $direction)
    {
        return " `{$entity}`.`{$column}` " . strtoupper($direction);
    }

    public function Execute($query)
    {
        return mysqli_query($this->DBLink(), $query);
    }

    public function GetNumRows($result)
    {
        return mysqli_num_rows($result);
    }

    public function GetRecentId()
    {
        return mysqli_insert_id($this->DBLink());
    }

    public function FetchArray($result)
    {
        return mysqli_fetch_assoc($result);
    }

    public function CustomQuery($query)
    {
        new ApiCustomException('Method is not valid');
        return null;
    }

    public function DBLink()
    {
        if (!isset($_SESSION['link'])) {
            $_SESSION['link'] = new mysqli($_ENV['DB_HOST'], $_ENV['DB_USER'], $_ENV['DB_PASS'], $_ENV['DB_DATABASE']);
            $_SESSION['link']->set_charset($_ENV['DB_CHARSET']);
        }

        return $_SESSION['link'];
    }

    public function DBClose(bool $rollback = false)
    {
        if (isset($_SESSION['link'])) {
            if ($rollback) {
                \mysqli_rollback($_SESSION['link']);
            }
            \mysqli_close($_SESSION['link']);
            unset($_SESSION['link']);
        }
    }

    public function DBReport($query = null, $line = 3)
    {
        debugSql(str_replace('', '\"', "\rError: " . mysqli_error($this->DBLink()) . "\rQuery: {$query}"), null, $line);
        $error = mysqli_error($this->DBLink());

        !$error ? $error = 'Unrecognized error' : null;

        new ApiCustomException($error);
    }
}
