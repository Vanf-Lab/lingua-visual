=== Lingua Visual ===
Contributors: vanflab
Tags: translation, multilingual, languages, visual editor, i18n
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visual multilingual translation with UNLIMITED languages, free. Click any frontend text and translate it on the spot. Per-language URLs (/en/page/) that persist while browsing and play nice with caching.

== Description ==

Lingua Visual lets you translate your site **directly on the frontend**: enable visual mode from the admin bar ("Translate this page"), click any text block, type the translation, done. No language limit, no paid APIs, no subscriptions.

* **Unlimited languages**: add as many as you want (code, name and emoji flag).
* **Per-language URLs**: each language lives at its own URL (`/en/my-page/`); the default language has no prefix. The translation **persists while browsing** and each language is a separate page-cache entry (compatible with WP Rocket and similar).
* **Own dictionary**: strings and translations are stored in dedicated tables (`wp_lingua_strings`, `wp_lingua_translations`).
* **Multilingual SEO**: per-language `hreflang` + `x-default` in the `<head>` and canonical matching the active language.
* **Backward compatible**: `?lang=` and the `lingua_lang` cookie keep working and redirect (302) to the prefixed URL.
* **Language switcher**: `[lingua_switcher]` shortcode + widget, with per-language URLs.
* **Import/export** the dictionary as CSV.
* **Hook for optional automation**: filter `lgv_auto_translate` to suggest translations with your own API.

== Installation ==

1. Upload the `lingua-visual` folder to `wp-content/plugins/`.
2. Activate the plugin from Plugins.
3. Go to Lingua Visual → Languages and add your languages.
4. In Lingua Visual → URLs verify that language prefixes are enabled.
5. On the frontend, from the admin bar, choose "Translate this page" and start clicking texts.

== Frequently Asked Questions ==

= Do pretty URLs like /en/page work? =

Yes, since 0.2.0. Each non-default language lives at its prefixed URL (`/en/my-page/`); the default language has no prefix. Internal links are rewritten automatically and `?lang=` / the cookie redirect to the prefixed URL.

= Does it work with page caches like WP Rocket? =

Yes, that's the point of the 0.2.0 design: since each language is a different URL, the cache stores one translated version per language without going through PHP.

= Does it break the page's JavaScript? =

`<script>` and `<style>` blocks are excluded from replacement. Still, test on staging if your theme injects texts via JS.

= What if I have a page with a slug equal to a language code? =

