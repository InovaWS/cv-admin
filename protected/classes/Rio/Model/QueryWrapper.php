<?php
namespace Rio\Model;

class QueryWrapper
{
	
	const CONDITION_FRAGMENT = 0;
	const CONDITION_VALUES = 1;
	
	private $databaseConnection;
	
	protected $tableName;
	protected $tableAlias = null;
	
	protected $parameters = array();
	
	protected $resultColumns = array('*');
	protected $usingDefaultResultColumns = true;
	
	protected $joinSources = array();
	
	protected $distinct = false;
	
	protected $rawQuery = null;
	protected $rawParameters = array();
	
	protected $whereConditions = array();
	
	protected $limit = null;
	
	protected $offset = null;
	
	protected $orderBy = array();
	protected $groupBy = array();
	
	protected $havingConditions = array();
	
	protected $pkFields = array('id');
	protected $expressionFields = array();
	
	protected $ignoreCache = false;
	
	public function __construct(DatabaseConnection $databaseConnection)
	{
		$this->databaseConnection = $databaseConnection;
	}
	
	public function getDatabaseConnection()
	{
		return $this->databaseConnection;
	}
	
	public function table($tableName)
	{
		$this->tableName = $tableName;
		
		return $this;
	}
	
	public function create($data = null)
	{
		$instance = $this->createInstanceFromRow($data, true);
		$instance->forceAllDirty();
		return $instance;
	}
	
	public function primaryKeys()
	{
		$this->pkFields = array_map('strval', func_get_args());
		return $this;
	}
	
	protected function createInstanceFromRow($row, $isNew = false)
	{
		$instance = new ResultSetRow($this->getDatabaseConnection(), $this->tableName, $this->pkFields, $isNew);
		$instance->hydrate($row);
		return $instance;
	}
	
	public function findOne($primaryKey = null)
	{
		if (!is_null($primaryKey))
			$this->wherePrimaryKeyIs($primaryKey);
		$this->limit(1);
		$rows = $this->run();
		
		if (empty($rows))
			return null;
		
		return $this->createInstanceFromRow($rows[0]);
	}
	
	public function findMany()
	{
		$rows = $this->run();
		$rows = array_map(array($this, 'createInstanceFromRow'), $rows);
		return new ResultSet($rows);
	}
	
	public function findArray()
	{
		return $this->run();
	}
	
	public function count($column = '*') { return $this->aggregateDatabaseFunction(__FUNCTION__, $column); }
	public function max($column) { return $this->aggregateDatabaseFunction(__FUNCTION__, $column); }
	public function min($column) { return $this->aggregateDatabaseFunction(__FUNCTION__, $column); }
	public function avg($column) { return $this->aggregateDatabaseFunction(__FUNCTION__, $column); }
	public function sum($column) { return $this->aggregateDatabaseFunction(__FUNCTION__, $column); }
	
	protected function aggregateDatabaseFunction($sql_function, $column)
	{
		$alias = strtolower($sql_function);
		$sql_function = strtoupper($sql_function);
		
		if ('*' != $column)
			$column = $this->getDatabaseConnection()->quoteIdentifier($column);
		
		$this->selectExpression("$sql_function($column)", $alias);
		$result = $this->findOne();
	
		$returnValue = 0;
		if ($result !== null && isset($result->$alias)) {
			if ((int) $result->$alias == (float) $result->$alias)
				$returnValue = (int) $result->$alias;
			else
				$returnValue = (float) $result->$alias;
		}
		
		return $returnValue;
	}
		
	public function rawQuery($rawQuery, $rawParameters)
	{
		$this->rawQuery = $rawQuery;
		$this->rawParameters = $rawParameters;
		
		return $this;
	}
	
	public function tableAlias($tableAlias)
	{
		$this->tableAlias = $tableAlias;
		return $this;
	}
	
	protected function addResultColumn($expression, $alias = null)
	{
		if (!is_null($alias))
			$expression .= ' AS ' . $this->getDatabaseConnection()->quoteIdentifier($alias);
	
		if ($this->usingDefaultResultColumns) {
			$this->resultColumns = array($expression);
			$this->usingDefaultResultColumns = false;
		}
		else
			$this->resultColumns[] = $expression;
		
		return $this;
	}
	
	public function select($column, $alias = null)
	{
		$column = $this->getDatabaseConnection()->quoteIdentifier($column);
		return $this->addResultColumn($column, $alias);
	}
	
	public function selectExpression($expression, $alias = null)
	{
		return $this->addResultColumn($expression, $alias);
	}
	
