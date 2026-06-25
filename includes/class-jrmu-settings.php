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
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'                  => 1,
			'convert_on_save'          => 1,
			'convert_on_output'        => 1,
			'convert_attachment_urls'  => 1,
			'convert_srcset'           => 1,
			'convert_admin_media_js'   => 1,
			'target_uploads'           => 1,
			'target_themes'            => 0,
			'target_plugins'           => 0,
			'extra_hosts'              => '',
			'batch_limit'              => 200,
		);
	}

	/**
	 * 获取当前设置，与默认值合并。
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
		$defaults = self::get_defaults();
		$output   = array();

		$input = is_array( $input ) ? $input : array();

		$output['enabled']                 = ! empty( $input['enabled'] ) ? 1 : 0;
		$output['convert_on_save']         = ! empty( $input['convert_on_save'] ) ? 1 : 0;
		$output['convert_on_output']       = ! empty( $input['convert_on_output'] ) ? 1 : 0;
		$output['convert_attachment_urls'] = ! empty( $input['convert_attachment_urls'] ) ? 1 : 0;
		$output['convert_srcset']          = ! empty( $input['convert_srcset'] ) ? 1 : 0;
		$output['convert_admin_media_js']  = ! empty( $input['convert_admin_media_js'] ) ? 1 : 0;
		$output['target_uploads']          = ! empty( $input['target_uploads'] ) ? 1 : 0;
		$output['target_themes']           = ! empty( $input['target_themes'] ) ? 1 : 0;
		$output['target_plugins']          = ! empty( $input['target_plugins'] ) ? 1 : 0;

		// 至少启用 uploads，避免误保存成完全不转换。
		if ( empty( $output['target_uploads'] ) && empty( $output['target_themes'] ) && empty( $output['target_plugins'] ) ) {
			$output['target_uploads'] = 1;
		}

		$output['extra_hosts'] = isset( $input['extra_hosts'] ) ? self::sanitize_extra_hosts( wp_unslash( $input['extra_hosts'] ) ) : '';
		$output['batch_limit'] = isset( $input['batch_limit'] ) ? max( 20, min( 1000, absint( $input['batch_limit'] ) ) ) : $defaults['batch_limit'];

		return $output;
	}

	/**
	 * 清洗额外域名列表。
	 *
	 * 支持填写域名或完整 URL，每行一个。
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

			// 允许用户输入 https://origin.example.com/path，也允许只输入 origin.example.com。
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

		$hosts = array_values( array_unique( $hosts ) );

		return implode( "\n", $hosts );
	}
}
