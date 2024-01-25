<?php

/**
 * Plugin Name: WPGraphQL Blocks
 * Plugin URI: https://github.com/webdeveducation/wp-graphql-blocks
 * Description: Enable blocks in WP GraphQL
 * Author: WebDevEducation 
 * Author URI: https://wp-block-tools.com
 * Version: 2.1.1
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
    public function __construct($data, $post_id, $post_content, $query_args, $global_theme_styles, $global_db_styles)
    {
      $blockString = render_block($data);
      wp_reset_postdata();
      $originalContent = str_replace("\n", "", $data['innerHTML']);
      $dynamicContent = str_replace("\n", "", $blockString);

      // dynamicContent and originalContent are false by default.
      // if they are true, add them
      if($query_args['dynamicContent']){
        $this->dynamicContent = $dynamicContent;
      }

      if($query_args['originalContent']){
        $this->originalContent = $originalContent;
      }

      $htmlContent = $dynamicContent ? $dynamicContent : $originalContent;
      $htmlContent = str_replace("\n", "", $htmlContent);
      $htmlContent = str_replace("\r", "", $htmlContent);
      $htmlContent = str_replace("\t", "", $htmlContent);
      
      $this->name = $data['blockName'];
      $attributes = $data['attrs'];

      if($attributes['style']['elements']['button']){
        $global_styles['core/button'] = $attributes['style']['elements']['button'];
      }

      if($global_theme_styles[$data['blockName']]){
        $attributes['globalStyles'] = array_merge($attributes['globalStyles'] ?? [], $global_theme_styles[$data['blockName']]);
      }

      if($global_db_styles[$data['blockName']]){
        $attributes['globalStyles'] = array_merge($attributes['globalStyles'] ?? [], $global_db_styles[$data['blockName']]);
      }

      if($data['blockName'] === "core/site-logo"){
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        if($custom_logo_id){
          $logo = wp_get_attachment_image_src( $custom_logo_id , 'full' );
          $image_alt = get_post_meta($custom_logo_id, '_wp_attachment_image_alt', TRUE);
          $attributes['id'] = (int)$custom_logo_id;
          $attributes['url'] = $logo[0];
          $attributes['alt'] = $image_alt;
        }
      }

      if ($data['blockName'] == 'core/media-text') {
        // get media item
        $img = wp_get_attachment_image_src($attributes['mediaId'], 'full');
        if ($img) {
          $attributes['width'] = $img[1];
          $attributes['height'] = $img[2];
        }
      }

      if($data['blockName'] === 'core/post-featured-image'){
        $attributes['id'] = get_post_thumbnail_id($post_id);
        $attributes['url'] = get_the_post_thumbnail_url($post_id, 'full');
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
            $image_alt = get_post_meta($attributes['id'], '_wp_attachment_image_alt', TRUE);
            $attributes['url'] = $img[0];
            $attributes['width'] = $img[1];
            $attributes['height'] = $img[2];
            if($image_alt){
              $attributes['alt'] = $image_alt;
            }

            $dom = new \DOMDocument();
            $htmlString = "<html><body>" . $htmlContent . "</body></html>";
            $htmlString = str_replace("\n", "", $htmlString);
            $htmlString = str_replace("\r", "", $htmlString);
            $htmlString = str_replace("\t", "", $htmlString);
            $dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            $img_tags = $dom->getElementsByTagName('img');
            if($img_tags && $img_tags[0]){
              $alt = $img_tags[0]->getAttribute('alt');
              if($alt){
                $attributes['alt'] = $alt;
              }
            }
            $fig_captions = $dom->getElementsByTagName('figcaption');
            if($fig_captions && $fig_captions[0]){
              $caption = $fig_captions[0]->nodeValue;
              if($caption){
                $attributes['caption'] = $caption;
              }
            }
            $anchors = $dom->getElementsByTagName('a');
            if($anchors && $anchors[0]){
              $href = $anchors[0]->getAttribute('href');
              $target = $anchors[0]->getAttribute('target');
              $rel = $anchors[0]->getAttribute('rel');
              $class_names = $anchors[0]->getAttribute('class');
              if($href){
                $attributes['href'] = $href;
              }
              if($target){
                $attributes['target'] = $target;
              }
              if($rel){
                $attributes['rel'] = $rel;
              }
              if($class_names){
                $attributes['linkClassName'] = $class_names;
              }
            }
            unset($dom);
          }
        }
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
                }
              }
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

      // handle mapping reference to other blocks to innerBlocks.
      if (!empty($data['attrs']['ref'])) {
        $ref = $data['attrs']['ref'];
        $reusablePost = get_post($ref);

        if (!empty($reusablePost)) {
          $innerBlocksRaw = parse_blocks($reusablePost->post_content);
        }
      }

      $innerBlocks = [];
      foreach ($innerBlocksRaw as $innerBlock) {
        if (isset($attributes['post_id_to_hydrate_template'])) {
          $innerBlock['attrs']['post_id_to_hydrate_template'] = $attributes['post_id_to_hydrate_template'];
        }
        if (isset($innerBlock['blockName'])) {
          $innerBlocks[] = new Block($innerBlock, $post_id, $post_content, $query_args, $global_theme_styles, $global_db_styles);
        }
      }

      if ($data['attrs']['post_id_to_hydrate_template']) {
        // set global post object here
        global $post;
        $post = get_post($data['attrs']['post_id_to_hydrate_template']);
        setup_postdata($post);
      }

      

      if($data['blockName'] === "core/list-item"){
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -5);
      }

      if ($data['blockName'] == 'core/paragraph') {
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -4);
        if($attributes['align']){
          $attributes['textAlign'] = $attributes['align'];
          unset($attributes['align']);
        }
      }
      if ($data['blockName'] == 'core/button') {
        $dom = new \DOMDocument();
        $htmlString = "<html><body>" . $htmlContent . "</body></html>";
        $htmlString = str_replace("\n", "", $htmlString);
        $htmlString = str_replace("\r", "", $htmlString);
        $htmlString = str_replace("\t", "", $htmlString);
        $dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $anchors = $dom->getElementsByTagName('a');
        if($anchors && $anchors[0]){
          $url = $anchors[0]->getAttribute('href');
          if($url){
            $attributes['url'] = $url;
          }
          $innerHtml = $dom->saveHTML($anchors[0]);
          $innerHtml = preg_replace('/^<a[^>]*>|<\/a>$/', '', $innerHtml);
          if($innerHtml){
            $attributes['content'] = $innerHtml;
          }
        }
        unset($dom);
      }
      if ($data['blockName'] == 'core/heading') {
        // level assumes that if there's no value set for this attributes, then it's default value is 2
        // so we need to make sure this is reflected in the attributes
        if (!isset($attributes['level'])) {
          $attributes['level'] = 2;
        }
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -5);
      }

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
        if($query_args['htmlContent']){
          $this->htmlContent = $htmlContent;
        }
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

      if ($attributes && $query_args['attributes']) {
        $this->attributes = $attributes;
      }
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
            ],
            'queryId' => [
              'type' => ['non_null' => 'Int'],
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
                    $mappedBlocksResult[] = new Block($block, $postId, null, $query_args, [], []);
                  }
                }
              }
            }
            $mappedBlocksResult = clean_attributes($mappedBlocksResult);
            return wp_json_encode($mappedBlocksResult);
          }
        ]);

        register_graphql_field('ContentNode', 'blocks', [
          'type' => 'JSON',
          'args' => [
            'postTemplate' => [
              'type' => 'Boolean',
              'defaultValue' => true,
              'description' => 'Also return the post template as JSON blocks. If set to false, only the post content will be returned as JSON blocks.',
            ],
            'attributes' => [
              'type' => 'Boolean',
              'defaultValue' => true,
              'description' => "Return each block's attributes as part of the response",
            ],
            'htmlContent' => [
              'type' => 'Boolean',
              'defaultValue' => false,
              'description' => 'Return the HTML markup of each block',
            ],
            'dynamicContent' => [
              'type' => 'Boolean',
              'defaultValue' => false,
              'description' => '(Used for backwards compatibility with @webdeveducation/wp-block-tools)',
            ],
            'originalContent' => [
              'type' => 'Boolean',
              'defaultValue' => false,
              'description' => '(Used for backwards compatibility with @webdeveducation/wp-block-tools)',
            ],
          ],
          'description' => __('Returns all blocks as a JSON object', 'wp-graphql-blocks'),
          'resolve' => function ($post, $args, $context, $info) {
            $mappedBlocks = get_mapped_blocks($post, $args);
            $mappedBlocks = clean_attributes($mappedBlocks);
            return wp_json_encode($mappedBlocks);
          }
        ]);

        register_graphql_field( 'RootQuery', 'siteLogo', [
          'type' => 'MediaItem',
          'description' => __( 'The logo set in the customizer', 'wp-graphql-blocks' ),
          'resolve' => function() {
            $logo_id = get_theme_mod( 'custom_logo' );
      
            if ( ! isset( $logo_id ) || ! absint( $logo_id ) ) {
              return null;
            }
      
            $media_object = get_post( $logo_id );
            return new \WPGraphQL\Model\Post( $media_object );
          }
        ]);

        register_graphql_field( 'RootQuery', 'cssVariables', [
          'type' => 'String',
          'description' => __( 'All CSS variables for the current theme', 'wp-graphql-blocks' ),
          'resolve' => function() {
            $stylesheet = wp_get_global_stylesheet();
            preg_match_all('/--[^;]*:[^;]*;/', $stylesheet, $matches);
            $variables = implode($matches[0]);
            $variables = str_replace("--wp--preset", "", $variables);
            $variables = str_replace("--wp--style--global", "", $variables);
            $variables = str_replace("--wp--style", "", $variables);
            return $variables;
          }
        ]);
      });
    }
  }
}

WPGraphQLBlocks::instance()->init();
