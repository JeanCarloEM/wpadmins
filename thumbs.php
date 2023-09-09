<?php

namespace jeancarloem\Wordpress\plugins\DefaultThumb;

use jeancarloem\Wordpress\plugins\DefaultThumb as dtmb;
use jeancarloem\Wordpress\Admins as wpa;
use jeancarloem\Wordpress\Admins\select as slt;

if (!class_exists('dthumb')) {
  if (is_admin()) {
    add_action('admin_menu', ['jeancarloem\Wordpress\plugins\DefaultThumb\dthumb', 'admin_menu']);
  }

  /*
   *
   */

  abstract class dthumb extends wpa\PagesConstruct
  {

    public static function &getMetaBoxOptions($post)
    {
      $c = [];
      return $c;
    }

    static function className()
    {
      return "jeancarloem\Wordpress\plugins\DefaultThumb\dthumb";
    }

    public static $optionsFields = [];
    static public $registeredTypes = false, $actualPostTypes = false;

    const PREFIX = "dthumb_", registeredTypeField = "dthumb__registered_types", thumb_identy = self::PREFIX . '_dthumb_';


    /*
     *
     */

    static function &getOptions()
    {
      if (empty(self::$optionsFields)) {
        self::$optionsFields[] = [
          'page_title' => 'Default Thumbnail',
          'menu_title' => 'Default Thumbnail',
          'capability' => 'administrator',
          'menu_slug' => 'dthumb_plugin_admin_page',
          'function' => [static::className(), 'dthumb_plugin_admin_page'],
          'parent_slugORicon_url' => 'options-general.php',
          'forms' => []
        ];

        /*
         * GERAL
         */
        self::$optionsFields[0]['forms'][] = [
          'title' => "Destague Global",
          'info' => "Informe a imagem de destaque padrao global. deixa em branco significa usar a primeira imagem do post.",
          'campos' => [
            self::createCampoMeta("dthumb_global", 'image', "Imagem Global")
          ]
        ];


        # PARA CADA POST TYPE ATUAL REGISTRA UMA SETTING, PARA GUARDAR
        # A URL
        foreach (\get_post_types('', 'names') as $name) {
          if (!in_array($name, ['attachment', 'revision', 'nav_menu_item'])) {
            self::$optionsFields[0]['forms'][] = [
              'title' => "$name",
              'info' => "",
              'campos' => []
            ];

            self::$optionsFields[0]['forms'][count(self::$optionsFields[0]['forms']) - 1]["campos"][] =  self::createCampoMeta(static::toNome($name) . "_prioridade", 'radio', "Primeiro, a 1º imagem do post", ['value' => '']);
            self::$optionsFields[0]['forms'][count(self::$optionsFields[0]['forms']) - 1]["campos"][] =  self::createCampoMeta(static::toNome($name) . "_prioridade", 'radio', "Primeiro, a imagem Padrão", ['value' => 'imagem']);
            self::$optionsFields[0]['forms'][count(self::$optionsFields[0]['forms']) - 1]["campos"][] =  self::createCampoMeta(static::toNome($name) . "_prioridade", 'radio', "Apenas a imagem Padrão", ['value' => 'apenaspadrao']);
            self::$optionsFields[0]['forms'][count(self::$optionsFields[0]['forms']) - 1]["campos"][] =  self::createCampoMeta(static::toNome($name), 'image', "Imagem Padrão");
          }
        }
      }

      return self::$optionsFields;
    }


    static public function getPrioridade($post_id = 0): string
    {
      if ($post_id <= 0) {
        $post_id = \get_the_ID();
      }

      return static::getVar(\get_post_type($post_id) . "_prioridade");
    }



    /*
     *
     */

    static function dthumb_plugin_admin_settings()
    {
      # CAMPO QUE GARDARA QUAIS A CONFIGURACOES SETTINGS FORAM REGISTRADAS
      \register_setting(self::PREFIX, self::registeredTypeField);

      # OBTEM OS CUSTOM POSTS PARA OS QUAIS REGISTRAMOS UMA SETTING
      self::$registeredTypes = \json_decode(\get_option(self::registeredTypeField, '[]'), true);

      \register_setting(self::PREFIX, static::getVarName("global"), ["type" => "text"]);

      # OBTEM OS POST TYPE ATUAIS
      self::$actualPostTypes = \get_post_types('', 'names');
      \sort(self::$actualPostTypes);

      # PARA CADA POST TYPE ATUAL REGISTRA UMA SETTING, PARA GUARDAR
      # A URL
      foreach (self::$actualPostTypes as $name) {
        if (!in_array($name, ['attachment', 'revision', 'nav_menu_item'])) {
          \register_setting(self::PREFIX, static::getVarName($name), ["type" => "text"]);
          \register_setting(self::PREFIX, static::getVarName($name) . "_prioridade", ["type" => "text"]);
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
            \unregister_setting(self::PREFIX, static::getVarName($name));
          }
        }
      }

      self::$registeredTypes = json_encode(self::$actualPostTypes);
    }

    /*
     *
     */

    static function uninstall()
    {
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
            \delete_blog_option($site->blog_id, static::getVarName($name));
            \delete_blog_option($site->blog_id, static::getVarName($name) . "_prioridade");
          }

          # DESREGISTRA O REGISTRO DE POSTS REGISTRADOS
          \delete_blog_option($site->blog_id, self::registeredTypeField);
          \delete_blog_option($site->blog_id, static::getVarName("global"));
        }
      } else {

        # OBTEM OS CUSTOM POSTS PARA OS QUAIS REGISTRAMOS UMA SETTING
        self::$registeredTypes = json_decode(get_option(self::registeredTypeField, '[]'), true);

        /*
         * OS POSTS TYPES QUE PORVENTURA NAO EXISTEM MAIS, DEVEM TER SUA SETTING
         * DE THUMBS MAXIMO EXCLUIDA
         */
        foreach (self::$registeredTypes as $name) {
          \unregister_setting(self::PREFIX, static::getVarName($name));
          \unregister_setting(self::PREFIX, static::getVarName($name) . "_prioridade");
        }

        # DESREGISTRA O REGISTRO DE POSTS REGISTRADOS
        \unregister_setting(self::PREFIX, self::registeredTypeField);
        \unregister_setting($site->blog_id, static::getVarName('global'));
      }
    }

    /*
     *
     */
    public static function getFirstImage($post_id = 0)
    {
      if ($post_id <= 0) {
        $post_id = \get_the_ID();
      }

      $ct = \get_the_content(null, false, $post_id);

      if (\preg_match('/<img[^s]+src=(\'|")([^\'"]+)"/i', $ct, $mt) !== 1) {
        $ct = \do_blocks($ct);

        /* PRIMEIRA IMAGEM DO ARTIGO */
        if (\preg_match('/<img[^s]+src=(\'|")([^\'"]+)"/i', $ct, $mt) !== 1) {
          return '';
        }
      }

      return $mt[2];
    }


    static public function primeiraImagem($post_id = 0): bool
    {
      return (!static::primeiraPadrao($post_id) && !static::apenasPadrao($post_id));
    }

    static public function primeiraPadrao($post_id = 0): bool
    {
      return (static::getPrioridade($post_id) === "imagem");
    }

    static public function apenasPadrao($post_id = 0): bool
    {
      return (static::getPrioridade($post_id) === "apenaspadrao");
    }

    /*
     *
     */
    public static function getThumb($post_id = 0)
    {
      if ($post_id <= 0) {
        $post_id = \get_the_ID();
      }

      /*
       * THUMB PADRAO POR POSTTYPE
       */

      # PRIMEIRA IMAGEM - SE FOR O CASO
      if (static::primeiraImagem($post_id)) {
        $pt = static::getFirstImage($post_id);

        if ((!empty($pt)) && (\filter_var($pt, FILTER_VALIDATE_URL) !== false)) {
          return $pt;
        }
      }

      # IMAGE PADRAO POR POST
      $pt = static::getVar(\get_post_type($post_id));

      if ((!empty($pt)) && (\filter_var($pt, FILTER_VALIDATE_URL) !== false)) {
        return $pt;
      }

      # A PRIMEIRA IMAGEM, SE NAO FOR APENAS A PRIMEIRA IMAGEM
      if (static::primeiraPadrao($post_id)) {
        $pt = static::getFirstImage($post_id);

        if ((!empty($pt)) && (\filter_var($pt, FILTER_VALIDATE_URL) !== false)) {
          return $pt;
        }
      }

      /*
       * THUMB PADRAO GLOAL
       */
      $pt = static::getVar('global');

      if ((!empty($pt)) && (\filter_var($pt, FILTER_VALIDATE_URL) !== false)) {
        return $pt;
      }

      if (!\have_posts()) {
        return '';
      }

      return static::getFirstImage($post_id);
    }


    /*
     *
     */

    static function dthumb_plugin_admin_page()
    {
      static::buildPageAdnBox(static::getOptions()[is_numeric($index) ? $index : 0]['forms']);
    }
  }
}
