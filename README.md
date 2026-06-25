# 九流媒体相对地址（Jiuliu Relative Media URLs）

WordPress 媒体 URL 根相对化插件。将同站点的媒体库绝对地址，例如：

```text
https://blog.example.com/wp-content/uploads/2026/06/a.jpg
```

自动转换为：

```text
/wp-content/uploads/2026/06/a.jpg
```

适合美国源站 + 香港 Nginx 反向代理缓存、源站隐藏、域名切换、多入口访问等场景。

## 核心特性

- 零核心修改，不修改 WordPress 源码。
- 保存文章时转换正文与摘要中的媒体绝对 URL。
- 前台输出兜底转换旧文章内容。
- 可选转换 `wp_get_attachment_url`、媒体库弹窗返回值、响应式图片 `srcset`。
- 只转换白名单域名 + 白名单路径，外链图片不会被动。
- 支持额外源站域名，例如 `origin-blog.example.com`。
- 后台提供手动批量转换现有文章功能。
- 卸载自动清理插件设置，不删除文章内容和媒体文件。

## 推荐用法

正式入口域名：

```text
blog.example.com
```

美国源站域名：

```text
origin-blog.example.com
```

插件后台把 `origin-blog.example.com` 加入“额外源站域名”，然后启用保存/输出转换。

文章中保存为：

```html
<img src="/wp-content/uploads/2026/06/a.jpg">
```

用户从 `blog.example.com` 访问时，浏览器会自动补成：

```text
https://blog.example.com/wp-content/uploads/2026/06/a.jpg
```

从而走香港反代缓存，而不是直连美国源站。

## 目录结构

```text
jiuliu-relative-media-urls/
├── jiuliu-relative-media-urls.php
├── uninstall.php
├── readme.txt
├── README.md
├── index.php
├── includes/
│   ├── class-jrmu-settings.php
│   ├── class-jrmu-converter.php
│   ├── class-jrmu-admin.php
│   └── index.php
├── assets/
│   ├── index.php
│   └── css/
│       ├── admin.css
│       └── index.php
└── languages/
    └── index.php
```

## 环境要求

- WordPress 5.8+
- PHP 7.4+

## License

GPLv2 or later
