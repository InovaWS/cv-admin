<?php
namespace Rio\Twig;

use Slim\View;

class TwigView extends View
{
	private $environment = null;
	private $templateDirs = array();
	private $options = array();
	private $extensions = array();
	
	public function __construct($cachePath = null)
	{
		$this->options = array(
			'charset' => 'utf-8',
			'cache' => $cachePath ? $cachePath : \Slim\Slim::getInstance()->config('templates.cache.path'),
			'auto_reload' => true,
			'strict_variables' => false,
			'autoescape' => true
		);
		$this->extensions = array(
			new \Twig_Extension_Slim(),
			new TwigExtension()
		);
	}
	
	public function getTemplateDirs()
	{
		if (empty($this->templateDirs)) {
			return array($this->getTemplatesDirectory());
		}
		return $this->templateDirs;
	}
	
	public function setTemplateDirs(array $dirs = array())
	{
		$this->templateDirs = $dirs;
	}
	
	public function getOptions()
	{
		return $this->options;
	}
	
	public function setOptions(array $options = array())
	{
		$this->options = $options;
	}
	
	public function appendOptions(array $options = array())
	{
		$this->options = array_merge($this->options, $options);
	}
	
	public function getExtensions()
	{
		return $this->extensions;
	}
	
	public function setExtensions(array $extensions = array())
	{
		$this->extensions = $extensions;
	}
	
	public function appendExtensions(array $extensions = array())
	{
		$this->extensions = array_merge($this->extensions, $extensions);
	}
	
	public function fetch($template)
	{
		$env = $this->getEnvironment();
		$template = $env->loadTemplate($template);
		
		return $template->render($this->data);
	}
	
	public function display($template)
	{
		echo $this->fetch($template);
	}
		
	public function getEnvironment()
	{
		if (!$this->environment) {
			$loader = new \Twig_Loader_Filesystem($this->getTemplateDirs());
			$this->environment = new \Twig_Environment(
				$loader,
				$this->options
			);
	
			foreach ($this->extensions as $ext) {
				$extension = is_object($ext) ? $ext : new $ext;
				$this->environment->addExtension($extension);
			}
		}
	
		return $this->environment;
	}
}