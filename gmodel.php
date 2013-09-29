<?php

/********************************************************************************************
	
	GModel
	Version : 	Gen minus 0.05 (Landy) 

The Idea :
	- To simplify database access - usig ORM techniques
	- Avoid configuration issues
	- Avoid Metadata loading - instead rely on design time knowledge of Database
	- Ease of configuration - hassle free compared to Hibernate,for isntance
	- based on the idea that we dont need all the features as in Hibernate !
	- Reference : Yii (Php), Redbean (Php), Hibernate(Java) and the related JSRs for design principles
	- We donot support Data Definition (create database,table or Alter commands)
	- Just Select,Insert and Update ! The reason being that all DDL are carried out by DBAs

	Status
		- Usable
		- Unit testing - superficial
		- Many to many relationship and hierarchy yet to be completed
		- Lots of TODOs
		- Doesnt support dynamic metaloading other than MySQL's (though our design requirement
			avoids it , occasionally we would need this
		- Could have lots of error !
		- NO DML
		- Need to fine tune code
		- Need to check performance and tune that
		
	How To Use ?
		1. Set database details in Config
		2. 
		
		
********************************************************************************************/

require_once ('config.php');
require_once ('utils.php');

/********************************************************************************************

	class : DBConnection
		- contains the db connection / link handling!
 
********************************************************************************************/
class DBConnection
{
	public 	$username;
	public 	$password;
	public 	$hostname;
	private $dbname;	//or db isntance
	private $provider;
	private $db;
	private $dialect;
	private $logintype;
	public 	$dsn;
	public 	$conn;
	static public $cn;
	
	public function __construct($dsn='',$user='',$passwd='') 
	{
		$username 	= $user;
		$password 	= $passwd;
		$this->conn = new PDO($dsn, $user, $passwd);
		if(isset($this->conn)) Util::dbg( 'yes pdo ok');
	}
	static public function auto()
	{
		//pick from config
		global $config;
		self::$cn=new DBConnection($config['db']['dsn'],
					$config['db']['user'],$config['db']['password'],
					array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION)
					);
		return self::$cn;
	}
	static public function sys()
	{
		if(isset(self::$cn))
		self::auto();
		return self::$cn;
	}
}

/********************************************************************************************
	class Field
		Represents the metada of a Column or Field of a Table

********************************************************************************************/

class Field
{
	public $name;
	public $type;
	public $isForeignKey;
	public $isPrimaryKey;
	public $allowNull;
	public $defaultValue;
	public $table;
	public $autoIncrement;
	public $comment;
	public $php_type;
	public $pdo_type;
	public $hidden;
	
	//constraint
	public function __construct($t='',$n='')
	{
		$this->name 	= $n;
		$this->table 	= &$t;
	}
	public function setValues($data)
	{
		Util::oCast($this,$data);
		//var_dump($this);
	}
}

/********************************************************************************************
	
	class Chainer
		- Helper class to chain methods
		- the idea is to use a per  class object with static resolution
		- will explain the idea later !
	
********************************************************************************************/

class Chainer
{
	private $cl;
	public function __construct($cl='')
	{
		$this->cl=$cl;
	}
	public function __call($name,$args)
	{
		$cl=$this->cl;
		return  call_user_func_array (array($cl,$name),$args);
			 
	}
}


/********************************************************************************************
	
	class Rows
		- array access for relationship - 12many and possibly manytomany records/rows
		
********************************************************************************************/

class RowSet implements arrayaccess,Iterator
{
    private $rows = array();
	private $position = 0;
	private $model;
	private $name;
    
	public function __construct($model='',$name='') {
		$this->name = $name;
        $this->rows = array();
		$this->position = 0;
		$this->model = $model;
    }
    public function offsetSet($offset, $value) 
	{
        if (is_null($offset)) 
		{
			$cl = get_class($this->model);
			$tmp = $cl::$onetomany_;
			$jc =  $tmp[$this->name][1];
        
			$value->{$jc} = $this->model->{$jc};
			$this->rows[] = $value;
			
        } 
		else 		
		{
            $this->rows[$offset] = $value;
        }
    }
    public function offsetExists($offset) 
	{
        return isset($this->rows[$offset]);
    }
    public function offsetUnset($offset) 
	{
        unset($this->rows[$offset]);
    }
    public function offsetGet($offset) 
	{
        return isset($this->rows[$offset]) ? $this->rows[$offset] : null;
    }
	
