<?php
/**
 * Fuel
 *
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package		Fuel
 * @version		1.0
 * @author		Fuel Development Team
 * @license		MIT License
 * @copyright	2010 - 2011 Fuel Development Team
 * @link		http://fuelphp.com
 */

/**
 * This code is based on Redisent, a Redis interface for the modest.
 *
 * It has been modified to work with Fuel and to improve the code slightly.
 *
 * @author 		Justin Poliey <jdp34@njit.edu>
 * @copyright 	2009 Justin Poliey <jdp34@njit.edu>
 * @modified	Alex Bilbie
 * @modified	Phil Sturgeon
 * @license 	http://www.opensource.org/licenses/mit-license.php The MIT License
 */

namespace Fuel\Core;

class Mongo_DB {
	
	protected $db;
	protected $persist = false;
	
	protected $selects = array();
	public $wheres = array();	// $wheres is public for sanity reasons - useful for debuggging
	protected $sorts = array();
	protected $updates = array();
	protected $limit = 999999;
	protected $offset = 0;
	
	protected static $instances = array();

	public static function instance($name = 'default')
	{
		if (\array_key_exists($name, static::$instances))
		{
			return static::$instances[$name];
		}

		if (empty(static::$instances))
		{
			\Config::load('db', true);
		}
		if ( ! ($config = \Config::get('db.mongo.'.$name)))
		{
			throw new \Mongo_Exception('Invalid instance name given.');
		}

		static::$instances[$name] = new static($config);

		return static::$instances[$name];
	}

	protected $connection = false;

	
	/**
	*	--------------------------------------------------------------------------------
	*	CONSTRUCTOR
	*	--------------------------------------------------------------------------------
	*
	*	Automatically check if the Mongo PECL extension has been installed/enabled.
	*	Generate the connection string and establish a connection to the MongoDB.
	*/
	public function __construct(array $config = array())
	{
		if ( ! class_exists('Mongo'))
		{
			throw new \Mongo_Exception("The MongoDB PECL extension has not been installed or enabled");
		}
		
		// Build up a connect options array for mongo
		$options = array("connect" => TRUE);
		
		if ( ! empty($config['persistent']))
		{
			$options['persist'] = 'fuel_mongo_persist';
		}
				
		$connection_string = "mongodb://";
		
		if (empty($config['hostname']))
		{
			throw new \Mongo_Exception("The host must be set to connect to MongoDB");
		}
		
		if (empty($config['database']))
		{
			throw new \Mongo_Exception("The database must be set to connect to MongoDB");
		}
		
		if ( ! empty($config['username']) and ! empty($config['password']))
		{
			$connection_string .= "{$config['username']}:{$config['password']}@";
		}
		
		if (isset($config['port']) && ! empty($config['port']))
		{
			$connection_string .= "{$config['hostname']}:{$config['port']}";
		}
		else
		{
			$connection_string .= "{$config['hostname']}";
		}
		
		$connection_string .= "/{$config['database']}";
		
		// Let's give this a go
		try
		{
			$this->connection = new \Mongo(trim($connection_string), $options);
			$this->db = $this->connection->selectDB($config['database']);
		} 
		catch (MongoConnectionException $e)
		{
			throw new \Mongo_Exception("Unable to connect to MongoDB: {$e->getMessage()}");
		}
	}

	// public function __destruct()
	// {
	// 	fclose($this->connection);
	// }
	
	/**
	*	--------------------------------------------------------------------------------
	*	Drop_db
	*	--------------------------------------------------------------------------------
	*
	*	Drop a Mongo database
	*	@usage $mongodb->drop_db("foobar");
	*/
	public function switch_db($database = '')
	{
		if (empty($database))
		{
			throw new \Mongo_Exception("To switch MongoDB databases, a new database name must be specified");
		}
		
		$this->dbname = $database;
		
		try
		{
			$this->db = $this->connection->{$this->dbname};
			return true;
		}
		catch (Exception $e)
		{
			throw new \Mongo_Exception("Unable to switch Mongo Databases: {$e->getMessage()}", 500);
		}
	}
		
