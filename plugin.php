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
if (!defined('ABSPATH')) {
	die('Silence is golden.');
}

if (!class_exists('WPGraphQLBlocks')) {

  class Block {
    public function __construct($data, $post_id, $post_content, $args) {
      //wp_send_json($post_id);
      $this->name = $data['blockName'];

      $attributes = $data['attrs'];

      if($data['blockName'] == 'core/media-text'){
        // get media item
        $img = wp_get_attachment_image_src($attributes['mediaId'], 'full');
        if($img){
          $attributes['width'] = $img[1];
          $attributes['height'] = $img[2];
        }
      }

      //if($data['blockName'])
  
      if($data['blockName'] == 'core/cover'){
        if($attributes['useFeaturedImage']){
          $attributes['id'] = get_post_thumbnail_id($post_id);
          $attributes['url'] = get_the_post_thumbnail_url($post_id, 'full');
        }
        // get media item
        $img = wp_get_attachment_image_src($attributes['id'], 'full');
        if($img){
          $attributes['width'] = $img[1];
          $attributes['height'] = $img[2];
        }
      }
  
      if($data['blockName'] == 'core/post-title'){
        $attributes['content'] = get_the_title($post_id) ?? "";
      }
  
      if($data['blockName'] == 'core/image'){
        if(!$attributes['height'] && !$attributes['width']){
          // get media item
          $img = wp_get_attachment_image_src($attributes['id'], 'full');
          if($img){
            $attributes['url'] = $img[0];
            $attributes['width'] = $img[1];
            $attributes['height'] = $img[2];
          }
        }
      }

      // MOVE THESE TO FRONT END
      /*
      if($data['blockName'] == 'core/table'){

      }

      if($data['blockName'] == 'core/paragraph'){
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -4);
      }
      if($data['blockName'] == 'core/heading'){
        // level assumes that if there's no value set for this attributes, then it's default value is 2
        // so we need to make sure this is reflected in the attributes
        if(!isset($attributes['level'])){
          $attributes['level'] = 2;
        }
        $attributes['content'] = substr($htmlContent, strpos($htmlContent, ">") + 1, -5);
      }
      if($data['blockName'] == 'core/columns'){
        // isStackedOnMobile ASSUMES THAT IF THERE'S NO VALUE SET FOR THIS ATTRIBUTE, THEN IT IS SWITCHED ON BY DEFAULT
        // so we need to make sure this is reflected in the attributes
        if(!isset($attributes['isStackedOnMobile'])){
          $attributes['isStackedOnMobile'] = true;
        }
      }
      if($data['blockName'] == 'core/gallery'){
        // imageCrop ASSUMES THAT IF THERE'S NO VALUE SET FOR THIS ATTRIBUTE, THEN IT IS SWITCHED ON BY DEFAULT
        // so we need to make sure this is reflected in the attributes
        if(!isset($attributes['imageCrop'])){
          $attributes['imageCrop'] = true;
        }
      }*/

      $attributes = apply_filters('wp_graphql_blocks_process_attributes', $attributes, $data, $post_id);
      if($args['attributes'] && $attributes){
        $this->attributes = $attributes;
      }

      $innerBlocksRaw = $data['innerBlocks'];

      if($data['blockName'] === 'core/post-content'){
        $innerBlocksRaw = parse_blocks($post_content);
      }

      // handle template-parts
      if($data['blockName'] === 'core/template-part' && !empty($data['attrs']['slug'])){
        // load file from /parts
        // this is just a normal page, get the default page template
        $file_content = file_get_contents(get_stylesheet_directory() . "/parts/" . $data['attrs']['slug'] . ".html");
        if($file_content){
          $innerBlocksRaw = parse_blocks($file_content);
        }else{
          $file_content = file_get_contents(get_template_directory() . "/parts/" . $data['attrs']['slug'] . ".html");
          if($file_content){
            $innerBlocksRaw = parse_blocks($file_content);
          }
        }
      }

      // handle core/pattern
      if($data['blockName'] === 'core/pattern' && !empty($data['attrs']['slug'])){
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
          //wp_send_json($innerBlocksRaw);
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

      $innerBlocks = [];
      foreach($innerBlocksRaw as $innerBlock){
        $innerBlocks[] = new Block($innerBlock, $post_id, $post_content, $args);
      }

      $blockString = render_block($data);
      $originalContent = str_replace("\n", "", $data['innerHTML']);
      $dynamicContent = str_replace("\n", "", $blockString);
      $htmlContent = $dynamicContent ? $dynamicContent : $originalContent;
      if($args['htmlContent'] && $htmlContent && $data['blockName'] !== "core/pattern"){
        // if not core/cover, core/media-text, and has inner blocks, gut
        // out the inner html from the top level tag, as it's not needed
        if($data['blockName'] != 'core/cover' && $data['blockName'] != 'core/media-text' && count($innerBlocks)){
          $dom = new \DOMDocument();
          $htmlString = "<html><body>" . $htmlContent . "</body></html>";
          $htmlString = str_replace("\n", "", $htmlString);
          $htmlString = str_replace("\r", "", $htmlString);
          $htmlString = str_replace("\t", "", $htmlString);
          $dom->loadHTML($htmlString, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
          $bodyElement = $dom->getElementsByTagName('body')->item(0);
          $topLevelElement = $bodyElement->childNodes[0];
         
          if($topLevelElement){
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
        $htmlContent = str_replace("\n", "", $htmlContent);
        $htmlContent = str_replace("\r", "", $htmlContent);
        $htmlContent = str_replace("\t", "", $htmlContent);
        $this->htmlContent = $htmlContent;
      }

      if($args['innerBlocks'] && count($innerBlocks)){
        $this->innerBlocks = $innerBlocks;
      }

      if($this->name == 'core/gallery'){
        $classId = $this->get_core_gallery_class_id($htmlContent);
        $this->inlineClassnames = $classId;
        $this->inlineStylesheet = $this->get_core_gallery_stylesheet($classId, $attributes);
      }
    }

    private function get_core_gallery_class_id($htmlContent){
      $needle = "wp-block-gallery-";
      $startPos = strpos($htmlContent, $needle);
      $endPos = strpos($htmlContent, " ", $startPos);
      $classId = substr($htmlContent, $startPos, $endPos - $startPos);
      return $classId;
    }
  
    private function get_core_gallery_stylesheet($classId, $attributes){		
      $gap = _wp_array_get( $attributes, array( 'style', 'spacing', 'blockGap' ) );
      // Skip if gap value contains unsupported characters.
      // Regex for CSS value borrowed from `safecss_filter_attr`, and used here
      // because we only want to match against the value, not the CSS attribute.
      if ( is_array( $gap ) ) {
        foreach ( $gap as $key => $value ) {
          // Make sure $value is a string to avoid PHP 8.1 deprecation error in preg_match() when the value is null.
          $value = is_string( $value ) ? $value : '';
          $value = $value && preg_match( '%[\\\(&=}]|/\*%', $value ) ? null : $value;
  
          // Get spacing CSS variable from preset value if provided.
          if ( is_string( $value ) && str_contains( $value, 'var:preset|spacing|' ) ) {
            $index_to_splice = strrpos( $value, '|' ) + 1;
            $slug            = _wp_to_kebab_case( substr( $value, $index_to_splice ) );
            $value           = "var(--wp--preset--spacing--$slug)";
          }
  
          $gap[ $key ] = $value;
        }
      } else {
        // Make sure $gap is a string to avoid PHP 8.1 deprecation error in preg_match() when the value is null.
        $gap = is_string( $gap ) ? $gap : '';
        $gap = $gap && preg_match( '%[\\\(&=}]|/\*%', $gap ) ? null : $gap;
  
        // Get spacing CSS variable from preset value if provided.
        if ( is_string( $gap ) && str_contains( $gap, 'var:preset|spacing|' ) ) {
          $index_to_splice = strrpos( $gap, '|' ) + 1;
          $slug            = _wp_to_kebab_case( substr( $gap, $index_to_splice ) );
          $gap             = "var(--wp--preset--spacing--$slug)";
        }
      }
  
      // --gallery-block--gutter-size is deprecated. --wp--style--gallery-gap-default should be used by themes that want to set a default
      // gap on the gallery.
      $fallback_gap = 'var( --wp--style--gallery-gap-default, var( --gallery-block--gutter-size, var( --wp--style--block-gap, 0.5em ) ) )';
      $gap_value    = $gap ? $gap : $fallback_gap;
      $gap_column   = $gap_value;
  
      if ( is_array( $gap_value ) ) {
        $gap_row    = isset( $gap_value['top'] ) ? $gap_value['top'] : $fallback_gap;
        $gap_column = isset( $gap_value['left'] ) ? $gap_value['left'] : $fallback_gap;
        $gap_value  = $gap_row === $gap_column ? $gap_row : $gap_row . ' ' . $gap_column;
      }
  
      // The unstable gallery gap calculation requires a real value (such as `0px`) and not `0`.
      if ( '0' === $gap_column ) {
        $gap_column = '0px';
      }
  
      // Set the CSS variable to the column value, and the `gap` property to the combined gap value.
      $style = '.wp-block-gallery.' . $classId . '{ --wp--style--unstable-gallery-gap: ' . $gap_column . '; gap: ' . $gap_value . '}';
      return $style;
    }
  }
  
	final class WPGraphQLBlocks {
		private static $instance;
		public static function instance() {
			if (!isset(self::$instance)) {
				self::$instance = new WPGraphQLBlocks();
			}

			return self::$instance;
		}

		public function init() {
      add_action( 'graphql_register_types', function() {
        register_graphql_scalar('JSON', [
          'serialize' => function ($value) {
            return json_decode($value);
          }
        ]);
        
        register_graphql_field( 'ContentNode', 'blocks', [
          'type' => 'JSON',
          'args' => [
            'htmlContent' => [
              'type' => 'Boolean',
              'description' => 'Whether to return the htmlContent for each block',
              'defaultValue' => true,
            ],
            'attributes' => [
              'type' => 'Boolean',
              'description' => 'Whether to return the attributes for each block',
              'defaultValue' => true,
            ],
            'name' => [
              'type' => 'Boolean',
              'description' => 'Whether to return the name for each block',
              'defaultValue' => true,
            ],
            'innerBlocks' => [
              'type' => 'Boolean',
              'description' => 'Whether to return the innerBlocks for each block',
              'defaultValue' => true,
            ],
          ],
          'description' => __( 'Returns all blocks as a JSON object', 'wp-graphql-blocks' ),
          'resolve' => function( \WPGraphQL\Model\Post $post, $args, $context, $info ) {        
            $the_post = get_post($post->ID);
            $blocks = parse_blocks($the_post->post_content);
            
            $templateName = $post->template['templateName'];

            // Get the post meta values
            $the_post_id = $the_post->ID;
            $the_post_content = $the_post->post_content;
            $the_post_type = $the_post->post_type;

            // START CHECK DB FIRST FOR A TEMPLATE
            $meta_value = get_post_meta($the_post_id, '_wp_page_template', true);            
            if($meta_value){
              
              // first get from wp_postmeta, where post_id === $post->ID && meta_key === _wp_page_template
              // if exists, then get the meta_value (i.e. "single-sidebar")
              // use the meta_value in a new query to query the wp_posts table where post_name === meta_value && post_type === "wp_template"

              // Define your query arguments
              $templateQueryArgs = array(
                'post_type'      => 'wp_template', // Specify the post type
                'post_name'    => $meta_value, // Specify the post name
                'posts_per_page' => 1, // Limit the query to 1 post
              );

              // Retrieve the single post based on the query arguments
              $postsFromQuery = get_posts($templateQueryArgs);

              // Check if a post was found
              if ($postsFromQuery) {
                $templatePost = $postsFromQuery[0]; // Get the first (and only) post
                $templatePostContent = $templatePost->post_content;
                $templateBlocks = parse_blocks($templatePostContent);
              }
            }else {
              // THIS POST USES THE DEFAULT TEMPLATE
              // check if page or post
              if($the_post_type === "page"){
                $front_page_id = get_option('page_on_front');
                // first check to see if a template file exists for page-{pageId}.html
                // if not, check to see if a template file exists for page-{slug}.html

                if(!$front_page_id){
                  // TODO: cater for just posts page
                }else if($front_page_id == $the_post_id){
                  // get front page default template
                  // get_stylesheet_directory for current theme
                  // get_template_directory for possible parent theme
                  $file_content = file_get_contents(get_stylesheet_directory() . "/templates/front-page.html");
                  if($file_content){
                    $templateBlocks = parse_blocks($file_content);
                  }else{
                    $file_content = file_get_contents(get_template_directory() . "/templates/front-page.html");
                    if($file_content){
                      $templateBlocks = parse_blocks($file_content);
                    }
                  }
                }else {
                  // this is just a normal page, get the default page template
                  $file_content = file_get_contents(get_stylesheet_directory() . "/templates/page.html");
                  if($file_content){
                    $templateBlocks = parse_blocks($file_content);
                  }else{
                    $file_content = file_get_contents(get_template_directory() . "/templates/page.html");
                    if($file_content){
                      $templateBlocks = parse_blocks($file_content);
                    }
                  }
                }
              }else {
                // get the default post template
                // this is just a normal page
                $file_content = file_get_contents(get_stylesheet_directory() . "/templates/single.html");
                if($file_content){
                  $templateBlocks = parse_blocks($file_content);
                }else{
                  $file_content = file_get_contents(get_template_directory() . "/templates/single.html");
                  if($file_content){
                    $templateBlocks = parse_blocks($file_content);
                  }
                }
              }
            }
            // END CHECK DB FIRST FOR A TEMPLATE

            // if there are no template blocks, i.e. no template was found, 
            // then just set equal to the page blocks;
            if(!$templateBlocks){
              $templateBlocks = $blocks;
            }

            $mappedBlocks = [];
            foreach($templateBlocks as $block){
              if(isset($block['blockName'])){
                $mappedBlocks[] = new Block($block, $the_post_id, $the_post_content, $args);
              }
            }
            return wp_json_encode($mappedBlocks);
          }
        ] );
      });
		}
	}
}

WPGraphQLBlocks::instance()->init();

?>