	///////////////////////////////// Iterator methods
	
    function rewind() 
	{
        $this->position = 0;
    }

    function current() 
	{
        return $this->rows[$this->position];
    }

    function key() 
	{
        return $this->position;
    }

    function next() 
	{
        ++$this->position;
    }

    function valid() 
	{
        return isset($this->rows[$this->position]);
    }
	
}

/********************************************************************************************
	
	class Table
		- abstraction of a table
		
********************************************************************************************/

class Table
{
	public $tablename	;
	public $db			;
	public $fields		;	//array of FieldMeta
	public $primaryKeySet	;
	public $set = array()	;
	private $filter_=array();

	public function __construct($_t='')
	{
		$this->tablename=$_t;
	}
	
	public   function _addFields($cl)
	{
	    $refclass = new ReflectionClass($cl);
		$this->fields=array();
		
		$defProp = $refclass->getDefaultProperties();
		$primaryKS =array();
		foreach ($refclass->getProperties() as $property)		
		{
			$name = $property->name;
			
			if ($property->class == $refclass->name)
			{
				//$defProp[$property->name] can return array ! so dump array instead - below
				//Util::dbg( "<p>Property {$property->name}  Value: {$defProp[$property->name]} <p>\n");
			
				$f = new Field( $this->tablename,$property->name);
				
				 if(is_array($defProp[$property->name]))
				 {
					$f->php_type=$defProp[$property->name][0];
					$f->defaultValue=$defProp[$property->name][1];
					if (isset($defProp[$property->name][2]) && strcasecmp($defProp[$property->name][2],'PRI')==0)
					{
						$primaryKS[]=$property->name;
						$f->isPrimaryKey = true;
					}
				}
				else
				{
					//if(is_int($f->php_type=$defProp[$property->name]))
					$f->defaultValue=$defProp[$property->name];
					$f->php_type=gettype($defProp[$property->name]);
				}
				 
				$this->primaryKeySet = $primaryKS;					
				$this->fields[] = $f;
			}
			
		}
		//foreach  - dump fields
		//$this->dump();
	}
	public   function dump()
	{
		foreach($this->fields as $f)
		{	
			Util::dbg('dumping field: ');
			Util::msg( '<pre>' . print_r($f,true) .'</pre>');
		}
	}
	public function _select()
	{
		return implode(',', $this->fieldarray());	
	}
	public function _selectAlias($n,$t,$j='_')
	{
		
		$na=array();
		if(!isset($t))$t=$this->tablename;
		foreach($this->fieldarray() as $f)
		{
				$na[]  = "{$t}.{$f} as {$n}{$j}{$f}";
		}
		return implode(', ',$na);
	}
	//private forea
	public function fieldarray()
	{
		$ar= array();
		foreach($this->fields as $key)
		{
			$ar[]=$key->name;
		}
		return $ar;
		 
	}
	
	public function query($limit=1)
	{ 
		$where='';
		//if(isset($this->filter_['current']))
		if(array_key_exists('current',$this->filter_  ) )
		{
			$where=' WHERE ' . $this->filter_['current'] ; Util::dbg($this->filter_['current'] );
		}
		return 'SELECT ' . $this->_select() . " FROM {$this->tablename} {$where} LIMIT {$limit}";
	}
	public function whereClause()
	{
		$where='';
		if(array_key_exists('current',$this->filter_ ) )
		{
			$where=' WHERE ' . $this->filter_['current'] ; Util::dbg($this->filter_['current'] );
		}
		return  $where;
	}
	public function queryAll()
	{ 
		$where='';
		if(array_key_exists('current',$this->filter_ ) )
		{
			$where=' WHERE ' . $this->filter_['current'] ; Util::dbg($this->filter_['current'] );
		}
		return 'SELECT ' . $this->_select() . " FROM {$this->tablename} {$where} ";
	}
	
	public function filter($n,$w)
	{	 
		$this->filter_[$n]=$w;
	}
	public function using($n)
	{
		
			Util::dbg('***using:'.$n);
			Util::objdump($this->filter_);
		if(isset($this->filter_[$n]))
		{
			$args=func_get_args();	
			$nf = $this->filter_[$n];
			array_shift($args);
			foreach($args as $a) //TODO: inefficient need to loop on ? instead
			{
			 	$nf=preg_replace('/\?/', $a, $nf , 1);
			}
			$this->filter_['current']= $nf ;
		}
		else
		{	
			Util::dbg('ERROR*** ' . $nf .' not found');
			
		//TODO else throw wxception
		}
		
	}
	public function where($n)
	{
		 
		$this->filter_['current']=$n;
	}
	
	
}


