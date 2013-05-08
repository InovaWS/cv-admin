<?php
namespace Rio\Model;

class ResultSetRow implements \ArrayAccess
{
	private $databaseConnection;
	
	private $isNew = false;
	private $tableName;
	private $pkFields;
	private $data = array();
	private $dirtyFields = array();
	private $expressionFields = array();
	
	public function __construct(DatabaseConnection $databaseConnection, $tableName, $pkFields = array('id'), $isNew = false)
	{
		$this->databaseConnection = $databaseConnection;
		$this->tableName = $tableName;
		$this->pkFields = $pkFields;
		$this->isNew = $isNew;
	}
	
	public function getDatabaseConnection()
	{
		return $this->databaseConnection;
	}
	
	public function offsetExists($key) { return isset($this->data[$key]); }
	public function offsetGet($key) { return $this->get($key); }
	public function offsetSet($key, $value) { if (!is_null($key)) $this->set($key, $value); }
	public function offsetUnset($key) { unset($this->data[$key]); unset($this->dirtyFields[$key]); }
	public function __get($key) { return $this->offsetGet($key); }
	public function __set($key, $value) { $this->offsetSet($key, $value); }
	public function __unset($key) { $this->offsetUnset($key); }
	public function __isset($key) { return $this->offsetExists($key); }
	
	public function setTableName($tableName)
	{
		$this->tableName = $tableName;
		return $this;
	}
	
	public function getTableName()
	{
		return $this->tableName;
	}
	
	public function setPrimaryKeys()
	{
		$this->pkFields = array_map('strval', func_get_args());
		return $this;
	}
	
	public function hydrate($data = array())
	{
		$this->data = $data;
		return $this;
	}
	
	public function forceAllDirty()
	{
		$this->dirtyFields = $this->data;
		return $this;
	}
	
	public function isDirty($key)
	{
		return isset($this->dirtyFields[$key]);
	}
	
	public function isNew()
	{
		return $this->isNew;
	}
	
	public function copy()
	{
		$instance = new ResultSetRow($this->getDatabaseConnection(), $this->tableName, $this->pkFields, $this->data);
		foreach ($this->pkFields as $pkField)
			unset($instance->dirtyFields[$pkField]);
		$instance->isNew = true;
		return $instance;
	}
	
	public function asArray()
	{
		if (func_num_args() === 0)
			return $this->data;
			
		$args = func_get_args();
		return array_intersect_key($this->data, array_flip($args));
	}
	
	public function asObject()
	{
		return (object)call_user_func_array(array($this, 'asArray'), func_get_args());
	}
	
	public function get($key)
	{
		return isset($this->data[$key]) ? $this->data[$key] : null;
	}
	
	public function primaryKeys()
	{
		$return = array();
		foreach ($this->pkFields as $pkField)
			$return[$pkField] = $this->get($pkField);
		
		return $return;
	}
	
	public function id()
	{
		$primaryKeys = $this->primaryKeys();
		if (count($primaryKeys) == 1)
			return current($primaryKeys);
		else
			return $primaryKeys;
	}
	
	public function set($key, $value = null)
	{
		$this->setProperty($key, $value);
	}
	
	public function setExpression($key, $value = null)
	{
		$this->setProperty($key, $value, true);
	}
	
	private function setProperty($key, $value = null, $isExpression = false)
	{
		if (!is_array($key))
			$key = array($key => $value);
	
		foreach ($key as $field => $value) {
			$this->data[$field] = $value;
			$this->dirtyFields[$field] = $value;
			if (!$isExpression && isset($this->expressionFields[$field]))
				unset($this->expressionFields[$field]);
			else if ($isExpression)
				$this->expressionFields[$field] = true;
		}
	}
	
	public function save()
	{
		$query = array();
		
		$parameters = array_diff_key($this->dirtyFields, $this->expressionFields);
	
		if (!$this->isNew) { // UPDATE
			// If there are no dirty values, do nothing
			if (empty($parameters) && empty($this->expressionFields))
				return true;
			
			$query = $this->buildUpdate();
			foreach ($this->primaryKeys() as $value)
				$parameters[] = $value;
		}
		else // INSERT
			$query = $this->buildInsert();
	
		$parameters = array_values($parameters);
		$success = $this->getDatabaseConnection()->execute($query, $parameters);
	
		// If we've just inserted a new record, set the ID of this object
		if ($this->isNew) {
			$this->isNew = false;
			if (is_null($this->id())) {
				if ($this->getDatabaseConnection()->driverName() == 'pgsql')
					$this->data[$this->pkFields[0]] = $this->getDatabaseConnection()->lastStatement()
					->fetchColumn();
				else
					$this->data[$this->pkFields[0]] = $this->getDatabaseConnection()->pdo()->lastInsertId();
			}
		}
	
		$this->dirtyFields = array();
		return $success;
	}
	
	protected function buildUpdate()
	{
		$query = array();
		$query[] = "UPDATE {$this->getDatabaseConnection()->quoteIdentifier($this->tableName)} SET";
	
		$fieldList = array();
		foreach ($this->dirtyFields as $key => $value) {
			if (!array_key_exists($key, $this->expressionFields))
				$value = '?';
			$fieldList[] = "{$this->getDatabaseConnection()->quoteIdentifier($key)} = $value";
		}
		$query[] = join(", ", $fieldList);
		$query[] = "WHERE";
	
		$pks = array();
		foreach ($this->pkFields as $pkField)
			$pks[] = $this->getDatabaseConnection()->quoteIdentifier($pkField) . ' = ?';
	
		$query[] = join(' AND ', $pks);
		return join(" ", $query);
	}
	
	protected function buildInsert()
	{
		$query[] = "INSERT INTO";
		$query[] = $this->getDatabaseConnection()->quoteIdentifier($this->tableName);
		$fieldList = array_map(array($this->getDatabaseConnection(), 'quoteIdentifier'),
			array_keys($this->dirtyFields));
		$query[] = "(" . join(", ", $fieldList) . ")";
		$query[] = "VALUES";
	
		$placeholders = $this->createPlaceholders($this->dirtyFields);
		$query[] = "({$placeholders})";
	
		if ($this->getDatabaseConnection()->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'pgsql') {
			$query[] = 'RETURNING '
				. $this->getDatabaseConnection()->quoteIdentifier($this->pkFields[0]);
		}
	
		return join(" ", $query);
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
	
	public function delete()
	{
		$query = join(" ", array("DELETE FROM",
			$this->getDatabaseConnection()->quoteIdentifier($this->tableName), "WHERE",
			join(' AND ', array_map(function($pkField) use(&$that) {
				return $this->getDatabaseConnection()->quoteIdentifier($pkField) . ' = ?';
			}, $this->pkFields))));
		return $this->getDatabaseConnection()->execute($query, array_values($this->primaryKeys()));
	}
}