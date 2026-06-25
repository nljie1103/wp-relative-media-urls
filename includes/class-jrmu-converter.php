<?php
/**
 * URL 转换核心类。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JRMU_Converter
 */
class JRMU_Converter {

	/**
	 * 单例实例。
	 *
	 * @var JRMU_Converter|null
	 */
	private static $instance = null;

	/**
	 * 获取单例。
	 *
	 * @return JRMU_Converter
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
	 * 初始化转换钩子。
	 */
	private function init_hooks() {
		$options = JRMU_Settings::get_options();

		if ( ! empty( $options['convert_post_on_save'] ) ) {
			add_filter( 'content_save_pre', array( $this, 'convert_content' ), 99 );
			add_filter( 'excerpt_save_pre', array( $this, 'convert_content' ), 99 );
		}

		if ( ! empty( $options['convert_post_on_frontend'] ) ) {
			add_filter( 'the_content', array( $this, 'convert_content' ), 99 );
			add_filter( 'the_excerpt', array( $this, 'convert_content' ), 99 );
			add_filter( 'widget_text', array( $this, 'convert_content' ), 99 );
			add_filter( 'widget_text_content', array( $this, 'convert_content' ), 99 );
			add_filter( 'render_block', array( $this, 'convert_content' ), 99 );
		}

		if ( ! empty( $options['convert_existing_media_output'] ) || ! empty( $options['convert_future_media_output'] ) ) {
			add_filter( 'wp_get_attachment_url', array( $this, 'convert_attachment_url' ), 99, 2 );
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'convert_attachment_image_attributes' ), 99, 3 );
			add_filter( 'wp_calculate_image_srcset', array( $this, 'convert_srcset' ), 99, 5 );
			add_filter( 'wp_prepare_attachment_for_js', array( $this, 'convert_attachment_for_js' ), 99, 3 );
			add_filter( 'post_thumbnail_html', array( $this, 'convert_content' ), 99 );
		}

		if ( ! empty( $options['convert_future_media_output'] ) ) {
			add_action( 'add_attachment', array( $this, 'mark_new_attachment' ), 10, 1 );
		}
	}

	/**
	 * 标记开启“未来媒体相对地址”后上传的新附件。
	 *
	 * @param int $attachment_id 附件 ID。
	 */
	public function mark_new_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return;
		}

		update_post_meta( $attachment_id, JRMU_ATTACHMENT_META_KEY, time() );
	}

	/**
	 * 判断附件是否应该转换 URL。
	 *
	 * @param int $attachment_id 附件 ID。
	 * @return bool
	 */
	public function should_convert_attachment( $attachment_id ) {
		$options = JRMU_Settings::get_options();

		if ( ! empty( $options['convert_existing_media_output'] ) ) {
			return true;
		}

		if ( empty( $options['convert_future_media_output'] ) ) {
			return false;
		}

		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return false;
		}

		$marked_at = absint( get_post_meta( $attachment_id, JRMU_ATTACHMENT_META_KEY, true ) );

		if ( $marked_at > 0 ) {
			return true;
		}

		// 兜底：如果插件开启后上传，但 add_attachment 没来得及标记，也按附件发布时间判断。
		$enabled_at = ! empty( $options['future_media_enabled_at'] ) ? absint( $options['future_media_enabled_at'] ) : 0;
		$post_time  = get_post_time( 'U', true, $attachment_id );

		return $enabled_at > 0 && $post_time && $post_time >= $enabled_at;
	}

	/**
	 * 转换附件 URL。
	 *
	 * @param string $url           附件 URL。
	 * @param int    $attachment_id 附件 ID。
	 * @return string
	 */
	public function convert_attachment_url( $url, $attachment_id = 0 ) {
		if ( ! $this->should_convert_attachment( $attachment_id ) ) {
			return $url;
		}

		return $this->convert_url( $url );
	}

	/**
	 * 转换一段 HTML/文本内容中的绝对 URL。
	 *
	 * @param string $content 原始内容。
	 * @return string
	 */
	public function convert_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return $content;
		}

		$converted = preg_replace_callback(
			'#https?://[^\s"\'<>)]+#i',
			array( $this, 'convert_url_match' ),
			$content
		);

		return is_string( $converted ) ? $converted : $content;
	}

	/**
	 * preg_replace_callback 回调。
	 *
	 * @param array $matches 匹配项。
	 * @return string
	 */
	public function convert_url_match( $matches ) {
		$url = isset( $matches[0] ) ? $matches[0] : '';

		return $this->convert_url( $url );
	}

	/**
	 * 将符合条件的绝对 URL 转换为根相对 URL。
	 *
	 * @param string $url 原始 URL。
	 * @return string
	 */
	public function convert_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );

		if ( empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$host = strtolower( $parts['host'] );

		if ( ! $this->is_allowed_host( $host ) ) {
			return $url;
		}

		$path = $parts['path'];

		if ( ! $this->is_allowed_path( $path ) ) {
			return $url;
		}

		$relative = $path;

		if ( isset( $parts['query'] ) && '' !== $parts['query'] ) {
			$relative .= '?' . $parts['query'];
		}

		if ( isset( $parts['fragment'] ) && '' !== $parts['fragment'] ) {
			$relative .= '#' . $parts['fragment'];
		}

		return $relative;
	}

	/**
	 * 转换 srcset 数组。
	 *
	 * @param array|false $sources       sources。
	 * @param array       $size_array    尺寸。
	 * @param string      $image_src     图片地址。
	 * @param array       $image_meta    图片元数据。
	 * @param int         $attachment_id 附件 ID。
	 * @return array|false
	 */
	public function convert_srcset( $sources, $size_array = array(), $image_src = '', $image_meta = array(), $attachment_id = 0 ) {
		if ( ! is_array( $sources ) || ! $this->should_convert_attachment( $attachment_id ) ) {
			return $sources;
		}

		foreach ( $sources as &$source ) {
			if ( isset( $source['url'] ) ) {
				$source['url'] = $this->convert_url( $source['url'] );
			}
		}

		return $sources;
	}

	/**
	 * 转换 wp_get_attachment_image_attributes 返回值。
	 *
	 * @param array   $attr       图片属性。
	 * @param WP_Post $attachment 附件对象。
	 * @param string  $size       尺寸。
	 * @return array
	 */
	public function convert_attachment_image_attributes( $attr, $attachment = null, $size = '' ) {
		$attachment_id = isset( $attachment->ID ) ? absint( $attachment->ID ) : 0;

		if ( ! is_array( $attr ) || ! $this->should_convert_attachment( $attachment_id ) ) {
			return $attr;
		}

		foreach ( array( 'src', 'data-src', 'data-lazy-src' ) as $key ) {
			if ( isset( $attr[ $key ] ) ) {
				$attr[ $key ] = $this->convert_url( $attr[ $key ] );
			}
		}

		if ( isset( $attr['srcset'] ) && is_string( $attr['srcset'] ) ) {
			$attr['srcset'] = $this->convert_content( $attr['srcset'] );
		}

		return $attr;
	}

	/**
	 * 转换媒体库弹窗 JS 响应。
	 *
	 * @param array   $response   附件响应。
	 * @param WP_Post $attachment 附件对象。
	 * @param array   $meta       元数据。
	 * @return array
	 */
	public function convert_attachment_for_js( $response, $attachment = null, $meta = array() ) {
		$attachment_id = isset( $attachment->ID ) ? absint( $attachment->ID ) : 0;

		if ( ! is_array( $response ) || ! $this->should_convert_attachment( $attachment_id ) ) {
			return $response;
		}

		if ( isset( $response['url'] ) ) {
			$response['url'] = $this->convert_url( $response['url'] );
		}

		if ( isset( $response['link'] ) ) {
			$response['link'] = $this->convert_url( $response['link'] );
		}

		if ( isset( $response['sizes'] ) && is_array( $response['sizes'] ) ) {
			foreach ( $response['sizes'] as &$size ) {
				if ( isset( $size['url'] ) ) {
					$size['url'] = $this->convert_url( $size['url'] );
				}
			}
		}

		return $response;
	}

	/**
	 * 获取允许转换的域名列表。
	 *
	 * @return array
	 */
	public function get_allowed_hosts() {
		$options = JRMU_Settings::get_options();
		$hosts   = array();

		foreach ( array( home_url(), site_url(), content_url(), includes_url() ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );

			if ( $host ) {
				$hosts[] = strtolower( $host );
			}
		}

		if ( is_multisite() ) {
			$network_host = wp_parse_url( network_home_url(), PHP_URL_HOST );
			if ( $network_host ) {
				$hosts[] = strtolower( $network_host );
			}
		}

		if ( ! empty( $options['extra_hosts'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $options['extra_hosts'] );

			foreach ( $lines as $line ) {
				$line = strtolower( trim( $line ) );

				if ( $line ) {
					$hosts[] = $line;
				}
			}
		}

		$hosts = array_filter( array_unique( $hosts ) );

		return apply_filters( 'jrmu_allowed_hosts', array_values( $hosts ) );
	}

	/**
	 * 判断域名是否允许转换。
	 *
	 * @param string $host 域名。
	 * @return bool
	 */
	public function is_allowed_host( $host ) {
		$host = strtolower( (string) $host );

		return in_array( $host, $this->get_allowed_hosts(), true );
	}

	/**
	 * 获取允许转换的路径前缀。
	 *
	 * @return array
	 */
	public function get_allowed_paths() {
		$options = JRMU_Settings::get_options();
		$paths   = array();

		if ( ! empty( $options['target_uploads'] ) ) {
			$uploads = wp_get_upload_dir();

			if ( ! empty( $uploads['baseurl'] ) ) {
				$paths[] = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
			}

			$paths[] = '/wp-content/uploads';
		}

		if ( ! empty( $options['target_themes'] ) ) {
			$paths[] = wp_parse_url( content_url( 'themes' ), PHP_URL_PATH );
			$paths[] = '/wp-content/themes';
		}

		if ( ! empty( $options['target_plugins'] ) ) {
			$paths[] = wp_parse_url( plugins_url(), PHP_URL_PATH );
			$paths[] = '/wp-content/plugins';
		}

		$paths = array_filter( $paths );
		$paths = array_map( array( $this, 'normalize_path_prefix' ), $paths );
		$paths = array_filter( array_unique( $paths ) );

		return apply_filters( 'jrmu_allowed_paths', array_values( $paths ) );
	}

	/**
	 * 判断路径是否允许转换。
	 *
	 * @param string $path URL path。
	 * @return bool
	 */
	public function is_allowed_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );

		foreach ( $this->get_allowed_paths() as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 统计内容中可转换 URL 的数量。
	 *
	 * @param string $content 内容。
	 * @return int
	 */
	public function count_convertible_urls( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return 0;
		}

		$count = 0;
		preg_replace_callback(
			'#https?://[^\s"\'<>)]+#i',
			function ( $matches ) use ( &$count ) {
				$url = isset( $matches[0] ) ? $matches[0] : '';
				if ( $url && $this->convert_url( $url ) !== $url ) {
					++$count;
				}
				return $url;
			},
			$content
		);

		return $count;
	}

	/**
	 * 获取内容中可转换 URL 预览。
	 *
	 * @param string $content 内容。
	 * @param int    $limit   数量。
	 * @return array
	 */
	public function get_convertible_url_samples( $content, $limit = 3 ) {
		$samples = array();

		if ( ! is_string( $content ) || '' === $content ) {
			return $samples;
		}

		preg_replace_callback(
			'#https?://[^\s"\'<>)]+#i',
			function ( $matches ) use ( &$samples, $limit ) {
				if ( count( $samples ) >= $limit ) {
					return isset( $matches[0] ) ? $matches[0] : '';
				}

				$url       = isset( $matches[0] ) ? $matches[0] : '';
				$converted = $this->convert_url( $url );

				if ( $url && $converted !== $url ) {
					$samples[] = array(
						'from' => $url,
						'to'   => $converted,
					);
				}

				return $url;
			},
			$content
		);

		return $samples;
	}

	/**
	 * 标准化路径前缀。
	 *
	 * @param string $path 路径。
	 * @return string
	 */
	private function normalize_path_prefix( $path ) {
		$path = '/' . trim( (string) $path, '/' );

		return untrailingslashit( $path );
	}
}