/********************************************************************************************
	class GModel 
		-The Model class - abstracts a row, table or rowset !
		-the magic class with most functionalities
		-should be inherited
		- not sure about the behaviour of 2nd or more level of inheritance ! - use with caustion

********************************************************************************************/
 
class GModel
{

	public 	static 		$fieldmeta;
	public  static		$table;
	public  static		$tableinst;
 
	const 				Nascent	=0;
	const	 			Loaded	=1;
	const 				Modified=1;
	
	protected 			$state;
	public 		 		$fields;
	
	private 			$colaliases	= array();
	private 			$mapping	= array();
	public 				$tablename;
	public				$visibility;
	public				$collectionset;
	
	protected 			$hidden			= array(); /// set to be hidden
	protected 			$pulled			= array() ; //// just pulled
	protected 			$filter_		= array();
	 
	public static		$onetoone_   	;
	public static		$onetomany_		;
	public static		$manytomany_ 	;
	
	public 				$relfields =array();		 
	
	public  static 		$chainer		;
	
	public static function getChainer()
	{
		//return  ( (static::$chainer==null) ? (static::$chainer=new Chainer(get_called_class())):static::$chainer);
		if(static::$chainer==null)
		{
			$ch=new Chainer(get_called_class());
			static::$chainer=&$ch;
		}
		return 	static::$chainer;
	}
	
	private  static function _addFields($cl)
	{
		static::$tableinst->_addFields($cl);
	}
	
	public function fieldarray()
	{
		return static::$tableinst->fieldarray();
	}
	
	public static function loadColumnMeta($table)
	{
		$data=(new MySQLMeta())->loadTableMeta($table);
		//print_r($data);
		$arr=array();
		foreach($data as $n=>$v)
		{
			$f=new Field($table,$v->name);
			$f->setValues($v);
			$arr[]=$f;
		}
		//Util::dbg( '<pre>'.print_r($arr,true).'</pre>');		
	}
	private static function init_($arg)
	{
				//connect to db - establish schema for early binding
				//args - default conn - table/qry - restricted fields				
				$cl=get_called_class();
				Util::dbg('init : ' .$cl);
				
				if(isset($arg[0]))
				{
					$t 					= $arg[0];
					$table				= new Table($t);				
					$tn 				= $table->tablename;
					
					//// Tech : Reference essential to store/reference static variable - overriding of static class Variable ! 
					
					$cl::$table			= &$tn;
					$cl::$tableinst 	= &$table;
					
					$a					= array();
					$b					= array();
					$c					= array();
					
					
					$cl::$onetoone_   	= &$a;
					$cl::$onetomany_	= &$b;
					$cl::$manytomany_ 	= &$c;
				}
				else
				{
					throw new Exception();
				}
				if(isset($arg[1]) && $arg[1])
				{
					//load meta data from table
				}
				self::_addFields($cl);
				Util::dbg('init done');
	}
	
	public function setfields()
	{
		$d = DBConnection::auto();
		if(isset($d)) Util::dbg( 'set !db');
		$q=static::$tableinst->query();
		Util::dbg($q);
		$data=$d->conn->query( $q ,PDO::FETCH_ASSOC);
		//$this->table->recordset=$data;
		foreach($data as $row) 
		{
			foreach($this->fields as $key=>$val)
			{
				$this->fields[$key] = $row[$key];
			}
		}	
	}

	public function  __construct()
	{
		Util::dbg( 'GModel instance ' . get_called_class() . ' created');
		$this->state=self::Nascent;
		foreach(static::$tableinst->fields as $f)
		{
			$this->fields[$f->name]=$f->defaultValue;			
			unset($this->{$f->name});
		}
		$m = static::$onetomany_;
		if(!empty($m))
		{		
			foreach($m as $rkey => $rel)
			{
				 Util::dbg(':::'.$rkey);
				 $this->relfields[$rkey] = new RowSet($this,$rkey);
				// $this->{$rkey} = array();
			}
		}
	}
	