	/**
	*	--------------------------------------------------------------------------------
	*	Drop_db
	*	--------------------------------------------------------------------------------
	*
	*	Drop a Mongo database
	*	@usage: $mongodb->drop_db("foobar");
	*/
	public function drop_db($database = '')
	{
		if (empty($database))
		{
			throw new \Mongo_Exception('Failed to drop MongoDB database because name is empty', 500);
		}
		
		else
		{
			try
			{
				$this->connection->{$database}->drop();
				return true;
			}
			catch (Exception $e)
			{
				throw new \Mongo_Exception("Unable to drop Mongo database `{$database}`: {$e->getMessage()}", 500);
			}
			
		}
	}
		
	/**
	*	--------------------------------------------------------------------------------
	*	Drop_collection
	*	--------------------------------------------------------------------------------
	*
	*	Drop a Mongo collection
	*	@usage: $mongodb->drop_collection('foo', 'bar');
	*/
	public function drop_collection($db = "", $col = "")
	{
		if (empty($db))
		{
			throw new \Mongo_Exception('Failed to drop MongoDB collection because database name is empty', 500);
		}
	
		if (empty($col))
		{
			throw new \Mongo_Exception('Failed to drop MongoDB collection because collection name is empty', 500);
		}
		
		else
		{
			try
			{
				$this->connection->{$db}->{$col}->drop();
				return TRUE;
			}
			catch (Exception $e)
			{
				throw new \Mongo_Exception("Unable to drop Mongo collection `{$col}`: {$e->getMessage()}", 500);
			}
		}
		
		return($this);
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	SELECT FIELDS
	*	--------------------------------------------------------------------------------
	*
	*	Determine which fields to include OR which to exclude during the query process.
	*	Currently, including and excluding at the same time is not available, so the 
	*	$includes array will take precedence over the $excludes array.  If you want to 
	*	only choose fields to exclude, leave $includes an empty array().
	*
	*	@usage: $mongodb->select(array('foo', 'bar'))->get('foobar');
	*/
	
	public function select($includes = array(), $excludes = array())
	{
	 	if ( ! is_array($includes))
	 	{
	 		$includes = array();
	 	}
	 	
	 	if ( ! is_array($excludes))
	 	{
	 		$excludes = array();
	 	}
	 	
	 	if ( ! empty($includes))
	 	{
	 		foreach ($includes as $col)
	 		{
	 			$this->selects[$col] = 1;
	 		}
	 	}
	 	else
	 	{
	 		foreach ($excludes as $col)
	 		{
	 			$this->selects[$col] = 0;
	 		}
	 	}
	 	return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents based on these search parameters.  The $wheres array should 
	*	be an associative array with the field as the key and the value as the search
	*	criteria.
	*
	*	@usage : $mongodb->where(array('foo' => 'bar'))->get('foobar');
	*/
	
	public function where($wheres, $value = null)
	{
		if ( ! is_array($wheres))
		{
			$wheres = array($wheres => $value);
		}
	
		foreach ($wheres as $wh => $val)
		{
			// If the ID is not an instance of MongoId (most likely a string) it will fail to match, so convert it
			if ($wh == '_id' and ! ($val instanceof \MongoId))
			{
				$val = new \MongoId($val);
			}
			
			$this->wheres[$wh] = $val;
		}
		
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	OR_WHERE PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field may be something else
	*
	*	@usage : $mongodb->or_where(array('foo'=>'bar', 'bar'=>'foo' ))->get('foobar');
	*/
	
	public function or_where($wheres = array())
	{
		if (count($wheres) > 0)
		{
			if ( ! isset($this->wheres['$or']) || ! is_array($this->wheres['$or']))
			{
				$this->wheres['$or'] = array();
			}
			
			foreach ($wheres as $wh => $val)
			{
				$this->wheres['$or'][] = array($wh=>$val);
			}
		}
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE_IN PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is in a given $in array().
	*
	*	@usage : $mongodb->where_in('foo', array('bar', 'zoo', 'blah'))->get('foobar');
	*/
	
	public function where_in($field = "", $in = array())
	{
		$this->_where_init($field);
		$this->wheres[$field]['$in'] = $in;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE_IN_ALL PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is in all of a given $in array().
	*
	*	@usage : $mongodb->where_in('foo', array('bar', 'zoo', 'blah'))->get('foobar');
	*/
	
	public function where_in_all($field = "", $in = array())
	{
		$this->_where_init($field);
		$this->wheres[$field]['$all'] = $in;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE_NOT_IN PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is not in a given $in array().
	*
	*	@usage : $mongodb->where_not_in('foo', array('bar', 'zoo', 'blah'))->get('foobar');
	*/
	
	public function where_not_in($field = "", $in = array())
	{
		$this->_where_init($field);
		$this->wheres[$field]['$nin'] = $in;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE GREATER THAN PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is greater than $x
	*
	*	@usage : $mongodb->where_gt('foo', 20);
	*/
	
	public function where_gt($field = "", $x)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$gt'] = $x;
		return $this;
	}

	/**
	*	--------------------------------------------------------------------------------
	*	WHERE GREATER THAN OR EQUAL TO PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is greater than or equal to $x
	*
	*	@usage : $mongodb->where_gte('foo', 20);
	*/
	
	public function where_gte($field = "", $x)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$gte'] = $x;
		return($this);
	}

	/**
	*	--------------------------------------------------------------------------------
	*	WHERE LESS THAN PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is less than $x
	*
	*	@usage : $mongodb->where_lt('foo', 20);
	*/
	
	public function where_lt($field = "", $x)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$lt'] = $x;
		return($this);
	}

	/**
	*	--------------------------------------------------------------------------------
	*	WHERE LESS THAN OR EQUAL TO PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is less than or equal to $x
	*
	*	@usage : $mongodb->where_lte('foo', 20);
	*/
	
	public function where_lte($field = "", $x)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$lte'] = $x;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE BETWEEN PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is between $x and $y
	*
	*	@usage : $mongodb->where_between('foo', 20, 30);
	*/
	
	public function where_between($field = "", $x, $y)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$gte'] = $x;
		$this->wheres[$field]['$lte'] = $y;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE BETWEEN AND NOT EQUAL TO PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is between but not equal to $x and $y
	*
	*	@usage : $mongodb->where_between_ne('foo', 20, 30);
	*/
	
	public function where_between_ne($field = "", $x, $y)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$gt'] = $x;
		$this->wheres[$field]['$lt'] = $y;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE NOT EQUAL TO PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents where the value of a $field is not equal to $x
	*
	*	@usage : $mongodb->where_not_equal('foo', 1)->get('foobar');
	*/
	
	public function where_ne($field = '', $x)
	{
		$this->_where_init($field);
		$this->wheres[$field]['$ne'] = $x;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	WHERE NOT EQUAL TO PARAMETERS
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents nearest to an array of coordinates (your collection must have a geospatial index)
	*
	*	@usage : $mongodb->where_near('foo', array('50','50'))->get('foobar');
	*/
	
	function where_near($field = '', $co = array())
	{
		$this->__where_init($field);
		$this->where[$what]['$near'] = $co;
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	Like
	*	--------------------------------------------------------------------------------
	*	
	*	Get the documents where the (string) value of a $field is like a value. The defaults
	*	allow for a case-insensitive search.
	*
	*	@param $flags
	*	Allows for the typical regular expression flags:
	*		i = case insensitive
	*		m = multiline
	*		x = can contain comments
	*		l = locale
	*		s = dotall, "." matches everything, including newlines
	*		u = match unicode
	*
	*	@param $enable_start_wildcard
	*	If set to anything other than TRUE, a starting line character "^" will be prepended
	*	to the search value, representing only searching for a value at the start of 
	*	a new line.
	*
	*	@param $enable_end_wildcard
	*	If set to anything other than TRUE, an ending line character "$" will be appended
	*	to the search value, representing only searching for a value at the end of 
	*	a line.
	*
	*	@usage : $this->mongo_db->like('foo', 'bar', 'im', FALSE, TRUE);
	*/
	
	public function like($field = "", $value = "", $flags = "i", $enable_start_wildcard = TRUE, $enable_end_wildcard = TRUE)
	 {
	 	$field = (string) trim($field);
	 	$this->where_init($field);
	 	$value = (string) trim($value);
	 	$value = quotemeta($value);
	 	
	 	if ($enable_start_wildcard !== TRUE)
	 	{
	 		$value = "^" . $value;
	 	}
	 	
	 	if ($enable_end_wildcard !== TRUE)
	 	{
	 		$value .= "$";
	 	}
	 	
	 	$regex = "/$value/$flags";
	 	$this->wheres[$field] = new MongoRegex($regex);
	 	return ($this);
	 }
	
	/**
	*	--------------------------------------------------------------------------------
	*	// Order by
	*	--------------------------------------------------------------------------------
	*
	*	Sort the documents based on the parameters passed. To set values to descending order,
	*	you must pass values of either -1, FALSE, 'desc', or 'DESC', else they will be
	*	set to 1 (ASC).
	*
	*	@usage : $this->mongo_db->where_between('foo', 20, 30);
	*/
	
	public function order_by($fields = array())
	{
		foreach ($fields as $col => $val)
		{
			if ($val == -1 || $val === FALSE || strtolower($val) == 'desc')
			{
				$this->sorts[$col] = -1; 
			}
			else
			{
				$this->sorts[$col] = 1;
			}
		}
		return ($this);
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	// Limit results
	*	--------------------------------------------------------------------------------
	*
	*	Limit the result set to $x number of documents
	*
	*	@usage : $this->mongo_db->limit($x);
	*/
	
	public function limit($x = 99999)
	{
		if ($x !== NULL && is_numeric($x) && $x >= 1)
		{
			$this->limit = (int) $x;
		}
		return ($this);
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	// Offset
	*	--------------------------------------------------------------------------------
	*
	*	Offset the result set to skip $x number of documents
	*
	*	@usage : $this->mongo_db->offset($x);
	*/
	
	public function offset($x = 0)
	{
		if ($x !== NULL && is_numeric($x) && $x >= 1)
		{
			$this->offset = (int) $x;
		}
		return ($this);
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	GET_WHERE
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents based upon the passed parameters
	*
	*	@usage : $mongodb->get_where('foo', array('bar' => 'something'));
	*/
	
	public function get_where($collection = "", $where = array())
	{
		return ($this->where($where)->get($collection));
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	GET
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents based upon the passed parameters
	*
	*	@usage : $mongodb->get('foo', array('bar' => 'something'));
	*/
	
	 public function get($collection)
	 {
	 	$documents = $this->db->{$collection}->find($this->wheres, $this->selects)->limit((int) $this->limit)->skip((int) $this->offset)->sort($this->sorts);
	 	
	 	// Clear
	 	$this->_clear();
	 	
	 	$returns = array();
	
		while ($documents->hasNext())
		{
		    $returns[] = $documents->getNext();
		}
		
		return $returns;
	 }
	

	/**
	*	--------------------------------------------------------------------------------
	*	GET ONE
	*	--------------------------------------------------------------------------------
	*
	*	Get the documents based upon the passed parameters
	*
	*	@usage : $mongodb->get('foo', array('bar' => 'something'));
	*/

	public function get_one($collection = "")
	{
		return array_shift($this->limit(1)->get($collection)) ?: null;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	COUNT
	*	--------------------------------------------------------------------------------
	*
	*	Count the documents based upon the passed parameters
	*
	*	@usage : $mongodb->get('foo');
	*/
	
	public function count($collection)
	{
		$count = $this->db->{$collection}->find($this->wheres)->limit((int) $this->limit)->skip((int) $this->offset)->count();
		$this->_clear();
		return ($count);
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	//! Insert
	*	--------------------------------------------------------------------------------
	*
	*	Insert a new document into the passed collection
	*
	*	@usage : $this->mongo_db->insert('foo', $data = array());
	*/
	
	public function insert($collection, $insert = array())
	{
		if (count($insert) == 0 || !is_array($insert))
		{
			show_error("Nothing to insert into Mongo collection or insert is not an array", 500);
		}
		
		try
		{
			$this->db->{$collection}->insert($insert);
			if (isset($insert['_id']))
			{
				return ($insert['_id']);
			}
			else
			{
				return (FALSE);
			}
		}
		catch (MongoCursorException $e)
		{
			show_error("Insert of data into MongoDB failed: {$e->getMessage()}", 500);
		}
	}
	
	
	/**
	*	--------------------------------------------------------------------------------
	*	//! Update
	*	--------------------------------------------------------------------------------
	*
	*	Updates a single document
	*
	*	@usage: $this->mongo_db->update('foo', $data = array());
	*/
	
	public function update($collection, $data = array(), $options = array())
	{
		if (is_array($data) && count($data) > 0)
		{
			array_merge($data, $this->updates);
		}
		
		try
		{
			$options = array_merge($options, array('multiple' => FALSE));
			
			$this->db->{$collection}->update($this->wheres, $this->updates, $options);
			$this->_clear();
			return (TRUE);
		}
		catch (MongoCursorException $e)
		{
			show_error("Update of data into MongoDB failed: {$e->getMessage()}", 500);
		}
	}
	
	
	/**
	*	--------------------------------------------------------------------------------
	*	Update all
	*	--------------------------------------------------------------------------------
	*
	*	Updates a collection of documents
	*
	*	@usage: $this->mongo_db->update_all('foo', $data = array());
	*/
	
	public function update_all($collection, $data = array())
	{
		if (count($data) == 0 || ! is_array($data))
		{
			show_error("Nothing to update in Mongo collection or update is not an array", 500);
		}
		
		try
		{
			$this->db->{$collection}->update($this->wheres, array('$set' => $data), array('multiple' => TRUE));
			$this->_clear();
			return (TRUE);
		}
		catch (MongoCursorException $e)
		{
			show_error("Update of data into MongoDB failed: {$e->getMessage()}", 500);
		}
	}
	
	
	/**
	*	--------------------------------------------------------------------------------
	*	Inc
	*	--------------------------------------------------------------------------------
	*
	*	Updates a collection of documents
	*
	*	@usage: $this->mongo_db->update_all('foo', $data = array());
	*/
	public function inc($fields = array(), $value = 0)
	{
		$this->_update_init('$inc');
		
		if (is_string($fields))
		{
			$this->updates['$inc'][$fields] = $value;
		}
		
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$inc'][$field] = $value;
			}
		}
		
		return $this;
	}
	
	public function set($fields, $value = NULL)
	{
		$this->_update_init('$set');
		
		if (is_string($fields))
		{
			$this->updates['$set'][$fields] = $value;
		}
		
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$set'][$field] = $value;
			}
		}
		return $this;
	}
	
	public function unset_field($fields)
	{
		$this->_update_init('$unset');
		
		if (is_string($fields))
		{
			$this->updates['$unset'][] = array($fields => 1);
		}
		
		elseif (is_array($fields))
		{
			foreach ($fields as $field)
			{
				$this->updates['$unset'][] = array($field => 1);
			}
		}
		return $this;
	}
	
	public function push($fields, $value = array())
	{
		$this->_update_init('$push');
		
		if (is_string($fields))
		{
			$this->updates['$push'][$fields] = $value;
		}
		
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$push'][$field] = $value;
			}
		}
		return $this;
	}
	
	public function push_all($fields, $value = array())
	{
		$this->_update_init('$pushAll');
		
		if (is_string($fields))
		{
			$this->updates['$pushAll'][] = array($fields => $value);
		}
		
		elseif (is_array($fields))
		{
			foreach ($fields as $field => $value)
			{
				$this->updates['$pushAll'][] = array($field => $value);
			}
		}
		return $this;
	}
	
	public function pop($field)
	{
		$this->_update_init('$pop');
		
		if (is_string($field))
		{
			$this->updates['$pop'][$field] = -1;
		}
		
		elseif (is_array($fields))
		{
			foreach ($fields as $field)
			{
				$this->updates['$pop'][$field] = -1;
			}
		}
		return $this;
	}

		
	public function pull($field = "", $value = "")
	{
		$this->_update_init('$pull');
	
		$this->updates['$pull'][$field] = $value;
		
		return $this;
	}
	
	public function pull_all($field = "", $values = array())
	{
		$this->_update_init('$pullAll');
	
		$this->updates['$pull'][$field] = $values;
		
		return $this;
	}
	
	public function rename_field($old, $new)
	{
		$this->_update_init('$rename');
	
		$this->updates['$rename'][] = array($old => $new);
		
		return $this;
	}
		
	/**
	*	--------------------------------------------------------------------------------
	*	DELETE
	*	--------------------------------------------------------------------------------
	*
	*	delete document from the passed collection based upon certain criteria
	*
	*	@usage : $mongodb->delete('foo', $data = array());
	*/
	
	public function delete($collection = "")
	{
		if (empty($collection))
		{
			throw new \Mongo_Exception("No Mongo collection selected to delete from");
		}
		
		try
		{
			var_dump($this->wheres);
			
			$this->db->{$collection}->remove($this->wheres, array('safe' => TRUE, 'justOne' => TRUE));
			$this->_clear();
			return true;
		}
		catch (MongoCursorException $e)
		{
			throw new \Mongo_Exception("Delete of data into MongoDB failed: {$e->getMessage()}", 500);
		}
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	DELETE_ALL
	*	--------------------------------------------------------------------------------
	*
	*	Delete all documents from the passed collection based upon certain criteria
	*
	*	@usage : $mongodb->delete_all('foo', $data = array());
	*/
	
	 public function delete_all($collection = "")
	 {
		if (empty($collection))
		{
			throw new \Mongo_Exception("No Mongo collection selected to delete from", 500);
		}
		
		try
		{
			$this->db->{$collection}->remove($this->wheres, array('safe' => TRUE, 'justOne' => FALSE));
			$this->_clear();
			return true;
		}
		catch (MongoCursorException $e)
		{
			throw new \Mongo_Exception("Delete of data into MongoDB failed: {$e->getMessage()}", 500);
		}
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	COMMAND
	*	--------------------------------------------------------------------------------
	*
	*	Runs a MongoDB command (such as GeoNear). See the MongoDB documentation for more usage scenarios:
	*	http://dochub.mongodb.org/core/commands
	*
	*	@usage : $mongodb->command(array('geoNear'=>'buildings', 'near'=>array(53.228482, -0.547847), 'num' => 10, 'nearSphere'=>true));
	*/
	
	public function command($query = array())
	{
		try
		{
			$run = $this->db->command($query);
			return $run;
		}
		
		catch (MongoCursorException $e)
		{
			throw new \Mongo_Exception("MongoDB command failed to execute: {$e->getMessage()}", 500);
		}
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	ADD_INDEX
	*	--------------------------------------------------------------------------------
	*
	*	Ensure an index of the keys in a collection with optional parameters. To set values to descending order,
	*	you must pass values of either -1, FALSE, 'desc', or 'DESC', else they will be
	*	set to 1 (ASC).
	*
	*	@usage : $mongodb->add_index($collection, array('first_name' => 'ASC', 'last_name' => -1), array('unique' => TRUE));
	*/
	
	public function add_index($collection = "", $keys = array(), $options = array())
	{
		if (empty($collection))
		{
			throw new \Mongo_Exception("No Mongo collection specified to add index to", 500);
		}
		
		if (empty($keys) || ! is_array($keys))
		{
			throw new \Mongo_Exception("Index could not be created to MongoDB Collection because no keys were specified", 500);
		}

		foreach ($keys as $col => $val)
		{
			if($val == -1 || $val === FALSE || strtolower($val) == 'desc')
			{
				$keys[$col] = -1; 
			}
			else
			{
				$keys[$col] = 1;
			}
		}
		
		if ($this->db->{$collection}->ensureIndex($keys, $options) == TRUE)
		{
			$this->_clear();
			return $this;
		}
		else
		{
			throw new \Mongo_Exception("An error occured when trying to add an index to MongoDB Collection", 500);
		}
	}
	
	
	
	/**
	*	--------------------------------------------------------------------------------
	*	REMOVE_INDEX
	*	--------------------------------------------------------------------------------
	*
	*	Remove an index of the keys in a collection. To set values to descending order,
	*	you must pass values of either -1, FALSE, 'desc', or 'DESC', else they will be
	*	set to 1 (ASC).
	*
	*	@usage : $mongodb->remove_index($collection, array('first_name' => 'ASC', 'last_name' => -1));
	*/
	
	public function remove_index($collection = "", $keys = array())
	{
		if (empty($collection))
		{
			throw new \Mongo_Exception("No Mongo collection specified to remove index from", 500);
		}
		
		if (empty($keys) || ! is_array($keys))
		{
			throw new \Mongo_Exception("Index could not be removed from MongoDB Collection because no keys were specified", 500);
		}
		
		if ($this->db->{$collection}->deleteIndex($keys, $options) == TRUE)
		{
			$this->_clear();
			return $this;
		}
		else
		{
			throw new \Mongo_Exception("An error occurred when trying to remove an index from MongoDB Collection", 500);
		}
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	REMOVE_ALL_INDEXES
	*	--------------------------------------------------------------------------------
	*
	*	Remove all indexes from a collection.
	*
	*	@usage : $mongodb->remove_all_index($collection);
	*/
	public function remove_all_indexes($collection = "")
	{
		if (empty($collection))
		{
			throw new \Mongo_Exception("No Mongo collection specified to remove all indexes from", 500);
		}
		$this->db->{$collection}->deleteIndexes();
		$this->_clear();
		return $this;
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	LIST_INDEXES
	*	--------------------------------------------------------------------------------
	*
	*	Lists all indexes in a collection.
	*
	*	@usage : $mongodb->list_indexes($collection);
	*/
	public function list_indexes($collection = "")
	{
		if (empty($collection))
		{
			throw new \Mongo_Exception("No Mongo collection specified to remove all indexes from", 500);
		}
		
		return ($this->db->{$collection}->getIndexInfo());
	}
	
	 
	/**
	*	--------------------------------------------------------------------------------
	*	_clear
	*	--------------------------------------------------------------------------------
	*
	*	Resets the class variables to default settings
	*/
	
	private function _clear()
	{
		$this->selects	= array();
		$this->wheres	= array();
		$this->limit	= 999999;
		$this->offset	= 0;
		$this->sorts	= array();
		$this->updates	= array();
	}

	/**
	*	--------------------------------------------------------------------------------
	*	WHERE INITIALIZER
	*	--------------------------------------------------------------------------------
	*
	*	Prepares parameters for insertion in $wheres array().
	*/
	
	private function _where_init($param)
	{
		if ( ! isset($this->wheres[$param]))
		{
			$this->wheres[ $param ] = array();
		}
	}
	
	/**
	*	--------------------------------------------------------------------------------
	*	Update initializer
	*	--------------------------------------------------------------------------------
	*
	*	Prepares parameters for insertion in $updates array().
	*/
	
	private function _update_init($method)
	{
		if ( ! isset($this->updates[$method]))
		{
			$this->updates[ $method ] = array();
		}
	}
}
/* End of file classes/mongodb.php */