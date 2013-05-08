<?php
namespace Rio\Twig;

class TwigExtension extends \Twig_Extension
{
	public function getName()
	{
		return 'Rio\Twig\TwigExtension';
	}

	public function getFunctions()
	{
		return array(
			'url' => new \Twig_Function_Method($this, 'url'),
			'currentPath' => new \Twig_Function_Method($this, 'currentPath'),
			'currentUrl' => new \Twig_Function_Method($this, 'currentUrl'),
			'currentRouteName' => new \Twig_Function_Method($this, 'currentRouteName'),
			'referer' => new \Twig_Function_Method($this, 'referer'),
			'coalesce' => new \Twig_Function_Method($this, 'coalesce')
		);
	}
	
	public function getFilters()
	{
		return array(
			'repeat' => new \Twig_Filter_Method($this, 'repeat')
		);
	}

	public function url($uri, array $params = null, $complete = false, $appName = 'default')
	{
		$app = \Slim\Slim::getInstance($appName);
		
		if ($app->router()->hasNamedRoute($uri))
			$uri = $app->urlFor($uri, $params === null ? array() :  $params);
		elseif (is_file(INVOKER_DIR . $uri))
			$uri = $app->request()->getRootUri() . $uri;
		elseif ($app->config('templates.validate'))
			throw new \RuntimeException('resource not found: ' . $uri);
		else
			$uri = $app->request()->getRootUri() . $uri;
		
		if ($complete)
			$uri = $app->request()->getScheme() . '://' . $app->request()->getHost() . $uri;
		
		return $uri;
	}
	
	public function currentPath($appName = 'default')
	{
		$app = \Slim\Slim::getInstance($appName);
		$request = $app->request();
		
		return $request->getPath();
	}
	
	public function currentUrl($appName = 'default')
	{
		$app = \Slim\Slim::getInstance($appName);
		$request = $app->request();
	
		return $request->getUrl();
	}
	
	public function currentRouteName($appName = 'default')
	{
		$app = \Slim\Slim::getInstance($appName);
		$router = $app->router();
		
		return $router->getCurrentRoute()->getName();
	}
	
	public function referer($appName = 'default')
	{
		$app = \Slim\Slim::getInstance($appName);
		$request = $app->request();
		
		return $request->getReferer();
	}
	
	public function repeat($value, $times) {
		return str_repeat($value, $times);
	}
	
	public function coalesce()
	{
		foreach (func_get_args() as $arg) {
			if ($arg)
				return $arg;
		}
	}
}