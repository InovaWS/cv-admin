<?php
namespace Rio\Model;

class EntityQueryWrapper extends QueryWrapper
{
	
	private static function getStaticProperty($className, $property, $default = null)
	{
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
	
	private $entityClass;
	
	public function entityClass($entityClass)
	{
		if (!class_exists($entityClass))
			throw new \InvalidArgumentException("class $entityClass don't exists");
			
		$this->entityClass = $entityClass;
		$this->table(self::getTableName($entityClass))->primaryKeys(self::getPrimaryKey($entityClass));
		
		return $this;
	}
	
	public function filter()
	{
		$args = func_get_args();
		$filterFunction = array_shift($args);
		array_unshift($args, $this);
		if (method_exists($this->entityClass, $filterFunction))
			return call_user_func_array(array($this->entityClass, $filterFunction), $args);
	}
	
	protected function createInstanceFromRow($row, $isNew = false)
	{
		$resultSetRow = new ResultSetRow($this->getDatabaseConnection(), $this->tableName, $this->pkFields, $isNew);
		$resultSetRow->hydrate($row);
		
		$instance = new $this->entityClass();
		$instance->setEntityRow($resultSetRow);
		return $instance;
	}
	
}