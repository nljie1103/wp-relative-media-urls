<?php
/**
 * 插件卸载脚本。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		delete_option( 'jiuliu_relative_media_urls_options' );
		restore_current_blog();
	}

	delete_site_option( 'jiuliu_relative_media_urls_options' );
} else {
	delete_option( 'jiuliu_relative_media_urls_options' );
}
