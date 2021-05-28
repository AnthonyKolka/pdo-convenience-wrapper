<?php
class DB {
	/*****
	 *MDB2 style convenience functions for PDO
	 *2016-2021
	 *Anthony L Kolka
	 *Handy Networks LLC
	 *http://handynetworks.com
	 */
	private $dbh;
	public  $error = false;
	public	$errorString;
	private $sth;
	
	private $sqlCache = [];

	public function __construct($host, $user, $pass, $db, $type='mysql')
	{
		$dsn = "$type:Server=$host;Database=$db";
		$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
		try
		{
			$this->dbh = new PDO($dsn, $user, $pass, $options);
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
		}
	}
	
	function __destruct()
	{
		$this->close();
	}
	
	public function execute($values)
	{
		//executes statements initiated with $this->prepare()
		try
		{
			//log details on a failure as well.
			$execute = $this->sth->execute($values);
			if(!$execute){
				$this->errormsg($values);
			}
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		return true;
	}

	public function exec($sql, $values = null)
	{
		//for inserts, updates, and other query's that do not return data
		try
		{
			$this->prepex($sql, $values);
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		return $this->sth->rowCount();
	}

  public function execAll($values, $mode = null)
  {
		try
		{
			$this->execute($values);
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		try
		{
		  $result = $this->sth->fetchAll($mode);
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		return $result;
	}

	public function fetchAll($mode = PDO::FETCH_ASSOC)
	{
		try
		{
			if($this->sth->columnCount() > 0)
			{
				$result = $this->sth->fetchAll($mode);
			}
			else
			{
				$result = [];
			}
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		$this->finish();
		return $result;
	}

	public function prepare($sql, $returnSth=false)
	{
		$this->error = false;
		try
		{
			if($returnSth)
			{
				return $this->dbh->prepare($sql);
			}
			else
			{
				$this->sth = $this->dbh->prepare($sql);
			}
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		return true;
	}

	private function prepex($sql, $values = null)
	{
		//prepares and executes statement
		$this->error = false;
		$this->prepare($sql);
		$this->execute($values);
	}

	public function query($sql, $values = null)
	{
		//returns statement handler as result set, finish must be called manually
		$this->prepex($sql, $values);
		return $this->sth;
	}

	public function queryAll($sql, $values = null, $mode = PDO::FETCH_ASSOC)
	{
		//Returns all results as an array
		$this->prepex($sql, $values);
		$result = $this->fetchAll($mode);
		$this->finish();
		return $result;
	}

	public function queryCol($sql, $values = null)
	{
		//returns an array containing all first column values
		$this->prepex($sql, $values);
		$result = $this->fetchAll(PDO::FETCH_NUM);
		$return = array_column($result, 0);
		$result = null;
		$this->finish();
		return $return;
	}

	public function queryOne($sql, $values = null)
	{
		//returns the first column from the first row as a scalar value
		$this->prepex($sql, $values);
		try
		{
			$result = $this->sth->fetch();
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		$this->finish();
		return $result[0];
	}

	public function queryRow($sql, $values = null, $mode=PDO::FETCH_ASSOC)
	{
		//returns the first row as an array
		$this->prepex($sql, $values);
		try
		{
			$result = $this->sth->fetch($mode);
		}
		catch(PDOException $e)
		{
			$this->seterror($e->getMessage());
			
			return false;
		}
		$this->finish();
		return $result;
	}

	public function queryObj($sql, $key, $values = null)
	{
		//returns a multidimensional associative array where each row is grouped by the specified column, that column becomes the first level
		if(empty('key'))
		{
			$this->setError("No key specified");
			return false;
		}
		$this->prepex($sql, $values);
		$result = $this->fetchAll(PDO::FETCH_ASSOC);
		$this->finish();
		if(empty($result))
		{
			$this->error = "No result set!";
			return false;
		}
		if(!array_key_exists($key, $result[0]))
		{
			$this->setError("Specified key($key) not found in data!");
			return false;
		}
		$obj = [];
		foreach($result as $row)
		{
			$obj[$row[$key]] = $row;
		}
		return $obj;
	}

	public function delete($table, $data)
	{
		if(!is_array($data))
		{
			$this->setError("Data must be provided, an associative array is expected.");
			return false;
		}
		$cols = array_keys($data);
		$token = md5( implode($cols) );
		if(!isset($this->sqlCache['insert'][$table][$token]))
		{
			$where = "";
			$and = false;
			foreach($cols as $col)
			{
				if($and) $where .= " and ";
				$where .= "$col = :$col";
				$and = true;
			}
			$this->sqlCache['delete'][$table][$token] = "DELETE from $table WHERE $where";
		}
		return $this->exec($this->sqlCache['delete'][$table][$token], $data);
	}

	public function insert($table, $data)
	{
		if(!is_array($data))
		{
			$this->setError("Data must be provided for insert, an associative array is expected.");
			return false;
		}
		$cols = array_keys($data);
		$token = md5( implode($cols) );
		if(!isset($this->sqlCache['insert'][$table][$token]))
		{
			$columns = implode(', ', $cols);
			$values = implode(', ',
				array_map(
					function($key)
					{
						return ":$key";
					},
					$cols
				)
			);
			$this->sqlCache['insert'][$table][$token] = "INSERT into $table ($columns) values ($values)";
		}
		return $this->exec($this->sqlCache['insert'][$table][$token], $data);
	}
	
	public function update($table, $data, $where='id')
	{
		if(!is_array($data))
		{
			$this->setError("Data must be provided for update, an associative array is expected.");
			return false;
		}
		$cols = array_keys($data);
		$token = md5( implode($cols) );
		if(!isset($this->sqlCache['update'][$table][$token]))
		{
			$sql = "UPDATE $table SET ";
			$columns = [];
			if(is_array($where))
			{
				foreach($cols as $col)
				{
					if(!in_array($col, $where))
					{
						$columns[] =  "$col = :$col";
					}
				}
			}
			else
			{
				foreach($cols as $col)
				{
					if($col != $where)
					{
						$columns[] =  "$col = :$col";
					}
				}
			}
			$sql .= implode(', ', $columns);
			$sql .= " WHERE ";
			if(is_array($where))
			{
				$and = false;
				foreach($where as $w)
				{
					if($and) $sql .= " and ";
					$sql .= "$w = :$w";
					$and = true;
				}
			}
			else
			{
				$sql .= "$where = :$where";
			}
			$this->sqlCache['update'][$table][$token] = $sql;
		}
		return $this->exec($this->sqlCache['update'][$table][$token], $data);
	}
	
	public function finish()
	{
		$this->sth->closeCursor();
	}

	public function close()
	{
		$this->sth = null;
		$this->dbh = null;
	}
	
	public function beginTransaction()
	{
		return $this->dbh->beginTransaction();
	}

	public function rollBack()
	{
		return $this->dbh->rollBack();
	}

	public function commit()
	{
		return $this->dbh->commit();
	}

	public function tExec($sql)
	{
		return $this->dbh->exec($sql);
	}

	public function lastInsertID()
	{
		return $this->dbh->lastInsertId();
	}

	private function errormsg($values=null)
	{
		$msg = $this->errorString;
		if(!empty($this->sth) && property_exists($this->sth, 'queryString'))
		{
			$msg .= ' SQL: ' . $this->sth->queryString;
		}
		trigger_error($msg, E_USER_WARNING);
		error_log(print_r(debug_backtrace(), true));
		if(!is_null($values))
		{
			error_log(print_r($values, true));
		}
	}
	
	private function setError($message, $values=null)
	{
		$this->error = true;
		$this->errorString = $message;
		$this->errormsg($values);
	}

}
?>