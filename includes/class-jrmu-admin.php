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
	 * 获取单例。
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
		add_action( 'admin_post_jrmu_convert_selected_posts', array( $this, 'handle_convert_selected_posts' ) );
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
	 * 加载后台资源。
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

		$options   = JRMU_Settings::get_options();
		$notice    = isset( $_GET['jrmu_notice'] ) ? sanitize_key( wp_unslash( $_GET['jrmu_notice'] ) ) : '';
		$changed   = isset( $_GET['jrmu_changed'] ) ? absint( $_GET['jrmu_changed'] ) : 0;
		$requested = isset( $_GET['jrmu_requested'] ) ? absint( $_GET['jrmu_requested'] ) : 0;
		$scan      = isset( $_GET['jrmu_scan'] ) ? absint( $_GET['jrmu_scan'] ) : 0;
		$results   = $scan ? $this->get_scan_results() : array();
		?>
		<div class="wrap jrmu-wrap">
			<h1>九流媒体相对地址 v<?php echo esc_html( JRMU_VERSION ); ?></h1>
			<p class="description">媒体库地址和文章内容地址分开控制。插件启用后默认不改变任何输出或数据库内容，所有转换都需要手动开启或手动执行。</p>

			<?php $this->render_notices( $notice, $requested, $changed ); ?>

			<div class="jrmu-grid">
				<div class="jrmu-main-card">
					<form method="post" action="options.php">
						<?php settings_fields( 'jrmu_settings_group' ); ?>

						<h2>一、媒体库地址</h2>
						<p class="description">这里控制媒体库、编辑器、附件 URL API、模板调用附件图时的输出地址。它默认是可逆的输出层转换，不移动文件、不删除文件、不修改 WordPress 核心。</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">已上传媒体</th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_existing_media_output]" value="1" <?php checked( $options['convert_existing_media_output'], 1 ); ?>>
										转换已经上传媒体的输出地址
									</label>
									<p class="description">开启后，之前已经上传到媒体库的图片，在媒体库、编辑器、<code>wp_get_attachment_url()</code>、特色图、<code>srcset</code> 等输出中会显示为 <code>/wp-content/uploads/...</code>。关闭后恢复 WordPress 默认完整 URL 输出。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">未来上传媒体</th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_future_media_output]" value="1" <?php checked( $options['convert_future_media_output'], 1 ); ?>>
										从开启后，新上传媒体默认使用相对地址输出
									</label>
									<p class="description">只影响开启之后上传的新附件。插件会给新附件打标记，之后它们在媒体库和前台附件输出中使用相对地址；旧媒体不受影响，除非你开启上面的“已上传媒体”。</p>
									<?php if ( ! empty( $options['future_media_enabled_at'] ) ) : ?>
										<p class="description">当前未来媒体规则开启时间：<?php echo esc_html( wp_date( 'Y-m-d H:i:s', absint( $options['future_media_enabled_at'] ) ) ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						</table>

						<h2>二、文章内容地址</h2>
						<p class="description">这里控制文章正文/摘要里面已经插入的图片地址。文章保存转换和前台临时转换相互独立。</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">未来保存文章</th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_post_on_save]" value="1" <?php checked( $options['convert_post_on_save'], 1 ); ?>>
										保存文章/页面时自动转换内容中的媒体地址
									</label>
									<p class="description">开启后，今后保存文章或页面时，正文/摘要里的同站点绝对媒体 URL 会永久保存为根相对路径。不会自动处理历史文章，除非你手动扫描并转换。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">前台临时输出</th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[convert_post_on_frontend]" value="1" <?php checked( $options['convert_post_on_frontend'], 1 ); ?>>
										前台显示文章时临时转换旧内容
									</label>
									<p class="description">只影响访客看到的前台 HTML，不修改数据库。适合先测试旧文章走反代缓存的效果。关闭插件或关闭此项后，前台恢复数据库原始内容。</p>
								</td>
							</tr>
						</table>

						<h2>三、多域名访问适配</h2>
						<p class="description">这里解决首页是香港反代域名，但点击文章、页面、菜单后又跳回源站固定域名的问题。默认关闭，开启后推荐使用白名单模式。</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">总开关</th>
								<td>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_adaptation_enabled]" value="1" <?php checked( $options['domain_adaptation_enabled'], 1 ); ?>>
										启用站内链接跟随当前访问域名
									</label>
									<p class="description">开启后，WordPress 生成文章、页面、分类、菜单等站内链接时，会优先使用当前访问域名。不会修改数据库里的 <code>home</code> / <code>siteurl</code>。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">允许域名</th>
								<td>
									<textarea name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_allowed_hosts]" rows="5" class="large-text code" placeholder="blog.jiuliu.org&#10;hk-blog.jiuliu.org&#10;origin-blog.jiuliu.org"><?php echo esc_textarea( $options['domain_allowed_hosts'] ); ?></textarea>
									<p class="description">每行一个允许访问入口域名。白名单模式下，只有这里列出的域名才会触发动态站点地址，避免任意 Host 被用来生成站内链接。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">适配模式</th>
								<td>
									<label><input type="radio" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_mode]" value="whitelist" <?php checked( $options['domain_mode'], 'whitelist' ); ?>> 仅允许白名单域名，推荐</label><br>
									<label><input type="radio" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_mode]" value="any" <?php checked( $options['domain_mode'], 'any' ); ?>> 允许任意 Host，高级模式</label>
									<p class="description">高级模式适合你明确知道所有绑定域名都可信的场景。公开站点一般建议使用白名单。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">协议</th>
								<td>
									<label><input type="radio" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_scheme]" value="https" <?php checked( $options['domain_scheme'], 'https' ); ?>> 强制 HTTPS，推荐</label><br>
									<label><input type="radio" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_scheme]" value="auto" <?php checked( $options['domain_scheme'], 'auto' ); ?>> 自动识别 HTTPS / X-Forwarded-Proto</label><br>
									<label><input type="radio" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_scheme]" value="http" <?php checked( $options['domain_scheme'], 'http' ); ?>> 强制 HTTP</label>
								</td>
							</tr>
							<tr>
								<th scope="row">高级选项</th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_dynamic_siteurl]" value="1" <?php checked( $options['domain_dynamic_siteurl'], 1 ); ?>> 同时动态适配 siteurl，影响主题/插件资源生成</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_rewrite_frontend_links]" value="1" <?php checked( $options['domain_rewrite_frontend_links'], 1 ); ?>> 前台 HTML 兜底替换站内绝对链接为当前域名</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_exclude_admin]" value="1" <?php checked( $options['domain_exclude_admin'], 1 ); ?>> 后台 wp-admin 保持原始站点地址</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_exclude_login]" value="1" <?php checked( $options['domain_exclude_login'], 1 ); ?>> 登录页 wp-login.php 保持原始站点地址</label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[domain_skip_system_endpoints]" value="1" <?php checked( $options['domain_skip_system_endpoints'], 1 ); ?>> REST API、Feed、Sitemap、XML 等系统端点保持原始站点地址</label>
									<p class="description">建议先只开启总开关和白名单，确认文章点击不再跳回源站后，再按需开启“前台 HTML 兜底替换”。</p>
								</td>
							</tr>
						</table>

						<h2>四、转换范围</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">路径范围</th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[target_uploads]" value="1" <?php checked( $options['target_uploads'], 1 ); ?>> 媒体上传目录：<code>/wp-content/uploads/</code></label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[target_themes]" value="1" <?php checked( $options['target_themes'], 1 ); ?>> 主题静态目录：<code>/wp-content/themes/</code></label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[target_plugins]" value="1" <?php checked( $options['target_plugins'], 1 ); ?>> 插件静态目录：<code>/wp-content/plugins/</code></label>
									<p class="description">默认只建议处理 uploads。主题/插件资源可能被缓存插件、构建工具或版本参数影响，确认无问题后再开启。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">额外源站域名</th>
								<td>
									<textarea name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[extra_hosts]" rows="5" class="large-text code" placeholder="origin-blog.jiuliu.org&#10;us-origin.example.com"><?php echo esc_textarea( $options['extra_hosts'] ); ?></textarea>
									<p class="description">每行一个。可填写美国源站域名、旧域名或完整 URL。插件只转换当前站点域名、siteurl/content_url 域名和这里列出的白名单域名。</p>
								</td>
							</tr>
							<tr>
								<th scope="row">扫描数量</th>
								<td>
									<input type="number" min="10" max="500" step="10" name="<?php echo esc_attr( JRMU_OPTION_KEY ); ?>[scan_limit]" value="<?php echo esc_attr( $options['scan_limit'] ); ?>" class="small-text"> 篇 / 次
									<p class="description">历史文章扫描的单次数量上限。站点较大时建议保持 100 左右。</p>
								</td>
							</tr>
						</table>

						<?php submit_button( '保存设置' ); ?>
					</form>

					<hr>
					<?php $this->render_history_tools( $results ); ?>
				</div>

				<div class="jrmu-side-card">
					<h2>当前状态</h2>
					<?php $this->render_status_list( $options ); ?>
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
							<li><code><?php echo esc_html( trailingslashit( $path ) ); ?></code></li>
						<?php endforeach; ?>
					</ul>
					<hr>
					<h2>多域名状态</h2>
					<?php $this->render_domain_status(); ?>
					<hr>
					<h2>转换示例</h2>
					<pre>https://example.com/wp-content/uploads/a.jpg
