<?php
$initialize = function() {

if (!isset($_ENV['SLIM_MODE'])) {
	$name = 'SLIM_MODE';
	while ( isset($_SERVER["REDIRECT_$name"]) )
		$name = "REDIRECT_$name";
	
	$_ENV['SLIM_MODE'] = isset($_SERVER[$name]) ? $_SERVER[$name] : 'production';
}

define('INVOKER_DIR', rtrim(getcwd(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
define('PROTECTED_DIR', rtrim(realpath(__DIR__ . '/protected'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
	
set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
}, E_ALL | E_STRICT);
	
set_exception_handler(function($exception) {
	header('HTTP/1.1 500 Internal Server Error', true, 500);
	header('Content-Type: text/html; charset=UTF-8');
	
	if ($_ENV['SLIM_MODE'] == 'production') {
		$title = 'Error';
		$body = '<p>A website error has occured. The website administrator has been notified of the issue. Sorry for the temporary inconvenience.</p>';
	}
	else {
		$title = 'Application Error';
		$code = $exception->getCode();
		$message = $exception->getMessage();
		$file = $exception->getFile();
		$line = $exception->getLine();
		$trace = $exception->getTraceAsString();
		$body = '<p>The application could not run because of the following error:</p>';
		$body .= '<h2>Details</h2>';
		$body .= sprintf('<div><strong>Type:</strong> %s</div>', get_class($exception));
		if ($code)
			$body .= sprintf('<div><strong>Code:</strong> %s</div>', $code);
		if ($message)
			$body .= sprintf('<div><strong>Message:</strong> %s</div>', $message);
		if ($file)
			$body .= sprintf('<div><strong>File:</strong> %s</div>', $file);
		if ($line)
			$body .= sprintf('<div><strong>Line:</strong> %s</div>', $line);
		if ($trace) {
			$body .= '<h2>Trace</h2>';
			$body .= sprintf('<pre>%s</pre>', $trace);
		}
	}
	
	printf("<html><head><title>%s</title><style>body{margin:0;padding:30px;font:12px/1.5 Helvetica,Arial,Verdana,sans-serif;}h1{margin:0;font-size:48px;font-weight:normal;line-height:48px;}strong{display:inline-block;width:65px;}</style></head><body><h1>%s</h1>%s</body></html>", $title, $title, $body);
});
	
spl_autoload_register(function($classname) {
	$classpath = PROTECTED_DIR . 'classes' . DIRECTORY_SEPARATOR . ltrim(str_replace(array('\\', '_'), DIRECTORY_SEPARATOR, $classname), DIRECTORY_SEPARATOR) . '.php';
	if (is_readable($classpath))
		require $classpath;
});
	
set_include_path(implode(PATH_SEPARATOR, array_unique(array(INVOKER_DIR, PROTECTED_DIR))));
		
define('APPLICATION_DIR', PROTECTED_DIR);
define('APPLICATION_PATH', APPLICATION_DIR . 'app.php');

if (!is_readable(APPLICATION_PATH))
	trigger_error('application file not found: ' . APPLICATION_PATH);

$app = function() {
	return (require APPLICATION_PATH);
};
$app = $app();
	
if (!($app instanceof \Slim\Slim))
	trigger_error('not-Slim application loaded from ' . APPLICATION_PATH);

$app->run();
};

$initialize();
unset($initialize);