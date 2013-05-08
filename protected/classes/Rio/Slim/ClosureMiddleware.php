<?php
namespace Rio\Slim;

use Slim\Middleware;

class ClosureMiddleware extends Middleware
{
	private $closure;

	public function __construct(\Closure $closure)
	{
		$this->closure = $closure;
	}
	
	public function call()
	{
		return call_user_func_array($this->closure, array_merge(array($this), func_get_args()));
	}

}