<?php
echo "\n=====Testing OO functions=====\n";
class ClassA
{
	public $value=5;
	static $s='A';
	function f($x)
	{
		return $x;
	}
	static function static_call()
	{
		echo "Called class is: ".get_called_class(),PHP_EOL;
		return static::$s;
	}
	static function self_call()
	{
		echo "Called class is: ".get_called_class(),PHP_EOL;
		return self::$s;
	}
}
class ClassB extends classA
{
	public $value=6;
	static $s='B';

	
}
$a=new classA();
$b=new ClassB();

echo "Testing get_class and static binding:\n";
echo $a->value,"=5, 6=",$b->value,PHP_EOL;
echo get_parent_class($b),"=ClassA\n";
echo get_class($b),"=ClassB\n";
echo ClassA::static_call(),"=A\n";
echo ClassA::self_call(),"=A\n";
echo ClassB::static_call(),"=B\n";
echo ClassB::self_call(),"=A\n";
echo PHP_EOL;

echo "Testing method-exists:\n";
echo method_exists($a, 'f'),"=1\n";
echo method_exists($b, 'f'),"=1\n";
echo method_exists(get_class($a), 'f'),"=1\n";
echo method_exists(get_class($b), 'f'),"=1\n";
echo method_exists(get_class($a), 'z')*1,"=0\n";
echo PHP_EOL;

echo "Testing property-exists:\n";
echo property_exists($a, "value"),"=1\n";
echo property_exists($b, "value"),"=1\n";
echo property_exists($b, "valuez")*1,"=0\n";
echo property_exists(get_class($a), "value")*1,"=1\n";
$b->valuez=1;
echo property_exists($b, "valuez")*1,"=1\n";
echo PHP_EOL;

echo "Testing is-a:\n";
echo is_a($a, get_class($a)),"=1\n";
echo is_a($b, get_class($a)),"=1\n";
echo is_a($a, get_class($b))*1,"=0\n";
echo is_a(get_class($b), get_class($a),false)*1,"=0\n";
echo is_a(get_class($b), get_class($a),true)*1,"=1\n";
echo PHP_EOL;

echo "Testing is_subclass_of:\n";
echo is_subclass_of($a, get_class($a))*1,"=0\n";
echo is_subclass_of($b, get_class($a))*1,"=1\n";
echo is_subclass_of(get_class($b), get_class($a),false)*1,"=0\n";
echo is_subclass_of(get_class($b), get_class($a),true)*1,"=1\n";
echo PHP_EOL;
die();

echo "\n=====Testing constant functions=====\n";

define ("a","b");
const c='d';

echo get_defined_constants()['a'],"=b\n";
echo get_defined_constants()['c'],"=d\n";
echo c,"=d\n";
echo a,"=b\n";

const C="D";
define("A","B",false);
echo C,"=D\n";
echo A,"=B\n";

echo defined("A"),defined("a"),defined("C"),defined("c"),"=1111",PHP_EOL;


echo "\n=====Testing output functions=====\n";

echo "Here comes BArray(3) Array(2): ";
print_r(A);
print_r([1,2,3]);
echo print_r([4,5],true),PHP_EOL;


echo "Here comes var_dump of true: ";
var_dump(true);
echo "Here comes var_dump of 3 numbers: ";
var_dump(1,2,3);


printf("Hello %s #%d\n","world","1");

vprintf("Again, hello %s #%d!\n",["world","2"]);

var_export([1,2,3]);
echo "should be the same as: ";
echo var_export([1,2,3],true)," and equal to 0=>1,1=>2,2=>3.",PHP_EOL;

echo "\n=====Testing extract/compact functions=====\n";

$x=1;
$y=2;
$z="3";
echo "Should be x=>1,y=>2 then x=>1,y=.2,z=>'3':\n";

$a=compact("x","y","Q",2.5);
var_dump($a);
$a=compact(["x","y",["z"]]);
var_dump($a);

extract(["a"=>5,"b"=>"10"]);
echo $a,"=5 & 10=",$b,PHP_EOL;

#TODO: more thorough tests for extract? it has many weird flags