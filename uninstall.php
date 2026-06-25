<?php
/**
 * 插件卸载脚本。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * 删除单站点数据。
 */
function jrmu_uninstall_delete_site_data() {
	global $wpdb;

	delete_option( 'jiuliu_relative_media_urls_options' );

	// 只清理插件给“未来上传媒体”添加的附件标记，不删除文章、不删除媒体文件。
	$wpdb->delete(
		$wpdb->postmeta,
		array( 'meta_key' => '_jrmu_future_relative_media' ),
		array( '%s' )
	);
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
		jrmu_uninstall_delete_site_data();
		restore_current_blog();
	}

	delete_site_option( 'jiuliu_relative_media_urls_options' );
} else {
	jrmu_uninstall_delete_site_data();
}
