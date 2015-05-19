<?php
/*
Plugin Name: Minit
Plugin URI: https://github.com/kasparsd/minit
GitHub URI: https://github.com/kasparsd/minit
Description: Combine JS and CSS files and serve them from the uploads folder.
Version: 1.1
Author: Kaspars Dambis
Author URI: http://kaspars.net
*/

$minit_instance = Minit::instance();

class Minit {

	static $instance;

	private function __construct() {
		if ( is_admin() ) {
			$this->load_admin();
		}
		else {
			$this->load_front();
		}

		// Prepend the filename of the file being included
		add_filter( 'minit-item-css', array( $this, 'comment_combined' ), 15, 3 );
		add_filter( 'minit-item-js', array( $this, 'comment_combined' ), 15, 3 );

		// Add table of contents at the top of the Minit file
		add_filter( 'minit-content-css', array( $this, 'add_toc' ), 100, 2 );
		add_filter( 'minit-content-js', array( $this, 'add_toc' ), 100, 2 );

		// Turn all local asset URLs into absolute URLs
		add_filter( 'minit-item-css', array( $this, 'resolve_css_urls' ), 10, 3 );

		// Add support for relative CSS imports
		add_filter( 'minit-item-css', array( $this, 'resolve_css_imports' ), 10, 3 );

		// Exclude styles with media queries from being included in Minit
		add_filter( 'minit-item-css', array( $this, 'exclude_css_with_media_query' ), 10, 3 );

		// Make sure that all Minit files are served from the correct protocol
		add_filter( 'minit-url-css', array( $this, 'maybe_ssl_url' ) );
		add_filter( 'minit-url-js', array( $this, 'maybe_ssl_url' ) );
	}

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new Minit();
		}

		return self::$instance;
	}


	public function load_admin() {
		include 'inc/minit-admin.php';
		new Minit_Admin;
	}

	public function load_front() {
		include 'inc/minit-front.php';
		new Minit_Front;
	}


	public function get_asset_relative_path( $base_url, $item_url ) {
		// Remove protocol reference from the local base URL
		$base_url = preg_replace( '/^(https?:\/\/|\/\/)/i', '', $base_url );

		// Check if this is a local asset which we can include
		$src_parts = explode( $base_url, $item_url );

		// Get the trailing part of the local URL
		$maybe_relative = end( $src_parts );

		if ( file_exists( ABSPATH . $maybe_relative ) ) {
			return (object) array( 'path' => $maybe_relative, 'base' => ABSPATH );
		}

		if ( file_exists( WP_CONTENT_DIR . $maybe_relative ) ) {
			return (object) array( 'path' => $maybe_relative, 'base' => WP_CONTENT_DIR );
		}

		return false;
	}

	public function purge_cache( $hard = false ) {
		// Use this as a global cache version number
		update_option( 'minit_cache_ver', time() );

		$wp_upload_dir = wp_upload_dir();
		$minit_files   = glob( $wp_upload_dir['basedir'] . '/minit/*' );

		if ( $minit_files ) {
			foreach ( $minit_files as $minit_file ) {
				unlink( $minit_file );
			}
		}
	}


	// Prepend the filename of the file being included
	public function comment_combined( $content, $object, $script ) {
		if ( ! $content ) {
			return $content;
		}

		return sprintf(
			"\n\n/* Minit: %s */\n",
			$object->registered[ $script ]->src
		) . $content;
	}


	// Add table of contents at the top of the Minit file
	public function add_toc( $content, $items ) {
		if ( ! $content || empty( $items ) ) {
			return $content;
		}

		$toc = array();

		foreach ( $items as $handle => $item_content ) {
			$toc[] = sprintf( ' - %s', $handle );
		}

		return sprintf( "/* TOC:\n%s\n*/", implode( "\n", $toc ) ) . $content;
	}


	// Turn all local asset URLs into absolute URLs
	public function resolve_css_urls( $content, $object, $script ) {
		if ( ! $content ) {
			return $content;
		}

		$src = $this->get_asset_relative_path(
			$object->content_url,
			$object->registered[ $script ]->src
		);

		// Make all local asset URLs absolute
		$content = preg_replace(
			'/url\(["\' ]?+(?!data:|https?:|\/\/)(.*?)["\' ]?\)/i',
			sprintf( "url('%s/$1')", $object->content_url . dirname( $src->path ) ),
			$content
		);

		return $content;
	}


	// Add support for relative CSS imports
	public function resolve_css_imports( $content, $object, $script ) {
		if ( ! $content ) {
			return $content;
		}

		$src = $this->get_asset_relative_path(
			$object->content_url,
			$object->registered[ $script ]->src
		);

		// Make all import asset URLs absolute
		$content = preg_replace(
			'/@import\s+(url\()?["\'](?!https?:|\/\/)(.*?)["\'](\)?)/i',
			sprintf( "@import url('%s/$2')", $object->base_url . dirname( $src->path ) ),
			$content
		);

		return $content;
	}


	// Exclude styles with media queries from being included in Minit
	public function exclude_css_with_media_query( $content, $object, $script ) {
		if ( ! $content ) {
			return $content;
		}

		$whitelist = array( '', 'all', 'screen' );

		// Exclude from Minit if media query specified
		if ( ! in_array( $object->registered[ $script ]->args, $whitelist ) ) {
			return false;
		}

		return $content;
	}


	// Make sure that all Minit files are served from the correct protocol
	public function maybe_ssl_url( $url ) {
		if ( is_ssl() ) {
			return str_replace( 'http://', 'https://', $url );
		}

		return $url;
	}

}