<?php //phpcs:ignore
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;
use Automattic\WordpressMcp\Utils\MarkdownToBlocks;

/**
 * Class for managing MCP Pages Tools functionality.
 */
class McpPagesTools {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wordpress_mcp_init', array( $this, 'register_tools' ) );
	}

	/**
	 * Register the tools.
	 */
	public function register_tools(): void {
		new RegisterMcpTool(
			array(
				'name'        => 'wp_pages_search',
				'description' => 'Search and filter WordPress pages with pagination',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages',
					'method' => 'GET',
				),
				'annotations' => array(
					'title'         => 'Search Pages',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_get_page',
				'description' => 'Get a WordPress page by ID',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages/(?P<id>[\d]+)',
					'method' => 'GET',
				),
				'annotations' => array(
					'title'         => 'Get Page',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_add_page',
				'description' => 'Add a new WordPress page. Content can be provided in Markdown format (will be automatically converted to Gutenberg blocks) or Gutenberg blocks format.',
				'type'        => 'create',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/pages',
					'method'                  => 'POST',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'title'   => array(
								'type' => 'string',
							),
							'content' => array(
								'type'        => 'string',
								'description' => 'The content of the page. Can be provided in Markdown format (will be automatically converted to Gutenberg blocks) or Gutenberg blocks format (JSON string with blocks array).',
							),
							'excerpt' => array(
								'type' => 'string',
							),
							'parent'  => array(
								'type'        => 'integer',
								'description' => 'The ID of the parent page',
							),
							'order'   => array(
								'type'        => 'integer',
								'description' => 'The order of the page in the menu',
							),
						),
						'required'   => array(
							'title',
							'content',
						),
					),
					'preCallback'             => array( $this, 'wp_add_page_pre_callback' ),
				),
				'annotations' => array(
					'title'           => 'Add Page',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => false,
					'openWorldHint'   => false,
				),
			),
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_update_page',
				'description' => 'Update a WordPress page by ID. Content can be provided in Markdown format (will be automatically converted to Gutenberg blocks) or Gutenberg blocks format.',
				'type'        => 'update',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages/(?P<id>[\d]+)',
					'method' => 'PUT',
					'preCallback' => array( $this, 'wp_update_page_pre_callback' ),
				),
				'annotations' => array(
					'title'           => 'Update Page',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_delete_page',
				'description' => 'Delete a WordPress page by ID',
				'type'        => 'delete',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages/(?P<id>[\d]+)',
					'method' => 'DELETE',
				),
				'annotations' => array(
					'title'           => 'Delete Page',
					'readOnlyHint'    => false,
					'destructiveHint' => true,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);
	}

	/**
	 * Pre-callback for wp_add_page to convert Markdown to Gutenberg blocks.
	 *
	 * @param array $args The arguments.
	 * @return array The processed arguments.
	 */
	public function wp_add_page_pre_callback( array $args ): array {
		if ( isset( $args['content'] ) && ! empty( $args['content'] ) ) {
			$content = $args['content'];
			
			// Check if content is already in Gutenberg blocks format
			if ( ! MarkdownToBlocks::is_blocks_format( $content ) ) {
				// Convert Markdown to Gutenberg blocks
				$args['content'] = MarkdownToBlocks::convert( $content );
			}
		}

		return array(
			'args' => $args,
		);
	}

	/**
	 * Pre-callback for wp_update_page to convert Markdown to Gutenberg blocks.
	 *
	 * @param array $args The arguments.
	 * @return array The processed arguments.
	 */
	public function wp_update_page_pre_callback( array $args ): array {
		if ( isset( $args['content'] ) && ! empty( $args['content'] ) ) {
			$content = $args['content'];
			
			// Check if content is already in Gutenberg blocks format
			if ( ! MarkdownToBlocks::is_blocks_format( $content ) ) {
				// Convert Markdown to Gutenberg blocks
				$args['content'] = MarkdownToBlocks::convert( $content );
			}
		}

		return array(
			'args' => $args,
		);
	}
}
