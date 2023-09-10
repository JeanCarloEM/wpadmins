<?php

/*
  Plugin Name: Multiple Post Thumbnails Editor
  Plugin URI: https://lab.jeancarloem.com/wordpress/plugins/multiple-post-thumbnails-editor/
  Description: Adds the ability to add multiple post thumbnails to a post type.
  Version: 1
  Author: Jean Carlo EM
  Author URI: http://jeancarloem.com/
  License: MPL
 */

namespace jeancarloem\Wordpress\plugins\MPTEditor;

use jeancarloem\Wordpress\plugins\MPTEditor as mpte;

if (!class_exists('MPTEditor')) {
  if (is_admin()) {
    add_action('admin_menu', ["jeancarloem\Wordpress\plugins\MPTEditor\MPTEditor", 'mpte_plugin_admin']);
    add_action('network_admin_menu', ["jeancarloem\Wordpress\plugins\MPTEditor\MPTEditor", 'mpte_plugin_admin']);
  }

  add_action('wp_loaded', ["jeancarloem\Wordpress\plugins\MPTEditor\MPTEditor", 'mpte_plugin_start']);

  # DESINSTALADOR
  register_uninstall_hook(__FILE__, ["jeancarloem\Wordpress\plugins\MPTEditor\MPTEditor", 'uninstall']);

  /*
   *
   */

  abstract class MPTEditor {

    static public $registeredTypes = false, $actualPostTypes = false;

    const PREFIX = "mpteditor", registeredTypeField = "mpteditor__registered_types", thumb_identy = self::PREFIX . '_thumbnail_';

    /*
     *
     */

    static function mpte_plugin_start() {
      foreach (\get_post_types('', 'names') as $name) {
        if (!in_array($name, ['attachment', 'revision', 'nav_menu_item'])) {
          $fname = self::PREFIX . "_" . self::toNome($name);

          if (esc_attr(get_option($fname)) > 1) {
            for ($i = 0; $i < esc_attr(get_option($fname)); $i++) {
              new \MultiPostThumbnails(array(
                  'label' => _("Thumbnail") . " " . ($i + 1),
                  'id' => self::thumb_identy . $i,
                  'post_type' => $name
              ));
            }
          }
        }
      }
    }

    static function getThumbs(int $post_id = null, $detailed = false): array {
      if (empty($post_id)) {
        global $post;
        $post_id = $post->ID;
        $post_type = \get_post_type($post_id);
      }

      $links = [];
      $r = [];

      if (\has_post_thumbnail()) {
        $links[] = trim(\wp_get_attachment_image_src(\get_post_thumbnail_id($post_id), 'single-post-thumbnail')[0]);
        $r[] = [
            $links[0],
            \wp_get_attachment_metadata(\get_post_thumbnail_id($post_id))
        ];
      }

      if (!in_array($post_type, ['attachment', 'revision', 'nav_menu_item'])) {
        $qtd = esc_attr(get_option(self::PREFIX . "_" . self::toNome($post_type)));

        for ($i = 0; $i < $qtd; $i++) {
          $item = trim(\MultiPostThumbnails::get_post_thumbnail_url($post_type, self::thumb_identy . $i));

          /* IMPEDE A ADICAO DUPLICADA */
          if (!in_array($item, $links)) {
            $links[] = $item;

            if ($detailed) {
              $item = [
                  $item,
                  \wp_get_attachment_metadata(\MultiPostThumbnails::get_post_thumbnail_id($post_type, self::thumb_identy . $i, $post_id))
              ];
            }

            if (((!empty($item)) && !$detailed) || ((!empty($item[0])) && $detailed)) {
              $r[] = $item;
            }
          }
        }
      }

      return $r;
    }

    /*
     *
     */

    static function toNome($str) {
      return \strtolower(\preg_replace('|[ ]+|i', '_', $str));
    }

    /*
     *
     */

    static function mpte_plugin_admin() {
      # REGISTRA O SUBMENU
      \add_options_page("Multiple Post Thumbnail Editor", "Multiple Post Thumbnail Editor", 'administrator', 'mpte_plugin_admin_page', [__CLASS__, 'mpte_plugin_admin_page']);

      # REGISTRA AS CONFIGURACOES
      \add_action('admin_init', [__CLASS__, 'mpte_plugin_admin_settings']);

      # ADICIONA CSS PARA AREA DE ADMIN
      \add_action('admin_head', [__CLASS__, 'mpte_plugin_admin_head']);
    }

    /*
     *
     */

    static function mpte_plugin_admin_head() {
      echo "<link rel='stylesheet' href='" . plugin_dir_url(__FILE__) . "assets/css/mpteadmin.css' type='text/css' media='all' />";
    }

    /*
     *
     */

    static function mpte_plugin_admin_settings() {
      # CAMPO QUE GARDARA QUAIS A CONFIGURACOES SETTINGS FORAM REGISTRADAS
      \register_setting(self::PREFIX, self::registeredTypeField);

      # OBTEM OS CUSTOM POSTS PARA OS QUAIS REGISTRAMOS UMA SETTING
      self::$registeredTypes = \json_decode(\get_option(self::registeredTypeField, '[]'), true);

      # OBTEM OS POST TYPE ATUAIS
      self::$actualPostTypes = \get_post_types('', 'names');
      \sort(self::$actualPostTypes);

      # PARA CADA POST TYPE ATUAL REGISTRA UMA SETTING, PARA GUARDAR
      # O THUMBNAIL MAXIMO
      foreach (self::$actualPostTypes as $name) {
        if (!in_array($name, ['attachment', 'revision', 'nav_menu_item'])) {
          \register_setting(self::PREFIX, self::PREFIX . "_" . self::toNome($name), ["type" => "integer"]);

          #echo "<br />>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>## [". get_option($f, 55) . "][". get_option("$f-5", -1) . "]"  ;
        }
      }

      /*
       * OS POSTS TYPES QUE PORVENTURA NAO EXISTEM MAIS, DEVEM TER SUA SETTING
       * DE THUMBS MAXIMO EXCLUIDA
       */
      if (is_array(self::$registeredTypes)) {
        foreach (self::$registeredTypes as $name) {
          /* SE ELE NAO EXISTIR MAIS, NOS ATUAIS, ELIMINAMOS A CONFIGURACAO */
          if (!in_array($name, self::$actualPostTypes)) {
            \unregister_setting(self::PREFIX, self::PREFIX . "_" . self::toNome($name));
          }
        }
      }

      self::$registeredTypes = json_encode(self::$actualPostTypes);
    }

    /*
     *
     */

    static function uninstall() {
      if (\is_multisite()) {
        /*
         * PERCORRE CADA UM DOS SITES E EXCLUI INDIVIDUALMENTE
         */
        foreach (get_sites() as $key => $site) {
          # OBTEM OS CUSTOM POSTS PARA OS QUAIS REGISTRAMOS UMA SETTING
          self::$registeredTypes = json_decode(get_blog_option($site->blog_id, self::registeredTypeField, '[]'), true);

          /*
           * OS POSTS TYPES QUE PORVENTURA NAO EXISTEM MAIS, DEVEM TER SUA SETTING
           * DE THUMBS MAXIMO EXCLUIDA
           */
          foreach (self::$registeredTypes as $name) {
            \delete_blog_option($site->blog_id, self::PREFIX . "_" . self::toNome($name));
          }

          # DESREGISTRA O REGISTRO DE POSTS REGISTRADOS
          \delete_blog_option($site->blog_id, self::registeredTypeField);
        }
      } else {

        # OBTEM OS CUSTOM POSTS PARA OS QUAIS REGISTRAMOS UMA SETTING
        self::$registeredTypes = json_decode(get_option(self::registeredTypeField, '[]'), true);

        /*
         * OS POSTS TYPES QUE PORVENTURA NAO EXISTEM MAIS, DEVEM TER SUA SETTING
         * DE THUMBS MAXIMO EXCLUIDA
         */
        foreach (self::$registeredTypes as $name) {
          \unregister_setting(self::PREFIX, self::PREFIX . "_" . self::toNome($name));
        }

        # DESREGISTRA O REGISTRO DE POSTS REGISTRADOS
        \unregister_setting(self::PREFIX, self::registeredTypeField);
      }
    }

    /*
     *
     */

    static function mpte_plugin_admin_page() {
      include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

      $hasPlugin = (class_exists('MultiPostThumbnails') || is_plugin_active('multi-post-thumbnails/multi-post-thumbnails.php'));

      echo "<div class='wrap mpte'><div class='requisits" . ($hasPlugin ? '' : ' no') . "'>" . _("Requirements") . ": <a href='https://br.wordpress.org/plugins/multiple-post-thumbnails/' target='_blank'>Multiple Post Thumbnails</a>.</div>";

      if ($hasPlugin) {
        echo "<div class='fields'><form method='post' action='options.php'><input type='hidden' name='" . self::registeredTypeField . "' value='" . self::$registeredTypes . "'>";
        \settings_fields(self::PREFIX);

        foreach (self::$actualPostTypes as $name) {
          if (!in_array($name, ['attachment', 'revision', 'nav_menu_item'])) {
            $post = get_post_type_object($name);
            $label = $post->label;
            $fname = self::PREFIX . "_" . self::toNome($name);
            echo "<div class='posttype $name'><h5><b>$label</b><br /><small><i>($name)</i></small></h5><div class='input'><input type='number' min='0' max='99' size='4' placeholder='" . _("Amount") . "' name='$fname' value='" . esc_attr(get_option($fname)) . "' /></div></div>";
          }
        }
        echo "</div><hr />";
        \submit_button();
        echo "</form>";
      }

      echo "</div>";
    }

  }

}