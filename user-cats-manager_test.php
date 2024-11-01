<?php

/**
 *  Testes unit�rios para o plugin de user-cats
 */

include ('test.php');

// Definindo os arquivos necessarios
global $current_user;

$pathInfo = pathinfo(__FILE__);
$files = array();
$files[] = '../../../wp-config.php';
$files[] = '../../../wp-includes/wp-db.php';
$files[] = '../../../wp-includes/functions.php';
$files[] = '../../../wp-includes/capabilities.php';
$files[] = '../../../wp-admin/includes/plugin.php';
$files[] = '../../../wp-admin/menu.php';
$files[]  = $pathInfo['dirname'] . '/' . str_replace('_test.php', '.php', $pathInfo['basename']);
$classTest = str_replace(' ', '', ucwords(str_replace('-', ' ', str_replace('_test', '', $pathInfo['filename']))));

// Os arquivos existem
foreach ($files as $file) {
	testCase(true, file_exists($file), 'O arquivo existe');
	include_once ($file);
}

// A classe
testCase(true, in_array($classTest,get_declared_classes()), 'A classe existe');

// Os métodos da classe
$espera = array (
  'init',
  'install',
  'uninstall',
  'options',
  'optionsMenu',
  'getCats',
  'allCats',
  'allCatsBm',
  'allUsersSelect',
  'save',
  'filterCats',
  'set_current_user',
  'loadpost',
);
testCase($espera, get_class_methods($classTest), 'Os metodos da classe condizem');


// DROP table

$wpdb->query(sprintf('DROP TABLE IF EXISTS %suser_cats_manager;', $wpdb->prefix));
$wpdb->query(sprintf ('SHOW TABLES LIKE \'%suser_cats_manager\'', $wpdb->prefix));
testCase($wpdb->num_rows, 0, 'A tabela foi excluída');

// Install

UserCatsManager::install();
$wpdb->query(sprintf ('SHOW TABLES LIKE \'%suser_cats_manager\'', $wpdb->prefix));
testCase($wpdb->num_rows, 1, 'A tabela foi criada');

// Submenu

$current_user = new WP_User(1);
include ('../../../wp-admin/menu.php');
testCase(array ('Categories And Users', 10, 'user-cats-manager/user-cats-manager.php', 'Categories and Users'), end($submenu['options-general.php']), 'Submenu instalado');

// Inserindo os dados
$wpdb->query(sprintf('TRUNCATE TABLE %sterms', $wpdb->prefix));
$wpdb->query(sprintf("INSERT INTO `%sterms` (`name`, `slug`, `term_group`) VALUES ('Uncategorized', 'uncategorized', 0), ('Blogroll', 'blogroll', 0), ('Culinaria', 'culinaria', 0), ('Rapida', 'rapida', 0), ('Miojo', 'miojo', 0), ('Internet', 'internet', 0), ('Moda', 'moda', 0), ('Turismo', 'turismo', 0), ('Gente', 'gente', 0), ('Brancos', 'brancos', 0), ('Negros', 'negros', 0);", $wpdb->prefix) );
$wpdb->query(sprintf('TRUNCATE TABLE %sterm_taxonomy', $wpdb->prefix));
$wpdb->query(sprintf("INSERT INTO `%sterm_taxonomy` (`term_id`, `taxonomy`, `description`, `parent`, `count`) VALUES (1,  'category', '', 0, 7), (2,  'link_category', '', 0, 7), (3,  'category', '', 0, 1), (4,  'category', '', 3, 1), (5,  'category', '', 3, 0), (6,  'category', '', 0, 0), (7,  'category', '', 0, 0), (8,  'category', '', 0, 0), (9,  'category', '', 7, 1), (10, 'category', '', 9, 1), (11, 'category', '', 9, 1);", $wpdb->prefix) );
$wpdb->query(sprintf("INSERT INTO `wodpress_simone`.`wp_user_cats_manager` VALUES ('2', '3'), ('2', '4');", $wpdb->prefix) );

// Está substituindo as {PALAVRAS} por variaveis

ob_start();UserCatsManager::optionsMenu();$return = ob_get_contents();ob_clean();
testCase(false, strpos($return, '{ACTION}'), 'Está substituindo as {PALAVRAS} por variaveis');

// Apenas o form de usuarios

ob_start();UserCatsManager::optionsMenu();$return = ob_get_contents();ob_clean();
testCase(false, strpos($return, 'type="checkbox"'), 'Se não passar o nick, apenas o form de usuarios');

// Categorias no form quando passado o id do nick

ob_start();UserCatsManager::optionsMenu(2);$return = ob_get_contents();ob_clean();
testCase(true, strpos($return, 'type="checkbox"') !== false, 'As categorias fazem parte do formulário?');

// Testando os usuarios

# Admin não entra
# $espera = '<option value="1">admin</option><option value="2">candango</option>';
$espera = '<option value="2">candango</option>';
testCase($espera, UserCatsManager::allUsersSelect(), 'Os usuarios existem');

// Testando o usuario selecionado

