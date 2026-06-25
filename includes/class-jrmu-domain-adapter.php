<?php
/**
 * 多域名访问适配类。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JRMU_Domain_Adapter
 */
class JRMU_Domain_Adapter {

	/**
	 * 单例实例。
	 *
	 * @var JRMU_Domain_Adapter|null
	 */
	private static $instance = null;

	/**
	 * 是否临时绕过 pre_option 过滤，避免递归。
	 *
	 * @var bool
	 */
	private $bypass_options = false;

	/**
	 * 获取单例。
	 *
	 * @return JRMU_Domain_Adapter
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
		$this->init_hooks();
	}

	/**
	 * 初始化钩子。
	 */
	private function init_hooks() {
		$options = JRMU_Settings::get_options();

		if ( empty( $options['domain_adaptation_enabled'] ) ) {
			return;
		}

		add_filter( 'pre_option_home', array( $this, 'filter_pre_option_home' ), 10, 3 );
		add_filter( 'pre_option_siteurl', array( $this, 'filter_pre_option_siteurl' ), 10, 3 );

		if ( ! empty( $options['domain_rewrite_frontend_links'] ) ) {
			add_filter( 'post_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'page_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'post_type_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'term_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'category_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'tag_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'author_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'day_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'month_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'year_link', array( $this, 'rewrite_url' ), 99 );
			add_filter( 'nav_menu_link_attributes', array( $this, 'rewrite_nav_menu_link_attributes' ), 99 );
			add_filter( 'the_content', array( $this, 'rewrite_html' ), 98 );
			add_filter( 'the_excerpt', array( $this, 'rewrite_html' ), 98 );
			add_filter( 'widget_text', array( $this, 'rewrite_html' ), 98 );
			add_filter( 'widget_text_content', array( $this, 'rewrite_html' ), 98 );
			add_filter( 'render_block', array( $this, 'rewrite_html' ), 98 );
		}
	}

	/**
	 * 动态 home 选项。
	 *
	 * @param mixed  $pre_option 预返回值。
	 * @param string $option     选项名。
	 * @param mixed  $default    默认值。
	 * @return mixed
	 */
	public function filter_pre_option_home( $pre_option = false, $option = 'home', $default = false ) {
		return $this->get_dynamic_base_url_or_false();
	}

	/**
	 * 动态 siteurl 选项。
	 *
	 * @param mixed  $pre_option 预返回值。
	 * @param string $option     选项名。
	 * @param mixed  $default    默认值。
	 * @return mixed
	 */
	public function filter_pre_option_siteurl( $pre_option = false, $option = 'siteurl', $default = false ) {
		$options = JRMU_Settings::get_options();

		if ( empty( $options['domain_dynamic_siteurl'] ) ) {
			return false;
		}

		return $this->get_dynamic_base_url_or_false();
	}

	/**
	 * 获取动态基础 URL；不适用时返回 false，让 WordPress 继续读数据库原值。
	 *
	 * @return string|false
	 */
	public function get_dynamic_base_url_or_false() {
		if ( $this->bypass_options || ! $this->should_adapt_current_request() ) {
			return false;
		}

		$current_host = $this->get_current_host_with_port();

		if ( ! $current_host || ! $this->is_current_host_allowed( $current_host ) ) {
			return false;
		}

		return $this->get_current_scheme() . '://' . $current_host;
	}

	/**
	 * 判断当前请求是否应该启用动态域名。
	 *
	 * @return bool
	 */
	public function should_adapt_current_request() {
		$options = JRMU_Settings::get_options();

		if ( empty( $options['domain_adaptation_enabled'] ) ) {
			return false;
		}

		if ( ! empty( $options['domain_exclude_admin'] ) && is_admin() ) {
			return false;
		}

		if ( ! empty( $options['domain_exclude_login'] ) && $this->is_login_request() ) {
			return false;
		}

		if ( ! empty( $options['domain_skip_system_endpoints'] ) && $this->is_system_endpoint_request() ) {
			return false;
		}

		return true;
	}

	/**
	 * 重写单个 URL 到当前访问域名。
	 *
	 * @param string $url 原始 URL。
	 * @return string
	 */
	public function rewrite_url( $url ) {
		if ( ! is_string( $url ) || '' === $url || ! $this->should_adapt_current_request() ) {
			return $url;
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$source_host = $this->normalize_host( $parts['host'] );

		if ( ! $this->is_source_host_allowed( $source_host ) ) {
			return $url;
		}

		$current_host = $this->get_current_host_with_port();

		if ( ! $current_host || ! $this->is_current_host_allowed( $current_host ) ) {
			return $url;
		}

		$rewritten = $this->get_current_scheme() . '://' . $current_host . $parts['path'];

		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$rewritten .= '?' . $parts['query'];
		}

		if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
			$rewritten .= '#' . $parts['fragment'];
		}

		return $rewritten;
	}

