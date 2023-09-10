<?php

namespace jeancarloem\Wordpress\Admins\tools;

use jeancarloem\Wordpress\Admins as wpa;
use jeancarloem\Wordpress\Admins\tools as tls;

defined('ABSPATH') or die("O Plugin wordpress NÃO foi carregador corretamente.");

/*
 *
 */

abstract class select
{
  /*
   *
   */

  public static function tratarParamTipo($val, array $args)
  {
    $tipos = ['json', 'xml', 'rss', 'atom'];

    for ($i = count($args) - 1; $i >= 0; $i--) {
      $val = in_array($val, $tipos) ? $val : 'json';
    }

    return $val;
  }

  /*
   *
   */

  public static function tratarParamPostType($val)
  {
    /* IDENTIFICANDO SE O POST TYPE EXISTE */
    if (!empty($val) && ($val !== 'all')) {
      if (is_string($val) && (strpos($val, ',') === false)) {
        $val = [trim($val)];
      } else {
        $val = \explode(',', $val);
      }

      for ($i = 0; $i < count($val); $i++) {
        if (!in_array($val[$i], \get_post_types('', 'names'))) {
          unset($val[$i]);
        }
      }

      return array_values($val);
    }

    return null;
  }

  /*
   *
   */

  public static function tratarParamQtd($val)
  {
    /* IDENTIFICANDO SE O POST TYPE EXISTE */
    return (is_numeric($val)) ? (int) $val : 0;
  }

  /*
   *
   */

  public static function tratarParamJson($vlr)
  {
    if ((strpos($vlr, '%') === false) && ((strpos($vlr, ':') === false))) {
      $vlr = \base64_decode($vlr);
    } else {
      $vlr = \urldecode($vlr);
    }

    return \json_decode($vlr, true);
  }

  /*
   *
   */

  public static function tratarParamCat($val)
  {
    $r = [];

    if (is_string($val) && (strpos($val, ',') !== false) || (strpos($val, '+') !== false)) {
      $no = true;

      $tags = wpa\PagesConstruct::getCategories();

      $r = preg_replace_callback('#([^,+]+)([,+]?)#', function ($mts) use ($tags) {
        $mts[1] = trim($mts[1]);
        if (in_array($mts[1], $tags)) {
          return $mts[1] . $mts[2];
        }
      }, $val);

      if (($r[strlen($r) - 1] === ',') || ($r[strlen($r) - 1] === '+')) {
        $r = substr($r, 0, -1);
      }
    } else {
      $r = (!empty($val) && in_array($val, wpa\PagesConstruct::getCategories())) ? $val : '';
    }

    return $r;
  }

  /*
   *
   */

  public static function tratarParamTags($val)
  {
    $r = [];

    if (is_string($val) && (strpos($val, ',') !== false) || (strpos($val, '+') !== false)) {
      $no = true;

      $tags = wpa\PagesConstruct::getTags();

      $r = preg_replace_callback('#([^,+]+)([,+]?)#', function ($mts) use ($tags) {
        $mts[1] = trim($mts[1]);
        if (in_array($mts[1], $tags)) {
          return $mts[1] . $mts[2];
        }
      }, $val);

      if ($r[strlen($r) - 1] === ',') {
        $r = substr($r, 0, -1);
      }

      $r = $r ? [$r] : [];
    } else {
      $r = (!empty($val) && in_array($val, wpa\PagesConstruct::getTags())) ? [$val] : [];
    }

    return $r;
  }

  /*
   *
   */

  public static function tratarQuery(array $args)
  {
    list($post_type, $categorias, $tags, $onlyExcerpt, $postQtd, $more_options, $filters, $tipo) = $args;

    return [
      static::tratarParamPostType($post_type),
      static::tratarParamCat($categorias),
      static::tratarParamTags($tags),
      static::tratarParamQtd($onlyExcerpt),
      $postQtd,
      static::tratarParamJson($more_options),
      static::tratarParamJson($filters),
      static::tratarParamTipo($tipo, $args)
    ];
  }

  /*
   *
   */

  public static function uriquery($uri, callable $callback, $all = false, $asttag = false)
  {
    /* URL SINTAX */
    list($post_type, $categorias, $tags, $onlyExcerpt, $postQtd, $more_options, $filters) = static::tratarQuery(is_string($uri) ? explode('/', $uri) : $uri);

    /* TENTA EXECUTAR */
    static::query($post_type, $categorias, $tags, $onlyExcerpt, $postQtd, $more_options, $filters, $callback, $all, $asttag);
  }

