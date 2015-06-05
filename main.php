<?php
require_once __DIR__."/PHP-Parser/lib/bootstrap.php";
use PhpParser\Node;

class Emulator
{	
	static $infinite_loop=20; #1000000;
	protected $last_node,$last_file;
	public $output;
	public $variables=[]; #TODO: make this a class, so that it can lookup magic variables, an retain them on push/pop
	public $functions=[];
	public $parser;
	public $variable_stack=[];
	function error_handler($errno, $errstr, $errfile, $errline)
	{
		$file=$errfile;
		$line=$errline;;
		if (isset($this->last_file)) $file=$this->last_file;
		if (isset($this->last_node)) $line=$this->last_node->getLine();
		$fatal=false;
		switch($errno) //http://php.net/manual/en/errorfunc.constants.php
		{
			case E_ERROR:
				$fatal=true;
				$str="Error";
				break;
			case E_WARNING:
				$str="Warning";
				break;
			default:
				$str="Error";
		}
		echo "PHP-Emul {$str}:  {$errstr} in {$file} on line {$line}",PHP_EOL;
		// if ($fatal) die();
		return true;
	}
	function error($msg)
	{
		trigger_error($msg);
	}
	function warning($msg)
	{
		trigger_error($msg);
	}
	function output_array(array $args)
	{
		$this->output.=implode("",$args);
	}
	function output()
	{
		$args=func_get_args();
		$this->output.=implode("",$args);
	}
	protected function push()
	{
		array_push($this->variable_stack, $this->variables);
		$this->variables=[];
	}
	protected function pop()
	{
		$this->variables=array_pop($this->variable_stack);
	}
	protected function run_function($name,$args)
	{
		$this->push();
		$function=$this->functions[$name];
		// if (count($function['params'])!=count($args))
			// $this->error("{$name} expects ".count($function['params'])." arguments but received ".count($args));
		reset($args);
		$count=count($args);
		$index=0;
		foreach ($function['params'] as $param)
		{
			$this->variables[$param->name]=current($args);
			if ($index>=$count) //all args consumed, either defaults or error
			{
				if (isset($param->default))
					$this->variables[$param->name]=$this->evaluate_expression($param->default);
				else
				{

					$this->warning("Missing argument ".($index+1)." for {$name}()");
					return null;
				}

			}
			else
			{
				next($args);
			}
			$index++;
		}
		$res=$this->run_code($function['code']);

		$this->pop();
		return $res;
	}
	protected function evaluate_expression_array(array $ast)
	{
		$out=[];
		foreach ($ast as $element)
			$out[]=$this->evaluate_expression($element);
		return $out;
	}
	protected function evaluate_expression($ast)
	{
		$node=$ast;
		$this->last_node=$node;
		if (false);
		elseif (is_array($node))
			die("array node!");
		elseif ($node instanceof Node\Expr\FuncCall)
		{
			$name=$this->name($node->name);
			$args=[];
			foreach ($node->args as $arg)
				$args[]=$this->evaluate_expression($arg->value);
			if (isset($this->functions[$name]))
				return $this->run_function($name,$args); //user function
			else
			{
				#FIXME: handle critical internal functions (e.g function_exists, ob_start, etc.)
				ob_start();	
				$ret=call_user_func_array($name,$args); //core function
				$output=ob_get_clean();
				$this->output($output);
				return $ret;
			}
		}
		elseif ($node instanceof Node\Expr\Assign)
		{
			// print_r($node);
			if ($node->var instanceof Node\Expr\Variable)	
				return $this->variables[$this->name($node->var->name)]=$this->evaluate_expression($node->expr);	
			elseif ($node->var instanceof Node\Expr\List_) //list(x,y)=f()
			{
				$resArray=$this->evaluate_expression($node->expr);
				if (count($resArray)!=count($node->var->vars))
					$this->warning("list() used but number of arguments do not match");
				reset($resArray);
				foreach ($node->var->vars as $var)
				{
					if ($var instanceof Node\Expr\Variable)
					{
						$this->variables[$this->name($var->name)]=current($resArray);
						next($resArray);
					}
				}
			}
			elseif ($node->var instanceof Node\Expr\ArrayDimFetch) //$x[...][...]=...
			{
				$t=$node->var;
				$dim=0;
				$indexes=[];
				//each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
				while ($t instanceof Node\Expr\ArrayDimFetch)
				{
					$dim++;
					$indexes[]=$this->evaluate_expression($t->dim);
					$t=$t->var;
				}
				$indexes=array_reverse($indexes);
				$varName=$this->name($t);

				$base=&$this->variables[$varName];
				foreach ($indexes as $index)
					$base=&$base[$index];
				$base=$this->evaluate_expression($node->expr);
				// $this->variables[$this->name($node->var->var->name)][$this->name($node->var->dim)]=$this->evaluate_expression($node->expr);
			}
			else
			{
				echo "Unknown assign: ";
				print_r($node);
			}
		}
		elseif ($node instanceof Node\Expr\ArrayDimFetch) //access multidimensional arrays $x[...][..][...]
		{
			$t=$node;
			$dim=0;
			$indexes=[];
			//each ArrayDimFetch has a var and a dim. var can either be a variable, or another ArrayDimFetch
			while ($t instanceof Node\Expr\ArrayDimFetch)
			{
				$dim++;
				$indexes[]=$this->evaluate_expression($t->dim);
				$t=$t->var;
			}
			$indexes=array_reverse($indexes);
			$varName=$this->name($t);
			if (!isset($this->variables[$varName]))
				$this->warning("Variable '\$$varName' not defined");
			$base=&$this->variables[$varName];
			foreach ($indexes as $index)
			{
				if (!isset($base[$index]))	
					$this->warning("Undefined array index {$index} in '\$$varName'");
				$base=&$base[$index];
			}
			return $base;
			// $base=$this->evaluate_expression($node->expr);


		}
		elseif ($node instanceof Node\Expr\Array_)
		{
			$out=[];
			foreach ($node->items as $item)
				if (isset($item->key))
					$out[$this->evaluate_expression($item->key)]=$this->evaluate_expression($item->value);
				else
					$out[]=$this->evaluate_expression($item->value);
			return $out;
		}
		elseif ($node instanceof Node\Expr\Cast)
		{
			if ($node instanceof Node\Expr\Cast\Int_)
				return (int)$this->evaluate_expression($node->expr);
			else
			{
				echo "Unknown cast: ";
				print_r($node);
			}
		}
		elseif ($node instanceof Node\Expr\BooleanNot)
			return !$this->evaluate_expression($node->expr);
		
		elseif ($node instanceof Node\Expr\PreInc)
			return ++$this->variables[$this->name($node->var)];
		elseif ($node instanceof Node\Expr\PostInc)
			return $this->variables[$this->name($node->var)]++;
		elseif ($node instanceof Node\Expr\PreDec)
			return --$this->variables[$this->name($node->var)];
		elseif ($node instanceof Node\Expr\PostDec)
			return $this->variables[$this->name($node->var)]--;

		elseif ($node instanceof Node\Expr\AssignOp)
		{
			if ($node instanceof Node\Expr\AssignOp\Plus)
				return $this->variables[$this->name($node->var)]+=$this->evaluate_expression($node->expr);
		}
		elseif ($node instanceof Node\Expr\BinaryOp)
		{
			if ($node instanceof Node\Expr\BinaryOp\Plus)
				return $this->evaluate_expression($node->left)+$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Div)
				return $this->evaluate_expression($node->left)/$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Minus)
				return $this->evaluate_expression($node->left)-$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Mul)
				return $this->evaluate_expression($node->left)*$this->evaluate_expression($node->right);
			
			elseif ($node instanceof Node\Expr\BinaryOp\Equal)
				return $this->evaluate_expression($node->left)==$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\NotEqual)
				return $this->evaluate_expression($node->left)!=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Smaller)
				return $this->evaluate_expression($node->left)<$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\SmallerOrEqual)
				return $this->evaluate_expression($node->left)<=$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\Greater)
				return $this->evaluate_expression($node->left)>$this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\GreaterOrEqual)
				return $this->evaluate_expression($node->left)>=$this->evaluate_expression($node->right);
			
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalAnd)
				return $this->evaluate_expression($node->left) and $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalOr)
				return $this->evaluate_expression($node->left) or $this->evaluate_expression($node->right);
			elseif ($node instanceof Node\Expr\BinaryOp\LogicalXor)
				return $this->evaluate_expression($node->left) xor $this->evaluate_expression($node->right);

			elseif ($node instanceof Node\Expr\BinaryOp\Concat)
				return $this->evaluate_expression($node->left).$this->evaluate_expression($node->right);

			else
			{
				echo "Unknown binary op: ";
				print_r($node);
			}
		}
		elseif ($node instanceof Node\Scalar)
		{
			if ($node instanceof Node\Scalar\String)
				return $node->value;
			elseif ($node instanceof Node\Scalar\DNumber)
				return $node->value;
			elseif ($node instanceof Node\Scalar\LNumber)
				return $node->value;
			elseif ($node instanceof Node\Scalar\Encapsed)
			{
				$res="";
				foreach ($node->parts as $part)	
					if (is_string($part))
						$res.=$part;
					else
						$res.=$this->evaluate_expression($part);

				return $res;
			}
			else
			{
				echo "Unknown scalar node: ";
				print_r($node);
			}
		}
		// elseif ($node instanceof Node\Expr\ArrayItem); //this is handled in Array_ implicitly
		
		elseif ($node instanceof Node\Expr\Variable)
		{
			if (array_key_exists($this->name($node), $this->variables))
				return $this->variables[$node->name];
			else
			{

				print_r($this->variables);
				$this->error("Undefined variable {$node->name}");
			}
		}
		elseif ($node instanceof Node\Expr\ConstFetch)
		{
			$name=$this->name($node->name);
			if (defined($name))
				return constant($name);
			else
				$this->error("Undefined constant {$name}");

		}
		elseif ($node instanceof Node\Expr\ErrorSuppress)
		{
			#TODO: error handling
			return $this->evaluate_expression($node->expr);
			// print_r($node);
		} 
		elseif ($node instanceof Node\Expr\Exit_)
			return $this->evaluate_expression($node->expr);
		else
		{
			echo "Unknown expression node: ",
			print_r($node);
		}
		return null;
	}
	protected function name($ast)
	{
		if (is_string($ast))
			return $ast;
		elseif ($ast instanceof Node\Scalar)
			return $ast->value;
		elseif ($ast instanceof Node\Expr\Variable)
			return $ast->name;
		$res="";
		// print_r($ast);
		foreach ($ast->parts as $part)
		{
			if (is_string($part))
				$res.=$part;
			else
				$res.=$this->evaluate_expression($part);
		}

		return $res;		
	}
	public function run_file($file)
	{
		$this->last_file=realpath($file);
		$code=file_get_contents($file);
		$ast=$this->parser->parse($code);
		return $this->run_code($ast);
	}
	
	function __construct()
	{
		$this->parser = new PhpParser\Parser(new PhpParser\Lexer);
		// $this->traverser     = new PhpParser\NodeTraverser;
    	// $this->traverser->addVisitor(new LiteralExplodeDetector);
    	$this->init();
	}
	function init()
	{
		foreach ($GLOBALS as $k=>$v)
		{
			if ($k=="GLOBALS") continue;
			$this->variables[$k]=$v;
		}
		// $this->variables['_POST']=isset($_POST)?$_POST:array();

	}
	function start($file)
	{
		set_error_handler(array($this,"error_handler"));
		$res=$this->run_file($file);
		restore_error_handler();

		return $res;
	}
	protected function run_code($ast)
	{
		foreach ($ast as $node)
		{
			// echo get_class($node),PHP_EOL;
			if ($node instanceof Node\Stmt\Echo_)
				$this->output_array($this->evaluate_expression_array($node->exprs));
			elseif ($node instanceof Node\Stmt\If_)
			{
				$done=false;
				if ($this->evaluate_expression($node->cond))
				{
					$done=true;
					$this->run_code($node->stmts);
				}
				else
				{
					if (is_array($node->elseifs))
						foreach ($node->elseifs as $elseif)
						{
							if ($this->evaluate_expression($elseif->cond))
							{
								$done=true;
								$this->run_code($elseif->stmts);
								break;
							}
						}
					if (!$done and isset($node->else))
						$this->run_code($node->else->stmts);
				}
			}
			elseif ($node instanceof Node\Stmt\Function_)
			{
				$name=$this->name($node->name);
				$this->functions[$name]=array("params"=>$node->params,"code"=>$node->stmts);
				// print_r($node);
			}
			elseif ($node instanceof Node\Stmt\Return_)
				return $this->evaluate_expression($node->expr);
			elseif ($node instanceof Node\Stmt\For_)
			{
				$i=0;
				for ($this->run_code($node->init);$this->evaluate_expression($node->cond[0]);$this->run_code($node->loop))
				{
					$i++;	
					$this->run_code($node->stmts);
					if ($i>self::$infinite_loop)
					{
						$this->error("Infinite loop");
						break;
					}
				}

			}
			elseif ($node instanceof Node\Stmt\While_)
			{
				$i=0;
				while ($this->evaluate_expression($node->cond))
				{
					$this->run_code($node->stmts);
					$i++;
					if ($i>self::$infinite_loop)
					{
						$this->error("Infinite loop");
						break;
					}
				}
			}
			elseif ($node instanceof Node\Stmt\Do_)
			{
				$i=0;
				do
				{
					$this->run_code($node->stmts);
					$i++;
					if ($i>self::$infinite_loop)
					{
						$this->error("Infinite loop");
						break;
					}
				}
				while ($this->evaluate_expression($node->cond));
			}
			elseif ($node instanceof Node\Stmt\Foreach_)
			{
				// print_r($node);
				$list=$this->evaluate_expression($node->expr);
				if (isset($node->keyVar))
					$keyVar=$this->name($node->keyVar->name);
				$valueVar=$this->name($node->valueVar->name);
				// $keyVarExists=false;
				// $valueVarExists=false;
				// if (isset($this->variables[$keyVar]))
				// 	$keyVarExists=true;
				// if (isset($this->variables[$valueVar]))
				// 	$valueVarExists=true;
				foreach ($list as $k=>$v)
				{
					if (isset($keyVar))
						$this->variables[$keyVar]=$k;
					$this->variables[$valueVar]=$v;
					$this->run_code($node->stmts);
				}
				// if (!$valueVarExists)
				// 	unset($this->variables[$valueVar]);
				// if (!$keyVarExists)
				// 	unset($this->variables[$keyVar]);
			}
			elseif ($node instanceof Node\Expr\Exit_)
				return $this->evaluate_expression($node);
			elseif ($node instanceof Node\Expr)
				$this->evaluate_expression($node);
			else
			{
				echo "Unknown node type: ";	
				print_r($node);
			}


		}
	}	
}




















$_GET['url']='http://abiusx.com/blog/wp-content/themes/nano2/images/banner.jpg';
$x=new Emulator;
$x->start("sample.php");
var_dump($x->output);
echo PHP_EOL,"### Variables ###",PHP_EOL;
var_dump($x->variables);