=== 九流媒体相对地址 ===
Contributors: jiuliu
Tags: media, relative urls, reverse proxy, cache, uploads
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

将 WordPress 媒体库绝对地址转换为根相对地址，适合反向代理、缓存节点、源站隐藏等场景。

== Description ==

九流媒体相对地址会把同站点媒体绝对 URL 转换为根相对 URL，例如：

https://blog.example.com/wp-content/uploads/a.jpg

转换为：

/wp-content/uploads/a.jpg

插件只转换白名单域名和白名单路径，不会转换外链图片。

== Features ==

* 保存文章时转换正文/摘要中的媒体链接。
* 前台输出时兜底转换旧文章。
* 可选转换附件 URL API、媒体库弹窗返回值、srcset。
* 支持额外源站域名。
* 支持手动批量转换现有文章。
* 卸载自动清理插件设置。

== Installation ==

1. 上传插件 ZIP。
2. WordPress 后台 → 插件 → 启用“九流媒体相对地址”。
3. 后台左侧菜单 → 媒体相对地址 → 配置额外源站域名。
4. 如需处理旧文章，点击“批量转换现有文章”。

== Changelog ==

= 1.0.0 =
* 首个版本发布。