	public function selectMany()
	{
		$columns = func_get_args();
		if (!empty($columns)) {
			$columns = $this->normaliseSelectManyColumns($columns);
			foreach ($columns as $alias => $column) {
				if (is_numeric($alias))
					$alias = null;
				$this->select($column, $alias);
			}
		}
		return $this;
	}
	
	protected function normaliseSelectManyColumns($columns)
	{
		$return = array();
		foreach ($columns as $column) {
			if (is_array($column)) {
				foreach ($column as $key => $value) {
					if (!is_numeric($key))
						$return[$key] = $value;
					else
						$return[] = $value;
				}
			}
			else
				$return[] = $column;
		}
		return $return;
	}
	
	public function distinct()
	{
		$this->distinct = true;
		return $this;
	}
	
	protected function addJoinSource($joinOperator, $table, $constraint, $tableAlias = null)
	{
		$joinOperator = trim("{$joinOperator} JOIN");
	
		$table = $this->getDatabaseConnection()->quoteIdentifier($table);
	
		// Add table alias if present
		if (!is_null($tableAlias)) {
			$tableAlias = $this->getDatabaseConnection()->quoteIdentifier($tableAlias);
			$table .= " {$tableAlias}";
		}
	
		// Build the constraint
		if (is_array($constraint)) {
			list($firstColumn, $operator, $secondColumn) = $constraint;
			$firstColumn = $this->getDatabaseConnection()->quoteIdentifier($firstColumn);
			$secondColumn = $this->getDatabaseConnection()->quoteIdentifier($secondColumn);
			$constraint = "{$firstColumn} {$operator} {$secondColumn}";
		}
	
		$this->joinSources[] = "{$joinOperator} {$table} ON {$constraint}";
		return $this;
	}
	
	public function join($table, $constraint, $tableAlias = null) {
		return $this->addJoinSource("", $table, $constraint, $tableAlias);
	}
	public function innerJoin($table, $constraint, $tableAlias = null) {
		return $this->addJoinSource("INNER", $table, $constraint, $tableAlias);
	}
	public function leftOuterJoin($table, $constraint, $tableAlias = null) {
		return $this->addJoinSource("LEFT OUTER", $table, $constraint, $tableAlias);
	}
	public function rightOuterJoin($table, $constraint, $tableAlias = null) {
		return $this->addJoinSource("RIGHT OUTER", $table, $constraint, $tableAlias);
	}
	public function fullOuterJoin($table, $constraint, $tableAlias = null) {
		return $this->addJoinSource("FULL OUTER", $table, $constraint, $tableAlias);
	}
	
	protected function addHaving($fragment, $values = array())
	{
		return $this->addCondition('having', $fragment, $values);
	}
	
	protected function addSimpleHaving($columnName, $separator, $value)
	{
		return $this->addSimpleCondition('having', $columnName, $separator, $value);
	}
	
	protected function addWhere($fragment, $values = array())
	{
		return $this->addCondition('where', $fragment, $values);
	}
	
	protected function addSimpleWhere($columnName, $separator, $value)
	{
		return $this->addSimpleCondition('where', $columnName, $separator, $value);
	}
	
	protected function addCondition($type, $fragment, $values)
	{
		$propertyName = "{$type}Conditions";
		if (!is_array($values))
			$values = array($values);
		
		array_push($this->$propertyName, array('fragment' => $fragment, 'values' => $values));
		return $this;
	}
	
	protected function addSimpleCondition($type, $columnName, $separator, $value)
	{
		if (count($this->joinSources) > 0 && strpos($columnName, '.') === false)
			$columnName = "{$this->tableName}.{$columnName}";
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		return $this->addCondition($type, "{$columnName} {$separator} ?", $value);
	}
	
	private function createPlaceholders($fields)
	{
		if (!empty($fields)) {
			$dbFields = array();
			foreach ($fields as $key => $value) {
				if (array_key_exists($key, $this->expressionFields))
					$dbFields[] = $value;
				else
					$dbFields[] = '?';
			}
			return implode(', ', $dbFields);
		}
	}
	
	public function where($columnName, $value)
	{
		return $this->whereEqual($columnName, $value);
	}
	
