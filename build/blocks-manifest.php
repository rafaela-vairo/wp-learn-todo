<?php
// This file is generated. Do not modify it manually.
return array(
	'wp-learn-todo' => array(
		'$schema' => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion' => 3,
		'name' => 'create-block/dspace-query-block',
		'version' => '0.1.0',
		'title' => 'DSpace query',
		'category' => 'widgets',
		'icon' => 'search',
		'description' => 'Example block scaffolded with Create Block tool.',
		'example' => array(
			
		),
		'supports' => array(
			'html' => false
		),
		'textdomain' => 'dspace-query-block',
		'editorScript' => 'file:./index.js',
		'editorStyle' => 'file:./index.css',
		'style' => 'file:./style-index.css',
		'attributes' => array(
			'authorQuery' => array(
				'type' => 'string',
				'default' => ''
			),
			'maxResults' => array(
				'type' => 'number',
				'default' => 1
			)
		),
		'render' => 'file:./render.php'
	)
);
