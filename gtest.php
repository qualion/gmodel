<?php

/************************************************************************************************

	The basic requirements

************************************************************************************************/

//setup config.php with username,dsn etc
require_once ('gmodel.php');

/***********************************************************************************************/ 

/************************************************************************************************
	
	EXAMPLE : 1
	
	A simple 'USERS' table with the following mysql schema
				
				mysql> desc users;
				+--------------+--------------+------+-----+---------+-------+
				| Field        | Type         | Null | Key | Default | Extra |
				+--------------+--------------+------+-----+---------+-------+
				| userId       | int(11)      | NO   | PRI | NULL    |       |
				| userName     | varchar(100) | NO   |     | NULL    |       |
				| userFullName | varchar(300) | NO   |     | NULL    |       |
				| password     | varchar(100) | NO   |     | NULL    |       |
				+--------------+--------------+------+-----+---------+-------+
				4 rows in set (0.00 sec)
	
	Create Table: CREATE TABLE `users` (
		  `userId` int(11) NOT NULL,
		  `userName` varchar(100) NOT NULL,
		  `userFullName` varchar(300) NOT NULL,
		  `password` varchar(100) NOT NULL,
		  PRIMARY KEY (`userId`)
		) ENGINE=InnoDB DEFAULT CHARSET=latin1
	Test Data :
		
	Insert Into Users Values 
		(1,'thekkamalai','Theks the Thekkamalai','passw0rd'),
		(2,'admin','Administrator','*&#@##'),
		(3,'jino','allinall','pass4567');
		
		The class need to be defined with 
		
		
************************************************************************************************/

Util::msg('Example 1');

/************************************************************************************************

Declare the table/row representation class with the needed fields. Unless you intend to update,
a primary key is not necessary

************************************************************************************************/

class User extends GModel {

	// the variable names should match with column names in the (users) table
	
	public $userId		 =	0 		 ;			// if this is not an array,this becomes the defaukt value !
	public $userName	 = 	'Manisha';			// Default type is string, default type, when there is no assignment  
	public $password	 =	'a8u0^#2';
	public $userFullName =  'Manisha, Koirala';

}

/* Mandatory init for each Model class */

User::init('users');	//// Mention the table name
User::dump();			///  Dump the default values of the fields

$user = new User();
$user->userId =	4;
$user->save();		// Use default values for fields and save (to table user)

//Util::ObjectDump($user);
Util::msg($user->toString());

/************************************************************************************************
	
	EXAMPLE : 2 (update)
	
************************************************************************************************/

Util::msg('Example 2');

/************************************************************************************************

	Declare the table/row representation class with Meta data in array

************************************************************************************************/

class UserModel extends GModel 
{
	public $userId		 =  array('int',1,'pri'); /// Pass meta info including default value in an array
	public $userName	 = 'testuser';
	public $userFullName = 'Test User';
	public $password 	 = 'test*&^#';

	// Support get/set methods too!
	public function getUserName()
	{
		echo '<br>get function<br>';
		return  $this->fields['userName'];
	}
}

UserModel::init('users'); /* just once for this class, can be put on top */

$t		      	= new UserModel();
$t->userId		= 5;
$t->userName	= 'aish'; 
$t->password 	= '***';

//Util::ObjectDump($t);
$t->save();

//// Reload could have been here !

$t->userFullName	='Aiswariya Rai'; 
Util::msg($t->userName);
$t->update(); 		//// Primary key needs to be defined as above

$jj = UserModel::fetch(5);
Util::msg($jj->toString());
//TODO : auto call to string in a fnction like Util::pp($obj); // pretty print or dump or so


/************************************************************************************************
	
	EXAMPLE : 3 (fetch)
	
************************************************************************************************/

Util::msg('Example 3');

/************************************************************************************************

	Call fetch one previous defines User class

************************************************************************************************/

/////////////// Fetches the first Row
Util::msg('Example 3.1');
$x=User::fetch(); 
//Util::ObjectDump($x);
Util::msg($x->userId);
Util::msg($x->userName);




/////////////// Fetch all Rows
Util::msg('Example 3.2');
$allusers = User::fetchAll();
$i=0;
foreach ($allusers as $a)
{
	Util::msg("$i {$a->userId}:{$a->userName}");
	$i++;
}



/////////////// Fetch a row with particular column value - where condition
Util::msg('Example 3.3');
$co	= User::where("userid=2")->fetch();//should return null if condition not met
//Util::ObjectDump($co);
Util::msg($co->userId);
Util::msg($co->userName);



/////////////// Fetch a row - using named where condition -- using method and then fetch
Util::msg('Example 3.4');
$tn = User::filter('userid','userid=1')->using('userid')->fetch();
//Util::ObjectDump($tn);
Util::msg($tn->userId);
Util::msg($tn->userName);


/////////////// Fetch a row using paremeterised filter !
$tns = User::filter('userid','userid=? and userName=\'?\'')->using('userid',3,'jino')->fetch();
//Util::ObjectDump($tns);


/////////////// Fetch a row using paremeterised filter ! - case username password verification
Util::msg('Example 3.5');
User::filter('userid_pass',"password='?' and userName='?'");
$valid = User::using('userid_pass','passw0rd','thekkamalai')->fetch();
if(isset($valid))
	Util::msg('User Authenticated');
	
