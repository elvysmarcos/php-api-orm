<?php


namespace APIORM;

use APIORM\Enums\TypeResponseEnum;
use mysqli;

class MysqlDatabase implements IDatabaseDrive
{
    public $debug = false;

    public function __construct()
    {
        DB_DEBUG ? $this->debug = true : null;
    }

    public function GetFormattedSelectColumns(string $entity, string $column)
    {
        return " `{$entity}`.`{$column}` as '{$entity}.{$column}'";
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

        switch (strtoupper($condition)) {
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
        Response::show(TypeResponseEnum::SQL, 'Método não é válid');
        return null;
    }

    public function DBLink()
    {
        if (!isset($_SESSION['link'])) {
            $_SESSION['link'] = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DATABASE);
            $_SESSION['link']->set_charset(DB_CHARSET);
        }

        return $_SESSION['link'];
    }

    public function DBReport($query = null, $line = 3)
    {
        debugSql(str_replace('', '\"', "\rError: " . mysqli_error($this->DBLink()) . "\rQuery: {$query}"), null, $line);
        $error = mysqli_error($this->DBLink());

        !$error ? $error = 'Erro não reonhecido' : null;

        mysqli_rollback($this->DBLink());

        Response::show(TypeResponseEnum::SQL, $error);
        return null;
    }
}
