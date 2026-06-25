=== 九流媒体相对地址 ===
Contributors: jiuliu
Tags: reverse proxy, media url, relative url, wordpress, multi domain, nginx cache
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress 反向代理与多域名链接助手：媒体相对地址、历史链接扫描/预览/恢复、多域名访问适配、反代环境检测、缓存检测与 Nginx 配置建议。

== Description ==

适合美国源站 + 香港/日本/新加坡反向代理缓存、多入口域名、源站隐藏、迁移换域名等场景。

默认不启用任何转换。所有永久修改都必须手动扫描、预览、勾选并确认。

== Features ==

* 媒体库输出地址相对化。
* 新上传媒体默认相对输出。
* 文章内容媒体地址保存时转换或前台临时转换。
* 多域名访问适配，站内链接跟随当前访问域名。
* Canonical 主域名控制。
* 全站链接扫描、Dry Run 预览、恢复工具。
* HTTP 混合内容检测与修复。
* 源站域名/IP 暴露提示。
* 反代环境检测与缓存响应头检测。
* Nginx proxy_cache 配置建议。

== Changelog ==

= 4.0.0 =
* 新增扫描 / 预览 / 修复中心。
* 新增相对媒体 URL 恢复完整 URL。
* 新增站内链接批量改写为目标域名或根相对。
* 新增混合内容修复。
* 新增源站暴露检测。
* 新增反代环境检测与缓存响应头检测。
* 新增 Nginx 配置建议。
* 新增 Canonical 主域名控制。

= 3.0.0 =
* 新增多域名访问适配。

= 2.0.0 =
* 默认关闭所有转换。
* 媒体库和文章内容分开控制。
