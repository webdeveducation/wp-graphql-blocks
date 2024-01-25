<?php

namespace WPGraphQLBlocks;

function get_block_template_by_slug($slug)
{
  $page_template = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template');
  return $page_template;
}

function get_default_template_for_cpt($post_id, $post_type, $post_slug)
{
  // single-{posttype}-{id}.html
  $page_template = get_block_template_by_slug("single-" . $post_type . "-" . $post_id);
  if ($page_template) {
    return $page_template;
  }

  // single-{posttype}-{slug}.html
  $page_template = get_block_template_by_slug("single-" . $post_type . "-" . $post_slug);
  if ($page_template) {
    return $page_template;
  }

  // single-{posttype}.html
  $page_template = get_block_template_by_slug("single-" . $post_type);
  if ($page_template) {
    return $page_template;
  }

  // single.html
  $page_template = get_block_template_by_slug("single");
  if ($page_template) {
    return $page_template;
  }

  // singular.html
  $page_template = get_block_template_by_slug("singular");
  if ($page_template) {
    return $page_template;
  }

  // index.html
  $page_template = get_block_template_by_slug("index");
  if ($page_template) {
    return $page_template;
  }

  return null;
}

function get_default_template_for_page($page_id, $page_slug)
{
  // page-{id}.html
  $page_template = get_block_template_by_slug("page-" . $page_id);
  if ($page_template) {
    return $page_template;
  }

  // page-{slug}.html
  $page_template = get_block_template_by_slug("page-" . $page_slug);
  if ($page_template) {
    return $page_template;
  }

  // if front page, front-page.html
  $front_page_id = get_option('page_on_front');
  if ($page_id == $front_page_id) {
    $page_template = get_block_template_by_slug("front-page");
    if ($page_template) {
      return $page_template;
    }
  }

  // page.html
  $page_template = get_block_template_by_slug("page");
  if ($page_template) {
    return $page_template;
  }

  // home.html
  $page_template = get_block_template_by_slug("home");
  if ($page_template) {
    return $page_template;
  }

  // singular.html
  $page_template = get_block_template_by_slug("singular");
  if ($page_template) {
    return $page_template;
  }

  // index.html
  $page_template = get_block_template_by_slug("index");
  if ($page_template) {
    return $page_template;
  }

  return null;
}

function get_default_template_for_post($post_id, $post_slug)
{
  // single-{id}.html
  $page_template = get_block_template_by_slug("single-" . $post_id);
  if ($page_template) {
    return $page_template;
  }

  // single-{slug}.html
  $page_template = get_block_template_by_slug("single-" . $post_slug);
  if ($page_template) {
    return $page_template;
  }

  // single.html
  $page_template = get_block_template_by_slug("single");
  if ($page_template) {
    return $page_template;
  }

  // singular.html
  $page_template = get_block_template_by_slug("singular");
  if ($page_template) {
    return $page_template;
  }

  // index.html
  $page_template = get_block_template_by_slug("index");
  if ($page_template) {
    return $page_template;
  }

  return null;
}

function get_template_blocks($post_id, $post_type, $post_slug)
{
  $slug = get_page_template_slug($post_id);
  $page_template = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template');
  // at this point if there's no page template available, it means the page / post
  // is using the default template, BUT there could also be overrides
  // to the default template, so need to go through each specific type of
  // post / page template
  if (!$page_template) {
    if ($post_type === "page") {
      $page_template = get_default_template_for_page($post_id, $post_slug);
    } else if ($post_type === "post") {
      $page_template = get_default_template_for_post($post_id, $post_slug);
    } else {
      // this is a custom post type
      $page_template = get_default_template_for_cpt($post_id, $post_type, $post_slug);
    }
  }

  if ($page_template) {
    $templateBlocks = parse_blocks($page_template->content);
    return $templateBlocks;
  }

  return null;
}

function parse_blocks_from_file($path)
{
  $file_content = load_file_contents($path);
  if ($file_content) {
    return parse_blocks($file_content);
  }
  return null;
}

function load_file_contents($path)
{
  // get_stylesheet_directory for current theme
  // get_template_directory for possible parent theme
  $file_content = file_get_contents(get_stylesheet_directory() . $path);
  if ($file_content) {
    return $file_content;
  } else {
    $file_content = file_get_contents(get_template_directory() . $path);
    if ($file_content) {
      return $file_content;
    }
  }
  return null;
}