	public function __set($name,$value)
	{
		if(array_key_exists($name,$this->fields))
		{
			if(isset($this->hidden[$name])) 
			{		
				Util::dbg( 'field unset');
				//throw new FieldUnSet();
			}
			else
			{
				$methname =  'set'.ucfirst($name);
				if(method_exists($this,$methname))
				{
					 $this->{$methname}($value);
				}
				else
				{
					//Util::dbg('field get'.$name);
					$this->fields[$name]=$value;
				}
			}
		
		}
		else if(array_key_exists($name,static::$onetoone_))
		{
			$s=static::$onetoone_ ;
			$jc=$s[$name][1]  ;
			Util::dbg("join column $jc");
			if(is_object($value))
			{
				//TODO throw error if not an object
				
				$this->relfields[$name] 		= $value; 				
				$this->relfields[$name]->{$jc} 	= $this->{$jc};
			}
			else
				Util::dbg('error setting value for ' .$name);
				
		}
		
		else
		{
			Util::dbg('Field not found error :' .$name);
		}
	}
	
	public function __get($name)
	{
		//Util::dbg( ' __get '. $name);
		if(array_key_exists($name,$this->fields))
		{
			if(isset($this->hidden[$name])) 
			{		
				Util::dbg( 'field unset');
				//throw new FieldUnSet();
			}
			else
			{
				if(method_exists($this,'get'.$name))
				{
					$methname =  'get'.$name;
					return $this->{$methname}();
				}
				else
				{
					//Util::dbg('field get'.$name);
					return $this->fields[$name];
				}
			}
		}
		else if(array_key_exists($name,static::$onetoone_))
		{
			if(method_exists($this,'get'.$name))
			{
				$methname =  'get'.$name;
				return $this->{$methname}();
			}
			else
			{
				//Util::dbg('field get'.$name);
				return $this->relfields[$name];
			}
		}
		else if(array_key_exists($name,static::$onetomany_))
		{
			if(method_exists($this,'get'.$name))
			{
				$methname =  'get'.$name;
				return $this->{$methname}();
			}
			else
			{
				//Util::dbg('field get'.$name);
				if(!isset($this->relfields[$name]) /*|| !is_array($this->relfields[$name])*/ )
				{
					Util::dbg( 'errrr');
					//$this->{$name} =   array();
					//$this->relfields[$name] = &$this->{$name};
				}
				else
				{
					Util::dbg('Error : retrieving 12many field : '.$name);
				}
				//$t =  $this->relfields[$name] ;
			 
				return $this->relfields[$name] ;
			}
		}
		else
		{
			Util::dbg('__get:  not found error :' .$name);
		}
	}
	
	
	/*
	 
	public static function saveAll()
	{
		///saves all in vault
	}
	*/
	public function save()
	{
		// accept parameter if specific acolumn
		// in class denote ->
		// so need  init / initwithmeta
		// gettign datatypes
		
		$d 		= DBConnection::auto();
		$t	 	= ':'.implode(', :',array_keys($this->fields));
		$f	 	= implode(', ',array_keys($this->fields));
		$tbl	= static::$tableinst->tablename;
		$q 		= "INSERT INTO   $tbl ( $f )  VALUES( $t ) ";
		Util::dbg($q);
		$p = $d->conn->prepare($q);
		Util::objdump($this->fields);
		foreach (static::$tableinst->fields as $f)
		{
			Util::dbg('bind-'.':'.$f->name.' - '.$this->fields[$f->name].' - ' .Util::pdo_type($f->php_type));
			$p->bindParam(':'.$f->name, $this->fields[$f->name], Util::pdo_type($f->php_type) );
		}
		try
		{
			if(!$data=$p->execute( )) throw new Exception('db insert exception'); 
		//	$d->conn->commit();
		}
		catch(PDOException $e)
		{
			Util::dbg('exception inserting' . $e->getMessage() . ' '. $e->getCode());
		}
		catch(Exception $e)
		{
			$tmp=$p->errorInfo();
			Util::msg( 'Error :'. '<pre>Execute:'. print_r( $tmp,true).' - '. $p->errorCode() .' </pre>');
			Util::dbg('exception inserting' ); ///need to throw out !
		}
		
		///// Now save related data
		//TODO check for nascency
		//// TODO later check for array , array of arrray on the enumerated field and save automatically)
		/// MUST USE TRANSACTION HERE (actually ryt from the above statement exec)
		$s = static::$onetoone_;
		if(!empty($s))
		{
			foreach($s as $rkey => $rel)
			{
				if(isset($this->relfields[$rkey]))
				{
					$jc = $rel[1];
					$this->relfields[$rkey]->save();
				}
				else
				{
					Util::dbg('onetoone field not set :' . $rkey );
				}
			}
		}
			$m = static::$onetomany_;
			if(!empty($m))
			{		
				foreach($m as $rkey => $rel)
				{
					if(isset($this->relfields[$rkey]))
					{
						Util::dbg("field set ** $rkey");
						$jc = $rel[1];
						//foreach($this->relfields[$rkey] as  $rec) // php refuses to co-operate  
						// will thrw error similar to :
						//Indirect modification of overloaded property User::$avatars has no effect in C:\\MyDesktop\\web\\aaa\\rel.php on line 44
						//foreach($this->{$rkey} as  $rec)
						
						foreach($this->relfields[$rkey] as  $rec) 
						{
							//TODO - condition based save - for nascent/dirty only
							// or call $rec->update as is appropriate
							// leave space for 
							// set the foregin key
							$rec->{$jc} = $this->{$jc};
							
							Util::dbg($rec->$jc . '  ' . $this->$jc);
							$rec->save();
						}
					}				
					else
					{
						Util::dbg('onetomany field not set :' . $rkey );
					}
				}
			}
		Util::dbg('insert exiting');
	}
	
	
	
	
	
