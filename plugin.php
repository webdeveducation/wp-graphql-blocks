<?php

/**
 * Plugin Name: WPGraphQL Blocks
 * Plugin URI: https://github.com/webdeveducation/wp-graphql-blocks
 * Description: Enable blocks in WP GraphQL
 * Author: WebDevEducation 
 * Author URI: https://webdeveducation.com
 * Version: 2.0.0
 * Requires at least: 6.0
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace WPGraphQLBlocks;

require_once 'includes/utils.php';

if (!defined('ABSPATH')) {
  die('Silence is golden.');
}

if (!class_exists('WPGraphQLBlocks')) {

  class Block
  {
    public function __construct($data, $post_id, $post_content)
    {
      $this->name = $data['blockName'];
      $attributes = $data['attrs'];

      if ($data['blockName'] == 'core/media-text') {
        // get media item
        $img = wp_get_attachment_image_src($attributes['mediaId'], 'full');
        if ($img) {
          $attributes['width'] = $img[1];
          $attributes['height'] = $img[2];
        }
      }

      if ($data['blockName'] == 'core/cover') {
        if ($attributes['useFeaturedImage']) {
          $attributes['id'] = get_post_thumbnail_id($post_id);
          $attributes['url'] = get_the_post_thumbnail_url($post_id, 'full');
        }
        // get media item
        $img = wp_get_attachment_image_src($attributes['id'], 'full');
        if ($img) {
          $attributes['width'] = $img[1];
          $attributes['height'] = $img[2];
        }
      }

      if ($data['blockName'] == 'core/post-title') {
        $attributes['content'] = get_the_title($data['attrs']['post_id_to_hydrate_template'] ?? $post_id) ?? "";
      }

      if ($data['blockName'] == 'core/image') {
        if (!$attributes['height'] && !$attributes['width']) {
          // get media item
          $img = wp_get_attachment_image_src($attributes['id'], 'full');
          if ($img) {
            $attributes['url'] = $img[0];
            $attributes['width'] = $img[1];
            $attributes['height'] = $img[2];
          }
        }
      }

      if ($data['blockName'] == 'core/table') {
      }

      if ($data['blockName'] == 'core/paragraph') {
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -4);
      }
      if ($data['blockName'] == 'core/heading') {
        // level assumes that if there's no value set for this attributes, then it's default value is 2
        // so we need to make sure this is reflected in the attributes
        if (!isset($attributes['level'])) {
          $attributes['level'] = 2;
        }
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -5);
      }
      if ($data['blockName'] == 'core/columns') {
        // isStackedOnMobile ASSUMES THAT IF THERE'S NO VALUE SET FOR THIS ATTRIBUTE, THEN IT IS SWITCHED ON BY DEFAULT
        // so we need to make sure this is reflected in the attributes
        if (!isset($attributes['isStackedOnMobile'])) {
          $attributes['isStackedOnMobile'] = true;
        }
      }
      if ($data['blockName'] == 'core/gallery') {
        // imageCrop ASSUMES THAT IF THERE'S NO VALUE SET FOR THIS ATTRIBUTE, THEN IT IS SWITCHED ON BY DEFAULT
        // so we need to make sure this is reflected in the attributes
        if (!isset($attributes['imageCrop'])) {
          $attributes['imageCrop'] = true;
        }
      }
      if ($data['blockName'] == 'core/navigation') {
        if (!isset($attributes['showSubmenuIcon'])) {
          $attributes['showSubmenuIcon'] = true;
        }
      }

      $attributes = apply_filters('wp_graphql_blocks_process_attributes', $attributes, $data, $post_id);

      $innerBlocksRaw = $data['innerBlocks'];

      if ($data['blockName'] === 'core/post-content' && isset($post_content)) {
        $innerBlocksRaw = parse_blocks($post_content);
      }

      if ($data['blockName'] === 'core/query') {
        $query_attrs = $attributes['query'];

        $core_query_args = array(
          'post_type' => $query_attrs['postType'],
          'posts_per_page' => $query_attrs['perPage'],
          'ignore_sticky_posts' => $query_attrs['sticky'] === "exclude",
          'cat' => $query_attrs['taxQuery']['category'] ?: [],
          'orderby' => $query_attrs['orderBy'],
          // 'desc' or 'asc'
          'order' => $query_attrs['order'],
          'offset' => $query_attrs['offset'],
          's' => $query_attrs['search'],
          'author' => $query_attrs['author']
        );

        if ($query_attrs['sticky'] === "exclude") {
          $sticky_posts = get_option('sticky_posts');
          $core_query_args['post__not_in'] = $sticky_posts;
        } else if ($query_attrs['sticky'] === "only") {
          $sticky_posts = get_option('sticky_posts');
          $core_query_args['post__in'] = $sticky_posts;
        }

        $query = new \WP_Query($core_query_args);

        $post_query_result = array();

        if ($query->have_posts()) {
          while ($query->have_posts()) {
            $query->the_post();
            $post_result = get_post(); // Get the entire post and store it in a variable
            $post_query_result[] = $post_result;
          }
          foreach ($innerBlocksRaw as $key1 => $innerBlock) {
            if ($innerBlock['blockName'] === "core/query-pagination") {
              foreach ($innerBlock['innerBlocks'] as $key2 => $paginationInnerBlock) {
                if ($paginationInnerBlock['blockName'] === "core/query-pagination-numbers") {
                  $innerBlocksRaw[$key1]['innerBlocks'][$key2]['attrs']['totalResults'] = $query->found_posts - intval($query_attrs['offset'] ?? 0);
                  $innerBlocksRaw[$key1]['innerBlocks'][$key2]['attrs']['totalPages'] = ceil(($query->found_posts - intval($query_attrs['offset'] ?? 0)) / $query_attrs['perPage']);
                  $innerBlocksRaw[$key1]['innerBlocks'][$key2]['attrs']['queryId'] = $attributes['queryId'];
                  break;
                }
              }
              break;
            } else if ($innerBlock['blockName'] === 'core/post-template') {
              $key_for_post_template = $key1;
              $core_post_template = $innerBlock;
              $attributes['postTemplateRaw'] = $core_post_template;
            }
          }
          wp_reset_postdata();
        }

        if (isset($key_for_post_template) && isset($core_post_template)) {
          // replace the element at the specified index
          $core_post_template_block_string = render_block($core_post_template);
          // at this point, add class names to the ul tag, based on the $data['attrs'] (i.e. the core/query attributes)
          $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";

          if ($data['attrs'] && $data['attrs']['displayLayout'] && $data['attrs']['displayLayout']['type'] === "flex") {
            $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";
            $core_post_template_block_string = str_replace("class=\"", "class=\"is-flex-container ", $core_post_template_block_string);
          }

          if ($data['attrs'] && $data['attrs']['displayLayout'] && isset($data['attrs']['displayLayout']['columns'])) {
            $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";
            $core_post_template_block_string = str_replace("class=\"", "class=\"columns-" . $data['attrs']['displayLayout']['columns'] . " ", $core_post_template_block_string);
          }

          $loop_inner_blocks = [];

          foreach ($post_query_result as $single_post_result) {
            $core_post_template['attrs'] = ['post_id_to_hydrate_template' => $single_post_result->ID];
            $loop_inner_blocks[] = [
              'blockName' => 'wp-block-tools/loop-item',
              'innerHTML' => "<li class=\"wp-block-post post-" . $single_post_result->ID . " post type-post status-publish format-standard hentry category-sub-cool-category\"></li>",
              'innerBlocks' => array($core_post_template)
            ];
          }
          array_splice($innerBlocksRaw, $key_for_post_template, 1, array([
            'blockName' => 'wp-block-tools/loop',
            'innerHTML' => $core_post_template_block_string,
            'innerBlocks' => $loop_inner_blocks
          ]));
        }
      }

      // handle template-parts
      if ($data['blockName'] === 'core/template-part' && !empty($data['attrs']['slug'])) {
        $slug = $data['attrs']['slug'];

        $temp = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template_part');
        if ($temp) {
          $innerBlocksRaw = parse_blocks($temp->content);
        }
      }

      // handle core/pattern
      if ($data['blockName'] === 'core/pattern' && !empty($data['attrs']['slug'])) {
        $block_pattern_slug = $data['attrs']['slug'];
        $registered_block_patterns = \WP_Block_Patterns_Registry::get_instance()->get_all_registered();

        // Find the block pattern by slug
        $block_pattern = null;
        foreach ($registered_block_patterns as $pattern) {
          if ($pattern['name'] === $block_pattern_slug) {
            $block_pattern = $pattern;
            break;
          }
        }

        if ($block_pattern) {
          // Retrieve the content/markup of the block pattern
          $block_pattern_content = $block_pattern['content'];
          $innerBlocksRaw = parse_blocks($block_pattern_content);
        }
      }

      // handle mapping reusable blocks to innerBlocks.
      if ($data['blockName'] === 'core/block' && !empty($data['attrs']['ref'])) {
        $ref = $data['attrs']['ref'];
        $reusablePost = get_post($ref);

        if (!empty($reusablePost)) {
          $innerBlocksRaw = parse_blocks($reusablePost->post_content);
        }
      }

      if ($attributes) {
        $this->attributes = $attributes;
      }

      $innerBlocks = [];
      foreach ($innerBlocksRaw as $innerBlock) {
        if (isset($attributes['post_id_to_hydrate_template'])) {
          $innerBlock['attrs']['post_id_to_hydrate_template'] = $attributes['post_id_to_hydrate_template'];
        }
        $innerBlocks[] = new Block($innerBlock, $post_id, $post_content);
      }

      if ($data['attrs']['post_id_to_hydrate_template']) {
        // set global post object here
        global $post;
        $post = get_post($data['attrs']['post_id_to_hydrate_template']);
        setup_postdata($post);
      }

      $blockString = render_block($data);
      wp_reset_postdata();
      $originalContent = str_replace("\n", "", $data['innerHTML']);
      $dynamicContent = str_replace("\n", "", $blockString);

      $htmlContent = $dynamicContent ? $dynamicContent : $originalContent;
      $htmlContent = str_replace("\n", "", $htmlContent);
      $htmlContent = str_replace("\r", "", $htmlContent);
      $htmlContent = str_replace("\t", "", $htmlContent);

      if ($htmlContent && $data['blockName'] !== "core/pattern") {
        // if not core/cover, core/media-text, and has inner blocks, gut
        // out the inner html from the top level tag, as it's not needed
        if ($data['blockName'] !== 'wp-block-tools/loop-item' && $data['blockName'] !== 'core/cover' && $data['blockName'] !== 'core/media-text' && $data['blockName'] !== "core/navigation" && $data['blockName'] !== 'core/navigation-submenu') {
          if (count($innerBlocks)) {
            $dom = new \DOMDocument();
            $htmlString = "<html><body>" . $htmlContent . "</body></html>";
            $htmlString = str_replace("\n", "", $htmlString);
            $htmlString = str_replace("\r", "", $htmlString);
            $htmlString = str_replace("\t", "", $htmlString);
            $dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $bodyElement = $dom->getElementsByTagName('body')->item(0);
            $topLevelElement = $bodyElement->childNodes[0];
            if ($topLevelElement) {
              while ($topLevelElement->hasChildNodes()) {
                $topLevelElement->removeChild($topLevelElement->firstChild);
              }
              $modifiedHtml = $dom->saveHTML();
              unset($dom);
              $modifiedHtml = str_replace("<html>", "", $modifiedHtml);
              $modifiedHtml = str_replace("</html>", "", $modifiedHtml);
              $modifiedHtml = str_replace("<body>", "", $modifiedHtml);
              $modifiedHtml = str_replace("</body>", "", $modifiedHtml);
              $htmlContent = $modifiedHtml;
            }
          }
        }
        $htmlContent = str_replace("\n", "", $htmlContent);
        $htmlContent = str_replace("\r", "", $htmlContent);
        $htmlContent = str_replace("\t", "", $htmlContent);
        $this->htmlContent = $htmlContent;
      }

      /*if ($core_post_template && $data['blockName'] === 'core/query') {
        $core_post_template_block_string = render_block($core_post_template);
        $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";
        $this->htmlContent = str_replace('</', $core_post_template_block_string . '</', $this->htmlContent);
      }*/

      if ($data['blockName'] === 'core/post-template') {
        unset($this->htmlContent);
      }

      if ($data['blockName'] === "core/comments") {
        $comments_allowed_globally = get_option('default_comment_status');
        $temp_post = get_post($post_id);
        if (!$comments_allowed_globally || ($comments_allowed_globally && $temp_post->comment_status === "closed")) {
          unset($this->htmlContent);
          $innerBlocks = [];
        }
      }

      if (count($innerBlocks)) {
        $this->innerBlocks = $innerBlocks;
      }

      if ($this->name == 'core/gallery') {
        $classId = $this->get_core_gallery_class_id($htmlContent);
        $this->inlineClassnames = $classId;
        $this->inlineStylesheet = $this->get_core_gallery_stylesheet($classId, $attributes);
      }
    }

    private function get_core_gallery_class_id($htmlContent)
    {
      $needle = "wp-block-gallery-";
      $startPos = strpos($htmlContent, $needle);
      $endPos = strpos($htmlContent, " ", $startPos);
      $classId = substr($htmlContent, $startPos, $endPos - $startPos);
      return $classId;
    }

    private function get_core_gallery_stylesheet($classId, $attributes)
    {
      $gap = _wp_array_get($attributes, array('style', 'spacing', 'blockGap'));
      // Skip if gap value contains unsupported characters.
      // Regex for CSS value borrowed from `safecss_filter_attr`, and used here
      // because we only want to match against the value, not the CSS attribute.
      if (is_array($gap)) {
        foreach ($gap as $key => $value) {
          // Make sure $value is a string to avoid PHP 8.1 deprecation error in preg_match() when the value is null.
          $value = is_string($value) ? $value : '';
          $value = $value && preg_match('%[\\\(&=}]|/\*%', $value) ? null : $value;

          // Get spacing CSS variable from preset value if provided.
          if (is_string($value) && str_contains($value, 'var:preset|spacing|')) {
            $index_to_splice = strrpos($value, '|') + 1;
            $slug            = _wp_to_kebab_case(substr($value, $index_to_splice));
            $value           = "var(--wp--preset--spacing--$slug)";
          }

          $gap[$key] = $value;
        }
      } else {
        // Make sure $gap is a string to avoid PHP 8.1 deprecation error in preg_match() when the value is null.
        $gap = is_string($gap) ? $gap : '';
        $gap = $gap && preg_match('%[\\\(&=}]|/\*%', $gap) ? null : $gap;

        // Get spacing CSS variable from preset value if provided.
        if (is_string($gap) && str_contains($gap, 'var:preset|spacing|')) {
          $index_to_splice = strrpos($gap, '|') + 1;
          $slug            = _wp_to_kebab_case(substr($gap, $index_to_splice));
          $gap             = "var(--wp--preset--spacing--$slug)";
        }
      }

      // --gallery-block--gutter-size is deprecated. --wp--style--gallery-gap-default should be used by themes that want to set a default
      // gap on the gallery.
      $fallback_gap = 'var( --wp--style--gallery-gap-default, var( --gallery-block--gutter-size, var( --wp--style--block-gap, 0.5em ) ) )';
      $gap_value    = $gap ? $gap : $fallback_gap;
      $gap_column   = $gap_value;

      if (is_array($gap_value)) {
        $gap_row    = isset($gap_value['top']) ? $gap_value['top'] : $fallback_gap;
        $gap_column = isset($gap_value['left']) ? $gap_value['left'] : $fallback_gap;
        $gap_value  = $gap_row === $gap_column ? $gap_row : $gap_row . ' ' . $gap_column;
      }

      // The unstable gallery gap calculation requires a real value (such as `0px`) and not `0`.
      if ('0' === $gap_column) {
        $gap_column = '0px';
      }

      // Set the CSS variable to the column value, and the `gap` property to the combined gap value.
      $style = '.wp-block-gallery.' . $classId . '{ --wp--style--unstable-gallery-gap: ' . $gap_column . '; gap: ' . $gap_value . '}';
      return $style;
    }
  }

  final class WPGraphQLBlocks
  {
    private static $instance;
    public static function instance()
    {
      if (!isset(self::$instance)) {
        self::$instance = new WPGraphQLBlocks();
      }

      return self::$instance;
    }

    public function init()
    {
      add_action('graphql_register_types', function () {
        register_graphql_scalar('JSON', [
          'serialize' => function ($value) {
            return json_decode($value);
          }
        ]);

        register_graphql_field('RootQuery', 'coreQuery', [
          'type' => 'JSON',
          'description' => __('Returns'),
          'args' => [
            'postId' => [
              'type' => ['non_null' => 'Int'],
              'description' => 'Argument 1 description',
            ],
            'queryId' => [
              'type' => ['non_null' => 'Int'],
              'description' => 'Argument 2 description',
            ],
            'page' => [
              'type' => ['non_null' => 'Int'],
            ],
          ],
          'resolve' => function ($node, $args) {
            // get post by postId
            $postId = $args['postId'];
            $queryId = $args['queryId'];
            $page = $args['page'];
            $post = get_post($postId);
            $mappedBlocks = get_mapped_blocks($post);
            $mappedBlocksResult = [];
            $queryBlock = get_query_by_id($queryId, $mappedBlocks);
            if (isset($queryBlock)) {
              // query found!
              // if query found, grab attributes.query
              $query_attrs = $queryBlock->attributes['query'];
              // if query found, grab the postTemplateRaw
              $postTemplateRaw = $queryBlock->attributes['postTemplateRaw'];

              $core_query_args = array(
                'post_type' => $query_attrs['postType'],
                'posts_per_page' => $query_attrs['perPage'],
                'ignore_sticky_posts' => $query_attrs['sticky'] === "exclude",
                'cat' => $query_attrs['taxQuery']['category'] ?: [],
                'orderby' => $query_attrs['orderBy'],
                // 'desc' or 'asc'
                'order' => $query_attrs['order'],
                //'offset' => $query_attrs['offset'] ?? 0,
                's' => $query_attrs['search'],
                'author' => $query_attrs['author'],
                'paged' => $page,
              );

              if (isset($query_attrs['offset']) && $query_attrs['offset'] != "0") {
                $core_query_args['offset'] = $query_attrs['offset'] * $page;
              }

              if ($query_attrs['sticky'] === "exclude") {
                $sticky_posts = get_option('sticky_posts');
                $core_query_args['post__not_in'] = $sticky_posts;
              } else if ($query_attrs['sticky'] === "only") {
                $sticky_posts = get_option('sticky_posts');
                $core_query_args['post__in'] = $sticky_posts;
              }

              $query = new \WP_Query($core_query_args);

              $post_query_result = array();

              if ($query->have_posts()) {
                while ($query->have_posts()) {
                  $query->the_post();
                  $post_result = get_post(); // Get the entire post and store it in a variable
                  $post_query_result[] = $post_result;
                }
              }

              wp_reset_postdata();

              $core_post_template = $postTemplateRaw;

              if (isset($core_post_template)) {
                // replace the element at the specified index
                $core_post_template_block_string = render_block($core_post_template);
                // at this point, add class names to the ul tag, based on the $data['attrs'] (i.e. the core/query attributes)
                $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";

                if ($queryBlock->attributes && $queryBlock->attributes['displayLayout'] && $queryBlock->attributes['displayLayout']['type'] === "flex") {
                  $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";
                  $core_post_template_block_string = str_replace("class=\"", "class=\"is-flex-container ", $core_post_template_block_string);
                }

                if ($queryBlock->attributes && $queryBlock->attributes['displayLayout'] && isset($queryBlock->attributes['displayLayout']['columns'])) {
                  $core_post_template_block_string = substr($core_post_template_block_string, 0, strpos($core_post_template_block_string, ">")) . "></ul>";
                  $core_post_template_block_string = str_replace("class=\"", "class=\"columns-" . $queryBlock->attributes['displayLayout']['columns'] . " ", $core_post_template_block_string);
                }

                $loop_inner_blocks = [];

                foreach ($post_query_result as $single_post_result) {
                  $core_post_template['attrs'] = ['post_id_to_hydrate_template' => $single_post_result->ID];
                  $loop_inner_blocks[] = [
                    'blockName' => 'wp-block-tools/loop-item',
                    'innerHTML' => "<li class=\"wp-block-post post-" . $single_post_result->ID . " post type-post status-publish format-standard hentry category-sub-cool-category\"></li>",
                    'innerBlocks' => array($core_post_template)
                  ];
                }
                $result = array([
                  'blockName' => 'wp-block-tools/loop',
                  'innerHTML' => $core_post_template_block_string,
                  'innerBlocks' => $loop_inner_blocks
                ]);
                foreach ($result as $block) {
                  if (isset($block['blockName'])) {
                    $mappedBlocksResult[] = new Block($block, $postId, null);
                  }
                }
              }
            }
            $mappedBlocksResult = clean_attributes($mappedBlocksResult);
            return wp_json_encode($mappedBlocksResult);
          }
        ]);

        register_graphql_field('Node', 'blocks', [
          'type' => 'JSON',
          'description' => __('Returns all blocks as a JSON object', 'wp-graphql-blocks'),
          'resolve' => function ($post, $args, $context, $info) {
            $mappedBlocks = get_mapped_blocks($post);
            $mappedBlocks = clean_attributes($mappedBlocks);
            return wp_json_encode($mappedBlocks);
          }
        ]);
      });
    }
  }
}

WPGraphQLBlocks::instance()->init();