  /*
   *
   */

  public static function getThumb($only_default_thumb = false, $prefer_default_thumb = false, $post_id = 0)
  {
    if ($post_id <= 0) {
      $post_id = \get_the_ID();
    }

    if ($prefer_default_thumb && \has_post_thumbnail()) {
      $imgs = [\wp_get_attachment_image_src(\get_post_thumbnail_id($post_id), 'single-post-thumbnail')[0]];
    }

    if (!$only_default_thumb && (!$prefer_default_thumb || ($prefer_default_thumb && (!isset($imgs) || (empty($imgs))))) && \class_exists("jeancarloem\Wordpress\plugins\MPTEditor\MPTEditor")) {
      $imgs = \jeancarloem\Wordpress\plugins\MPTEditor\MPTEditor::getThumbs();
    }

    if (empty($imgs) && \has_post_thumbnail()) {
      $imgs = [\wp_get_attachment_image_src(\get_post_thumbnail_id($post_id), 'single-post-thumbnail')[0]];
    }

    /*
     * OBTEM O THUMBS PADRAO CONFORME PLUGIN
     */
    if (empty($imgs)) {
      $imgs = \jeancarloem\Wordpress\plugins\DefaultThumb\dthumb::getThumb($post_id);
      $imgs = empty($imgs) ? [] : [$imgs];
    }

    if (!empty($imgs) && (is_array($imgs))) {
      /* OBTEM OS MIMES */
      $mimes = \json_decode(\file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '.img.mimes.json'), true);

      foreach ($imgs as $key => &$value) {
        $fsize = null;
        $fmime = null;

        $value = is_string($value) ? $value : $value[0];

        $path = realpath(dirname(dirname(dirname(__DIR__))) . \wp_make_link_relative($value));

        /*
         * SE URL LOCAL
         */
        if (!empty($path) && file_exists($path) && is_file($path)) {
          $fsize = \filesize($path);
          $fmime = $mimes[strtolower(pathinfo($path)['extension'])] ?? 'image/*';

          /* SE REMOTA, MAS COM DADOS CADASTRADO */
        } else {
          $header = \wp_get_attachment_metadata(\get_post_thumbnail_id($post_id));

          $fsize = @$header['size'];
          $fmime = @$header['content-type'];
        }

        /*
         * SE REMOTA E SEM DADOS CADASTRADOS
         */
        if ($fmime === null) {
          $context = stream_context_create([
            'ssl' => [
              'verify_peer' => false,
              'verify_peer_name' => false,
            ],
          ]);

          $headers = \get_headers("$value", 1, $context);

          foreach ($headers as $hk => $val) {
            if ($key !== strtolower($hk)) {
              $headers[strtolower($hk)] = $val;
              unset($headers[$hk]);
            }
          }

          /* TENTA OBTER O TAMANHO PELO HEADER */
          $fsize = (int) trim(@$headers['content-length'] ?? '0');

          /* TENTA OBTER A EXTENSAO PELA URL */
          $fmime = $mimes[strtolower(pathinfo($value)['extension'])] ?? null;

          if (empty($fmime)) {
            $fmime = trim($headers['content-type']);

            if (strpos($fmime, ';')) {
              $fmime = explode(';', $fmime);

              foreach ($fmime as $mk => $inds) {
                if (strpos($inds, 'image') !== false) {
                  $fmime = $inds;
                  break;
                }
              }
            }
          }
        }

        $value = [
          "url" => $value,
        ];

        if ($fmime) {
          $value["mime_type"] = $fmime;
        }

        if ($fsize) {
          $value["size_in_bytes"] = $fsize;
        }
      }

      return $imgs;
    }
  }

  /*
   *
   */

  public static function tagsContentToJson(string $content, array &$params, $json = false)
  {
    \preg_replace_callback(
      '/\s*<(?<tagname>\w+)(?<param>(\s*[^"\'=>]+(\s*=\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')){0,1})*)>(?<content>.*?)?<\/\k<tagname>>\s*/is',
      /* AQUI CADA UM DOS CASAMENTO EH EXECUTADO */
      function ($mt) use (&$params, $json) {
        /* NESTE FUNCAO ELIMINAMOS A TAG E SETAMOS PARAM COM SEU CONTEUDO */
        $seter = function (&$params, $val, $json = false) {
          foreach ($params as $key => &$value) {
            if (($val !== null) && (\preg_match("/<\/\s*$key\s*>/is", $val))) {
              $value = wpa\PagesConstruct::tratarAspasUTF8(\html_entity_decode(\trim(\preg_replace("/<\/?s*$key\s*>/is", '', \preg_replace("/\s*[\r\n]+\s*/is", '', $val)))));

              if ($json && (!empty($value))) {

                $v = \json_decode($value, true);

                if (!empty($v)) {
                  $value = $v;
                }
              }
            }
          }

          return $value;
        };

        /* ATUALIZA */
        $seter($params, $mt[0], $json);

        /* NAO EH PARA SUBSTITUIR NA VERDADE */
        return $mt[0];
      },
      $content
    );
  }

  /*
   *
   */

  public static function propsToVetor($string, array &$arr)
  {
    return \preg_replace_callback('/(?<param>((\s*[^"\'=>]+)(\s*=\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')){0,1})*)/is', function ($mt) use (&$arr) {
      $k = trim($mt[3]);

      if (!empty($k)) {
        $v = trim(\array_key_exists(5, $mt) ? $mt[5] : 1);

        if (($v[0] === "'") || ($v[0] === '"')) {
          $v = substr($v, 1);
        }

        if (($v[strlen($v) - 1] === "'") || ($v[strlen($v) - 1] === '"')) {
          $v = substr($v, 0, -1);
        }

        $arr[$k] = $v;
      }
    }, $string);
  }

  /*
   *
   */

  public static function query($post_type = null, $categorias = null, $tags = null, $onlyExcerpt = null, $postQtd = null, $more_options = null, $filters = null, callable $callback, $all = false, $asttag = false)
  {

    /*
     * FAZ O FILTRO
     */

    $params = [];

    /* PARAMETROS ADICINAIS NAO PODEM SOBRESCREVER A O QUE FOI PASSADA NA URL
     * POR ISSO ADICIONAMOS ELE PRIMEIRO
     */

    if ((!empty($more_options)) && (is_array($more_options))) {
      $params = $more_options;
    }

    /* TIPOS DE POSTS */
    $params['post_type'] = (!empty($post_type)) ? $post_type : 'post';

    /* CATEGORIAS */
    if (!empty($categorias)) {
      $params['category_name'] = is_string($categorias) ? $categorias : (is_array($categorias) ? ((count($categorias) === 1) ? $categorias[0] : implode(',', $categorias)) : '');
    }

    /* TAGS */
    if (!empty($tags)) {
      $params['tag'] = is_string($tags) ? $tags : (is_array($tags) ? ((count($tags) === 1) ? $tags[0] : implode(',', $tags)) : '');
    }

    $original_post_id = \get_the_ID();

    if (!empty($filters)) {
      $filters = is_string($filters) ? ((strpos(trim($filters), ' ') !== false) ? $filters : \base64_decode($filters)) : $filters;
      $filters = is_string($filters) ? \json_decode($filters, true) : (is_array($filters) ? $filters : []);

      if (!empty($filters)) {
        foreach ($filters as $key => $value) {
          if (!empty($value)) {
            $key = trim($key);
            $f = $filters[$key];

            /*
             *
             */
            $filters[$key] = function ($r) use ($f) {
              /*
               *
               */
              $tratar = function ($e) {
                if (preg_match('/(^|[^\w]+|_)users($|[^\w]+)/is', $e)) {
                  return '';
                }

                $e = \preg_replace_callback('/#\(\$(\w+)\)/is', function ($mt) {
                  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                  global $wpdb;

                  $mt[1] = strtolower(trim($mt[1]));

                  switch ($mt[1]) {
                    case 'prefix':
                      return $wpdb->prefix;
                      break;

                    default:
                      if (\defined($mt[1])) {
                        return \constant($mt[1]);
                      }

                      break;
                  }

                  return $mt[0];
                }, $e);

                return $e;
              };

              if (is_string($f)) {
                return "$r " . $tratar($f);
              }

              if (is_array($f) && (array_key_exists('pre', $f) || array_key_exists('pos', $f))) {
                return $tratar(@$f['pre'] ?? '') . " $r " . $tratar(@$f['pos'] ?? '');
              }

              if (is_array($f) && (array_key_exists('replace', $f))) {
                return $tratar(@$f['replace'] ?? '');
              }

              return $r;
            };

            \add_filter(\trim($key), $filters[$key]);
          }
        }
      }
    }

    $original_post = $wp_query; // isso nao estava aqui, acrescentei, mas nao tenho certeza se é aqui ou outor lugar
    $wp_query = new \WP_Query($params);

    if (\strpos($wp_query->request, "as dt") !== false) {
      //die("\n\n\nXXXXXXXXXXXXXXXXXXXXXXX\n\n" . ($wp_query->request) . "\n\n\n\n");
    }

    /* REMOVE OS FILTROS OPICIONAIS */
    if (!empty($filters)) {
      foreach ($filters as $key => $value) {
        \remove_filter(trim($key), $filters[$key]);
      }
    }


    /*
     * INICIA O FOREACH DOS ITENS
     */

    $items = [];

    $i = $start = 0;

    if (is_string($postQtd) && (strpos($postQtd, '-') !== false)) {
      $postQtd = explode('-', $postQtd);
      $start = $postQtd[0];
      $postQtd = $postQtd[1];
    }

    while ($wp_query && $wp_query->have_posts() && (($i < $postQtd) || (empty($postQtd)))) {
      $wp_query->the_post();

      if ($i >= $start) {
        $item = [
          'type' => \get_post_type(),
          'id' => \get_permalink(),
          /* COMPATIVEL COM RSS-AGGREGATOR */
          'url' => !empty(\get_post_meta(\get_the_ID(), 'jcem_link', true)) ? \get_post_meta(\get_the_ID(), 'jcem_link', true) : (!empty(\get_post_meta(\get_the_ID(), 'wprss_item_permalink', true)) ? \get_post_meta(\get_the_ID(), 'wprss_item_permalink', true) : \get_permalink()),
          'title' => \get_the_title(),
          'date_published' => \get_gmt_from_date(\get_the_date('Y-m-d H:i:s'), 'c'),
          'date_modified' => \get_gmt_from_date(\get_the_modified_date('Y-m-d H:i:s'), 'c'),
          'author' => ['name' => \get_the_author()],
        ];

        /* CATEGORIA */
        $cat = \trim(\implode(' ', \wp_get_post_categories(\get_the_ID(), ['fields' => 'names'])) . " " . implode(' ', \wp_get_post_tags(\get_the_ID(), ['fields' => 'names'])));

        if (!empty($cat)) {
          $item['tags'] = array_flip(array_flip(explode(' ', $cat)));
        }

        $thumb = static::getThumb();

        if (!empty($thumb)) {
          $item["attachments"] = $thumb;
        }

        /* CONTENT HTML */
        if (($i < ($onlyExcerpt + $start)) || (($onlyExcerpt + $start) < 0)) {
          $item['content_html'] = \get_the_content_feed('json') ?? \get_post_field('post_excerpt', $post->ID);
        } else {
          $item['content_html'] = \get_the_excerpt() ?? (\get_post_field('post_excerpt', $post->ID) ?? \get_the_content_feed('json'));
        }

        $item['excerpt'] = \get_the_excerpt() ?? (\get_post_field('post_excerpt', $post->ID) ?? \get_the_content_feed('json'));

        $item = \apply_filters('feedx_item_json', $item, get_post());

        if ($asttag) {
          $tag = (is_string($asttag) && (!empty($asttag))) ? $asttag : 'post';
          $props = '';
          $content = '';

          foreach ($item as $key => $value) {
            if ($key === 'content_html') {
              $content = $value;
            } else {
              $props .= " $key='" . (is_string($value) ? $value : \json_encode($value, JSON_HEX_QUOT && JSON_HEX_TAG && JSON_HEX_AMP)) . "'";
            }
          }

          $item = "<$tag$props>$content</$tag>";
        }

        if (!$all) {
          $callback($item, $post_type, $categorias, $tags, $onlyExcerpt, $postQtd, $more_options, $filters);
        } else {
          $items[] = $item;
        }
      }

      $i++;
    }

    /* RESTAURA O POST ORIGINAL */
    (new \WP_Query([
      'p' => $original_post_id,
      'post_type' => 'any'
    ]))->the_post();

    if ($all) {
      $callback($items, $post_type, $categorias, $tags, $onlyExcerpt, $postQtd, $more_options, $filters);
    }

    $wp_query = $original_post;
  }
}