# Admin não entra
# $espera = '<option value="1">admin</option><option value="2" selected="selected">candango</option>';
$espera = '<option value="2" selected="selected">candango</option>';
testCase($espera, UserCatsManager::allUsersSelect(2), 'Os usuarios existem');

// Os itens que o usuario pode trabalhar estão selecionados

testCase(true, (bool) strchr(UserCatsManager::allCats(2), 'value="3" checked="checked"'), 'Os itens que o usuario pode trabalhar estão selecionados');
testCase(true, (bool) strpos(UserCatsManager::allCats(2), 'value="4" checked="checked"'), 'Os itens que o usuario pode trabalhar estão selecionados');

// Salvando e limpando as categorias desejadas

UserCatsManager::save(2,array());
testCase(true, strpos(UserCatsManager::allCats('string',2), 'checked="checked"') == false, 'Limpando as categorias desejadas');
UserCatsManager::save(2,array(1,5));
testCase(true, strpos(UserCatsManager::allCats(2), 'value="1" checked="checked"') !== false, 'Salvando as categorias desejadas');
testCase(true, strpos(UserCatsManager::allCats(2), 'value="5" checked="checked"') !== false, 'Salvando as categorias desejadas');

// Testando se todas as categorias foram listadas

$espera = array(array ('1', 'uncategorized', 'Uncategorized'), array ('3', 'culinaria', 'Culinaria'), array ('4', 'rapida', 'Rapida'), array ('5', 'miojo', 'Miojo'), array ('6', 'internet', 'Internet'), array ('7', 'moda', 'Moda'), array ('8', 'turismo', 'Turismo'), array ('9', 'gente', 'Gente'), array ('10', 'brancos', 'Brancos'), array ('11', 'negros', 'Negros'));
foreach ($espera as $item) {
  testCase(true, (bool) strchr(UserCatsManager::allCats('array'), sprintf(' type="checkbox" name="categoria[]" value="%s"  /> %s', $item[0], $item[1])), 'Testando se recebeu todas as categorias: ' . $item[1]);
}

testCase(true, strpos (UserCatsManager::allCats(), 'type="checkbox"') !== false, 'Está gerando os inputs');

// O usuario admin pode tudo, inclusive mecher com as categorias
UserCatsManager::set_current_user();
testCase(true, $current_user->allcaps['manage_categories'], 'O usuario admin pode tudo, inclusive mecher com as categorias');

// O submenu não pode aparecer para quem não for administrador

$current_user = new WP_User(2);
include ('../../../wp-admin/menu.php');
testCase(null, $submenu['options-general.php'], 'Submenu não pode aparecer para o candango');

// O filtro de categorias só demonstra o que elegermos para ele

$entradaTmp = array (
  array (
    'term_id'          => 3,
    'name'             => 'Culinaria',
    'slug'             => 'culinaria',
    'term_group'       => 0,
    'term_taxonomy_id' => 3,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 0,
    'count'            => 1,
  ), array (
    'term_id'          => 5,
    'name'             => 'Miojo',
    'slug'             => 'miojo',
    'term_group'       => 0,
    'term_taxonomy_id' => 3,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 3,
    'count'            => 1,
  ), array (
    'term_id'          => 4,
    'name'             => 'Rapida',
    'slug'             => 'rapida',
    'term_group'       => 0,
    'term_taxonomy_id' => 4,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 3,
    'count'            => 1,
  ), array (
    'term_id'          => 6,
    'name'             => 'Internet',
    'slug'             => 'internet',
    'term_group'       => 0,
    'term_taxonomy_id' => 6,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 0,
    'count'            => 0,
  ), array (
    'term_id'          => 7,
    'name'             => 'Moda',
    'slug'             => 'moda',
    'term_group'       => 0,
    'term_taxonomy_id' => 7,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 0,
    'count'            => 0,
  ), array (
    'term_id'          => 8,
    'name'             => 'Turismo',
    'slug'             => 'turismo',
    'term_group'       => 0,
    'term_taxonomy_id' => 8,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 0,
    'count'            => 0,
  ), array (
    'term_id'          => 1,
    'name'             => 'Uncategorized',
    'slug'             => 'uncategorized',
    'term_group'       => 0,
    'term_taxonomy_id' => 1,
    'taxonomy'         => 'category',
    'description'      => '',
    'parent'           => 0,
    'count'            => 7,
  ),
);

$entrada = $saida = array();

foreach ($entradaTmp as $id => $inTmp) {
  $entrada[$id] = new stdClass();
	foreach ($inTmp as $item => $value)
		$entrada[$id]->$item = $value;
	if (in_array($id, array (1,6)))
	  $saida[$id] = $entrada[$id];
}

testCase($saida, UserCatsManager::filterCats($entrada), 'O filtro de categorias só demonstra o que elegermos para ele');

// Qualquer outro usuário não pode alterar as categorias
UserCatsManager::set_current_user();
testCase(null, $current_user->allcaps['manage_categories'], 'Qualquer outro usuário não pode alterar as categorias');

UserCatsManager::uninstall();
$wpdb->query(sprintf ('SHOW TABLES LIKE \'%suser_cats_manager\'', $wpdb->prefix));

testCase($wpdb->num_rows, 0, 'A tabela foi exclu�da');

testResume();
?>