	/*** uses primary key***/
	public function update()
	{
		$t=array();//
		$pk=array(); 
		Util::objdump(static::$tableinst->primaryKeySet);
		
		foreach($this->fields as $f=>$v) 
		{		
			if(in_array($f,static::$tableinst->primaryKeySet))
			{ 	$pk[]= $f . ' = :' .$f ;				 		}
			else
			{	$t[]= $f . ' = :' .$f ; 						}
		}
		$t = implode(',',$t);
		$m = implode(' and ',$pk);
 		$tbl=static::$tableinst->tablename;

		$q = "UPDATE  $tbl SET $t where $m ";
		Util::dbg($q);
		$d = DBConnection::auto();
		$p = $d->conn->prepare($q);
		Util::objdump($this->fields);
		foreach (static::$tableinst->fields as $f)
		{
			Util::dbg('bind-'.':'.$f->name.' - '.$this->fields[$f->name].' - ' .Util::pdo_type($f->php_type));
			$p->bindParam(':'.$f->name, $this->fields[$f->name], Util::pdo_type($f->php_type) );
		}
		try
		{
			if(!$data=$p->execute( )) throw new Exception('db update exception'); 
		//	$d->conn->commit();
		}
		catch(PDOException $e)
		{
			Util::dbg('exception update' . $e->getMessage() . ' '. $e->getCode());
		}
		catch(Exception $e)
		{
			$tmp=$p->errorInfo();
			Util::msg('Error:' . '<pre>Exc:'. print_r( $tmp,true).' - '. $p->errorCode() .' </pre>');
			Util::dbg('update error' ); ///need to throw out !
		}
		
		
	}
	public static function __callStatic($name, $arg)
    {
			if( $name === 'init')
			{
				self::init_($arg);
			}
			else
			{	
				preg_match('/(find)(.+)*/',$name,$matches);
				if(count($matches)>0)
				{
					if(count($matches)==2)
					{
						Util::dbg('find called');
						Util::objdump($matches);
						
					}
					elseif(count($matches)==3)
					{
						//Util::objdump($matches);
						//Util::objdump(
						Util::objdump(preg_split('/(_a_|and|_and_)/',$matches[2]));
						//return static::where("{$matches[2]}={$arg[0]}")->fetch();
						return null;
					}					
				}
				else
				{
					$t=array('where','filter','using');
					call_user_func_array(array(static::$tableinst,$name),$arg);
					return static::getChainer();
				}
			}
			
    }
	
	/* One to many affects instance */
	public static function dump()
	{
		static::$tableinst->dump();
	}
	public   static function related($vrbl,$vn,$class,$fkey,$jtable,$jcol)
	{
		if (class_exists($class))
		{
			if( !isset($fkey))
			{
				//$class::get_foreign_keys();
			}
			else
			{
				$args = func_get_args();
				array_shift($args);array_shift($args);
				static::${$vrbl}[$vn] = $args ;
			}	 
		}
		else
		{
				//use  select * / tmp stdClass ?
		}
	}
	public static function oneToMany ($vn='',$class='',$fkey='',$jtable='',$jcol='')
	{
		static::related('onetomany_',$vn,$class,$fkey,$jtable,$jcol );
	}
	public function manyToMany($vn='',$class='',$fkey='',$jtable='',$jcol='')
	{
		static::related('manytomany_',$vn='',$class='',$fkey='',$jtable='',$jcol='' );
	}
	public   static function oneToOne($vn='',$class='',$fkey='',$jtable='',$jcol='')
	{
		static::related('onetoone_',$vn,$class,$fkey,$jtable,$jcol );
	}
	
