<?php

namespace WPGraphQLGutenberg\Blocks;
use ArrayAccess;
use GraphQLRelay\Relay;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use voku\helper\HtmlDomParser;

class Block implements ArrayAccess {
	public static function create_blocks($blocks, $post_id, $registry, $parent = null) {
		$result = [];
		$order = 0;

		foreach ($blocks as $block) {
			if (empty($block['blockName'])) {

				if (json_encode($block['innerHTML']) === '"\n\n"') {
					continue;
				}

				$block['blockName'] = 'core/freeform';
			}

			$result[] = new Block($block, $post_id, $registry, $order, $parent);
			$order++;
		}

		return $result;
	}

	protected static function strip_newlines($html) {
		return preg_replace('/^\n|\n$/', '', $html);
	}

	protected static function parse_inner_content($data) {
		$result = '';
		$index = 0;

		foreach ($data['innerContent'] as $value) {
			if ($value === null) {
				$result = $result . self::parse_inner_content($data['innerBlocks'][$index]);
				$index++;
			} else {
				$result = $result . self::strip_newlines($value);
			}
		}

		return $result;
	}

	protected static function source_attributes($node, $type) {
		$result = [];

		foreach ($type as $key => $value) {
			$source = $value['source'] ?? null;

			switch ($source) {
				case 'html':
					$source_node = !empty($value['selector']) ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						if (!empty($value['multiline'])) {
							$tag = $value['multiline'];

							$value = '';

							foreach ($source_node->childNodes as $childNode) {

								$childNode = new \voku\helper\SimpleHtmlDom($childNode);

								if (strtolower($childNode->tag) !== $tag) {
									continue;
								}

								$value = $value . $childNode->outerhtml;
							}
							
							$result[$key] = $value;
						} else {
							$result[$key] = $source_node->innerhtml;
						}

					}

					break;
				case 'attribute':
					$source_node = $value['selector'] ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						$result[$key] = $source_node->getAttribute($value['attribute']);
					}
					break;
				case 'text':
					$source_node = $value['selector'] ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						$result[$key] = $source_node->plaintext;
					}
					break;
				case 'tag':
					$result[$key] = $node->tag;
					break;

				case 'query':
					foreach ($node->find($value['selector']) as $source_node) {
						$result[$key][] = self::source_attributes($source_node, $value['query']);
					}
					break;

