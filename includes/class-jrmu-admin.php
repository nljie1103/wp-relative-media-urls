<?php
/**
 * 后台管理类。
 *
 * @package JiuliuRelativeMediaUrls
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * JRMU_Admin
 */
class JRMU_Admin {

	/**
	 * 单例实例。
	 *
	 * @var JRMU_Admin|null
	 */
	private static $instance = null;

	/**
	 * 获取单例实例。
	 *
	 * @return JRMU_Admin
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
	 * 初始化后台钩子。
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_jrmu_batch_convert', array( $this, 'handle_batch_convert' ) );
	}

	/**
	 * 添加后台菜单。
	 */
	public function add_admin_menu() {
		add_menu_page(
			'九流媒体相对地址',
			'媒体相对地址',
			'manage_options',
			'jiuliu-relative-media-urls',
			array( $this, 'render_settings_page' ),
			'dashicons-admin-links',
			58
		);
	}

	/**
	 * 注册设置。
	 */
	public function register_settings() {
		register_setting(
			'jrmu_settings_group',
			JRMU_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'JRMU_Settings', 'sanitize' ),
				'default'           => JRMU_Settings::get_defaults(),
			)
		);
	}

	/**
	 * 加载后台静态资源。
	 *
	 * @param string $hook 当前页面 hook。
	 */
	public function enqueue_assets( $hook ) {
		if ( 'toplevel_page_jiuliu-relative-media-urls' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'jrmu-admin', JRMU_PLUGIN_URL . 'assets/css/admin.css', array(), JRMU_VERSION );
	}

	/**
	 * 渲染设置页。
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = JRMU_Settings::get_options();
		$notice  = isset( $_GET['jrmu_notice'] ) ? sanitize_key( wp_unslash( $_GET['jrmu_notice'] ) ) : '';
		$count   = isset( $_GET['jrmu_count'] ) ? absint( $_GET['jrmu_count'] ) : 0;
		$changed = isset( $_GET['jrmu_changed'] ) ? absint( $_GET['jrmu_changed'] ) : 0;
		?>
		<div class="wrap jrmu-wrap">
			<h1>九流媒体相对地址 v<?php echo esc_html( JRMU_VERSION ); ?></h1>
			<p class="description">将同站点媒体绝对地址转换为根相对地址，适合香港反代缓存、源站隐藏、域名切换等场景。</p>

			<?php if ( 'batch_done' === $notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>批量转换完成：本次扫描 <?php echo esc_html( $count ); ?> 篇内容，实际更新 <?php echo esc_html( $changed ); ?> 篇。</p>
				</div>
			<?php elseif ( 'batch_none' === $notice ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>没有发现需要转换的内容。</p>
				</div>
			<?php endif; ?>

			<div class="jrmu-grid">
				<div class="jrmu-main-card">
					<form method="post" action="options.php">
						<?php settings_fields( 'jrmu_settings_group' ); ?>

						<h2>基础设置</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">启用插件</th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $options['enabled'], 1 ); ?>> 启用相对地址转换</label>
									<p class="description">关闭后所有保存、输出、媒体库转换都会停止。</p>
								</td>
							</tr>

							<tr>
								<th scope="row">转换时机</th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_on_save]" value="1" <?php checked( $options['convert_on_save'], 1 ); ?>> 保存文章时转换正文/摘要</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_on_output]" value="1" <?php checked( $options['convert_on_output'], 1 ); ?>> 前台输出时兜底转换旧内容</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_attachment_urls]" value="1" <?php checked( $options['convert_attachment_urls'], 1 ); ?>> 转换附件 URL API（wp_get_attachment_url / 图片属性）</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_srcset]" value="1" <?php checked( $options['convert_srcset'], 1 ); ?>> 转换响应式图片 srcset</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_admin_media_js]" value="1" <?php checked( $options['convert_admin_media_js'], 1 ); ?>> 转换媒体库弹窗返回值，让编辑器插入时更容易使用相对地址</label>
								</td>
							</tr>

							<tr>
								<th scope="row">转换路径</th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[target_uploads]" value="1" <?php checked( $options['target_uploads'], 1 ); ?>> 媒体上传目录：<code>/wp-content/uploads/</code></label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[target_themes]" value="1" <?php checked( $options['target_themes'], 1 ); ?>> 主题静态目录：<code>/wp-content/themes/</code></label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[target_plugins]" value="1" <?php checked( $options['target_plugins'], 1 ); ?>> 插件静态目录：<code>/wp-content/plugins/</code></label>
									<p class="description">默认只转换媒体库 uploads。主题/插件资源建议确认缓存策略后再开启。</p>
								</td>
							</tr>

							<tr>
								<th scope="row">额外源站域名</th>
								<td>
									<textarea name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[extra_hosts]" rows="5" class="large-text code" placeholder="origin-blog.jiuliu.org&#10;us-origin.example.com"><?php echo esc_textarea( $options['extra_hosts'] ); ?></textarea>
									<p class="description">每行一个。可填域名或完整 URL。用于把旧文章里写死的美国源站域名也转换为根相对路径。</p>
								</td>
							</tr>

							<tr>
								<th scope="row">批量处理数量</th>
								<td>
									<input type="number" min="20" max="1000" step="10" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[batch_limit]" value="<?php echo esc_attr( $options['batch_limit'] ); ?>" class="small-text"> 篇 / 次
									<p class="description">手动批量转换时单次最多扫描的文章数量。站点很大时建议保持 200 左右，分多次执行。</p>
								</td>
							</tr>
						</table>

						<?php submit_button( '保存设置' ); ?>
					</form>
				</div>

				<div class="jrmu-side-card">
					<h2>手动批量转换</h2>
					<p>把已经保存到文章内容里的同站点绝对媒体地址转换成根相对地址。</p>
					<p>例：</p>
					<pre>https://blog.jiuliu.org/wp-content/uploads/a.jpg
↓
/wp-content/uploads/a.jpg</pre>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('建议先备份数据库。本操作会修改文章正文/摘要中的匹配 URL，确认继续吗？');">
						<input type="hidden" name="action" value="jrmu_batch_convert">
						<?php wp_nonce_field( 'jrmu_batch_convert' ); ?>
						<?php submit_button( '批量转换现有文章', 'secondary', 'submit', false ); ?>
					</form>

					<hr>
					<h2>当前识别范围</h2>
					<p><strong>允许域名：</strong></p>
					<ul class="jrmu-list">
						<?php foreach ( JRMU_Converter::instance()->get_allowed_hosts() as $host ) : ?>
							<li><code><?php echo esc_html( $host ); ?></code></li>
						<?php endforeach; ?>
					</ul>

					<p><strong>允许路径：</strong></p>
					<ul class="jrmu-list">
						<?php foreach ( JRMU_Converter::instance()->get_allowed_paths() as $path ) : ?>
							<li><code><?php echo esc_html( $path ); ?></code></li>
						<?php endforeach; ?>
					</ul>

					<p class="description">插件只会转换上面域名 + 路径同时命中的 URL，不会动外链图片。</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 处理手动批量转换。
	 */
	public function handle_batch_convert() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '权限不足。' );
		}

		check_admin_referer( 'jrmu_batch_convert' );

		$options   = JRMU_Settings::get_options();
		$limit     = ! empty( $options['batch_limit'] ) ? absint( $options['batch_limit'] ) : 200;
		$converter = JRMU_Converter::instance();
		$changed   = 0;
		$count     = 0;

		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		// 补充常见非 public 但可能有正文的类型，保持保守。
		$post_types = array_unique( array_merge( $post_types, array( 'post', 'page' ) ) );

		$query = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => $limit,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $query->posts as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			++$count;

			$new_content = $converter->convert_content( $post->post_content );
			$new_excerpt = $converter->convert_content( $post->post_excerpt );

			if ( $new_content !== $post->post_content || $new_excerpt !== $post->post_excerpt ) {
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $new_content,
						'post_excerpt' => $new_excerpt,
					)
				);

				++$changed;
			}
		}

		$notice = $changed > 0 ? 'batch_done' : 'batch_none';
		$url    = add_query_arg(
			array(
				'page'         => 'jiuliu-relative-media-urls',
				'jrmu_notice'  => $notice,
				'jrmu_count'   => $count,
				'jrmu_changed' => $changed,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
