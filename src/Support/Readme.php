<?php
/**
 * Reads a plugin's readme.txt for the plugin information popup.
 *
 * @package Jcore\Update\Support
 */

declare(strict_types=1);

namespace Jcore\Update\Support;

/**
 * Class Readme
 *
 * Parses the WordPress readme.txt format into the HTML sections that
 * `plugins_api` consumers such as the "View details" popup render.
 */
final class Readme {

	/**
	 * Readme section titles mapped to the keys WordPress renders as tabs.
	 *
	 * @var array<string, string>
	 */
	private const SECTION_KEYS = array(
		'description'                => 'description',
		'installation'               => 'installation',
		'frequently asked questions' => 'faq',
		'faq'                        => 'faq',
		'screenshots'                => 'screenshots',
		'changelog'                  => 'changelog',
		'upgrade notice'             => 'upgrade_notice',
	);

	/**
	 * The readme contents, or null until read.
	 *
	 * @var string|null
	 */
	private ?string $contents = null;

	/**
	 * Readme constructor.
	 *
	 * @param string $path Absolute path to readme.txt.
	 */
	public function __construct( private readonly string $path ) {
	}

	/**
	 * Whether the readme exists and has content.
	 *
	 * @return bool
	 */
	public function exists(): bool {
		return $this->contents() !== '';
	}

	/**
	 * Reads one header line, for example "Tested up to".
	 *
	 * @param string $header The header name, case-insensitive.
	 *
	 * @return string|null Null when the header is missing.
	 */
	public function header( string $header ): ?string {
		$contents = $this->contents();
		if ( $contents === '' ) {
			return null;
		}

		if ( \preg_match( '/^' . \preg_quote( $header, '/' ) . ':[ \t]*(.+)$/mi', $contents, $match ) ) {
			$value = \trim( $match[1] );

			return $value === '' ? null : $value;
		}

		return null;
	}

	/**
	 * Parses the readme into HTML sections keyed the way WordPress expects.
	 *
	 * Only the sections WordPress knows how to display are returned:
	 * description, installation, faq, screenshots, changelog, upgrade_notice.
	 *
	 * @return array<string, string>
	 */
	public function sections(): array {
		$contents = $this->contents();
		if ( $contents === '' ) {
			return array();
		}

		$parts = \preg_split( '/^==[ \t]*([^=\r\n]+?)[ \t]*==[ \t]*$/m', $contents, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! \is_array( $parts ) ) {
			return array();
		}

		$sections = array();
		$count    = \count( $parts );

		for ( $i = 1; $i + 1 < $count; $i += 2 ) {
			$title = \strtolower( \trim( $parts[ $i ] ) );
			if ( ! isset( self::SECTION_KEYS[ $title ] ) ) {
				continue;
			}

			$html = self::toHtml( $parts[ $i + 1 ] );
			if ( $html !== '' ) {
				$sections[ self::SECTION_KEYS[ $title ] ] = $html;
			}
		}

		return $sections;
	}

	/**
	 * Combines a changelog from the update service with the bundled one.
	 *
	 * The service typically sends only the newest release, in raw readme
	 * markup. It is converted and kept on top unless the bundled changelog
	 * already lists that version.
	 *
	 * @param string $remote Changelog from the update service: raw readme markup or HTML.
	 * @param string $local  Changelog from readme.txt, already HTML.
	 *
	 * @return string
	 */
	public static function mergeChangelog( string $remote, string $local ): string {
		$remote = \trim( $remote );
		if ( $remote === '' ) {
			return $local;
		}

		if ( $local === '' ) {
			return self::isHtml( $remote ) ? $remote : self::toHtml( $remote );
		}

		if ( \preg_match( '/^(?:=|<h[1-6][^>]*>)\s*v?([0-9][0-9A-Za-z.\-]*)/i', $remote, $match ) ) {
			$version = \preg_quote( $match[1], '/' );
			if ( \preg_match( '/<h[1-6]>v?' . $version . '(?![0-9A-Za-z.\-])/i', $local ) ) {
				return $local;
			}
		}

		$remoteHtml = self::isHtml( $remote ) ? $remote : self::toHtml( $remote );

		return $remoteHtml . $local;
	}

	/**
	 * Converts readme markup to HTML.
	 *
	 * Handles `= Heading =` lines, bulleted and numbered lists, paragraphs,
	 * and inline bold, code, Markdown links and bare URLs.
	 *
	 * @param string $text Readme markup.
	 *
	 * @return string
	 */
	public static function toHtml( string $text ): string {
		$html      = '';
		$list      = '';
		$paragraph = array();

		$closeList = static function () use ( &$html, &$list ): void {
			if ( $list !== '' ) {
				$html .= '</' . $list . '>';
				$list  = '';
			}
		};

		$closeParagraph = static function () use ( &$html, &$paragraph ): void {
			if ( $paragraph !== array() ) {
				$html     .= '<p>' . self::inline( \implode( ' ', $paragraph ) ) . '</p>';
				$paragraph = array();
			}
		};

		$lines = \preg_split( '/\r\n|\r|\n/', \trim( $text ) );

		foreach ( \is_array( $lines ) ? $lines : array() as $line ) {
			$line = \rtrim( $line );

			if ( \trim( $line ) === '' ) {
				$closeList();
				$closeParagraph();
				continue;
			}

			if ( \preg_match( '/^=[ \t]*(.+?)[ \t]*=$/', $line, $match ) ) {
				$closeList();
				$closeParagraph();
				$html .= '<h4>' . self::inline( $match[1] ) . '</h4>';
				continue;
			}

			if ( \preg_match( '/^[ \t]*(?:[*\-]|(\d+)\.)[ \t]+(.+)$/', $line, $match ) ) {
				$closeParagraph();
				$type = $match[1] === '' ? 'ul' : 'ol';
				if ( $list !== $type ) {
					$closeList();
					$html .= '<' . $type . '>';
					$list  = $type;
				}
				$html .= '<li>' . self::inline( $match[2] ) . '</li>';
				continue;
			}

			$closeList();
			$paragraph[] = \trim( $line );
		}

		$closeList();
		$closeParagraph();

		return $html;
	}

	/**
	 * Returns the readme contents, reading the file once.
	 *
	 * @return string Empty when the file is missing or unreadable.
	 */
	private function contents(): string {
		if ( $this->contents === null ) {
			$this->contents = '';

			if ( $this->path !== '' && \is_file( $this->path ) && \is_readable( $this->path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$contents       = \file_get_contents( $this->path );
				$this->contents = $contents === false ? '' : $contents;
			}
		}

		return $this->contents;
	}

	/**
	 * Converts inline readme markup: bold, code, Markdown links and bare URLs.
	 *
	 * @param string $text One line or paragraph of readme markup.
	 *
	 * @return string
	 */
	private static function inline( string $text ): string {
		$text = \function_exists( 'esc_html' )
			? \esc_html( $text )
			: \htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );

		$text = (string) \preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		$text = (string) \preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
		$text = (string) \preg_replace( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2">$1</a>', $text );

		if ( \function_exists( 'make_clickable' ) ) {
			return \make_clickable( $text );
		}

		return (string) \preg_replace( '/(?<!["\'>])\bhttps?:\/\/[^\s<]+/', '<a href="$0">$0</a>', $text );
	}

	/**
	 * Whether a changelog string is already HTML rather than readme markup.
	 *
	 * @param string $text The text.
	 *
	 * @return bool
	 */
	public static function isHtml( string $text ): bool {
		return (bool) \preg_match( '/^\s*<(?:h[1-6]|p|ul|ol|div)\b/i', $text );
	}
}
