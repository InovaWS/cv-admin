<?php
$authenticate = function() use($app, $database, $session) {
	if ( $session->usuario )
		$usuario = $database->table('cv2_usuarios')->where('ativo', 1)->where('id_grupos_usuarios', 1)->findOne($session->usuario);
	else
		$usuario = null;
	
	if ( $usuario ) {
		$app->view()->appendData(array('usuario_logado' => $usuario));
	}
	else
		$app->redirect($app->urlFor('/login'));
};

$app->map('/login', function() use($app, $database, $session) {
	
	if ( $app->request()->isPost() ) {
		
		$usuario = $database->table('cv2_usuarios')->where('ativo', 1)->where('id_grupos_usuarios', 1)
		->where('usuario', $app->request()->post('usuario'))->where('senha', $app->request()->post('senha'))
		->findOne();
		
		if ( !$usuario )
			$app->flashNow('nao_encontrado', true);
		else {
			$session->usuario = $usuario->id;
			$app->redirect($app->urlFor('/'));
		}
	}
	
	$app->render('login.twig', array('usuario' => $app->request()->post('usuario')));
})->via('GET', 'POST')->name('/login');

$app->get('/logout', $authenticate, function() use($app, $session) {
	unset($session->usuario);
	$app->redirect($app->urlFor('/login'));
})->name('/logout');