	/**
	 * 重写 HTML 中的站内绝对链接。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	public function rewrite_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || ! $this->should_adapt_current_request() ) {
			return $html;
		}

		$rewritten = preg_replace_callback(
			'#https?://[^\s"\'<>)]+#i',
			function ( $matches ) {
				$url = isset( $matches[0] ) ? $matches[0] : '';
				return $this->rewrite_url( $url );
			},
			$html
		);

		return is_string( $rewritten ) ? $rewritten : $html;
	}

	/**
	 * 重写菜单链接属性。
	 *
	 * @param array $atts 菜单链接属性。
	 * @return array
	 */
	public function rewrite_nav_menu_link_attributes( $atts ) {
		if ( isset( $atts['href'] ) ) {
			$atts['href'] = $this->rewrite_url( $atts['href'] );
		}

		return $atts;
	}

	/**
	 * 当前访问 Host，保留非标准端口。
	 *
	 * @return string
	 */
	public function get_current_host_with_port() {
		$raw_host = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$raw_host = trim( (string) $raw_host );

		if ( '' === $raw_host ) {
			return '';
		}

		// 防御 Host 注入，只保留常见域名、IPv4、IPv6 方括号和端口字符。
		$raw_host = preg_replace( '/[^a-zA-Z0-9\-\.\[\]:]/', '', $raw_host );
		$raw_host = strtolower( $raw_host );

		return $raw_host;
	}

	/**
	 * 当前访问域名，不含端口。
	 *
	 * @return string
	 */
	public function get_current_host_without_port() {
		return $this->normalize_host( $this->get_current_host_with_port() );
	}

	/**
	 * 获取当前协议。
	 *
	 * @return string
	 */
	public function get_current_scheme() {
		$options = JRMU_Settings::get_options();
		$scheme  = isset( $options['domain_scheme'] ) ? $options['domain_scheme'] : 'https';

		if ( 'http' === $scheme ) {
			return 'http';
		}

		if ( 'auto' !== $scheme ) {
			return 'https';
		}

		if ( is_ssl() ) {
			return 'https';
		}

		$forwarded_proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) : '';

		if ( false !== strpos( $forwarded_proto, 'https' ) ) {
			return 'https';
		}

		$forwarded_ssl = isset( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_X_FORWARDED_SSL'] ) ) : '';

		if ( 'on' === $forwarded_ssl ) {
			return 'https';
		}

		return 'http';
	}

	/**
	 * 判断当前 Host 是否允许作为动态目标域名。
	 *
	 * @param string $host Host，可含端口。
	 * @return bool
	 */
	public function is_current_host_allowed( $host ) {
		$options = JRMU_Settings::get_options();
		$host    = $this->normalize_host( $host );

		if ( ! $host ) {
			return false;
		}

		if ( 'any' === $options['domain_mode'] ) {
			return true;
		}

		return in_array( $host, $this->get_allowed_domain_hosts(), true );
	}

	/**
	 * 判断源 URL 域名是否允许被改写。
	 *
	 * @param string $host Host。
	 * @return bool
	 */
	public function is_source_host_allowed( $host ) {
		$host = $this->normalize_host( $host );

		if ( ! $host ) {
			return false;
		}

		return in_array( $host, $this->get_rewrite_source_hosts(), true );
	}

