<?php
$app->get('/', $authenticate, function() use($app, $database) {
	$app->render('painel.twig');
})->name('/');