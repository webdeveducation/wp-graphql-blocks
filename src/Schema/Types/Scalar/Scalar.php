<?php

namespace WPGraphQLGutenberg\Schema\Types\Scalar;

class Scalar {
	public function __construct() {
		add_action( get_graphql_register_action(), function ($type_registry) {
			register_graphql_scalar('JSON', [
				'serialize' => function ($value) {
					return json_decode($value);
				}
			]);
		});	
	}
}