	/**
	 * 获取多域名白名单。
	 *
	 * @return array
	 */
	public function get_allowed_domain_hosts() {
		$options = JRMU_Settings::get_options();
		$hosts   = array();

		if ( ! empty( $options['domain_allowed_hosts'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $options['domain_allowed_hosts'] );

			foreach ( $lines as $line ) {
				$host = $this->extract_host_from_line( $line );
				if ( $host ) {
					$hosts[] = $host;
				}
			}
		}

		$hosts = array_filter( array_unique( $hosts ) );

		return apply_filters( 'jrmu_domain_allowed_hosts', array_values( $hosts ) );
	}

	/**
	 * 获取允许作为“旧链接来源”的域名列表。
	 *
	 * @return array
	 */
	public function get_rewrite_source_hosts() {
		$hosts   = $this->get_allowed_domain_hosts();
		$options = JRMU_Settings::get_options();

		if ( ! empty( $options['extra_hosts'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $options['extra_hosts'] );
			foreach ( $lines as $line ) {
				$host = $this->extract_host_from_line( $line );
				if ( $host ) {
					$hosts[] = $host;
				}
			}
		}

		foreach ( array( 'home', 'siteurl' ) as $option ) {
			$url  = $this->get_raw_option_value( $option );
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host ) {
				$hosts[] = $this->normalize_host( $host );
			}
		}

		$hosts = array_filter( array_unique( $hosts ) );

		return apply_filters( 'jrmu_domain_rewrite_source_hosts', array_values( $hosts ) );
	}

	/**
	 * 获取当前状态，用于后台展示。
	 *
	 * @return array
	 */
	public function get_debug_info() {
		$current_host = $this->get_current_host_with_port();
		$base_url     = $this->get_dynamic_base_url_or_false();

		return array(
			'current_host'     => $current_host,
			'current_scheme'   => $this->get_current_scheme(),
			'is_allowed'       => $current_host ? $this->is_current_host_allowed( $current_host ) : false,
			'dynamic_base_url' => $base_url ? $base_url : '',
			'allowed_hosts'    => $this->get_allowed_domain_hosts(),
			'source_hosts'     => $this->get_rewrite_source_hosts(),
		);
	}

	/**
	 * 获取未被本插件 pre_option 改写的原始选项值。
	 *
	 * @param string $option 选项名。
	 * @return string
	 */
	private function get_raw_option_value( $option ) {
		$this->bypass_options = true;
		$value                = get_option( $option );
		$this->bypass_options = false;

		return is_string( $value ) ? $value : '';
	}

	/**
	 * 从设置行中提取域名。
	 *
	 * @param string $line 原始行。
	 * @return string
	 */
	private function extract_host_from_line( $line ) {
		$line = trim( (string) $line );

		if ( '' === $line ) {
			return '';
		}

		if ( false === strpos( $line, '://' ) ) {
			$line = 'https://' . $line;
		}

		$host = wp_parse_url( $line, PHP_URL_HOST );

		return $host ? $this->normalize_host( $host ) : '';
	}

	/**
	 * 标准化 Host，移除端口。
	 *
	 * @param string $host Host。
	 * @return string
	 */
	private function normalize_host( $host ) {
		$host = strtolower( trim( (string) $host ) );

		if ( '' === $host ) {
			return '';
		}

		// IPv6 方括号形式保留主体；常规域名/IPv4 去掉端口。
		if ( '[' === substr( $host, 0, 1 ) ) {
			$end = strpos( $host, ']' );
			if ( false !== $end ) {
				return substr( $host, 1, $end - 1 );
			}
		}

		if ( false !== strpos( $host, ':' ) ) {
			$host = preg_replace( '/:\d+$/', '', $host );
		}

		$host = preg_replace( '/[^a-z0-9\-\.]/i', '', $host );

		return $host;
	}

	/**
	 * 是否登录页请求。
	 *
	 * @return bool
	 */
	private function is_login_request() {
		$pagenow = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';
		$script  = isset( $_SERVER['PHP_SELF'] ) ? (string) wp_unslash( $_SERVER['PHP_SELF'] ) : '';

		return 'wp-login.php' === $pagenow || false !== strpos( $script, 'wp-login.php' );
	}

	/**
	 * 是否系统端点请求。
	 *
	 * @return bool
	 */
	private function is_system_endpoint_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? strtolower( (string) wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		foreach ( array( '/wp-json', '/xmlrpc.php', 'sitemap', '.xml' ) as $needle ) {
			if ( false !== strpos( $request_uri, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
