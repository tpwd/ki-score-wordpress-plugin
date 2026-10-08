=== KIscore ===
Tags: ai, crawler, chatgpt, analytics, geo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shows in KIscore which AI crawlers visit your site and which visitors come from ChatGPT, Perplexity and other AI assistants.

== Description ==

KIscore measures how visible a brand is in AI answers. This plugin adds the traffic side: it reports page views from your WordPress server to KIscore, which keeps

* hits of AI crawlers such as GPTBot, ClaudeBot and PerplexityBot, and
* visits referred by AI assistants such as ChatGPT or Perplexity, recognised by referrer or `utm_source`.

Everything else is discarded. You see the results in the KIscore dashboard under Traffic Overview.

* No script and no cookie on your pages
* Reports are sent after the page is delivered (PHP-FPM, LiteSpeed) or with a one-second limit, and never break a page
* Query parameters other than `utm_*` never leave your server
* Requires a KIscore account

== Installation ==

1. Install and activate the plugin.
2. In the KIscore dashboard, open Traffic Overview → Integration and copy the site ID and the tracking secret.
3. In WordPress, open Settings → KIscore, enter both values and save.
4. Click "Check connection".

To keep the values out of the database, define them in `wp-config.php` instead:

`define( 'KISCORE_SITE_ID', '…' );`
`define( 'KISCORE_TRACKING_SECRET', '…' );`

Environment variables with the same names work as well.

== Frequently Asked Questions ==

= My site runs behind a proxy, load balancer or CDN =

Then WordPress sees the proxy's address instead of the visitor's, and all visits would count as one visitor. Enter the header your proxy fills with the visitor IP under "Visitor IP header", for example `X-Real-IP` or `CF-Connecting-IP` (Cloudflare). The settings page warns you when it detects this situation. Only a single, valid IP address is accepted, so lists such as `X-Forwarded-For` cannot be used.

= Does it work with page caching? =

Pages served from a page cache or CDN never reach WordPress and cannot be reported by this plugin. AI crawlers that only receive cached pages can therefore be missing in KIscore.

= Which requests are reported? =

Frontend GET requests for HTML pages. Not reported: the admin area, REST API, AJAX, cron, feeds, robots.txt, previews, redirects, browser prefetches and requests without a valid visitor IP.

= How do I test it? =

Open any page of your site with `?utm_source=chatgpt.com&utm_campaign=kiscore-integration-test`. The visit then appears in KIscore under KI-Verweise.

== External services ==

This plugin sends data to the KIscore tracking API at `https://api.kiscore.ai/track/<site-id>`, operated by KIscore (https://kiscore.ai). It is needed to show AI crawler visits and AI referrals in your KIscore account.

For every reported page view it sends: the path with its `utm_*` parameters, the visitor's user agent, the referrer and the visitor IP, together with your site ID and tracking secret. For human visitors KIscore stores only a daily hash of the IP, never the address; hits of AI crawlers are stored with their IP. Nothing is sent before you enter a site ID and tracking secret.

The "Check connection" button sends one request without visitor data.

== Changelog ==

= 1.0.0 =
* First release.
