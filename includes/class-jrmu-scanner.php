<?php
/**
 * 扫描、预览、修复工具。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JRMU_Scanner
 */
class JRMU_Scanner {
	/** @var JRMU_Scanner|null */
	private static $instance = null;

	/** 获取单例。 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** 扫描文章。 */
	public function scan_posts( $args = array() ) {
		$options = JRMU_Settings::get_options();
		$args    = wp_parse_args(
			$args,
			array(
				'post_type'   => 'any',
				'post_status' => 'publish',
				'keyword'     => '',
				'limit'       => ! empty( $options['scan_limit'] ) ? absint( $options['scan_limit'] ) : 100,
				'target_base' => home_url(),
			)
		);

		$post_types = 'any' === $args['post_type'] ? array( 'post', 'page' ) : array( sanitize_key( $args['post_type'] ) );
		$post_status = 'any' === $args['post_status'] ? array( 'publish', 'draft', 'private', 'pending', 'future' ) : sanitize_key( $args['post_status'] );

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => $post_status,
				'posts_per_page'         => max( 1, min( 1000, absint( $args['limit'] ) ) ),
				's'                      => sanitize_text_field( $args['keyword'] ),
				'fields'                 => 'ids',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$rows = array();
		foreach ( $query->posts as $post_id ) {
			$row = $this->scan_single_post( $post_id, $args['target_base'] );
			if ( $row && $row['total'] > 0 ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/** 扫描单篇。 */
	public function scan_single_post( $post_id, $target_base = '' ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$content = (string) $post->post_content . "\n" . (string) $post->post_excerpt;
		$conv    = JRMU_Converter::instance();
		$domain  = JRMU_Domain_Adapter::instance();

		$media_samples    = $conv->get_convertible_url_samples( $content, 5 );
		$restore_samples  = $conv->get_restorable_url_samples( $content, $target_base ? $target_base : home_url(), 5 );
		$internal_samples = $domain->get_rewriteable_url_samples( $content, 5, $target_base ? $target_base : home_url() );
		$mixed_samples    = $this->get_mixed_content_samples( $content, 5 );
		$exposed_samples  = $this->get_source_exposure_samples( $content, 5 );

		$total = count( $media_samples ) + count( $restore_samples ) + count( $internal_samples ) + count( $mixed_samples ) + count( $exposed_samples );

		return array(
			'id'               => absint( $post_id ),
			'title'            => get_the_title( $post_id ),
			'type'             => $post->post_type,
			'status'           => $post->post_status,
			'edit_link'        => get_edit_post_link( $post_id, 'raw' ),
			'media_count'      => count( $media_samples ),
			'restore_count'    => count( $restore_samples ),
			'internal_count'   => count( $internal_samples ),
			'mixed_count'      => count( $mixed_samples ),
			'exposure_count'   => count( $exposed_samples ),
			'total'            => $total,
			'samples'          => array_slice( array_merge( $media_samples, $restore_samples, $internal_samples, $mixed_samples, $exposed_samples ), 0, 8 ),
		);
	}

	/** 扫描 postmeta，只读展示。 */
	public function scan_postmeta( $limit = 100 ) {
		global $wpdb;
		$limit  = max( 1, min( 500, absint( $limit ) ) );
		$hosts  = array_merge( JRMU_Converter::instance()->get_allowed_hosts(), JRMU_Domain_Adapter::instance()->get_rewrite_source_hosts() );
		$hosts  = array_filter( array_unique( $hosts ) );
		$likes  = array();
		$params = array();

		foreach ( $hosts as $host ) {
			$likes[]  = 'meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $host ) . '%';
		}
		foreach ( JRMU_Converter::instance()->get_allowed_paths() as $path ) {
			$likes[]  = 'meta_value LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $path ) . '%';
		}
		if ( empty( $likes ) ) {
			return array();
		}

		$params[] = $limit;
		$sql      = "SELECT post_id, meta_key, LEFT(meta_value, 260) AS sample FROM {$wpdb->postmeta} WHERE (" . implode( ' OR ', $likes ) . ') LIMIT %d';
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/** 执行文章批量动作。 */
	public function apply_post_action( $post_ids, $action, $target_base = '' ) {
		$post_ids    = array_map( 'absint', (array) $post_ids );
		$post_ids    = array_filter( array_unique( $post_ids ) );
		$action      = sanitize_key( $action );
		$target_base = $target_base ? untrailingslashit( esc_url_raw( $target_base ) ) : home_url();
		$updated     = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$old_content = (string) $post->post_content;
			$old_excerpt = (string) $post->post_excerpt;
			$new_content = $old_content;
			$new_excerpt = $old_excerpt;

			switch ( $action ) {
				case 'convert_media_relative':
					$new_content = JRMU_Converter::instance()->convert_content( $old_content );
					$new_excerpt = JRMU_Converter::instance()->convert_content( $old_excerpt );
					break;
				case 'restore_media_full':
					$new_content = JRMU_Converter::instance()->restore_content_to_domain( $old_content, $target_base );
					$new_excerpt = JRMU_Converter::instance()->restore_content_to_domain( $old_excerpt, $target_base );
					break;
				case 'internal_to_target':
					$new_content = JRMU_Domain_Adapter::instance()->rewrite_html_to_domain( $old_content, $target_base );
					$new_excerpt = JRMU_Domain_Adapter::instance()->rewrite_html_to_domain( $old_excerpt, $target_base );
					break;
				case 'internal_to_relative':
					$new_content = JRMU_Domain_Adapter::instance()->rewrite_html_to_relative( $old_content );
					$new_excerpt = JRMU_Domain_Adapter::instance()->rewrite_html_to_relative( $old_excerpt );
					break;
				case 'fix_mixed_https':
					$new_content = $this->fix_mixed_content( $old_content );
					$new_excerpt = $this->fix_mixed_content( $old_excerpt );
					break;
			}

			if ( $new_content !== $old_content || $new_excerpt !== $old_excerpt ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $new_content,
						'post_excerpt' => $new_excerpt,
					)
				);
				++$updated;
			}
		}

		$this->add_log( $action, count( $post_ids ), $updated, $target_base );
		return array( 'requested' => count( $post_ids ), 'updated' => $updated );
	}

	/** 混合内容样本。 */
	public function get_mixed_content_samples( $content, $limit = 5 ) {
		$samples = array();
		if ( ! is_string( $content ) || '' === $content ) {
			return $samples;
		}
		$hosts = array_merge( JRMU_Converter::instance()->get_allowed_hosts(), JRMU_Domain_Adapter::instance()->get_rewrite_source_hosts() );
		$hosts = array_unique( array_filter( $hosts ) );
		preg_replace_callback(
			'#http://[^\s"\'<>)]+#i',
			function ( $matches ) use ( &$samples, $limit, $hosts ) {
				if ( count( $samples ) >= $limit ) {
					return $matches[0];
				}
				$url   = $matches[0];
				$parts = wp_parse_url( $url );
				$host  = ! empty( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
				if ( $host && in_array( $host, $hosts, true ) ) {
					$samples[] = array( 'from' => $url, 'to' => preg_replace( '#^http://#i', 'https://', $url ), 'type' => 'HTTP 混合内容' );
				}
				return $url;
			},
			$content
		);
		return $samples;
	}

	/** 修复混合内容。 */
	private function fix_mixed_content( $content ) {
		$samples = $this->get_mixed_content_samples( $content, 999999 );
		foreach ( $samples as $sample ) {
			$content = str_replace( $sample['from'], $sample['to'], $content );
		}
		return $content;
	}

	/** 源站暴露样本。 */
	public function get_source_exposure_samples( $content, $limit = 5 ) {
		$samples = array();
		if ( ! is_string( $content ) || '' === $content ) {
			return $samples;
		}
		$options = JRMU_Settings::get_options();
		$hosts   = array();
		if ( ! empty( $options['extra_hosts'] ) ) {
			$hosts = preg_split( '/\r\n|\r|\n/', $options['extra_hosts'] );
		}
		foreach ( $hosts as $host ) {
			$host = trim( $host );
			if ( '' === $host ) {
				continue;
			}
			if ( false !== stripos( $content, $host ) ) {
				$samples[] = array( 'from' => $host, 'to' => '建议改为入口域名或根相对地址', 'type' => '源站暴露' );
				if ( count( $samples ) >= $limit ) {
					break;
				}
			}
		}
		preg_replace_callback(
			'#https?://\d{1,3}(?:\.\d{1,3}){3}[^\s"\'<>)]+#',
			function ( $matches ) use ( &$samples, $limit ) {
				if ( count( $samples ) < $limit ) {
					$samples[] = array( 'from' => $matches[0], 'to' => '疑似源站 IP 暴露', 'type' => '源站 IP' );
				}
				return $matches[0];
			},
			$content
		);
		return $samples;
	}

	/** 写日志。 */
	public function add_log( $action, $requested, $updated, $target_base = '' ) {
		$logs = get_option( JRMU_LOG_OPTION_KEY, array() );
		$logs = is_array( $logs ) ? $logs : array();
		array_unshift(
			$logs,
			array(
				'time'        => time(),
				'action'      => sanitize_key( $action ),
				'requested'   => absint( $requested ),
				'updated'     => absint( $updated ),
				'target_base' => esc_url_raw( $target_base ),
			)
		);
		$logs = array_slice( $logs, 0, 50 );
		update_option( JRMU_LOG_OPTION_KEY, $logs, false );
	}

	/** 获取日志。 */
	public function get_logs() {
		$logs = get_option( JRMU_LOG_OPTION_KEY, array() );
		return is_array( $logs ) ? $logs : array();
	}
}
