<?php //phpcs:ignore
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Utils;

/**
 * Utility class to convert Markdown to WordPress Gutenberg blocks format.
 */
class MarkdownToBlocks {

	/**
	 * Convert Markdown content to Gutenberg blocks format.
	 *
	 * @param string $markdown The Markdown content.
	 * @return string JSON-encoded Gutenberg blocks format.
	 */
	public static function convert( string $markdown ): string {
		if ( empty( trim( $markdown ) ) ) {
			return self::create_empty_blocks();
		}

		$lines     = explode( "\n", $markdown );
		$blocks    = array();
		$i         = 0;
		$in_code   = false;
		$code_lines = array();
		$code_lang  = '';

		while ( $i < count( $lines ) ) {
			$line = $lines[ $i ];

			// Handle code blocks
			if ( preg_match( '/^```(\w*)$/', $line, $matches ) ) {
				if ( $in_code ) {
					// End of code block
					$code_content = implode( "\n", $code_lines );
					$blocks[]     = self::create_code_block( $code_content, $code_lang );
					$code_lines   = array();
					$code_lang    = '';
					$in_code      = false;
				} else {
					// Start of code block
					$code_lang = $matches[1] ?? '';
					$in_code   = true;
				}
				$i++;
				continue;
			}

			if ( $in_code ) {
				$code_lines[] = $line;
				$i++;
				continue;
			}

			// Handle headers
			if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $matches ) ) {
				$level   = strlen( $matches[1] );
				$content = trim( $matches[2] );
				$blocks[] = self::create_heading_block( $level, $content );
				$i++;
				continue;
			}

			// Handle horizontal rules
			if ( preg_match( '/^[-*_]{3,}$/', $line ) ) {
				$blocks[] = self::create_separator_block();
				$i++;
				continue;
			}

			// Handle unordered lists
			if ( preg_match( '/^[-*+]\s+(.+)$/', $line, $matches ) ) {
				$list_items = array();
				$list_items[] = trim( $matches[1] );
				$i++;
				// Collect consecutive list items
				while ( $i < count( $lines ) && preg_match( '/^[-*+]\s+(.+)$/', $lines[ $i ], $item_matches ) ) {
					$list_items[] = trim( $item_matches[1] );
					$i++;
				}
				$blocks[] = self::create_list_block( $list_items, false );
				continue;
			}

