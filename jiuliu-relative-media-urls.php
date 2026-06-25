<?php
/**
 * Plugin Name: 九流媒体相对地址
 * Plugin URI: https://www.jiuliu.org
 * Description: 将 WordPress 文章内容、媒体库返回值与前台输出中的同站点媒体绝对地址自动转换为根相对地址，适合反向代理、缓存节点、源站隐藏等场景。无需修改主题或 WordPress 核心。
 * Version: 1.0.0
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

// 禁止直接访问。
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 定义插件常量。
define( 'JRMU_VERSION', '1.0.0' );
define( 'JRMU_PLUGIN_FILE', __FILE__ );
define( 'JRMU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JRMU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JRMU_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JRMU_OPTION_KEY', 'jiuliu_relative_media_urls_options' );

/**
 * 插件主类。
 *
 * 单例模式，负责加载依赖、注册钩子与初始化模块。
 */
final class Jiuliu_Relative_Media_Urls {

	/**
	 * 单例实例。
	 *
	 * @var Jiuliu_Relative_Media_Urls|null
	 */
	private static $instance = null;

	/**
	 * 获取单例实例。
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
	 * 加载依赖文件。
	 */
	private function load_dependencies() {
		require_once JRMU_PLUGIN_DIR . 'includes/class-jrmu-settings.php';
		require_once JRMU_PLUGIN_DIR . 'includes/class-jrmu-converter.php';
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
	 * 插件激活回调：写入默认选项。
	 */
	public function on_activate() {
		$defaults = JRMU_Settings::get_defaults();
		$current  = get_option( JRMU_OPTION_KEY, array() );

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		update_option( JRMU_OPTION_KEY, wp_parse_args( $current, $defaults ) );
	}

	/**
	 * 插件停用回调：保留数据，卸载时再清理。
	 */
	public function on_deactivate() {
		// 预留位置。
	}

	/**
	 * 加载多语言文件。
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'jiuliu-relative-media-urls', false, dirname( JRMU_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * 初始化后台与转换模块。
	 */
	public function init_modules() {
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

// 启动插件。
Jiuliu_Relative_Media_Urls::instance();
