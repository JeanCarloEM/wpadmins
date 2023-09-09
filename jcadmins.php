<?php

/*
  Plugin Name: JCAdmins
  Description: Adiciona recursos de cristação de páginas de administração que podem ser usados por plugins e temas.
  Version: 1
  Author: Jean Carlo EM
  Author URI: http://jeancarloem.com/
  License: MPL
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . '.admin.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'select.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'thumbs.php';

add_action('admin_enqueue_scripts', function() {
  wp_enqueue_style('jcadmins_css', \plugins_url('jcadmins') . '/assets/css/admin.css');
});