			// Handle ordered lists
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $matches ) ) {
				$list_items = array();
				$list_items[] = trim( $matches[1] );
				$i++;
				// Collect consecutive list items
				while ( $i < count( $lines ) && preg_match( '/^\d+\.\s+(.+)$/', $lines[ $i ], $item_matches ) ) {
					$list_items[] = trim( $item_matches[1] );
					$i++;
				}
				$blocks[] = self::create_list_block( $list_items, true );
				continue;
			}

			// Handle blockquotes
			if ( preg_match( '/^>\s+(.+)$/', $line, $matches ) ) {
				$quote_lines = array();
				$quote_lines[] = trim( $matches[1] );
				$i++;
				// Collect consecutive quote lines
				while ( $i < count( $lines ) && preg_match( '/^>\s*(.+)$/', $lines[ $i ], $quote_matches ) ) {
					$quote_lines[] = trim( $quote_matches[1] );
					$i++;
				}
				$blocks[] = self::create_quote_block( implode( "\n", $quote_lines ) );
				continue;
			}

			// Handle empty lines
			if ( empty( trim( $line ) ) ) {
				$i++;
				continue;
			}

			// Handle regular paragraphs
			$paragraph_lines = array();
			$paragraph_lines[] = $line;
			$i++;
			// Collect consecutive non-empty lines that aren't special markdown
			while ( $i < count( $lines ) ) {
				$next_line = $lines[ $i ];
				if ( empty( trim( $next_line ) ) ||
					preg_match( '/^#{1,6}\s+/', $next_line ) ||
					preg_match( '/^[-*+]\s+/', $next_line ) ||
					preg_match( '/^\d+\.\s+/', $next_line ) ||
					preg_match( '/^>\s+/', $next_line ) ||
					preg_match( '/^```/', $next_line ) ||
					preg_match( '/^[-*_]{3,}$/', $next_line ) ) {
					break;
				}
				$paragraph_lines[] = $next_line;
				$i++;
			}
			$paragraph_text = self::process_inline_markdown( implode( "\n", $paragraph_lines ) );
			if ( ! empty( trim( $paragraph_text ) ) ) {
				$blocks[] = self::create_paragraph_block( $paragraph_text );
			}
		}

		// Handle unclosed code block
		if ( $in_code && ! empty( $code_lines ) ) {
			$code_content = implode( "\n", $code_lines );
			$blocks[]     = self::create_code_block( $code_content, $code_lang );
		}

		// If no blocks were created, create an empty paragraph
		if ( empty( $blocks ) ) {
			$blocks[] = self::create_paragraph_block( '' );
		}

		return wp_json_encode( array( 'blocks' => $blocks ) );
	}

	/**
	 * Process inline Markdown (bold, italic, links, code) in text.
	 *
	 * @param string $text The text to process.
	 * @return string HTML with inline formatting.
	 */
	private static function process_inline_markdown( string $text ): string {
		// Escape HTML first
		$text = esc_html( $text );

		// Process inline code
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );

		// Process bold (**text** or __text__)
		$text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
		$text = preg_replace( '/__([^_]+)__/', '<strong>$1</strong>', $text );

		// Process italic (*text* or _text_)
		$text = preg_replace( '/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $text );
		$text = preg_replace( '/(?<!_)_([^_]+)_(?!_)/', '<em>$1</em>', $text );

		// Process links [text](url)
		$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text );

		return $text;
	}

	/**
	 * Create an empty blocks structure.
	 *
	 * @return string JSON-encoded empty blocks.
	 */
	private static function create_empty_blocks(): string {
		return wp_json_encode( array( 'blocks' => array() ) );
	}

	/**
	 * Create a paragraph block.
	 *
	 * @param string $content The paragraph content (can include HTML).
	 * @return array The block structure.
	 */
	private static function create_paragraph_block( string $content ): array {
		$html = '<p>' . $content . '</p>';
		return array(
			'blockName'    => 'core/paragraph',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a heading block.
	 *
	 * @param int    $level The heading level (1-6).
	 * @param string $content The heading content.
	 * @return array The block structure.
	 */
	private static function create_heading_block( int $level, string $content ): array {
		$level   = max( 1, min( 6, $level ) );
		$content = self::process_inline_markdown( $content );
		$html    = "<h{$level}>{$content}</h{$level}>";
		return array(
			'blockName'    => 'core/heading',
			'attrs'        => array( 'level' => $level ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a list block.
	 *
	 * @param array $items The list items.
	 * @param bool  $ordered Whether it's an ordered list.
	 * @return array The block structure.
	 */
	private static function create_list_block( array $items, bool $ordered ): array {
		$tag          = $ordered ? 'ol' : 'ul';
		$processed_items = array_map( array( __CLASS__, 'process_inline_markdown' ), $items );
		$html         = "<{$tag}><li>" . implode( "</li><li>", $processed_items ) . "</li></{$tag}>";
		$inner_content = array( "<{$tag}>" );
		foreach ( $processed_items as $item ) {
			$inner_content[] = '<li>';
			$inner_content[] = $item;
			$inner_content[] = '</li>';
		}
		$inner_content[] = "</{$tag}>";

		return array(
			'blockName'    => 'core/list',
			'attrs'        => array( 'ordered' => $ordered ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => $inner_content,
		);
	}

	/**
	 * Create a code block.
	 *
	 * @param string $content The code content.
	 * @param string $language The language identifier.
	 * @return array The block structure.
	 */
	private static function create_code_block( string $content, string $language = '' ): array {
		$content = esc_html( $content );
		$html    = '<pre class="wp-block-code"><code' . ( ! empty( $language ) ? ' class="language-' . esc_attr( $language ) . '"' : '' ) . '>' . $content . '</code></pre>';
		return array(
			'blockName'    => 'core/code',
			'attrs'        => array( 'language' => $language ),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a quote block.
	 *
	 * @param string $content The quote content.
	 * @return array The block structure.
	 */
	private static function create_quote_block( string $content ): array {
		$content = self::process_inline_markdown( $content );
		$html    = '<blockquote class="wp-block-quote"><p>' . $content . '</p></blockquote>';
		return array(
			'blockName'    => 'core/quote',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Create a separator block.
	 *
	 * @return array The block structure.
	 */
	private static function create_separator_block(): array {
		$html = '<hr class="wp-block-separator"/>';
		return array(
			'blockName'    => 'core/separator',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => $html,
			'innerContent' => array( $html ),
		);
	}

	/**
	 * Check if content is already in Gutenberg blocks format.
	 *
	 * @param string $content The content to check.
	 * @return bool True if content is already in blocks format.
	 */
	public static function is_blocks_format( string $content ): bool {
		if ( empty( $content ) ) {
			return false;
		}

		// Try to decode as JSON
		$decoded = json_decode( $content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		// Check if it has the blocks structure
		return isset( $decoded['blocks'] ) && is_array( $decoded['blocks'] );
	}
}

