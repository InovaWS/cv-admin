<?php
$app = new \Slim\Slim();

$app->getLog()->setWriter(new \Slim\Extras\Log\DateTimeFileWriter(array('path' => APPLICATION_DIR . 'logs')));
$app->getLog()->setEnabled(true);

setlocale(LC_ALL, 'pt_BR.UTF-8');
date_default_timezone_set('America/Sao_Paulo');

$app->configureMode('development', function() use($app) {
	ini_set('display_errors', 'on');
});

$app->configureMode('staging', function() use($app) {
	ini_set('display_errors', 'on');
});

$app->configureMode('production', function() use($app) {
	ini_set('display_errors', 'off');
});

// model
$app->configureMode('development', function() use($app) {
	$app->config('app.database.dsn', 'mysql:host=localhost;dbname=centraldoveicu');
	$app->config('app.database.username', 'root');
	$app->config('app.database.password', '');
	$app->config('app.database.driver.options', array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
	$app->config('app.database.logging', true);
});

$app->configureMode('staging', function() use($app) {
	$app->config('app.database.dsn', 'mysql:host=mysql.centraldoveiculo.com.br;dbname=centraldoveicu');
	$app->config('app.database.username', 'centraldoveicu');
	$app->config('app.database.password', 'hesoyam22');
	$app->config('app.database.driver.options', array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
	$app->config('app.database.logging', true);
});

$app->configureMode('production', function() use($app) {});

$session = new \Rio\Model\Session();
$database = new \Rio\Model\DatabaseConnection(array(
	'dsn' => $app->config('app.database.dsn'),
	'username' => $app->config('app.database.username'),
	'password' => $app->config('app.database.password'),
	'driverOptions' => $app->config('app.database.driver.options'),
	'logging' => $app->config('app.database.logging')
));

// view
$app->config('templates.path', APPLICATION_DIR . 'templates');
$app->view(new \Rio\Twig\TwigView());

$app->notFound(function() use($app) {
	$app->render('erros/404.twig');
});

require APPLICATION_DIR . 'routes/login.php';
require APPLICATION_DIR . 'routes/index.php';
require APPLICATION_DIR . 'routes/clientes.php';

return $app;