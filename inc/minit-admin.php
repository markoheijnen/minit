<?php

class Minit_Admin {

	public function __construct() {
		add_filter( 'plugin_action_links_' . plugin_basename( dirname( dirname( __FILE__ ) ) ) . '/minit.php', array( $this, 'minit_cache_purge_admin_link' ) );
		add_action( 'admin_init', array( $this, 'purge_minit_cache' ) );
	}


	/**
	 * Add a Purge Cache link to the plugin list
	 */
	public function minit_cache_purge_admin_link( $links ) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url( add_query_arg( 'purge_minit', true ), 'purge_minit' ),
			__( 'Purge cache', 'minit' )
		);

		return $links;
	}

	/**
	 * Maybe purge minit cache
	 */
	public function purge_minit_cache() {
		if ( ! isset( $_GET['purge_minit'] ) ) {
			return;
		}

		if ( ! check_admin_referer( 'purge_minit' ) ) {
			return;
		}

		$minit = Minit::instance();
		$minit->purge_cache();

		add_action( 'admin_notices', array( $this, 'minit_cache_purged_success' ) );

		// Allow other plugins to know that we purged
		do_action( 'minit-cache-purged' );
	}

	/**
	 * Success message after purge
	 */
	public function minit_cache_purged_success() {
		printf(
			'<div class="updated"><p>%s</p></div>',
			__( 'Success: Minit cache purged.', 'minit' )
		);
	}

}