<?php
/**
 * Plugin Name: 九流媒体相对地址
 * Plugin URI: https://www.jiuliu.org
 * Description: 将 WordPress 媒体库输出与文章内容中的同站点媒体绝对地址转换为根相对地址，适合反向代理、缓存节点、源站隐藏和多入口域名场景，并支持站内链接跟随当前访问域名。默认不启用任何转换，所有动作均需手动授权。
 * Version: 3.0.0
 * Author: 九流
 * Author URI: https://www.jiuliu.org
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: jiuliu-relative-media-urls
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JRMU_VERSION', '3.0.0' );
define( 'JRMU_PLUGIN_FILE', __FILE__ );
define( 'JRMU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JRMU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JRMU_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JRMU_OPTION_KEY', 'jiuliu_relative_media_urls_options' );
define( 'JRMU_ATTACHMENT_META_KEY', '_jrmu_future_relative_media' );

/**
 * 插件主类。
 */
final class Jiuliu_Relative_Media_Urls {

	/**
	 * 单例实例。
	 *
	 * @var Jiuliu_Relative_Media_Urls|null
	 */
	private static $instance = null;

	/**
	 * 获取单例。
	 *
	 * @return Jiuliu_Relative_Media_Urls
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * 构造函数。
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * 加载依赖。
	 */
	private function load_dependencies() {
		require_once JRMU_PLUGIN_DIR . 'includes/class-jrmu-settings.php';
		require_once JRMU_PLUGIN_DIR . 'includes/class-jrmu-converter.php';
		require_once JRMU_PLUGIN_DIR . 'includes/class-jrmu-domain-adapter.php';
		require_once JRMU_PLUGIN_DIR . 'includes/class-jrmu-admin.php';
	}

	/**
	 * 初始化钩子。
	 */
	private function init_hooks() {
		register_activation_hook( JRMU_PLUGIN_FILE, array( $this, 'on_activate' ) );
		register_deactivation_hook( JRMU_PLUGIN_FILE, array( $this, 'on_deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init_modules' ) );
		add_filter( 'plugin_action_links_' . JRMU_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * 激活：只写入安全默认设置，不自动启用转换。
	 */
	public function on_activate() {
		$current = get_option( JRMU_OPTION_KEY, null );

		if ( ! is_array( $current ) ) {
			update_option( JRMU_OPTION_KEY, JRMU_Settings::get_defaults() );
			return;
		}

		// 升级兼容：合并新默认值，但不继承 1.x 的自动开启行为。
		$defaults = JRMU_Settings::get_defaults();
		$merged   = wp_parse_args( $current, $defaults );

		foreach ( JRMU_Settings::get_boolean_keys() as $key ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}
			// 2.0 新键默认保持关闭，避免从旧版本升级后自动改变站点输出。
			if ( ! array_key_exists( $key, $current ) ) {
				$merged[ $key ] = $defaults[ $key ];
			}
		}

		update_option( JRMU_OPTION_KEY, $merged );
	}

	/**
	 * 停用：保留设置。
	 */
	public function on_deactivate() {
		// 停用不删除任何设置或内容。
	}

	/**
	 * 加载语言包。
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'jiuliu-relative-media-urls', false, dirname( JRMU_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * 初始化模块。
	 */
	public function init_modules() {
		JRMU_Domain_Adapter::instance();
		JRMU_Converter::instance();

		if ( is_admin() ) {
			JRMU_Admin::instance();
		}
	}

	/**
	 * 插件操作链接。
	 *
	 * @param array $links 现有链接。
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=jiuliu-relative-media-urls' ) ),
			esc_html__( '设置', 'jiuliu-relative-media-urls' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}

Jiuliu_Relative_Media_Urls::instance();
