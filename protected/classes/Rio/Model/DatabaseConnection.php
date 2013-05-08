<?php
namespace Rio\Model;

class DatabaseConnection
{
	private static $defaultSettings = array(
		'dsn' => 'sqlite:memory',
		'username' => '',
		'password' => '',
		'driverOptions' => array(),
		'logging' => false,
		'caching' => false,
		'quoteIdentifierCharacter' => null
	);
	
	private $settings;
	private $lastStatement;
	private $pdo;
	private $queryLog = array();
	private $queryCache = array();
	
	public function __construct(array $settings = array())
	{
		$settings = array_merge(self::$defaultSettings, $settings);
		
		$this->settings = $settings;
		$this->pdo = null;
	}
	
	public function table($tableName)
	{
		$query = new QueryWrapper($this);
		$query->table($tableName);
		return $query;
	}
	
	public function entity($entity)
	{
		$query = new EntityQueryWrapper($this);
		$query->entityClass(Entity::byName($entity));
		return $query;
	}
	
	public function pdo()
	{
		if ($this->pdo === null) {
			$this->pdo = new \PDO($this->settings['dsn'], $this->settings['username'],
			                      $this->settings['password'],
			                      $this->settings['driverOptions']);
			$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
			$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		}
		
		return $this->pdo;
	}
	
	public function driverName()
	{
		return $this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
	}
	
	public function quoteIdentifier($identifier)
	{
		if (!$this->settings['quoteIdentifierCharacter']) {
			switch ($this->pdo()->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
				case 'pgsql':
				case 'sqlsrv':
				case 'dblib':
				case 'mssql':
				case 'sybase':
				case 'firebird':
					$this->settings['quoteIdentifierCharacter'] = '"';
					break;
					
				case 'mysql':
				case 'sqlite':
				case 'sqlite2':
				default:
					$this->settings['quoteIdentifierCharacter'] = '`';
			}
		}
		
		$quoteCharacter = $this->settings['quoteIdentifierCharacter'];
	
		$parts = explode('.', $identifier);
		$parts = array_map(function($part) use($quoteCharacter) {
			if ($part === '*')
				return $part;
				
			return $quoteCharacter . str_replace($quoteCharacter, str_repeat($quoteCharacter, 2), $part) . $quoteCharacter;
		}, $parts);
		return join('.', $parts);
	}
	
	public function execute($query, $parameters = array())
	{
		$this->logQuery($query, $parameters);
		$statement = $this->pdo()->prepare($query);
		
		$this->lastStatement = $statement;
		return $statement->execute($parameters);
	}
	
	public function lastStatement()
	{
		return $this->lastStatement;
	}
	
	public function lastQuery()
	{
		if (empty($this->queryLog))
			return '';
		
		return end($this->queryLog);
	}
	
	private function logQuery($query, $parameters)
	{
		if (!$this->settings['logging'])
			return false;
		
		if (count($parameters) > 0) {
			$parameters = array_map(array($this->pdo(), 'quote'), $parameters);
		
			$query = str_replace("%", "%%", $query);
		
			if (false !== strpos($query, "'") || false !== strpos($query, '"'))
				$query = $this->replaceOutsideQuotes("?", "%s", $query);
			else
				$query = str_replace("?", "%s", $query);
		
			$boundQuery = vsprintf($query, $parameters);
		}
		else
			$boundQuery = $query;
		
		$this->lastQuery = $boundQuery;
		$this->queryLog[] = $boundQuery;
		return true;
	}
	
	private function replaceOutsideQuotes($string, $search, $replace) {
		$valid = '/
                # Validate string having embedded quoted substrings.
                ^                           # Anchor to start of string.
                (?:                         # Zero or more string chunks.
                  "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
                | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk,
                | [^\'"\\\\]+               # or an unquoted chunk (no escapes).
                )*                          # Zero or more string chunks.
                \z                          # Anchor to end of string.
                /sx';
		if (!preg_match($valid, $string))
			throw new \LogicException("Subject string is not valid in the replaceOutsideQuotes context.");
		
		$parse = '/
                # Match one chunk of a valid string having embedded quoted substrings.
                  (                         # Either $1: Quoted chunk.
                    "[^"\\\\]*(?:\\\\.[^"\\\\]*)*"  # Either a double quoted chunk,
                  | \'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'  # or a single quoted chunk.
                  )                         # End $1: Quoted chunk.
                | ([^\'"\\\\]+)             # or $2: an unquoted chunk (no escapes).
                /sx';
		return preg_replace_callback($parse, function($matches) use($search, $replace) {
			if ($matches[1])
				return $matches[1];
			
			return preg_replace('/'. preg_quote($search, '/') .'/', $replace, $matches[2]);
		}, $string);
	}
	
	public function queryLog()
	{
		return $this->queryLog;
	}
	
	public function isLoggingEnabled()
	{
		return !!$this->settings['logging'];
	}
	
	public function clearCache()
	{
		$this->queryCache = array();
	}
	
	public function cacheQueryResult($cacheKey, $value)
	{
		$this->queryCache[$cacheKey] = $value;
	}
	
	public function isCachingEnabled()
	{
		return !!$this->settings['caching'];
	}
	
	public function createCacheKey($query, $parameters)
	{
		return sha1($query . ':' . join(',', $parameters));
	}
	
	public function checkQueryCache($cacheKey)
	{
		if (isset($this->queryCache[$cacheKey]))
			return $this->queryCache[$cacheKey];
	
		return false;
	}
}