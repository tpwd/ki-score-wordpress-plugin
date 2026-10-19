# KIscore for WordPress

WordPress plugin that reports page views to [KIscore](https://kiscore.ai), so
AI crawler hits and visits referred by ChatGPT, Perplexity & co. show up in
Traffic Overview. Server-side, without script or cookie. The user-facing
description is in [readme.txt](readme.txt).

## For site owners

1. Download `kiscore.zip` and upload it under **Plugins → Add New → Upload Plugin**, then activate it.
2. Copy the connection key from **Settings → Traffic tracking → WordPress** in the KIscore dashboard.
3. Paste it under **Settings → KIscore**, save, click **Check connection**.

Behind Cloudflare the visitor IP comes from `CF-Connecting-IP` automatically.
Behind another proxy or CDN, define the header that carries the visitor IP as
`KISCORE_CLIENT_IP_HEADER` in `wp-config.php` (the settings page warns when it
sees a private address). The connection key can be defined the same way as
`KISCORE_CONNECTION_KEY`, also as environment variable; the 1.0 names
`KISCORE_SITE_ID` and `KISCORE_TRACKING_SECRET` still work.
`KISCORE_API_ENDPOINT` points the plugin at another API, e.g. in tests.

## How it works

- `template_redirect` (priority 20, after canonical redirects) decides whether
  the request is a frontend page view: GET, HTML, no admin/REST/AJAX/cron/feed/
  robots/preview, no browser fetch or prefetch (`Sec-Fetch-Mode`,
  `Sec-Purpose`), and a single valid visitor IP.
- The report runs on `shutdown`. On PHP-FPM and LiteSpeed the page is handed
  to the visitor first (`fastcgi_finish_request`); elsewhere delivery does
  not wait for the API response. Redirect responses are not reported.
- Sent: path plus `utm_*` parameters, user agent, referrer, visitor IP. The
  secret travels in `X-Kiscore-Tracking-Secret`; redirects are refused.
- The settings page shows the last API answer (stored when it changes, at most
  hourly otherwise, only when the runtime can read it after finishing the page)
  and offers a connection check that sends no visitor data and waits for a real answer.

## Development

```bash
composer install
composer test          # unit tests (Page_View)
composer test:php74    # syntax and unit tests on PHP 7.4, the minimum (Docker)
composer lint          # WordPress coding standards and PHP 7.4 compatibility (PHPCompatibilityWP)
composer test:e2e      # real WordPress in Docker: Apache/mod_php and nginx/PHP-FPM
composer build         # dist/kiscore.zip
```

The e2e stack (`tests/e2e/docker-compose.yml`) keeps running after a test run
and is reachable at http://localhost:8091 (Apache) and http://localhost:8092
(PHP-FPM); the test admin is defined in `tests/e2e/run.sh`. Remove it with
`docker compose -f tests/e2e/docker-compose.yml down -v`. WordPress' own
review tool runs against the built zip:

```bash
cd tests/e2e
docker compose run --rm cli wp plugin install plugin-check --activate
docker compose run --rm cli wp plugin check /var/www/html/wp-content/plugins/kiscore/dist/kiscore
```

It reports one expected warning: `load_plugin_textdomain()` loads the bundled
German translations, which a plugin outside wordpress.org needs.

### Translations

Source strings are English. `languages/translate.py` holds the German texts
and writes `de_DE` (du) and `de_DE_formal` (Sie); it fails on a missing
translation or on wording that addresses the reader without both variants.

```bash
cd tests/e2e && docker compose run --rm cli wp i18n make-pot wp-content/plugins/kiscore wp-content/plugins/kiscore/languages/kiscore.pot --exclude=vendor,tests,dist && cd ../..
python3 languages/translate.py
for l in de_DE de_DE_formal; do msgfmt -o languages/kiscore-$l.mo languages/kiscore-$l.po; done
```

### Release

Bump the version in the plugin header, `KISCORE_VERSION` (both in
`kiscore.php`) and `Stable tag` in `readme.txt`; `composer build` refuses to
build when they differ. For the wordpress.org directory, `readme.txt` still
needs a `Contributors:` line with wordpress.org user names and links to the
KIscore terms and privacy policy in "External services".

The release source is the GitLab repository
[`tpwd/ki-score-wordpress-plugin`](https://gitlab.tpwd.de/tpwd/ki-score-wordpress-plugin).
After the checks above pass, commit the version and tag that commit as
`v<version>`. Build from that clean tagged checkout, publish `dist/kiscore.zip`
and its SHA-256 checksum as assets of the GitLab release. Fast-forward the
public GitHub repository to the same source, push the same tag, and publish
the identical ZIP and checksum there. Then copy that exact ZIP to KIscore's
`dashboard/public/downloads/kiscore-wordpress-<version>.zip`.
Record the release URL, source commit and checksum in KIscore's integration
documentation, and update `WORDPRESS_PLUGIN` there. Do not change plugin source
inside the KIscore repository or rebuild the ZIP there.

The GitLab repository is private. Public source and releases are available at
[`tpwd/ki-score-wordpress-plugin` on GitHub](https://github.com/tpwd/ki-score-wordpress-plugin).
KIscore also serves the same release ZIP directly. GitLab remains the source
for development and reviews; publish the tested release to both repositories
instead of maintaining separate versions.
