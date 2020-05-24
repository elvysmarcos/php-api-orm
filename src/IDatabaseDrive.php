<?php

namespace APIORM;

interface IDatabaseDrive
{
    public function __construct();

    public function GetFormattedSelectColumns(string $entity, string $column);

    public function GetFormattedSelectQuery(string $table, string $entity, string $columns);

    public function GetFormattedInsertColumns(string $column);

    public function GetFormattedUpdateColumns(string $column, string $value);

    public function GetFormattedJoinQuery(?string $type, string $table, string $entity, string $conditions);

    public function GetFormattedFullLimit(int $current, int $limit);

    public function GetFormattedBasicLimit(int $limit);

    public function GetFormattedLimit(string $query, ?string $queryLimit);

    public function GetFormattedEqualityComparison(string $ofEntity, string $ofColumn, string $toEntity, string $toColumn);

    public function GetFormattedConditionStart();

    public function GetFormattedConditions(?string $operator, ?string $entity, string $column, string $condition, ?string $compare);

    public function GetFormattedConditionsPrimaryKeys(string $column, $value);

    public function GetFormattedOrders(string $entity, string $column, string $direction);

    public function Execute($query);

    public function GetNumRows($result);

    public function GetRecentId();

    public function FetchArray($result);

    public function CustomQuery($query);

    public function DBLink();

    public function DBReport($query = null, $line = 3);
}