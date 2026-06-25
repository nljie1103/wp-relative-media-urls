=== 九流媒体相对地址 ===
Contributors: jiuliu
Tags: media urls, relative urls, reverse proxy, wordpress, cache, multi domain
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

将 WordPress 媒体库输出、文章内容媒体地址，以及多入口域名下的站内链接适配到反向代理/缓存节点场景。默认不启用任何转换。

== Description ==

九流媒体相对地址适合以下场景：

* WordPress 源站在美国服务器；
* 香港、日本、新加坡等 VPS 只做反向代理和缓存；
* 不想使用 Cloudflare、对象存储或付费 CDN；
* 希望媒体链接使用 /wp-content/uploads/... 根相对路径；
* 希望用户从哪个域名进入，点击文章/页面/菜单时仍然停留在当前域名。

3.0.0 版本新增“多域名访问适配”模块，可以让 WordPress 生成站内链接时跟随当前访问域名，解决首页通过反代访问、点击文章却跳回源站固定域名的问题。

插件启用后默认不改变任何输出或数据库内容，用户必须在设置页手动开启对应功能。

== Installation ==

1. 上传插件目录到 `/wp-content/plugins/`，或在后台上传 zip 安装。
2. 启用插件。
3. 进入“媒体相对地址”设置页。
4. 根据需要开启媒体库、文章内容或多域名访问适配模块。

== Changelog ==

= 3.0.0 =
* 新增多域名访问适配模块。
* 支持站内链接跟随当前访问域名。
* 支持白名单域名、任意 Host 高级模式、协议设置。
* 支持前台 HTML 兜底替换站内绝对链接。
* 支持排除后台、登录页、REST API、Feed、Sitemap、XML 等系统端点。
* 保持默认关闭，不自动修改数据库。

= 2.0.0 =
* 默认关闭所有转换。
* 媒体库地址与文章内容地址分开控制。
* 新增未来上传媒体标记。
* 新增历史文章扫描、预览、勾选转换。

= 1.0.0 =
* 初始版本：媒体 URL 根相对转换。