// TODO - the above needs an elegant way to tell not to fetch certain columns ! password column here 

////////////// experimental 
//User::findXyz();
//$tt=User::findAlluserId_a_age(1,30);
//$tt=User::anyUserId(1,2);
// sum,count, average,

/////////////// Simpler where condition -- may not work now
Util::msg('Example 3.6');
$tt=User::findUserId(5);
//Util::ObjectDump($tt);
if(isset($tt))
{

	Util::msg($tt->userId);
	Util::msg($tt->userName);
	Util::msg($tt->userFullName);
}
/************************************************************************************************
	
	EXAMPLE : 4,5 (Relationships)
		
		A related table for examples demonstrating relationships - onetoone and onetomany
		
	mysql> desc Avatars;
	+-------------+--------------+------+-----+---------+-------+
	| Field       | Type         | Null | Key | Default | Extra |
	+-------------+--------------+------+-----+---------+-------+
	| userId      | int(11)      | YES  |     | NULL    |       |
	| avatarName  | varchar(200) | YES  |     | NULL    |       |
	| avatarImage | varchar(200) | YES  |     | NULL    |       |
	| isDefault   | int(11)      | YES  |     | NULL    |       |
	+-------------+--------------+------+-----+---------+-------+
	4 rows in set (0.01 sec)

	Create Table: CREATE TABLE `avatars` (
				  `userId` int(11) DEFAULT NULL,
				  `avatarName` varchar(200) DEFAULT NULL,
				  `avatarImage` varchar(200) DEFAULT NULL,
				  `isDefault` int(11) DEFAULT NULL
				) ENGINE=InnoDB DEFAULT CHARSET=latin1
	
	Insert Into avatars Values
		 (1,'Hero','(none)',1) ,
		 (1,'Programmer','(none)',0),
		 (2,'Boss','(none)',1),
		 (2,'Walter','(none)',0),
		 (3,'Joshua','(none)',1);
		 
************************************************************************************************/

Util::msg('Examples: Relationship');

/************************************************************************************************

		Relationships :
			onetomany,onetoone are implemented
			Note one to many and hierarchy are under development and not included in thsi version
		
		To use this in example 
			we use another table - Avatar in conjunction with User Table in the first example
		
************************************************************************************************/
class Avatar extends GModel 
{
	public $userId		= array('int',-1,'for'); ////// type, default value, if it is a foregin key or a primary key!
	public $avatarName	;	////////////// default value empty string/default type - string !
	public $avatarImage	='(none)';
	public $isDefault 	= 0;
}

///////// init Avatar class with table name

Avatar::init('avatars');

//Syntax :
//User::oneToMany('avatars2','Avatar','userId','otherfield - default 1','JoinTable','join field1','join field2 default 1');

//TODO think about coalescing oneto one into the main object and not in a separate object
/// though - need to retain the object/class info for updating

//// Declare the oneToOne Relationship
//// declaration is permanent and effective till end of program
////

User::oneToOne('defaultAvatar','Avatar','userId');
 
$k = User::where('userid=1')->fetch();
//Util::ObjectDump($k);
//Util::ObjectDump($k->defaultAvatar->fields); /////$k->defaultAvatar is the avatar object!
Util::msg($k->toString());
// TODO : a dump to string function to dump all fields !
//Util::msg($k->defaultAvatar);
//Util::ObjectDump($k->defaultAvatar);
//User::oneToMany('class','joincol','join2','x','y');
// need to add shortcut functions 
// TODO single value query and other functions

//////////////////////////// Declare one to many relationship - takes effect here and perssits until program exits

User::oneToMany('avatars','Avatar','userId');
$f = User::where('userid=1')->fetch(1);

//Util::ObjectDump($k);
Util::msg($f->toString());
 
foreach ($f->avatars as $av)
{
	Util::msg($av->avatarName);
}


///////////////////// One to One Save

Util::msg('Example 4');

Util::msg('One To One save/insertion');
$mm = new User();
$mm->userId			= 6;
$mm->userFullName 	= 'Cow Boy';
$mm->password 		= '**chuck**';
$mm->userName		= 'cowboy';

$aa 				= new Avatar();
$aa->avatarName 	= 'Scorpio';
$mm->defaultAvatar 	= $aa;
$mm->save();

//Util::ObjectDump($mm);
//TODO - add strict mode !

///////////////////// One To Many Save
Util::msg('Example 5');
Util::msg('One to Many save/insertion');

$mo 				= new User();
//Util::ObjectDump($mo);

$mo->userId			= 7;
$mo->userFullName 	= 'Water Cooper';
$mo->password		= '**coops**';
$mo->userName		= 'cooper';

$av 				= new Avatar();
$av->avatarName 	= 'soulofJan';

$ab = new Avatar();
$ab->avatarName 	= 'soulofMoses';

$mo->avatars[]	 	= $av;
$mo->avatars[]		= $ab;
//Util::ObjectDump($mo);
Util::msg($mo->toString());
foreach($mo->avatars as $av)
{
	Util::msg($av->toString());
}
$mo->save();

?>
