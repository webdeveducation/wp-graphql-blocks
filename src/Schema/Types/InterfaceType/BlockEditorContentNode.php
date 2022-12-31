<?php

namespace WPGraphQLGutenberg\Schema\Types\InterfaceType;

use GraphQL\Deferred;
use WPGraphQLGutenberg\Blocks\Block;
use WPGraphQLGutenberg\Schema\Utils;
use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;

class BlockEditorContentNode {
	private $type_registry;

	function __construct() {
		$fields = [
			'id' => [
			   'type' => ['non_null' => 'ID']			   
			],
			'blocks' => [
				'type' => 'JSON',
				'description' => __('Gutenberg blocks as json object', 'wp-graphql-gutenberg'),
				'resolve' => function ($model, $args, $context, $info) {
					$blocks = Block::create_blocks(
						parse_blocks(get_post($model->ID)->post_content),
						$model->ID,
						Registry::get_registry()
					);

					return wp_json_encode($blocks);
				}
			]
		];

		add_action('graphql_register_types', function ($type_registry) use ($fields) {
			$this->type_registry = $type_registry;

			register_graphql_interface_type('BlockEditorContentNode', [
				'description' => __( 'Gutenberg post interface', 'wp-graphql-gutenberg' ),
				'interfaces'  => [ 'Node' ],
				'fields'      => $fields,
				'resolveType' => function ( $model ) use ( $type_registry ) {
					return $type_registry->get_type( Utils::get_post_graphql_type( $model, $type_registry ) );
				},
			]);

			$types = Utils::get_editor_graphql_types();

			if (count($types)) {
				register_graphql_interfaces_to_types(['BlockEditorContentNode'], $types);
			}
		});
	}
}
