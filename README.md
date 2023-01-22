# WPGraphQL Blocks

Get gutenberg blocks as JSON through wp-graphql

## Install

- Requires PHP 8.0+
- Requires wp-graphql 1.13.0+
- Requires WordPress 6.0+

### Quick Install

Download and install like any WordPress plugin.

## Usage

This plugin can be used hand-in-hand with the @webdeveducation/wp-block-tools library to easily render core WordPress blocks in your React (Gatsby & Next) apps out of the box.

## Modifying the attributes

You can easily modify any attributes that are returned as part of a particular block type. There are SO many cases where this is useful. For example if a particular block only references an image id but you need the image URL. Or if a particular block's markup is just a WordPress shortcode. This is the case when using Contact Form 7, we only get the form's id back as part of the attributes and the innerHTML of `$data` doesn't contain the form markup, only the WordPress shortcode. We can easily hook into the `wp_graphql_blocks_process_attributes` filter to get the associated HTML markup for that form, and add it to a `formMarkup` attribute, like so:

```php
// functions.php
add_filter('wp_graphql_blocks_process_attributes', function($attributes, $data, $post_id){
  if($data['blockName'] == 'contact-form-7/contact-form-selector'){
    $content = do_shortcode($data['innerHTML']);
    $attributes['formMarkup'] = $content;
  }
  return $attributes;
}, 0, 3);
```

> **Note**
> A neat debugging technique to see what the `$attributes`, `$data`, or `$post_id` values are above is to return those as the GraphQL result via `wp_send_json`, for example if you want to see what the `$data` variable looks like:

```php
add_filter('wp_graphql_blocks_process_attributes', function($attributes, $data, $post_id){
  if($data['blockName'] == 'contact-form-7/contact-form-selector'){
    wp_send_json($data);
  }
  return $attributes;
}, 0, 3);
```
