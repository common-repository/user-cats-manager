<?php

/*
  Plugin Name: User-Cats Manager
  Plugin URI: http://dgmike.wordpress.com/user-cats-manager
  Description: Provides to admin users a way to select what categorie determined users can write.
  Version: 2.1
  Author: DGmike
  Author URI: http://dgmike.wordpress.com
*/

class UserCatsManager {
  static $wpdb;
  static $info;

  public static function init() {
    global $wpdb;
    UserCatsManager::$wpdb = $wpdb;
    //Outros mapeamentos
    UserCatsManager::$info['plugin_fpath'] = dirname(__FILE__);
    add_action ('admin_menu', array('UserCatsManager','options'));
    add_action ('load-post.php', array('UserCatsManager','loadpost'));
    add_filter ('get_terms', array ('UserCatsManager', 'filterCats'), 0);
  }
  static function install (){
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    UserCatsManager::$wpdb->query (sprintf('
      CREATE TABLE %suser_cats_manager (
        `user_id` INT NOT NULL,
        `term_id` INT NOT NULL,
        PRIMARY KEY (`user_id`, `term_id`)
      )
    ', UserCatsManager::$wpdb->prefix));
  }
  static function uninstall () {
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    UserCatsManager::$wpdb->query (sprintf ('
      DROP TABLE %suser_cats_manager
    ', UserCatsManager::$wpdb->prefix));
  }
  static function options () {
    add_options_page (__('Categories and Users'), 'Categories And Users', 10, __FILE__, array('UserCatsManager','optionsMenu'));
  }
  static function optionsMenu ($nick = '') {
    if ($_POST['user']) $nick = $_POST['user'];
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    if ($nick) $nick = UserCatsManager::$wpdb->get_results(sprintf('SELECT * FROM %susers WHERE ID=\'%s\'', UserCatsManager::$wpdb->prefix, $nick));
    $tplObj = new FileReader(UserCatsManager::$info['plugin_fpath'] . '/options.html');
    $tpl = $tplObj->read($tplObj->_length);
    $itens = array (
      '{LEGEND}'               => __('Choose the categories that the user can use'),
      '{LEGEND_BM}'            => __('Choose the categories of bookmarks that the user can use'),
      '{ACTION}'               => $_SERVER['REQUEST_URI'],
      '{ALL_CATS}'             => UserCatsManager::allCats($nick[0]->ID),
      '{ALL_CATS_BM}'          => UserCatsManager::allCatsBm($nick[0]->ID),
      '{USERS}'                => UserCatsManager::allUsersSelect(),
      '{NICK}'                 => $nick[0]->ID,
      '{SELECT_USER}'          => __('Select the user <small>(admin users have all access)</small>'),
      '{EDITING}'              => __('Editing user ') . $nick[0]->user_nicename,
      '{EDIT}'                 => __('edit'),
      '{SAVE}'                 => __('Save'),
      '{SAVED}'                => __(''),
      '{MESSAGE_DEFAULT_CATS}' => __('If the user do not chosse a categorie, the wordpress puts the default categorie on post and/or bookmark. Tip: give to your user the privilegies on default categories. For edit the default category, go to <strong>Writing Settings</strong>.')
    );
    if ($_POST['nick']) {
      UserCatsManager::save($_POST['nick'], (array) $_POST['categoria']);
      $itens['{SAVED}'] = __('<div id="message" class="updated fade"><p>The changes has been taked</p></div>');
    }
    $tpl = str_replace("\n", '\n', $tpl);
    if ($nick == '')
      $tpl = preg_replace("/{POST}.*{\/POST}/", '', $tpl);
    $tpl = str_replace('\n', "\n", $tpl);
    $tpl = preg_replace("/{\/?POST}/", '', $tpl);
    foreach ($itens as $key=>$value)
    	$tpl = str_replace ($key, $value, $tpl);
    print $tpl;
  }
  static function getCats($n=0, $user='', $type='category'){
    if ($_POST) $user = $_POST['user'];
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    $wpdb = UserCatsManager::$wpdb;
    $sqlCats = 'SELECT term_id, slug, name FROM %sterms NATURAL JOIN %sterm_taxonomy WHERE taxonomy = \'%s\' AND parent = \'%s\'';
    $cats = $wpdb->get_results(sprintf($sqlCats, $wpdb->prefix, $wpdb->prefix, $type, $n), ARRAY_N);
    $base = '%s<li class="popular-category"><label for="ck_%s"><input id="ck_%s" type="checkbox" name="categoria[]" value="%s"%s  /> %s%s</label></li>';
    $return = array ();
    if (count($cats)) {
      foreach ($cats as $cat){
        $uniq = uniqid();
        $isSelect = $wpdb->get_results(sprintf(
            'SELECT * FROM %suser_cats_manager WHERE user_id = \'%s\' AND term_id = \'%s\'',
            $wpdb->prefix,
            $user,
            $cat[0]
          )
        );
        $checked = count($isSelect) ? ' checked="checked"' : '';
        $default = in_array($cat[0], array(get_option('default_category'), get_option('default_link_category') )) ?
                   __(' <strong>(default)</strong>') :
                   '';
      	$return[] = sprintf ($base, "\n    ", $uniq, $uniq, $cat[0], $checked, $cat[1], $default);
        if (count(UserCatsManager::getCats($cat[0])))
          $return[] = "\n<ul>" . implode ('', UserCatsManager::getCats($cat[0], $user)) . "\n</ul>\n";
      }
    }
    return $return;
  }
  static function allCats($user = '') {
    $return = UserCatsManager::getCats(0, $user);
    return '<ul>'.implode ('', $return).'</ul>';
  }
  static function allCatsBm($user = '') {
    $return = UserCatsManager::getCats(0, $user, 'link_category');
    return '<ul>'.implode ('', $return).'</ul>';
  }
  static function allUsersSelect($user = '') {
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    if ($_POST) $user = $_POST['user'];
    $wpdb = UserCatsManager::$wpdb;
    $res = $wpdb->get_results(sprintf('SELECT id, user_nicename FROM %susers', $wpdb->prefix));
    $base = '<option value="%s"%s>%s</option>';
    $return = '';
    foreach ($res as $value) {
      $usr = new WP_User($value->id);
      if ((int) $usr->user_level === 10) continue;
      $selected = ( preg_match ('/\d+/', $user) && $user == $value->id) ? ' selected="selected"' : '';
      $return .= sprintf($base, $value->id, $selected, $value->user_nicename);
    }
    return $return;
  }
  static function save ($user, $cats) {
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    $wpdb = UserCatsManager::$wpdb;
    $wpdb->query(sprintf('DELETE FROM %suser_cats_manager WHERE user_id = \'%s\'', $wpdb->prefix, $user));
    foreach ($cats as $cat) {
      $sql = sprintf('INSERT INTO %suser_cats_manager VALUES (\'%s\', \'%s\');', $wpdb->prefix, $user, $cat);
    	$wpdb->query($sql);
    }
  }
  static function filterCats($cats) {
    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();
    $wpdb = UserCatsManager::$wpdb;
    $current_user = wp_get_current_user();
    if ($current_user->user_level == 10)
      return $cats;
    if (gettype($cats) != 'array') return $cats;
    foreach ($cats as $key=>$cat) {
      $sql = sprintf('SELECT * FROM %suser_cats_manager WHERE user_id = \'%s\' AND term_id = \'%s\' ', $wpdb->prefix, $current_user->ID, $cat->term_id);
      $catH = $wpdb->get_results($sql);
      if (!count($catH))
        unset ($cats[$key]);
    }
    return $cats;
    //$accepteds = $wpdb->get_results()
  }
  static function set_current_user () {
    global $current_user;
    if ($current_user->user_level == 10) return;
    unset($current_user->allcaps['manage_categories']);
  }
  static function loadpost () {
    if ($_REQUEST['action'] != 'edit' && !isset($_REQUEST['post'])) return;

    global $current_user;
    if ($current_user->user_level == 10) return;

    if ( is_null(UserCatsManager::$wpdb) ) UserCatsManager::init();

    $categories = UserCatsManager::$wpdb->get_results(sprintf(
        'SELECT term_id FROM %suser_cats_manager WHERE user_id = \'%s\'',
        UserCatsManager::$wpdb->prefix,
        $current_user->ID
      ), 'ARRAY_N'
    );
    $validCategories = array();
    foreach ($categories as $cat) $validCategories[] = $cat[0];
    $categories = wp_get_post_categories($_GET['post']);

    if ((bool) array_diff_assoc($categories, $validCategories) === true) {
      $_GET = array();
      $_POST = array();
      $_REQUEST = array();
    }
  }
}

$ucmPluginFile = substr(strrchr(dirname(__FILE__),DIRECTORY_SEPARATOR),1).DIRECTORY_SEPARATOR.basename(__FILE__);
register_activation_hook($ucmPluginFile,array('UserCatsManager','install'));
register_deactivation_hook($ucmPluginFile,array('UserCatsManager','uninstall'));

add_action('set_current_user', array('UserCatsManager','set_current_user'));

add_filter('init', array('UserCatsManager','init'));


?>