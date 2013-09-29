<?php

class Util
{
	static public function oCast($toObject, stdClass &$object)
	{
		foreach($object as $property => &$value)
		{
			$toObject->$property = &$value;
			unset($object->$property);
		}
		//unset($value);
		return $toObject;
	}
	static public function pdo_type($t)
	{
	
		$map	=	array(	'bool'	=>PDO::PARAM_BOOL 	,		
							'int'	=>PDO::PARAM_INT 	,			
							'string'=>PDO::PARAM_STR 	);		
	       //PDO::PARAM_NULL  //PDO::PARAM_LOB 	PDO::PARAM_STMT		 PDO::PARAM_INPUT_OUTPUT
		   return array_key_exists($t,$map) ? $map[$t] :PDO::PARAM_STR;
	}   
	static public function dbg($v)
	{
		if(defined('DEBUG'))
		{
			echo '<hr>DEBUG:  ' . $v . '<br>';
		}
	}
	static public function objdump($obj)
	{
		if(defined('DEBUG'))
		echo '<hr><u>DEBUG (OBJECT DUMP):</u><br><pre>'.print_r($obj,true).'</pre><br><hr>';
	}	
	
	static public function msg($v)
	{
		echo '<hr>MSG :  ' . $v . '<br>';
	}
	
	static public function ObjectDump($obj)
	{
		echo '<hr><u>MSG : (ObjectDump):</u><br><pre>'.print_r($obj,true).'</pre><br><hr>';
	}	
}        
?>