function get_query_by_id($query_id, $mappedBlocks)
{
  function traverse($query_id, $blocks)
  {
    foreach ($blocks as $block) {
      if ($block->name === "core/query") {
        if ($block->attributes['queryId'] == $query_id) {
          return $block;
        }
      }
      if (count($block->innerBlocks ?? [])) {
        $found = traverse($query_id, $block->innerBlocks);
        if ($found) {
          return $found;
        }
      }
    }
  }
  return traverse($query_id, $mappedBlocks);
}

function get_mapped_blocks($post, $query_args)
{
  // get global styles from the theme.json file
  $global_theme_styles = [];
  $theme_directory = get_template_directory();
  $theme_json_path = $theme_directory . '/theme.json';
  if (file_exists($theme_json_path)) {
    $theme_json_contents = file_get_contents($theme_json_path);
    $theme_json_data = json_decode($theme_json_contents, true);
    if($theme_json_data && $theme_json_data['styles'] && $theme_json_data['styles']['blocks']){
      $global_theme_styles = $theme_json_data['styles']['blocks'];
    }
  }

  // get global styles from db (these will be overrides from any default
  // styles using the site editor, i.e. overriding any theme.json styles)
  // `wp-global-styles-${themeName}`
  $global_db_styles = [];
  $theme_slug = get_stylesheet();
  $global_styles_post = get_page_by_path("wp-global-styles-" . $theme_slug, OBJECT, 'wp_global_styles');
  if ($global_styles_post && $global_styles_post->post_content) {
    $decoded_global_styles = json_decode($global_styles_post->post_content, true);
    if($decoded_global_styles && $decoded_global_styles['styles'] && $decoded_global_styles['styles']['blocks']){
      $global_db_styles = $decoded_global_styles['styles']['blocks'];
    }
  }

  $uri = $post->uri;
  $main_blog_page_id = get_option('page_for_posts');
  if ((!$post->ID && $uri === "/") || (!$post->ID && $main_blog_page_id !== "0") || ($post->ID == $main_blog_page_id)) {
    // if no post id and the page is the index page,
    // then we need to load the home.html template
    // OR
    // if there's no post id and there's a blog page set
    // this is the main blog page so load the home.html template
    // or if home not found, default to index
    $page_template = get_block_template_by_slug("home");
    if ($page_template) {
      $templateBlocks = parse_blocks($page_template->content);
    } else {
      $page_template = get_block_template_by_slug("index");
      if ($page_template) {
        $templateBlocks = parse_blocks($page_template->content);
      }
    }
  } else {
    $the_post = get_post($post->ID);
    $blocks = parse_blocks($the_post->post_content);

    // Get the post meta values
    $the_post_id = $the_post->ID;
    $the_post_content = $the_post->post_content;
    $the_post_type = $the_post->post_type;
    $the_post_slug = $the_post->post_name;

    $templateBlocks = get_template_blocks($the_post_id, $the_post_type, $the_post_slug);
  }

  // if there are no template blocks, i.e. no template was found, 
  // then just set equal to the page blocks;
  if (!$templateBlocks || !$query_args['postTemplate']) {
    $templateBlocks = $blocks;
  }

  $mappedBlocks = [];
  foreach ($templateBlocks as $block) {
    if (isset($block['blockName'])) {
      $mappedBlocks[] = new Block($block, $the_post_id, $the_post_content, $query_args, $global_theme_styles, $global_db_styles);
    }
  }
  return $mappedBlocks;
}

function clean($blocks)
{
  foreach ($blocks as $block) {
    if ($block->attributes && $block->attributes['post_id_to_hydrate_template']) {
      unset($block->attributes['post_id_to_hydrate_template']);
    }
    if ($block->attributes && $block->attributes['postTemplateRaw']) {
      unset($block->attributes['postTemplateRaw']);
    }

    if (!$block->attributes || !count($block->attributes)) {
      unset($block->attributes);
    }

    if ($block->innerBlocks) {
      clean($block->innerBlocks);
    }
  }
}

function clean_attributes($blocks)
{
  clean($blocks);
  return $blocks;
}
