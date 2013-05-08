<?php
namespace Rio\Model;

class Entity
{
	
	private static $entitiesClasses = array();
	
	public static function register($entityClass, $entityAlias = null)
	{
		if ($entityAlias === null)
			$entityAlias = $entityClass;
		self::$entitiesClasses[$entityAlias] = $entityClass;
	}
	
	public static function byName($entityName)
	{
		return isset(self::$entitiesClasses[$entityName]) ? self::$entitiesClasses[$entityName] : $entityName;
	}
	
	private static function getStaticProperty($className, $property, $default = null)
	{
		if (is_object($className))
			$className = get_class($className);
		
		if (!property_exists($className, $property))
			return $default;
	
		$properties = get_class_vars($className);
		return $properties[$property];
	}
	
	private static function getTableName($className)
	{
		$tableName = self::getStaticProperty($className, '_table');
		if ($tableName === null)
			return strtolower(preg_replace(array('/\\\\/', '/(?<=[a-z])([A-Z])/', '/__/'),
				array('_', '_$1', '_'), ltrim($className, '\\')));
	
		return $tableName;
	}
	
	private static function getPrimaryKey($className)
	{
		return self::getStaticProperty($className, '_primaryKey', 'id');
	}
	
	private $row;
	
	public function setEntityRow(ResultSetRow $row)
	{
		$this->row = $row;
	}
	
	public function getDatabaseConnection()
	{
		return $this->row->getDatabaseConnection();
	}
	
	public function __get($property) {
		$ret = $this->row->get($property);
		if ( $ret === null && method_exists($this, $property) )
			return call_user_func($callback);
		
		return $ret;
	}
	public function __set($property, $value) { $this->row->set($property, $value); }
	public function __isset($property) { return $this->row->__isset($property); }
	public function get($property) { return $this->row->get($property); }
	public function set($property, $value = null) { $this->row->set($property, $value); }
	public function setExpression($property, $value = null) { $this->row->setExpression($property, $value); }
	public function isDirty($property) { return $this->row->is_dirty($property); }
	public function isNew() { return $this->row->isNew(); }
	public function asArray() { $args = func_get_args(); return call_user_func_array(array($this->row, 'asArray'), $args); }
	public function asObject() { $args = func_get_args(); return call_user_func_array(array($this->row, 'asObject'), $args); }
	public function save() { return $this->row->save(); }
	public function delete() { return $this->row->delete(); }
	public function id() { return $this->row->id(); }
	public function hydrate($data) { $this->row->hydrate($data)->forceAllDirty(); }
	
	protected function hasOneOrMany($associatedEntityName, $foreignKeyName = null)
	{
		$associatedEntityClass = Entity::byName($associatedEntityName);
		$baseTableName = self::getTableName($this);
		
		if ($foreignKeyName === null)
			$foreignKeyName = $baseTableName . '_id';
		
		$query = new EntityQueryWrapper($this->row->getDatabaseConnection());
		return $query->entityClass(Entity::byName($associatedEntityClass))->where($foreignKeyName, $this->id());
	}
	
	protected function hasOne($associatedEntityName, $foreignKeyName = null)
	{
		return $this->hasOneOrMany($associatedEntityName, $foreignKeyName);
	}
	
	protected function hasMany($associatedEntityName, $foreignKeyName = null)
	{
		return $this->hasOneOrMany($associatedEntityName, $foreignKeyName);
	}
	
	protected function belongsTo($associatedEntityName, $foreignKeyName = null)
	{
		$associatedEntityClass = Entity::byName($associatedEntityName);
		$baseTableName = self::getTableName($this);
		
		if ($foreignKeyName === null)
			$foreignKeyName = $baseTableName . '_id';
		
		$query = new EntityQueryWrapper($this->row->getDatabaseConnection());
		return $query->entityClass(Entity::byName($associatedEntityClass))->wherePrimaryKeyIs($this->$foreignKeyName);
	}
	
	protected function hasManyThrough($associatedEntityName, $joinEntityName = null, $keyToBaseTable = null,
	                                  $keyToAssociatedTable = null)
	{
		$baseClassName = get_class($this);
		$associatedClassName = Entity::byName($associatedEntityName);
	
		if ($joinEntityName === null) {
			$classNames = array($baseClassName, Entity::byName($associatedEntityName));
			sort($classNames, SORT_STRING);
			$joinClassName = join('', $classNames);
		}
	
		$baseTableName = self::getTableName($baseClassName);
		$associatedTableName = self::getTableName($associatedClassName);
		$joinTableName = self::getTableName($joinClassName);
		
		$baseTablePrimaryKey = self::getPrimaryKey($baseClassName);
		$associatedTablePrimaryKey = self::getPrimaryKey($associatedClassName);
		
		if ($keyToBaseTable === null)
			$keyToBaseTable = $baseTableName . '_id';
		if ($keyToAssociatedTable === null)
			$keyToAssociatedTable = $associatedTableName . '_id';
		
		$query = new EntityQueryWrapper($this->row->getDatabaseConnection());
		$query->table($associatedTableName)->primaryKeys($associatedTablePrimaryKey)
		->select("{$associatedTableName}.*")->join($joinTableName, "{$associatedTableName}.{$associatedTablePrimaryKey}",
		'=', "{$joinTableName}.{$keyToAssociatedTable}")->where("{$joinTableName}.{$keyToAssociatedTable}", $this->id());
	}
}