	private function pullRelations()
	{
		Util::dbg('pullRelations');
		$r = static::$onetomany_;
		if(isset($r))
		{
			foreach($r as $rkey => $rel)
			{
				$cl = $rel[0];
				if(!empty($rel[2]))
					$rnxt = $rel[2];
				else
					$rnxt = $rel[1];
				$this->fields[$rkey] = $cl::where("{$rnxt}={$this->$rel[1]}")->fetchAll();
			}
		}
		$s = static::$onetoone_;
		if(!empty($s))
		{
				 
				$cnt=2;
				$ot = static::$table;
				$oi = static::$tableinst;
				$oq = static::$tableinst->query();
				$from = " ({$oq}) t1";
				$rc='';
				$oc = static::$tableinst->_selectAlias('t1','t1' );
					foreach($s as $rkey => $rel)
					{
						$cl = $rel[0];
						$jc = $rel[1];
						$rt = $cl::$table;
						$ri = $cl::$tableinst;
						
						$rc = "{$rc}, " . $cl::$tableinst->_selectAlias("t{$cnt}","t{$cnt}");
						//Util::dbg($oc);
						//Util::dbg($rc);
						$from = "$from, $rt t{$cnt}";
						if(isset($joinwhere))
						$joinwhere= "{$joinwhere} and t1.{$jc} = t{$cnt}.{$jc} " ;
						else
						$joinwhere= "t1.{$jc} = t{$cnt}.{$jc} " ;
						$cnt++;
					}
			
				Util::dbg("joinwhere : $joinwhere");
				Util::dbg("from : $from");
				$limit=1;
				 
				$q="SELECT {$oc} {$rc} FROM {$from} WHERE {$joinwhere} LIMIT 1";
				Util::dbg("final : {$q}");
							
					try
					{		
					$d=DBConnection::auto();
					$data=$d->conn->query( $q );
					$rows=$data->fetchAll(PDO::FETCH_ASSOC);
					$row=$rows[0];
					$cnt = 1;
					foreach(static::$tableinst->fields as $f)
					{
						$this->{$f->name} = $row["t1_{$f->name}"]; 
					}
					$cnt=2;
					foreach($s as $rkey => $rel)
					{
						$cl = $rel[0];
						$ri = $cl::$tableinst;
						$tmp = new $cl;
						foreach($ri->fields as $f)
						{
							$tmp->{$f->name} = $row["t{$cnt}_{$f->name}"];
						}
						
						$this->fields[$rkey] = $tmp;
						$cnt++;
					}	
				}
				catch(PDOException $e)

				{
				Util::dbg('exception update' . $e->getMessage() . ' '. $e->getCode());
				}
				 
			
		}
		
	}
	
	/*public static function find()
	{
		
	}*/
	 

 
	 static public function fetch() /// w limit!
	 {
			$cl = get_called_class();
			Util::dbg('fetch function ' . $cl);
			$obj = new  $cl();
			$obj->setfields();
			$obj->pullRelations();
			return $obj;
	 }
	 
	 static public function fetchAll($vaultname='') ///vaults if a parameter is passed
	 {
			$d = DBConnection::auto();
			$q=static::$tableinst->queryAll();
			$data=$d->conn->query( $q ,PDO::FETCH_ASSOC);
			Util::objdump($data);
			
			$arr=array();
			$cl=get_called_class();
			foreach($data as $row)
			{
				$obj = new $cl();
				foreach($obj->fields as $key=>$val)
				{
					$obj->fields[$key] = $row[$key];
				}
				$arr[]=$obj;
			}	

		return $arr;
	 }
	 public function hide($colarray)
	 {
		$this->__hideA=$colarray;
	 }
	 public function select($colarray) // selects just these // accessing the rest of the property throws exception
	 {
		$this->__selectA = $colarray;
	 } 
	 public function toString($delim=', ')
	 {
		$res=array();
		foreach($this->fields as $f=>$v)
		{
			if(!is_object($v) && !is_array($v))
			$res[]=implode(':',array($f,$v));
		}
		return implode($delim,$res);
	 }
}

?>	