<?php
/**
 * 设置管理类。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JRMU_Settings
 */
class JRMU_Settings {

	/**
	 * 获取默认配置。
	 *
	 * 默认所有转换均关闭，用户必须进入设置页手动开启。
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			// 媒体库模块。
			'convert_existing_media_output' => 0,
			'convert_future_media_output'   => 0,
			'future_media_enabled_at'       => 0,

			// 文章内容模块。
			'convert_post_on_save'          => 0,
			'convert_post_on_frontend'      => 0,

			// 多域名访问适配模块。
			'domain_adaptation_enabled'    => 0,
			'domain_dynamic_siteurl'        => 1,
			'domain_rewrite_frontend_links' => 0,
			'domain_exclude_admin'         => 1,
			'domain_exclude_login'         => 1,
			'domain_skip_system_endpoints' => 1,
			'domain_mode'                  => 'whitelist',
			'domain_scheme'                => 'https',
			'domain_allowed_hosts'         => '',

			// 转换范围。
			'target_uploads'                => 1,
			'target_themes'                 => 0,
			'target_plugins'                => 0,
			'extra_hosts'                   => '',

			// 历史文章扫描。
			'scan_limit'                    => 100,
		);
	}

	/**
	 * 布尔设置键。
	 *
	 * @return array
	 */
	public static function get_boolean_keys() {
		return array(
			'convert_existing_media_output',
			'convert_future_media_output',
			'convert_post_on_save',
			'convert_post_on_frontend',
			'domain_adaptation_enabled',
			'domain_dynamic_siteurl',
			'domain_rewrite_frontend_links',
			'domain_exclude_admin',
			'domain_exclude_login',
			'domain_skip_system_endpoints',
			'target_uploads',
			'target_themes',
			'target_plugins',
		);
	}

	/**
	 * 获取设置。
	 *
	 * @return array
	 */
	public static function get_options() {
		$options = get_option( JRMU_OPTION_KEY, array() );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		return wp_parse_args( $options, self::get_defaults() );
	}

	/**
	 * 清洗设置。
	 *
	 * @param array $input 原始输入。
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old      = self::get_options();
		$defaults = self::get_defaults();
		$output   = array();
		$input    = is_array( $input ) ? $input : array();

		foreach ( self::get_boolean_keys() as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		// 至少保留 uploads 作为默认转换范围，避免保存后没有任何目标路径。
		if ( empty( $output['target_uploads'] ) && empty( $output['target_themes'] ) && empty( $output['target_plugins'] ) ) {
			$output['target_uploads'] = 1;
		}

		// 未来媒体开关首次开启时记录时间。关闭后清零，重新开启则重新计算“未来”。
		if ( ! empty( $output['convert_future_media_output'] ) ) {
			$output['future_media_enabled_at'] = ! empty( $old['convert_future_media_output'] ) && ! empty( $old['future_media_enabled_at'] ) ? absint( $old['future_media_enabled_at'] ) : time();
		} else {
			$output['future_media_enabled_at'] = 0;
		}

		$domain_mode = isset( $input['domain_mode'] ) ? sanitize_key( wp_unslash( $input['domain_mode'] ) ) : $defaults['domain_mode'];
		$output['domain_mode'] = in_array( $domain_mode, array( 'whitelist', 'any' ), true ) ? $domain_mode : $defaults['domain_mode'];

		$domain_scheme = isset( $input['domain_scheme'] ) ? sanitize_key( wp_unslash( $input['domain_scheme'] ) ) : $defaults['domain_scheme'];
		$output['domain_scheme'] = in_array( $domain_scheme, array( 'https', 'http', 'auto' ), true ) ? $domain_scheme : $defaults['domain_scheme'];

		$output['domain_allowed_hosts'] = isset( $input['domain_allowed_hosts'] ) ? self::sanitize_extra_hosts( wp_unslash( $input['domain_allowed_hosts'] ) ) : '';
		$output['extra_hosts']           = isset( $input['extra_hosts'] ) ? self::sanitize_extra_hosts( wp_unslash( $input['extra_hosts'] ) ) : '';
		$output['scan_limit']            = isset( $input['scan_limit'] ) ? max( 10, min( 500, absint( $input['scan_limit'] ) ) ) : $defaults['scan_limit'];

		return $output;
	}

	/**
	 * 清洗额外域名列表。
	 *
	 * @param string $raw 原始文本。
	 * @return string
	 */
	public static function sanitize_extra_hosts( $raw ) {
		$raw   = (string) $raw;
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$hosts = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( false === strpos( $line, '://' ) ) {
				$line = 'https://' . $line;
			}

			$host = wp_parse_url( $line, PHP_URL_HOST );

			if ( ! $host ) {
				continue;
			}

			$host = strtolower( sanitize_text_field( $host ) );
			$host = preg_replace( '/[^a-z0-9\-\.]/i', '', $host );

			if ( $host ) {
				$hosts[] = $host;
			}
		}

		return implode( "\n", array_values( array_unique( $hosts ) ) );
	}
}