	public function whereEqual($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, '=', $value);
	}
	
	public function whereNotEqual($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, '!=', $value);
	}
	
	public function wherePrimaryKeyIs($pk)
	{
		if (is_array($pk)) {
			foreach ($this->pkFields as $pkField) {
				if (isset($pk[$pkField]))
					$this->where($pkField, $pk[$pkField]);
			}
		}
		else
			$this->where($this->pkFields[0], $pk);
		
		return $this;
	}
	
	public function whereLike($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, 'LIKE', $value);
	}
	
	public function whereNotLike($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, 'NOT LIKE', $value);
	}
	
	public function whereGreaterThan($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, '>', $value);
	}
	
	public function whereLessThan($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, '<', $value);
	}
	
	public function whereGreaterThanOrEquals($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, '>=', $value);
	}
	
	public function whereLessThanOrEquals($columnName, $value)
	{
		return $this->addSimpleWhere($columnName, '<=', $value);
	}
	
	public function whereGT($columnName, $value) { return $this->whereGreaterThan($columnName, $value); }
	public function whereLT($columnName, $value) { return $this->whereLessThan($columnName, $value); }
	public function whereGTE($columnName, $value) { return $this->whereGreaterThanOrEquals($columnName, $value); }
	public function whereLTE($columnName, $value) { return $this->whereLessThanOrEquals($columnName, $value); }
	
	public function whereIn($columnName, $values)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		$placeholders = $this->createPlaceholders($values);
		return $this->addWhere("{$columnName} IN ({$placeholders})", $values);
	}
	
	public function whereNotIn($columnName, $values)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		$placeholders = $this->createPlaceholders($values);
		return $this->addWhere("{$columnName} NOT IN ({$placeholders})", $values);
	}
	
	public function whereNull($columnName)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		return $this->addWhere("{$columnName} IS NULL");
	}
	
	public function whereNotNull($columnName)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		return $this->addWhere("{$columnName} IS NOT NULL");
	}
	
	public function whereRaw($clause, $parameters = array())
	{
		return $this->addWhere($clause, $parameters);
	}
	
	public function limit($limit)
	{
		$this->limit = $limit;
		return $this;
	}
	
	public function offset($offset)
	{
		$this->offset = $offset;
		return $this;
	}
	
	protected function addOrderBy($columnName, $ordering)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		$this->orderBy[] = "{$columnName} {$ordering}";
		return $this;
	}
	
	public function orderByDesc($columnName)
	{
		return $this->addOrderBy($columnName, 'DESC');
	}
	
	public function orderByAsc($columnName)
	{
		return $this->addOrderBy($columnName, 'ASC');
	}
	
	public function orderByExpression($expression)
	{
		$this->orderBy[] = $expression;
		return $this;
	}
	
	public function groupBy($columnName)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		$this->groupBy[] = $columnName;
		return $this;
	}
	
	public function groupByExpression($expression)
	{
		$this->groupBy[] = $expression;
		return $this;
	}
	
	public function having($columnName, $value)
	{
		return $this->havingEqual($columnName, $value);
	}
	
	public function havingEqual($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, '=', $value);
	}
	
	public function havingNotEqual($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, '!=', $value);
	}
	
	public function havingPrimaryKeyIs($pk)
	{
		if (is_array($pk)) {
			foreach ($this->pkFields as $pkField) {
				if (isset($pk[$pkField]))
					$this->having($pkField, $pk[$pkField]);
			}
		}
		else
			$this->having($this->pkFields[0], $pk);
	}
	
	public function havingLike($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, 'LIKE', $value);
	}
	
	public function havingNotLike($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, 'NOT LIKE', $value);
	}
	
	public function havingGreaterThan($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, '>', $value);
	}
	
	public function havingLessThan($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, '<', $value);
	}
	
	public function havingGreaterThanOrEquals($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, '>=', $value);
	}
	
	public function havingLessThanOrEquals($columnName, $value)
	{
		return $this->addSimpleHaving($columnName, '<=', $value);
	}
	
	public function havingGT($columnName, $value) { return $this->havingGreaterThan($columnName, $value); }
	public function havingLT($columnName, $value) { return $this->havingLessThan($columnName, $value); }
	public function havingGTE($columnName, $value) { return $this->havingGreaterThanOrEquals($columnName, $value); }
	public function havingLTE($columnName, $value) { return $this->havingLessThanOrEquals($columnName, $value); }
	
	public function havingIn($columnName, $values)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		$placeholders = $this->createPlaceholders($values);
		return $this->addHaving("{$columnName} IN ({$placeholders})", $values);
	}
	
	public function havingNotIn($columnName, $values)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		$placeholders = $this->createPlaceholders($values);
		return $this->addHaving("{$columnName} NOT IN ({$placeholders})", $values);
	}
	
	public function havingNull($columnName)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		return $this->addHaving("{$columnName} IS NULL");
	}
	
	public function havingNotNull($columnName)
	{
		$columnName = $this->getDatabaseConnection()->quoteIdentifier($columnName);
		return $this->addHaving("{$columnName} IS NOT NULL");
	}
	
	public function havingRaw($clause, $parameters = array())
	{
		return $this->addHaving($clause, $parameters);
	}
	
	protected function buildSelect()
	{
		if ($this->rawQuery) {
			$this->parameters = $this->rawParameters;
			return $this->rawQuery;
		}
		
		return $this->joinIfNotEmpty(" ",
			array($this->buildSelectStart(),
				$this->buildJoin(), $this->buildWhere(),
				$this->buildGroupBy(),
				$this->buildHaving(),
				$this->buildOrderBy(),
				$this->buildLimit(), $this->buildOffset()));
	}
	
	protected function buildSelectStart()
	{
		$resultColumns = join(', ', $this->resultColumns);
	
		if ($this->distinct)
			$resultColumns = 'DISTINCT ' . $resultColumns;
	
		$fragment = "SELECT {$resultColumns} FROM "	.
		            $this->getDatabaseConnection()->quoteIdentifier($this->tableName);
	
		if (!is_null($this->tableAlias))
			$fragment .= " " . $this->getDatabaseConnection()->quoteIdentifier($this->tableAlias);
		
		return $fragment;
	}
	
	protected function buildJoin()
	{
		if (count($this->joinSources) === 0)
			return '';
	
		return join(" ", $this->joinSources);
	}
	
	protected function buildWhere()
	{
		return $this->buildConditions('where');
	}
	
	protected function buildHaving()
	{
		return $this->buildConditions('having');
	}
	
	protected function buildGroupBy()
	{
		if (count($this->groupBy) === 0)
			return '';
		
		return "GROUP BY " . join(", ", $this->groupBy);
	}
	
	protected function buildConditions($type)
	{
		$propertyName = "{$type}Conditions";
		
		if (count($this->$propertyName) === 0)
			return '';
	
		$conditions = array();
		foreach ($this->$propertyName as $condition) {
			$conditions[] = $condition['fragment'];
			$this->parameters = array_merge($this->parameters, $condition['values']);
		}
	
		return strtoupper($type) . " " . join(" AND ", $conditions);
	}
	
	protected function buildOrderBy()
	{
		if (count($this->orderBy) === 0)
			return '';
		
		return "ORDER BY " . join(", ", $this->orderBy);
	}
	
	protected function buildLimit()
	{
		if (!is_null($this->limit)) {
			$clause = 'LIMIT';
			if ($this->databaseConnection->pdo()
			    ->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'firebird')
				$clause = 'ROWS';
			return "$clause " . $this->limit;
		}
		return '';
	}
	
	protected function buildOffset()
	{
		if (!is_null($this->offset)) {
			$clause = 'OFFSET';
			if ($this->databaseConnection->pdo()
			    ->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'firebird')
			$clause = 'TO';
			return "$clause " . $this->offset;
		}
		return '';
	}
	
	protected function joinIfNotEmpty($glue, $pieces)
	{
		$filteredPieces = array();
		foreach ($pieces as $piece) {
			if (is_string($piece))
				$piece = trim($piece);
			if (!empty($piece))
				$filteredPieces[] = $piece;
		}
		return join($glue, $filteredPieces);
	}
	
	public function ignoreCache($ignore = true)
	{
		$this->ignoreCache = $ignore;
		return $this;
	}
	
	protected function run()
	{
		$query = $this->buildSelect();
		
		$isCachingEnabled = $this->databaseConnection->isCachingEnabled();
		
		if ($isCachingEnabled) {
			$cacheKey = $this->databaseConnection->createCacheKey($query, $this->parameters);
			
			if (!$this->ignoreCache) {
				$cachedResult = $this->databaseConnection->checkQueryCache($cacheKey);
			
				if ($cachedResult !== false)
					return $cachedResult;
			}
		}
		
		$this->databaseConnection->execute($query, $this->parameters);
		$statement = $this->databaseConnection->lastStatement();
		
		$rows = array();
		while ($row = $statement->fetch(\PDO::FETCH_ASSOC))
			$rows[] = $row;
		
		if ($isCachingEnabled)
			$this->databaseConnection->cacheQueryResult($cacheKey, $rows);
		
		$this->parameters = array();
		$this->resultColumns = array('*');
		$this->usingDefaultResultColumns = true;
		
		return $rows;
	}
	
	public function deleteMany()
	{
		$query = $this->joinIfNotEmpty(" ",
			array("DELETE FROM",
			$this->getDatabaseConnection()->quoteIdentifier($this->tableName),
			$this->buildWhere()));
	
		$this->databaseConnection->execute($query, $this->parameters);
	}
}