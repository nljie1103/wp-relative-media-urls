=== 九流媒体相对地址 ===
Contributors: jiuliu
Tags: media, relative urls, reverse proxy, nginx cache, wordpress media
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

将 WordPress 媒体库输出与文章内容里的同站点媒体绝对地址转换为根相对地址，适合反向代理、缓存节点、源站隐藏、多入口域名等场景。

== Description ==

九流媒体相对地址用于解决 WordPress 在反向代理、多入口域名、源站隐藏场景下，媒体 URL 写死为完整域名导致绕过代理或缓存节点的问题。

例如将：

https://example.com/wp-content/uploads/2026/06/a.jpg

转换为：

/wp-content/uploads/2026/06/a.jpg

浏览器会根据当前访问域名自动补全前缀，因此同一套 WordPress 可以在不同入口域名下调用对应入口的资源。

2.0.0 版本默认不启用任何转换。媒体库地址与文章内容地址分开控制，历史文章必须扫描、预览并手动选择后才会修改。

== Features ==

* 默认不自动转换，安装启用后不会立刻改变站点输出。
* 媒体库地址和文章内容地址分开控制。
* 支持已上传媒体输出转换，默认可逆，不移动文件，不改核心。
* 支持从开启后新上传媒体默认使用相对地址输出。
* 支持保存文章时自动转换新内容。
* 支持前台临时输出转换旧文章，不修改数据库。
* 支持扫描历史文章，预览可转换链接，勾选指定文章后转换。
* 支持额外源站域名白名单。
* 支持限制转换路径，默认仅 /wp-content/uploads/。

== Installation ==

1. 上传插件目录到 /wp-content/plugins/。
2. 在 WordPress 后台启用插件。
3. 进入“媒体相对地址”设置页。
4. 按需开启媒体库或文章内容转换功能。

== Upgrade Notice ==

= 2.0.0 =
第二版将默认行为改为安全模式：插件启用后不自动转换任何媒体库或文章内容输出，所有功能都需要手动开启。

== Changelog ==

= 2.0.0 =
* 默认关闭全部转换功能。
* 拆分媒体库地址与文章内容地址。
* 新增“已上传媒体输出转换”。
* 新增“未来上传媒体相对地址输出”。
* 新增“保存文章时转换”。
* 新增“前台临时输出转换”。
* 新增历史文章扫描、预览、勾选转换功能。
* 改进设置页文案与状态显示。

= 1.0.0 =
* 初始版本。
