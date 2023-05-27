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
  if ($page_id === $front_page_id) {
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