				default:
				// @TODO: Throw exception
				// pass
			}

			if (empty($result[$key]) && isset($value['default'])) {
				$result[$key] = $value['default'];
			}
		}

		return $result;
	}

	protected static function parse_attributes($data, $block_type, $postId) {
		$attributes = $data['attrs'];

		/**
		 * Filters the block attributes.
		 *
		 * @param array $attributes Block attributes.
		 * @param array $block      Block object.
		 */
		$attributes = apply_filters('graphql_gutenberg_block_attributes_fields', $attributes, $block_type);

		if ($block_type === null) {
			return [
				'attributes' => $attributes
			];
		}

		$types = [$block_type['attributes']];

		foreach ($block_type['deprecated'] ?? [] as $deprecated) {
			if (!empty($deprecated['attributes'])) {
				$types[] = $deprecated['attributes'];
			}
		}

		if($data['blockName'] == 'core/cover'){
			if($attributes['useFeaturedImage']){
				$attributes['url'] = get_the_post_thumbnail_url($postId, 'full');
			}
		}

		if($data['blockName'] == 'core/post-title'){
			$attributes['content'] = get_the_title($postId) ?? "";
		}

		if($data['blockName'] == 'core/image'){
			if(!$attributes['height'] && !$attributes['width']){
				// get media item
				$img = wp_get_attachment_image_src($attributes['id'], 'full');
				if($img){
					$attributes['width'] = $img[1];
					$attributes['height'] = $img[2];
				}
			}
		}

		// if it's an ACF block
		if(isset($attributes['data']) && $block_type['acf_block_version']){
			$attributesData = $attributes['data'];
			foreach ($attributesData as $key => $value) {
				// attributes that start with an underscore _ are set to the field keys
				if(substr($key, 0, 1) == '_' && function_exists('get_field_object')){
					$fieldObject = get_field_object($value);

					// handle acf image field
					if($fieldObject && $fieldObject['type'] == 'image'){
						$imageId = $attributes['data'][substr($key, 1)];
						// get media item
						$img = wp_get_attachment_image_src($imageId, 'full');
						$image_alt = get_post_meta($imageId, '_wp_attachment_image_alt', TRUE);
						$image_title = get_the_title($imageId);

						if($fieldObject['return_format'] == 'url'){
							$attributes['data'][substr($key, 1)] = $img[0];
						}else if($fieldObject['return_format'] == 'array'){
							$attributes['data'][substr($key, 1)] = array(
								'id' => $imageId,
								'url' => $img[0],
								'width' => $img[1],
								'height' => $img[2],
								'resized' => $img[3],
								'alt' => $image_alt,
								'title' => $image_title
							);
						}else if($fieldObject['return_format'] == 'id'){
							$attributes['data'][substr($key, 1)] = $imageId;
						}
					}

					// handle page link
					if($fieldObject && $fieldObject['type'] == 'page_link'){
						$linkedPostId = $attributes['data'][substr($key, 1)];
						$linkedPost = get_post($linkedPostId);
						$pageUri = get_page_uri($linkedPostId);
						$attributes['data'][substr($key, 1)] = $pageUri;
					}

					// handle post object
					if($fieldObject && $fieldObject['type'] == 'post_object'){
						if($fieldObject['return_format'] == 'object'){
							$linkedPostId = $attributes['data'][substr($key, 1)];
							$linkedPost = get_post($linkedPostId);
							$pageUri = get_page_uri($linkedPostId);
							$linkedPost->uri = $pageUri;
							$attributes['data'][substr($key, 1)] = $linkedPost;
						}
					}
				}
			}
		}

		foreach ($types as $type) {
			$schema = Schema::fromJsonString(
				json_encode([
					'type' => 'object',
					'properties' => $type,
					'additionalProperties' => false
				])
			);

			$validator = new Validator();

			// Convert $attributes to an object, handle both nested and empty objects.
			$attrs = empty($attributes)
				? (object)$attributes
				: json_decode(json_encode($attributes), false);
			$result = $validator->schemaValidation($attrs, $schema);

			if ($result->isValid()) {
				// Avoid empty HTML, which can trigger an error on PHP 8.
				$html = empty($data['innerHTML']) ? '<!-- -->' : $data['innerHTML'];
				return [
					'attributes' => array_merge(
						self::source_attributes(HtmlDomParser::str_get_html($html), $type),
						$attributes
					),
					'type' => $type
				];
			}
		}

		return [
			'attributes' => $attributes,
			'type' => $block_type['attributes']
		];
	}

	public function __construct($data, $post_id, $registry, $order, $parent) {
		$innerBlocks = $data['innerBlocks'];
	
		// handle mapping reusable blocks to innerBlocks.
		if ( $data['blockName'] === 'core/block' && ! empty( $data['attrs']['ref'] ) ) {
			$ref            = $data['attrs']['ref'];
					$reusablePost = get_post( $ref );
	
			if ( ! empty( $reusablePost ) ) {
				$innerBlocks = parse_blocks( $reusablePost->post_content );
			}
		}
	
		$this->innerBlocks = self::create_blocks( $innerBlocks, $post_id, $registry, $this );

		$this->name = $data['blockName'];
		//$this->postId = $post_id;
		$blockType = $registry[$this->name];
		//$this->originalContent = self::strip_newlines($data['innerHTML']);
		//$this->saveContent = self::parse_inner_content($data);
		//$this->order = $order;
		/*$this->get_parent = function () use (&$parent) {
			return $parent;
		};*/

		$result = self::parse_attributes($data, $blockType, $post_id);

		$this->attributes = $result['attributes'];
		//$this->attributesType = $result['type'];

		//$this->dynamicContent = $this->render_dynamic_content($data);

	}

	private function render_dynamic_content($data) {
		$registry = \WP_Block_Type_Registry::get_instance();
		$server_block_type = $registry->get_registered($this->name);

		if (empty($server_block_type) || !$server_block_type->is_dynamic()) {
			return null;
		}

		return render_block($data);
	}

	public function offsetExists($offset) {
		return isset($this->$offset);
	}

	public function offsetGet($offset) {
		return $this->$offset;
	}

	public function offsetSet($offset, $value) {
		$this->$offset = $value;
	}

	public function offsetUnset($offset) {
		unset($this->$offset);
	}
}