<?php
$app->get('/clientes(/:tipo(/:p))', function($tipo = 0, $p = 1) use($app, $database) {
	$pesquisa = $app->request()->get('pesquisa');
	$tipos = $database->table('cv2_tipos_vendedores')->orderByAsc('descricao')->findMany();
	
	$query = $database->table('cv2_vendedores')->where('ativo', 1)->orderByAsc('nome');
	
	if ( $tipo )
		$query->where('id_tipo', $tipo);
	
	if ( $pesquisa )
		$query->whereLike('nome', "%$pesquisa%");
	
	$countQuery = clone $query;
	
	$clientes = $query->offset(($p - 1) * 30)->limit(30)->findMany();
	$pCount = ceil($query->count() / 30);
	
	$app->render('clientes/listar.twig', compact('tipo', 'pesquisa', 'tipos', 'clientes', 'p', 'pCount'));
})->name('/clientes');

$capturarPost = function(\Slim\Http\Request $request) {
	return array(
		'nome' => $request->post('nome'),
		'id_tipo' => intval($request->post('tipo')),
		'bloqueado' => intval($request->post('bloqueado')),
		'razao_social' => $request->post('razao_social'),
		'cpf' => $request->post('cpf'),
		'cnpj' => $request->post('cnpj'),
		'telefone_fixo' => $request->post('telefone_fixo'),
		'telefone_celular' => $request->post('telefone_celular'),
		'email' => $request->post('email')
	);
};

$validarDados = function(array $dados) use($database) {
	
	$v = 'Respect\Validation\Validator';
	
	try {
		$validator = $v::arr()
		               ->key('nome', $v::notEmpty())
		               ->key('id_tipo', $v::int()->positive()->callback(function($value) use($database) {
		               	$tipo = $database->table('cv2_tipos_vendedores')->findOne($value);
		               	return !empty($tipo);
		               }))
		               ->key('bloqueado', $v::int()->in(array(0, 1)))
		               ->key('telefone_fixo', $v::notEmpty()->digit()->length(2 + 7, 2 + 9))
		               ->key('telefone_celular', $v::digit()->length(2 + 7, 2 + 9), false)
		               ->key('email', $v::email());
		
		if (isset($dados['id_tipo'])) {
			if (in_array($dados['id_tipo'], array(1)))
				$validator->key('cpf', $v::notEmpty()->cpf());
			elseif (in_array($dados['id_tipo'], array(2, 3)))
				$validator->key('razao_social', $v::notEmpty())->key('cnpj', $v::notEmpty()->cnpj());
		}
		
		$validator->assert($dados);

		return false;
	}
	catch (Respect\Validation\Exceptions\ValidationException $e) {
		return array_filter($e->findMessages(array (
			'nome' => 'você deve declarar o nome do vendedor',
			'id_tipo' => 'você declarou um tipo de vendedor inválido',
			'bloqueado' => 'valor de bloqueio inválido',
			'telefone_fixo' => 'você deve declarar um número válido para o telefone fixo',
			'telefone_celular' => 'você deve declarar um número válido para o telefone celular',
			'email' => 'você deve declarar um e-mail válido',
			'cpf' => 'CPF inválido',
			'razao_social' => 'você deve declarar a razão social do vendedor',
			'cnpj' => 'CNPJ inválido'
		)));
	}
};

$app->map('/cliente/cadastrar', function() use($app, $database, $capturarPost, $validarDados) {
	
	if ( $app->request()->isPost() ) {
		$dados = $capturarPost($app->request());
		$dados['data_cadastro'] = date('Y-m-d H:i:s');
		$dados['ativo'] = 1;
		
		$cliente = $database->table('cv2_vendedores')->create($dados);
		
		$erros = $validarDados($dados);
		
		if ( empty($erros) ) {
			try {
				$cliente->save();
				$app->redirect($app->urlFor('/cliente', array('id' => $cliente->id)));
			}
			catch ( \PDOException $e ) {
				$erros = array('falha ao salvar dados: ' . $e->getMessage());
			}
		}
	}
	else
		$cliente = $database->table('cv2_vendedores')->create();
	
	$tipos = $database->table('cv2_tipos_vendedores')->orderByAsc('id')->findMany();
	
	$app->render('clientes/cadastro.twig', compact('cliente', 'tipos', 'erros'));
})->via('GET', 'POST')->name('/cliente/cadastrar');

$app->get('/cliente/:id', function($id) use($app, $database) {
	$cliente = $database->table('cv2_vendedores')->where('ativo', 1)->orderByAsc('nome')->findOne($id);
	
	if ( !$cliente )
		$app->notFound();
	
	$cliente->tipo = $database->table('cv2_tipos_vendedores')->findOne($cliente->id_tipo)->asObject();
	
	$app->render('clientes/visualizar.twig', compact('cliente'));
})->conditions(array('id' => '\d+'))->name('/cliente');

$app->get('/cliente/:id/veiculos', function($id) use($app, $database) {
	$cliente = $database->table('cv2_vendedores')->where('ativo', 1)->orderByAsc('nome')->findOne($id);
	
	if ( !$cliente )
		$app->notFound();
	
	$veiculos = $database->table('cv2_veiculos_veiculos')->where('ativo', 1)->orderByDesc('id')->findMany();
	
	$app->render('clientes/veiculos.twig', compact('cliente', 'veiculos'));
})->conditions(array('id' => '\d+'))->name('/cliente/veiculos');

$app->get('/cliente/:id/faturas', function($id) use($app, $database) {
	$cliente = $database->table('cv2_vendedores')->where('ativo', 1)->orderByAsc('nome')->findOne($id);
	
	if ( !$cliente )
		$app->notFound();
	
	$veiculos = $database->table('cv2_veiculos_veiculos')->where('ativo', 1)->orderByDesc('id')->findMany();
	
	$app->render('clientes/faturas.twig', compact('cliente', 'faturas'));
})->conditions(array('id' => '\d+'))->name('/cliente/faturas');

$app->map('/cliente/:id/alterar', function($id) use($app, $database) {
	$cliente = $database->table('cv2_vendedores')->where('ativo', 1)->orderByAsc('nome')->findOne($id);
	
	if ( !$cliente )
		$app->notFound();
	
	$tipos = $database->table('cv2_tipos_vendedores')->orderByAsc('id')->findMany();
	$cliente->tipo = $database->table('cv2_tipos_vendedores')->findOne($cliente->id_tipo)->asObject();
	
	$app->render('clientes/alterar.twig', compact('cliente', 'tipos'));
})->via('GET', 'POST')->conditions(array('id' => '\d+'))->name('/cliente/alterar');

$app->map('/cliente/:id/apagar', function($id) use($app, $database) {
	$cliente = $database->table('cv2_vendedores')->where('ativo', 1)->orderByAsc('nome')->findOne($id);

	if ( !$cliente )
		$app->notFound();

	$app->render('clientes/apagar.twig', compact('cliente'));
})->via('GET', 'POST')->conditions(array('id' => '\d+'))->name('/cliente/apagar');