↓
/wp-content/uploads/a.jpg</pre>
					<p class="description">根相对地址会由浏览器按当前访问域名自动补全，所以适合香港反代、美国源站、多入口访问。</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 渲染提示。
	 *
	 * @param string $notice    提示类型。
	 * @param int    $requested 请求数量。
	 * @param int    $changed   变更数量。
	 */
	private function render_notices( $notice, $requested, $changed ) {
		if ( 'converted' === $notice ) :
			?>
			<div class="notice notice-success is-dismissible"><p>转换完成：你选择了 <?php echo esc_html( $requested ); ?> 篇内容，实际更新 <?php echo esc_html( $changed ); ?> 篇。</p></div>
			<?php
		elseif ( 'no_posts' === $notice ) :
			?>
			<div class="notice notice-info is-dismissible"><p>没有选择任何文章，因此没有执行转换。</p></div>
			<?php
		elseif ( 'security_failed' === $notice ) :
			?>
			<div class="notice notice-error is-dismissible"><p>安全验证失败，请刷新页面后重试。</p></div>
			<?php
		endif;
	}

	/**
	 * 渲染状态列表。
	 *
	 * @param array $options 设置。
	 */
	private function render_status_list( $options ) {
		$items = array(
			'已上传媒体输出转换' => ! empty( $options['convert_existing_media_output'] ),
			'未来上传媒体相对输出' => ! empty( $options['convert_future_media_output'] ),
			'保存文章时转换'     => ! empty( $options['convert_post_on_save'] ),
			'前台文章临时转换'   => ! empty( $options['convert_post_on_frontend'] ),
				'多域名访问适配'     => ! empty( $options['domain_adaptation_enabled'] ),
		);
		?>
		<ul class="jrmu-status-list">
			<?php foreach ( $items as $label => $enabled ) : ?>
				<li><span class="jrmu-badge <?php echo $enabled ? 'is-on' : 'is-off'; ?>"><?php echo $enabled ? '开启' : '关闭'; ?></span> <?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ul>
		<?php if ( ! array_filter( $items ) ) : ?>
			<p class="jrmu-warning">当前没有开启任何转换。插件已安装，但不会改变媒体库、前台输出或数据库内容。</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * 渲染多域名状态。
	 */
	private function render_domain_status() {
		$adapter = JRMU_Domain_Adapter::instance();
		$info    = $adapter->get_debug_info();
		?>
		<ul class="jrmu-list">
			<li>当前 Host：<code><?php echo esc_html( $info['current_host'] ? $info['current_host'] : '未识别' ); ?></code></li>
			<li>当前协议：<code><?php echo esc_html( $info['current_scheme'] ); ?></code></li>
			<li>是否允许：<span class="jrmu-badge <?php echo $info['is_allowed'] ? 'is-on' : 'is-off'; ?>"><?php echo $info['is_allowed'] ? '允许' : '不允许'; ?></span></li>
			<li>动态基础 URL：<code><?php echo esc_html( $info['dynamic_base_url'] ? $info['dynamic_base_url'] : '未启用/不适用' ); ?></code></li>
		</ul>
		<p><strong>多域名白名单：</strong></p>
		<ul class="jrmu-list">
			<?php if ( empty( $info['allowed_hosts'] ) ) : ?>
				<li><em>未设置</em></li>
			<?php else : ?>
				<?php foreach ( $info['allowed_hosts'] as $host ) : ?>
					<li><code><?php echo esc_html( $host ); ?></code></li>
				<?php endforeach; ?>
			<?php endif; ?>
		</ul>
		<p><strong>可改写来源域名：</strong></p>
		<ul class="jrmu-list">
			<?php foreach ( $info['source_hosts'] as $host ) : ?>
				<li><code><?php echo esc_html( $host ); ?></code></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * 渲染历史文章工具。
	 *
	 * @param array $results 扫描结果。
	 */
	private function render_history_tools( $results ) {
		$scan_post_type = isset( $_GET['jrmu_post_type'] ) ? sanitize_key( wp_unslash( $_GET['jrmu_post_type'] ) ) : 'any';
		$scan_status    = isset( $_GET['jrmu_post_status'] ) ? sanitize_key( wp_unslash( $_GET['jrmu_post_status'] ) ) : 'publish';
		$keyword        = isset( $_GET['jrmu_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['jrmu_keyword'] ) ) : '';
		?>
		<h2>五、历史文章处理</h2>
		<p class="description">这里处理已经发布/已经保存到数据库里的文章内容。它和媒体库输出转换是两个独立功能。先扫描预览，再勾选文章执行转换。</p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="jrmu-scan-form">
			<input type="hidden" name="page" value="jiuliu-relative-media-urls">
			<input type="hidden" name="jrmu_scan" value="1">
			<p>
				<label>内容类型：
					<select name="jrmu_post_type">
						<option value="any" <?php selected( $scan_post_type, 'any' ); ?>>文章 + 页面</option>
						<option value="post" <?php selected( $scan_post_type, 'post' ); ?>>文章 post</option>
						<option value="page" <?php selected( $scan_post_type, 'page' ); ?>>页面 page</option>
					</select>
				</label>
				<label>状态：
					<select name="jrmu_post_status">
						<option value="publish" <?php selected( $scan_status, 'publish' ); ?>>已发布</option>
						<option value="draft" <?php selected( $scan_status, 'draft' ); ?>>草稿</option>
						<option value="private" <?php selected( $scan_status, 'private' ); ?>>私密</option>
						<option value="any" <?php selected( $scan_status, 'any' ); ?>>全部状态</option>
					</select>
				</label>
				<label>关键词：
					<input type="search" name="jrmu_keyword" value="<?php echo esc_attr( $keyword ); ?>" placeholder="可留空">
				</label>
				<?php submit_button( '扫描可转换链接', 'secondary', 'submit', false ); ?>
			</p>
		</form>

		<?php if ( ! empty( $_GET['jrmu_scan'] ) ) : ?>
			<?php if ( empty( $results ) ) : ?>
				<div class="notice notice-info inline"><p>没有发现可转换的文章内容。</p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('建议先备份数据库。本操作只会修改你勾选的文章正文/摘要中的匹配 URL，确认继续吗？');">
					<input type="hidden" name="action" value="jrmu_convert_selected_posts">
					<?php wp_nonce_field( 'jrmu_convert_selected_posts' ); ?>
					<table class="widefat striped jrmu-results-table">
						<thead>
							<tr>
								<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.jrmu-post-check').forEach(function(el){el.checked=this.checked;}, this);"></td>
								<th>标题</th>
								<th>类型/状态</th>
								<th>可转换链接数</th>
								<th>预览</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $results as $row ) : ?>
								<tr>
									<th class="check-column"><input type="checkbox" class="jrmu-post-check" name="post_ids[]" value="<?php echo esc_attr( $row['id'] ); ?>"></th>
									<td>
										<strong><?php echo esc_html( $row['title'] ); ?></strong><br>
										<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>" target="_blank">编辑</a>
									</td>
									<td><?php echo esc_html( $row['type'] . ' / ' . $row['status'] ); ?></td>
									<td><?php echo esc_html( $row['count'] ); ?></td>
									<td>
										<?php foreach ( $row['samples'] as $sample ) : ?>
											<div class="jrmu-sample"><code><?php echo esc_html( $this->shorten_url( $sample['from'] ) ); ?></code><br>↓<br><code><?php echo esc_html( $sample['to'] ); ?></code></div>
										<?php endforeach; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php submit_button( '转换选中文章', 'primary' ); ?>
				</form>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * 扫描文章。
	 *
	 * @return array
	 */
	private function get_scan_results() {
		$options        = JRMU_Settings::get_options();
		$converter      = JRMU_Converter::instance();
		$scan_post_type = isset( $_GET['jrmu_post_type'] ) ? sanitize_key( wp_unslash( $_GET['jrmu_post_type'] ) ) : 'any';
		$scan_status    = isset( $_GET['jrmu_post_status'] ) ? sanitize_key( wp_unslash( $_GET['jrmu_post_status'] ) ) : 'publish';
		$keyword        = isset( $_GET['jrmu_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['jrmu_keyword'] ) ) : '';
		$limit          = ! empty( $options['scan_limit'] ) ? absint( $options['scan_limit'] ) : 100;

		$post_types = 'any' === $scan_post_type ? array( 'post', 'page' ) : array( $scan_post_type );
		$status     = 'any' === $scan_status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : array( $scan_status );

		$args = array(
			'post_type'              => $post_types,
			'post_status'            => $status,
			'posts_per_page'         => $limit,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			's'                     => $keyword,
		);

		$query   = new WP_Query( $args );
		$results = array();

		foreach ( $query->posts as $post ) {
			$content = (string) $post->post_content . "\n" . (string) $post->post_excerpt;

			if ( false === stripos( $content, 'http' ) ) {
				continue;
			}

			$count = $converter->count_convertible_urls( $content );

			if ( $count <= 0 ) {
				continue;
			}

			$results[] = array(
				'id'      => $post->ID,
				'title'   => get_the_title( $post ),
				'type'    => $post->post_type,
				'status'  => $post->post_status,
				'count'   => $count,
				'samples' => $converter->get_convertible_url_samples( $content, 3 ),
			);
		}

		return $results;
	}

	/**
	 * 处理转换选中文章。
	 */
	public function handle_convert_selected_posts() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足。', 'jiuliu-relative-media-urls' ) );
		}

		if ( ! check_admin_referer( 'jrmu_convert_selected_posts' ) ) {
			$this->redirect_with_notice( 'security_failed', 0, 0 );
		}

		$post_ids = isset( $_POST['post_ids'] ) && is_array( $_POST['post_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['post_ids'] ) ) : array();
		$post_ids = array_values( array_filter( array_unique( $post_ids ) ) );

		if ( empty( $post_ids ) ) {
			$this->redirect_with_notice( 'no_posts', 0, 0 );
		}

		$converter = JRMU_Converter::instance();
		$changed   = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

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

		$this->redirect_with_notice( 'converted', count( $post_ids ), $changed );
	}

	/**
	 * 跳转并带提示。
	 *
	 * @param string $notice    提示。
	 * @param int    $requested 请求数量。
	 * @param int    $changed   变更数量。
	 */
	private function redirect_with_notice( $notice, $requested, $changed ) {
		$url = add_query_arg(
			array(
				'page'           => 'jiuliu-relative-media-urls',
				'jrmu_notice'    => $notice,
				'jrmu_requested' => absint( $requested ),
				'jrmu_changed'   => absint( $changed ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * 缩短 URL 预览。
	 *
	 * @param string $url URL。
	 * @return string
	 */
	private function shorten_url( $url ) {
		$url = (string) $url;

		if ( strlen( $url ) <= 90 ) {
			return $url;
		}

		return substr( $url, 0, 50 ) . '...' . substr( $url, -25 );
	}
}
