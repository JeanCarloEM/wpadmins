<?php

namespace jeancarloem\Wordpress\Admins;

use jeancarloem\Wordpress\Admins as wpa;
use jeancarloem\Wordpress\Admins\select as slt;

if (!class_exists('jeancarloem\Wordpress\Admins\PagesConstruct')) {

  abstract class PagesConstruct {

    abstract static function className();

    abstract static function &getOptions();

    abstract public static function &getMetaBoxOptions($post);

    /*
     * O NOME DO CAMPO QUE SALVARA OS POSTTYPE REGISTRADOS NO MOMENTO DO ULTIMO
     * SALVAMENTO, ISSO GARANTE QUE SE HOUVER ALTRAÇÃO (ELIMINACAO) DE UM TIPO
     * DE POST, NOS CONSIGAMOS APAGAR A VARIAVEL REGISTRADA PARA ELE
     */

    const fileToArmPostTypeSaved = 'registered_posts_types';

    /*
     *
     */

    public static function tratarAspasUTF8($val) {
      $val = \html_entity_decode($val, ENT_XHTML, 'UTF-8');


      $chr_map = array(
          // Windows codepage 1252
          "–" => "-", // U+0082⇒U+201A single low-9 quotation mark
          "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
          "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
          "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
          "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
          "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
          "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
          "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
          "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark
          // Regular Unicode     // U+0022 quotation mark (")
          // U+0027 apostrophe     (')
          "″" => '"',
          "\xC2\xAB" => '"', // U+00AB left-pointing double angle quotation mark
          "\xC2\xBB" => '"', // U+00BB right-pointing double angle quotation mark
          "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
          "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
          "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
          "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
          "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
          "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
          "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
          "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
          "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
          "\xE2\x80\xBA" => "'", // U+203A single right-pointing angle quotation mark
      );
      $chr = array_keys($chr_map); // but: for efficiency you should
      $rpl = array_values($chr_map); // pre-calculate these two arrays
      return str_replace($chr, $rpl, $val);
    }

    /*
     *
     */

    public static function getCategories() {
      $c = [];
      foreach (\get_categories() as $key => $value) {
        $c[] = $value->slug;
      }
      return $c;
    }

    /*
     *
     */

    public static function getTags() {
      $c = [];
      foreach (\get_tags() as $key => $value) {
        $c[] = $value->slug;
      }
      return $c;
    }

    static function toNome($str) {
      return strtolower(preg_replace('|[^0-9a-zA-Z_\-]|i', '', preg_replace('|[ ]+|i', '_', iconv('utf-8', 'ascii//TRANSLIT', $str))));
    }

    protected static function &Options() {
      foreach (static::getOptions() as $key => &$pagina) {
        foreach ($pagina['forms'] as &$grupo) {
          $grupo['name'] = $grupo['name'] ?? static::toNome($grupo['title']);
        }
      }

      return static::getOptions();
    }

    /*
     *
     */

    public static function admin_add_metabox_posts($post) {
      $boxes = static::getMetaBoxOptions($post);
      $actualPostTypes = \get_post_types('', 'names');

      if ((is_array($boxes)) && (is_array($actualPostTypes))) {
        foreach ($boxes as $key => $box) {
          foreach ($actualPostTypes as $k => $posttype) {
            \add_meta_box($box['id'], $box['title'], $box['function'], $posttype, 'advanced', 'default', ["findex" => $key]);
          }
        }
      }
    }

    /*
     *
     */

    public static function buildBoxMetaHTML($post, $box) {
      static::buildPageAdnBox(static::getMetaBoxOptions($post)[$box['args']['findex']]['forms'], true);
    }

    /*
     *
     */

    public static function admin_save_metabox_posts($post_id) {
      $boxes = static::getMetaBoxOptions($post_id);

      if ((\is_array($boxes)) && (!empty($boxes))) {
        foreach ($boxes as $key => $box) {

          if ((\is_array($box)) && (!empty($box))) {
            foreach ($box['forms'] as $k => $forms) {
              if ((\is_array($forms)) && (!empty($forms))) {
                $compos_entitled = (\array_key_exists('__entitled__', $forms['campos']) && ($forms['campos']['__entitled__'])) ? $forms['campos'] : [0];

                foreach ($compos_entitled as $ck => $cmp) {
                  if (\is_numeric($ck)) {
                    if (\array_key_exists('__entitled__', $forms['campos']) && ($forms['campos']['__entitled__'])) {
                      $campos = $cmp;

                      if (\array_key_exists('name', $campos)) {
                        $campos = [$campos];
                      }
                    } else {
                      $campos = $forms['campos'];
                    }

                    foreach ($campos as $k => $campo) {                      
                      if (\is_array($campo)) {
                        /* EDITADO POR CORRECAO DE LOG - CKEY NAME INEXISTENTE */
                        if (\array_key_exists('name', $campo) && ((array_key_exists(static::getVarName($campo['name']), $_POST)) || ($campo['type'] === "checkbox"))) {
                          @\update_post_meta(
                                          $post_id,
                                          static::getVarName($campo['name']),
                                          $_POST[static::getVarName($campo['name'])]
                          );
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    /*
     *
     */

    static function admin_menu() {
      foreach (static::Options() as $pagina) {
        # REGISTRA O SUBMENU
        static::addPage($pagina);

        # REGISTRA AS CONFIGURACOES
        add_action('admin_init', [static::className(), '__admin_init']);

        # ADICIONA CSS PARA AREA DE ADMIN
        add_action('admin_head', [static::className(), '__admin_head']);

        add_action('admin_enqueue_scripts', [static::className(), 'load_wp_media_files']);

        /* ADICIONA METABOX EM CADA POST */
        add_action('add_meta_boxes', [static::className(), 'admin_add_metabox_posts']);
        add_action('save_post', [static::className(), 'admin_save_metabox_posts']);
      }
    }

    static function network_admin_menu() {

    }

    /*
     *
     */

    static function load_wp_media_files() {
      wp_enqueue_media();
    }

    /*
     *
     */

    static function __admin_init() {
      # REGISTRANDO OS CAMPOS DE SOCIAIS
      foreach (static::Options() as $pagina) {
        foreach ($pagina['forms'] as $grupo) {
          $compos_entitled = (\array_key_exists('__entitled__', $grupo['campos']) && ($grupo['campos']['__entitled__'])) ? $grupo['campos'] : [0];

          foreach ($compos_entitled as $ck => $cmp) {
            if (\is_numeric($ck)) {
              if (\array_key_exists('__entitled__', $grupo['campos']) && ($grupo['campos']['__entitled__'])) {
                $campos = $cmp;

                if (\array_key_exists('name', $campos)) {
                  $campos = [$campos];
                }
              } else {
                $campos = $grupo['campos'];
              }
              foreach ($campos as $campo) {
                if (\is_array($campo)) {
                  $tipo = ( ($campo['type'] === 'checkbox') || ($campo['type'] === 'radio') ) ? 'boolean' : (
                          ($campo['type'] === 'number') ? 'number' : 'string'
                          );

                  /* REGISTRA A VARIAVEL  */
                  \register_setting(static::getGroupFieldName($grupo["name"]), static::getVarName($campo["name"]), $tipo);

                  if ($campo["name"] === static::getFieldRegisteredPostTypesName($grupo["name"])) {
                    # OBTEM OS CUSTOM POSTS PARA OS QUAIS REGISTRAMOS UMA SETTING ANTERIORMENTE
                    # PARA COMPARAMOS E ELIMINARMOS AQUELES QUE NAO EXISTEM MAIS
                    $registeredTypes = static::getFieldRegisteredPostTypes($grupo["name"]);

                    /*
                     * OS POSTS TYPES QUE PORVENTURA NAO EXISTEM MAIS, DEVEM TER SUA SETTING
                     * DE THUMBS MAXIMO EXCLUIDA
                     */
                    if (is_array($registeredTypes)) {
                      $actualPostTypes = \get_post_types('', 'names');

                      foreach ($registeredTypes as $name) {
                        /* SE ELE NAO EXISTIR MAIS, NOS ATUAIS, ELIMINAMOS A CONFIGURACAO */
                        if (!in_array($name, $actualPostTypes)) {
                          static::deleteVar(static::getPostTypeFieldName($grupo["name"], $name));
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    static function getGroupFieldName($title) {
      return static::PREFIX . static::toNome($title);
    }

    /*
     * GERA O NOME COMPLETO DO CAMPO REGISTERED-POSTTYPE, INCLUINDO O PREFIXO
     */

    protected static function getPostTypeFieldName($title, $name) {
      return self::getVarName(static::toNome($title) . "_" . static::toNome($name));
    }

    /*
     * GERA O NOME COMPLETO DO CAMPO INCLUINDO O PREFIXO
     */

    protected static function getVarName($name) {
      return (strpos($name, STATIC::PREFIX) === 0) ? $name : STATIC::PREFIX . static::toNome($name);
    }

    /*
     * OBTEM O VALOR FA VARIAVEL
     * CASO A MESMA NAO ESTEJ PREFIXADA, ESTA FUNCAO ADICIONA O PREFIXO
     */

    public static function getVar($nome, $default = '', $site_id = null) {
      if (empty($site_id) || !is_int($site_id)) {
        return \get_option(static::getVarName($nome), $default);
      } else {
        return \get_blog_option($site_id, static::getVarName($nome), $default);
      }
    }

    /*
     *  OBTEM O META DE UM POST ESPECIFICO OU DO POST ATUAL
     */

    public static function getMeta($name, $default = '', $post_id = null) {
      global $post;

      if (\is_object($post_id)) {
        $post_id = $post_id->ID;
      } else if (!\is_numeric($post_id)) {
        $post_id = $post->ID;
      }

      if ((!\is_numeric($post_id)) && (\array_key_exists('post_ID', $_POST))) {
        $post_id = $_POST['post_ID'];
      }

      return \get_post_meta($post_id, static::getVarName($name), true) ?? $default;
    }

    /*
     * DELETA UMA VARIAVEL
     * SE $site_id FOR ESPECIFICADO, A VARIAVEL SERAH DELETADO DO SITE $site_id
     * CASO CONTRARIO (NULL) SERAH DELETADA A VARIA DO SITE ATUAL
     */

    protected static function deleteVar($nome, $site_id = null) {
      if (empty($site_id) || !is_int($site_id)) {
        return \delete_option($nome);
      } else {
        return \delete_blog_option($site_id, $nome);
      }
    }

    /*
     *
     */

    static function __admin_head() {
      echo "<link rel='stylesheet' href='" . get_template_directory_uri() . "/assets/css/admin.css' type='text/css' media='all' /><link rel='stylesheet' href='https://use.fontawesome.com/releases/v5.2.0/css/all.css' integrity='sha384-hWVjflwFxL6sNzntih27bfxkr27PmbbK/iSvJ+a4+0owXq79v+lsFkW54bOGbiDQ' crossorigin='anonymous'>";
      echo "<script src='" . get_template_directory_uri() . "/assets/js/admin.js'></script>";
    }

    static function get_wp_content() {

      function __get_content() {
        \ob_start();
        \the_content('Continue...');
        return \ob_get_clean();
      }

      if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return preg_replace('|[ ]*\r?\n[ ]*\t?[ ]*|i', "\n\t", preg_replace('|[ ]{2,20}|i', '', preg_replace("#'[ ]*//#i", "'https://", preg_replace('#"[ ]*//#i', '"https://', preg_replace('|http://|i', 'https://', __get_content())))));
      }

      return __get_content();
    }

    /*
     *
     */

    static function addPage($page_titleORArray, string $menu_title = '', string $capability = '', string $menu_slug = '', $function = null, string $parent_slugORicon_url = '', int $position = null) {
      if (is_array($page_titleORArray)) {
        if (\filter_var($page_titleORArray['parent_slugORicon_url'], FILTER_VALIDATE_URL) !== false) {
          return \add_menu_page($page_titleORArray['page_title'], $page_titleORArray['menu_title'], $page_titleORArray['capability'], $page_titleORArray['menu_slug'], $page_titleORArray['function'], $page_titleORArray['parent_slugORicon_url'], $page_titleORArray['position']);

          /*
           * SUBMENU
           */
        } else if (!empty($page_titleORArray['parent_slugORicon_url'])) {
//die(var_dump($page_titleORArray));
          return \add_submenu_page($page_titleORArray['parent_slugORicon_url'], $page_titleORArray['page_title'], $page_titleORArray['menu_title'], $page_titleORArray['capability'], $page_titleORArray['menu_slug'], $page_titleORArray['function']);
        }
      } else {
        /* MENU SUPERIOR */
        if (\filter_var($parent_slugORicon_url, FILTER_VALIDATE_URL) !== false) {
          return \add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $parent_slugORicon_url, $position);
          /*
           * SUBMENU
           */
        } else if (!empty($parent_slugORicon_url)) {
          return \add_submenu_page($parent_slugORicon_url, $page_title, $menu_title, $capability, $menu_slug, $function);
        }
      }
    }

    # https://developer.wordpress.org/reference/functions/add_submenu_page/
    # https://developer.wordpress.org/reference/functions/add_menu_page/

    static function createPageMeta(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $function = null, string $parent_slugORicon_url = '', int $position = null) {
      return [
          'page_title' => $page_title,
          'menu_title' => $menu_title,
          'capability' => $capability,
          'menu_slug' => $menu_slug,
          'function' => $function,
          'parent_slugORicon_url' => $parent_slugORicon_url,
          'position' => $position
      ];
    }

    /*
     *
     */

    static function createCampoMeta($name, $type, $placeholder, $args = null) {
      $r = [
          'name' => static::getVarName($name),
          'placeholder' => $placeholder,
          'type' => $type
      ];

      if (is_array($args)) {
        return array_merge($r, $args);
      }

      return $r;
    }

    /*
     * REMOVE AS CONFIGURACOES
     * VARRE TODAS AS CONFIGURACOES E DELETA-AS. SE FOR UM CAMPO DE POSTTYPE
     * TRANSFORMA ELE EM ARRAY E VARRE ELE DELETANDO AS VARIAVEIS ESPECIFICAS
     * GARANTINDO QUE TODAS, INCLUSIVE AQUELES VARIAVEIS DE TIPOS DE POSTS
     * ELIMINADOS SEMA APAGADOS
     */

    protected static function removeSiteAllOptions($site_id = null) {
      $site_id = ((!empty($site_id)) && (is_int($site_id))) ? $site_id : '';

      foreach (static::Options() as $pagina) {
        foreach ($pagina['forms'] as $grupo) {
          $compos_entitled = (\array_key_exists('__entitled__', $grupo['campos']) && ($grupo['campos']['__entitled__'])) ? $grupo['campos'] : [0];

          foreach ($compos_entitled as $ck => $cmp) {
            if (\is_numeric($ck)) {
              if (\array_key_exists('__entitled__', $grupo['campos']) && ($grupo['campos']['__entitled__'])) {
                $campos = $cmp;

                if (\array_key_exists('name', $campos)) {
                  $campos = [$campos];
                }
              } else {
                $campos = $grupo['campos'];
              }
              foreach ($campos as $campo) {
                if (\is_array($campo)) {
                  /* SE HOUVER REGISTRO DE POSTTYPES */
                  if (trim(static::getFieldRegisteredPostTypesName($grupo['name'])) === trim($campo["name"])) {
                    $registeredPosts = static::getFieldRegisteredPostTypes($grupo['name'], $site_id);

                    /* ELIMINA AS VARIAVEIS ESPECIFICAS PARA CADA POSTTYPE */
                    foreach ($registeredPosts as $key => $post_name) {
                      static::deleteVar($campo["name"], static::getPostTypeFieldName($grupo['name'], $site_id));
                    }
                  }


                  file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "removeStiny.{$site_id}_{$campo["name"]}.txt", $site_id);
                  static::deleteVar($campo["name"], $site_id);
                }
              }
            }
          }
        }
      }
    }

    /*
     *
     */

    protected static function removeAllOptions($site_id = null) {
      if (\is_multisite()) {
        file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "removeStiny.txt", '0');
        foreach (\get_sites() as $key => $site) {
          file_put_contents(__DIR__ . DIRECTORY_SEPARATOR . "removeStiny.{$site->blog_id}.txt", $site->blog_id);
          static::removeSiteAllOptions($site->blog_id);
        }
      } else {
        static::removeSiteAllOptions();
      }
    }

    /*
     *
     */

    static function getFieldRegisteredPostTypesName($title) {
      return static::getPostTypeFieldName($title, static::fileToArmPostTypeSaved);
    }

    /*
     * OBTEM O NOME COMPLETO, COM PREFIXO DO CAMPO REGISTERED POST TYPE
     * QUE ARMAZENA O JSON DAS CONFIGURACOES BASEADOS NOS TIPOS DE POSTAS
     */

    public static function getFieldRegisteredPostTypes($title, $site_id = null) {
      return \json_decode(static::getVar(static::getFieldRegisteredPostTypesName($title), '[]', $site_id), true);
    }

    /*
     *
     */

    static function getPostTypeFieldContent($title, $post_type = '', $default = '') {
      if (empty($post_type)) {
        global $post;
        $post_type = \get_post_type($post->ID);
      }

      $registeredTypes = Static::getPostTypeFieldName($title, $post_type);
      return static::getVar($registeredTypes, $default);
    }

    /*
     *
     */

    static function buildMetaFormFieldsPostType($title, $fildtype = 'string', array $ignore = []) {
      # OBTEM OS POST TYPE ATUAIS
      $actualPostTypes = \get_post_types('', 'names');
      \sort($actualPostTypes);

      # ADICIONA O CAMPO HIDDEN QUE CONTEM OS TIPOS DE POSTS REGISTRADOS ATUALMENTRE
      $metas = [self::createCampoMeta(static::getFieldRegisteredPostTypesName($title), 'hidden', '', ['value' => \json_encode($actualPostTypes)])];

      # PARA CADA POST TYPE ATUAL REGISTRA UMA SETTING, PARA GUARDAR
      # O THUMBNAIL MAXIMO
      foreach ($actualPostTypes as $name) {
        if (!in_array($name, $ignore)) {
          /* LISTA DE CAMPOS META JÁ CRIADOS */
          if (\is_array($fildtype) && (!empty($fildtype))) {
            $metas[] = [];
            foreach ($fildtype as $key => $value) {
              $value['name'] .= "_" . $name;
              $value['style'] = 'display: inline-block !important;margin-right:.3rem;';
              $metas[\count($metas) - 1][] = $value;
            }
            $metas[\count($metas) - 1]['__title__'] = \get_post_type_object($name)->label;
          } else {
            $metas[] = self::createCampoMeta(static::getPostTypeFieldName($title, $name), $fildtype, \get_post_type_object($name)->label, ['class' => "post_type $name"]);
          }
        }
      }

      if (is_array($fildtype) && (!empty($fildtype))) {
        $metas['__entitled__'] = true;
      }

      return $metas;
    }

    /*
     *
     */

    static function createCampoTag($nameOrArray, $placeholder = null, $type = null, $args = null, $postmeta = false) {
      $args = (!is_array($nameOrArray)) ? static::createCampoMeta($nameOrArray, $type, $placeholder, $args) : $nameOrArray;

      $args['type'] = strtolower($args['type']);

      if (in_array($args['type'], ['text', 'tel', 'select', 'date', 'hidden', 'number', 'email', 'password', 'textarea', 'checkbox', 'radio'])) {
        $props = '';
        foreach ($args as $key => $value) {
          if ((($args['type'] !== 'textarea') && ($args['type'] !== 'checkbox') && ($args['type'] !== 'radio')) || ($key !== 'value')) {
            $props .= (empty($props) ? '' : ' ') . "$key='$value'";
          }
        }


        if (($args['type'] === 'checkbox') && ($args['value'])) {
          $props .= ' checked';
        }

        if (($args['type'] === 'radio')) {
          $props .= " value='" . $args['value'] . "'";

          if ($args['value'] === ( $postmeta ? static::getMeta($args['name']) : static::getVar($args['name']) )) {
            $props .= ' checked';
          }
        }

        if (($args['type'] === 'select') && \is_array($args['options'])) {
          $retorno = "<select $props><option value='' disabled >{$args['placeholder']}</option>";

          foreach ($args['options'] as $key => $value) {
            $v = (\is_string($key) && (strlen($key) > 1)) ? $key : $value;
            $value = (\is_string($key) && (strlen($key) > 1)) ? \trim($value) : \strtolower(\trim($value));
            $checked = ($args['value'] === $value) ? " selected" : '';
            $retorno .= "\n\t<option value='$value'$checked>" . $v . "</option>";
          }

          $retorno .= "</select>";
          return $retorno;
        }

        if ($args['type'] === 'textarea') {
          return "<textarea $props>{$args['value']}</textarea>";
        } else if (in_array($args['type'], ['checkbox', 'radio'])) {
          return "<label>{$args['placeholder']}</label><input $props /><span class='checkmark'></span>";
        } else {
          return "<input $props />";
        }
      }

      if ($args['type'] === 'color') {
        $props = '';
        foreach ($args as $key => $value) {
          $props .= (empty($props) ? '' : ' ') . "$key='$value'";
        }

        return "<input $props /><label>{$args['placeholder']}</label>";
      }
    }

    /*
     *
     */

    static function buildPageAdnBox($forms, $postmeta = false) {
      include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

      echo "<div class='wrap jcem_wpAdmin'>";

      foreach ($forms as $fk => $form) {
        echo "<div class='form'>" . ($form['title'] ? "<h2>{$form['title']}</h2>" : "") . "<div class='info'>{$form['info']}</div>" . ((!$postmeta) ? "<form method='post' action='options.php'>" : "") . "<div class='fields'>";
        if (!$postmeta) {
          settings_fields(static::getGroupFieldName($form["name"]));
        }

        $compos_entitled = (\array_key_exists('__entitled__', $form['campos']) && ($form['campos']['__entitled__'])) ? $form['campos'] : [0];

        foreach ($compos_entitled as $ck => $cmp) {
          if (\is_numeric($ck)) {
            if (\array_key_exists('__entitled__', $form['campos']) && ($form['campos']['__entitled__'])) {
              $campos = $cmp;
              echo "<div class='subgroup'><h3>{$campos['__title__']}</h3>";

              if (\array_key_exists('name', $campos)) {
                $campos = [$campos];
              }
            } else {
              $campos = $form['campos'];
            }

            foreach ($campos as $fc => $campo) {
              if (\is_array($campo)) {
                /* TRATANDO A PROP NAME PARA GARANTIR QUE POSSUA O PREFIXO */
                $campo['name'] = static::getVarName($campo['name']);

                $campo['style'] = $campo['style'] ? " style='" . $campo['style'] . "'" : '';

                echo ($campo['type'] !== 'hidden') ? ( "<div class='input {$campo['name']} {$campo['type']} " . ($campo['class'] ?? '') . "'" . ($campo['style'] ?? '') . ">" ) : '';

                if (($campo['type'] === 'image') || ($campo['type'] === 'imagem')) {
                  echo "<button type='button' class='open'></button><button type='button' class='upload'></button>";
                  $campo['type'] = 'text';
                }

                if (($campo['type'] === 'radio')) {
                  $campo['value'] = ($campo['value'] ?? static::toNome($campo['placeholder']));
                }

                echo static::createCampoTag(array_merge($campo, ["value" => ($campo['value'] ?? ( $postmeta ? static::getMeta($campo['name']) : static::getVar($campo['name']) ) )]), null, null, null, $postmeta);
                echo ($campo['type'] !== 'hidden') ? "</div>" : '';
              }
            }

            if (\array_key_exists('__entitled__', $form['campos']) && ($form['campos']['__entitled__'])) {
              echo "</div>";
            }
          }
        }

        echo "</div><hr />";
        if (!$postmeta) {
          submit_button();
        }
        echo ((!$postmeta) ? "</form>" : "") . "</div>";
      }

      echo "</div>";
    }

  }

}