Avoid using a registered language code as a slug (e.g. don't create a page called "en"), because the language prefix takes priority in the URL.

== Changelog ==

= 0.7.0 =
* Per-language image files: the Media tab now lets you assign a different image file per language (perfect for banners with text). Frontend swaps automatically via `wp_get_attachment_image_src` and `wp_get_attachment_url` filters.
= 0.6.0 =
* XLIFF 1.2 import/export: new professional translator workflow in Import / Export. Export translation jobs as XLIFF for CAT tools (memoQ, Trados, OmegaT), re-import the translated file — translations land in the dictionary as manual (never overwritten).
* Media translation: new "Media" tab. Translate alt text, title, caption and description of your media library per language. Values live in the dictionary, so the frontend output buffer translates them automatically, plus an explicit filter on image attributes.
* Switcher Studio live preview: now shows a clear message instead of blank when fewer than two languages are configured, and AJAX failures surface a visible error instead of failing silently.

= 0.5.4 =
* AJAX compatibility: the translation buffer now also hooks into `init` for admin-ajax.php (where `template_redirect` never fires), so AJAX-rendered HTML (e.g. video theme history/watchlist endpoints) gets translated too, including string values inside JSON responses.

= 0.5.1 =
* Compatibility fix for themes that `exit` during `template_redirect` (e.g. StreamVid profile pages): the output buffer now starts at priority 2 so it is always registered before any early `exit`.

= 0.5.0 =
* Frontend string discovery: new "Discover" tab that crawls sitemap.xml (or a manual URL list) and registers all visible texts + translatable attributes (title, alt, placeholder, aria-label, meta description, buttons) in the dictionary. Works in batches via AJAX with a progress bar, polite rate-limiting and retries. Honest limitation: 100% JavaScript-generated content is not captured server-side (visual mode and output buffering cover that).
* Automatic translation: new "Automatic" tab with a provider architecture. MyMemory (free, no key, works out of the box: ~5,000 chars/day, ~50,000 with email) as default; DeepL (500,000 chars/month free with a free API key) as the max-quality option; free Google endpoint as experimental fallback (unofficial, may be throttled).
* "Translate all pending" button: translates in atomic per-string batches, marking them as automatic (pending review). "Only automatic" filter in Strings, 🤖 badge and "Mark as reviewed" action. If you edit an automatic translation by hand, it becomes manual and is never overwritten.
* Per-provider quota control (day/month) with graceful stop: when exhausted it stops on its own and notifies, without breaking the batch. Already-translated strings are not re-translated.
* The visual mode `lgv_auto_translate` hook now suggests translations with the configured provider (respecting its quota).
* Privacy: texts are sent to the chosen provider's servers; documented in the admin.

= 0.4.0 =
* Automatic language detection on first visit: new "Detection" tab in the admin.
* Priority chain: explicit user choice (always wins) → browser language (Accept-Language) → IP geolocation → configurable fallback language. Configurable method: browser + IP (recommended), browser only or IP only.
* Country-by-IP providers in order: Cloudflare header (free, no external calls), configurable local CIDR=COUNTRY map, and optional external API (off by default for privacy) with aggressive transient caching.
* Bundled country → languages table (all ISO countries: CA→en/fr, BE→nl/fr/de, CH→de/fr/it, IN→en/hi, etc.); the first language matching your active languages wins.
* 302 redirect with anti-cache headers to the prefixed URL; never redirects bots/crawlers (critical for SEO), nor admin, AJAX, REST or CRON. First visit only (30-day lgv_detected cookie).
* Options: scope (all pages or homepage only), "We switched you to X" notice with a link to go back, and a test simulator (country + Accept-Language) to see which language would result.
* Without active URL prefixes, detection sets the lingua_lang cookie and the translation is applied via output buffering.

= 0.3.1 =
* Color picker with transparency: the 5 Switcher Studio colors now have an opacity slider; rgba() is saved when there is transparency, classic hex otherwise (compatible with already-saved values).
* Tooltip with the language name on hover, enabled by default and toggleable from the Switcher tab. Text comes out in the site's active language (English site → "Spanish"; Spanish site → "Inglés"), with a built-in 42-language dictionary and fallback to the native name.
* Accessibility improvement: each switcher option carries an aria-label with the language name.

= 0.3.0 =
* Switcher Studio: new customization tab for the language switcher.
* 7 display modes: dropdown, horizontal list, flags only, text only, flag+text, opposite-language button and pill with globe + modal dialog (Airbnb style).
* Set of 55 own SVG flags (flag-icons style) with rectangle/rounded/circle shape and configurable size.
* Per-language flag variant: pick UK or USA for English, Spain/Mexico/Argentina/Colombia for Spanish, Portugal/Brazil, France/Quebec, Germany/Austria/Switzerland, China/Taiwan, etc. You can also upload your own SVG per language.
* Configurable text: native name, ISO code or nothing; flag position (before/after/hidden); per-language custom label.
* Behavior: open on click or hover, up/down direction, animations, search box inside the dropdown, manual drag or alphabetical order, show/hide current and default language, hide languages without translations.
* Intuitive styling: colors, borders, padding, typography, shadow, one-click preset gallery, live preview in the admin and custom CSS.
* Own mobile section: different mode on mobile (flags only or full-screen drawer) with configurable breakpoint.
* Placement: [lingua_switcher] shortcode with attributes (mode, flag_shape, name_format…), widget, menu item and floating button.
* Accessibility: ARIA listbox/dialog, keyboard navigation (Tab, arrows, Enter, Escape) and visible focus.

= 0.2.0 =
* Language-prefixed URLs (/en/my-page/): the language persists while browsing and each language is a separate page-cache entry.
* 302 redirect from ?lang= and the lingua_lang cookie to the prefixed URL; 301 from the default language prefix to the prefix-less canonical.
* Automatic internal link rewriting (permalinks, home_url, menus) with the active language prefix.
* Per-language hreflang + x-default in the <head>; canonical matching the language.
* Language switcher (shortcode and widget) with per-language URLs.
* New "URLs" tab in the admin to enable/disable prefixes and redirects.
* The 0.1.0 output buffering stays as a second layer.

= 0.1.0 =
* Initial release: unlimited language manager, own dictionary, click-to-translate visual mode, switcher, CSV.
