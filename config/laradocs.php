<?php

declare(strict_types=1);

use cebe\openapi\Reader;
use Laradocs\Http\Middleware\EnsureDocsEnabled;
use Laradocs\Http\Middleware\SetDocsLocale;
use Laradocs\Http\Middleware\SetDocsVersion;

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | When disabled, no routes are registered and the docs are completely
    | inaccessible. Handy for hiding docs in production environments.
    |
    | Investbrain only serves these docs on Investbrain Cloud (SELF_HOSTED=false).
    | Self-hosted instances read the same Markdown on GitHub instead.
    |
    */

    'enabled' => (bool) env('LARADOCS_ENABLED', ! (bool) env('SELF_HOSTED', true)),

    /*
    |--------------------------------------------------------------------------
    | Localisation
    |--------------------------------------------------------------------------
    |
    | Every user-facing string in the bundled views is translatable. Publish
    | the language files with `php artisan vendor:publish --tag=laradocs-lang`
    | and add a directory per locale (e.g. lang/vendor/laradocs/fr).
    |
    | Page *content* is localised too: add a translated copy of a markdown file
    | as either a filename suffix (docs/guide.fr.md) or a locale directory
    | (docs/fr/guide.md). Both resolve to the same slug, so the URL is stable
    | across languages. A locale only counts as a content language when it is
    | one of the "available" locales below; when a page has no translation for
    | the active locale the default-locale file is served instead, so a
    | partially translated site never 404s. See the "Localisation" guide.
    |
    | "default"   The locale the docs render in. Defaults to "en". Set
    |             LARADOCS_LOCALE to override.
    | "available" Locales offered in the in-page language selector.
    |             Leave null (the default) to auto-detect: the package scans
    |             lang/vendor/laradocs/ and treats each sub-directory as an
    |             available locale. Add a meta.php inside a locale directory
    |             returning ['label' => 'My Label'] for a custom display name;
    |             otherwise the locale code itself is used.
    |             Supply an array to override auto-detection entirely: keys are
    |             locale codes, values are human-readable labels. An empty array
    |             disables the selector outright.
    | "selector"  Show the language selector in the header. It is hidden
    |             automatically when fewer than two locales are available.
    |
    | "url"       Put the locale in the URL path instead of a query parameter:
    |             /docs/fr/guide rather than /docs/guide?lang=fr. The default
    |             locale is served unprefixed (/docs/guide), so only non-default
    |             languages carry a segment, and every internal link, canonical
    |             and hreflang tag is generated per locale for clean SEO and
    |             URL-keyed caching. A legacy `?lang=<code>` is 301-redirected to
    |             the path form for back-compat. Enabled by default; only takes
    |             effect once two or more locales are available. Set
    |             LARADOCS_LOCALE_URL=false to fall back to the query/cookie form.
    |
    | A visitor can switch language with a `?lang=<code>` query parameter (which,
    | when "url" is on, redirects to the path form). If the visitor has made no
    | explicit choice, the browser's Accept-Language header is consulted (when
    | detect_browser is true) before falling back to the default locale. See the
    | "Localisation" guide.
    |
    | "cookie"          Persist the visitor's language choice in a one-year
    |                   `laradocs_locale` cookie so it survives navigation
    |                   without re-appending ?lang=. Disabled by default — EU
    |                   deployments require cookie consent before setting
    |                   non-essential cookies. Enable once your site has an
    |                   appropriate consent mechanism in place, or — better —
    |                   drive it from that mechanism directly with
    |                   `Laradocs::cookiesEnabled(fn () => ...)`, which takes
    |                   priority over this flag and is re-evaluated on every
    |                   request (so a stale cookie is cleared automatically
    |                   the moment consent is withdrawn). See the
    |                   "Cookie persistence" section of the Localisation guide.
    |                   Set LARADOCS_LOCALE_COOKIE=true to enable.
    | "detect_browser"  Honour the browser's Accept-Language header for
    |                   first-time visitors who haven't made an explicit choice.
    |                   When true (the default) the highest-quality header
    |                   locale that matches an available locale is selected. Set
    |                   LARADOCS_DETECT_BROWSER=false to disable.
    |
    */

    'locale' => [
        'default' => env('LARADOCS_LOCALE', 'en'),
        'available' => env('LARADOCS_LOCALE_AVAILABLE') !== null
            ? (array) json_decode((string) env('LARADOCS_LOCALE_AVAILABLE'), true)
            : null,
        'selector' => (bool) env('LARADOCS_LOCALE_SELECTOR', true),
        'url' => (bool) env('LARADOCS_LOCALE_URL', true),
        'cookie' => (bool) env('LARADOCS_LOCALE_COOKIE', false),
        'detect_browser' => (bool) env('LARADOCS_DETECT_BROWSER', true),

        // Format string for the "Last updated" date rendered on each page.
        // Tokens follow PHP date() conventions (e.g. 'Y-m-d', 'd/m/Y', 'F j, Y'),
        // but the date is formatted via Carbon::translatedFormat() so locale-aware
        // tokens — month names (F, M), day names (l, D) — are automatically
        // translated into the active docs locale. The English ordinal suffix (S,
        // e.g. "1st") is not meaningful in every language; for multi-locale sites
        // consider a neutral default such as 'j F Y' or 'Y-m-d'. Override per
        // deployment with LARADOCS_DATE_FORMAT, or publish the config and set it
        // directly.
        'date_format' => env('LARADOCS_DATE_FORMAT', 'jS F Y'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Version Docs
    |--------------------------------------------------------------------------
    |
    | Serve more than one version of your documentation from sibling
    | sub-directories of your docs path (e.g. docs/v1/, docs/v2/).
    |
    | "enabled"   Toggle multi-version support. When false the rest of these
    |             settings are ignored and the docs behave as a single version.
    | "default"   The version handle shown when a visitor arrives without an
    |             explicit version in the URL (e.g. /docs/ instead of /docs/v2/).
    |             Defaults to the first detected version when left null.
    |             Set LARADOCS_VERSION_DEFAULT to override.
    | "available" Version list offered in the in-page selector.
    |             Leave null (the default) to auto-detect: the package scans
    |             laradocs.docs.path and treats each sub-directory as a version.
    |             Add a _version.json inside a version directory containing
    |             {"label": "My Label"} for a custom display name; otherwise
    |             the directory name is used.
    |             Supply an explicit array to override auto-detection entirely.
    |             Entries accept the legacy string-value form:
    |               'available' => ['v1' => 'Version 1', 'v2' => 'Version 2']
    |             or a richer per-version array carrying extra metadata:
    |               'available' => [
    |                 'v2.0' => ['label' => 'v2.0', 'stable' => true, 'deprecated' => false],
    |               ]
    |             An empty array disables the selector outright.
    | "selector"  Show the version switcher in the header. Hidden automatically
    |             when fewer than two versions are available.
    | "strategy"  How the available version list is resolved: "auto" scans the
    |             docs path, "config" uses only the explicit "available" array,
    |             "both" merges auto-detected versions with config metadata.
    | "unversioned" What to serve at the bare prefix (e.g. /docs) when versioning
    |             is on: "redirect" to the default version's URL, or "render"
    |             the default version's content in place.
    | "aliases"   Map alias handles to real version keys so stable URLs survive
    |             version bumps, e.g. ['latest' => 'v2.0', 'stable' => 'v1.0'].
    | "outdated_banner" Show an "outdated version" banner when a visitor is
    |             viewing any version other than the latest.
    | "shared"    Pages served identically across every version (e.g. a global
    |             changelog) so they need not be duplicated per version directory.
    |               "enabled"   Toggle shared pages on or off.
    |               "directory" Sub-directory (relative to the docs path) holding
    |                           the shared pages.
    |               "nav_label" Heading the shared pages appear under in the nav.
    | "inline"    Version metadata surfaced inside page content.
    |               "enabled"   Toggle inline version annotations on or off.
    |               "behaviour" How annotations render — e.g. "badge" or "note".
    |
    | URL structure: /docs/{version}/{page-slug}
    | Cache keys are namespaced per version so each set is independent.
    |
    | See docs/multi-version-migration.md for a migration guide.
    |
    */

    'versions' => [
        'enabled' => (bool) env('LARADOCS_VERSIONS', false),
        'default' => env('LARADOCS_VERSION_DEFAULT'),
        'available' => null,
        'selector' => (bool) env('LARADOCS_VERSION_SELECTOR', true),

        // How the version list is resolved: auto | config | both.
        'strategy' => env('LARADOCS_VERSION_STRATEGY', 'auto'),

        // What to serve at the bare prefix when versioning is on: redirect | render.
        'unversioned' => env('LARADOCS_VERSION_UNVERSIONED', 'redirect'),

        // Alias handle => real version key, e.g. ['latest' => 'v2.0'].
        'aliases' => [],

        // Show an "outdated version" banner on any non-latest version.
        'outdated_banner' => (bool) env('LARADOCS_VERSION_OUTDATED_BANNER', true),

        // Pages shared identically across every version (e.g. a global changelog).
        'shared' => [
            'enabled' => (bool) env('LARADOCS_VERSION_SHARED', false),
            'directory' => env('LARADOCS_VERSION_SHARED_DIR', '_shared'),
            'nav_label' => env('LARADOCS_VERSION_SHARED_NAV_LABEL', 'Shared'),
        ],

        // Version metadata surfaced inside page content.
        'inline' => [
            'enabled' => (bool) env('LARADOCS_VERSION_INLINE', false),
            'behaviour' => env('LARADOCS_VERSION_INLINE_BEHAVIOUR', 'badge'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    |
    | "register"          When false, the package skips registering its own routes so
    |                     the consumer app can wire the render action into a route it
    |                     owns (e.g. behind tenant-resolving middleware). The package's
    |                     controllers are still available; see DocumentRouter for the
    |                     canonical action references.
    | "prefix"            The URL segment your docs live under (e.g. /docs).
    | "domain"            Optionally serve docs on a dedicated subdomain.
    | "middleware"        Middleware applied to every docs route (user-supplied stack,
    |                     e.g. ['web', 'auth']).
    | "package_middleware" Package-owned middleware merged after "middleware". You can
    |                     reorder, replace, or extend this list to control exactly which
    |                     built-in middleware run on docs routes.
    | "name"              Route name prefix, used by route('laradocs.show', ...).
    |
    */

    'route' => [
        'register' => env('LARADOCS_ROUTE_REGISTER', true),
        'prefix' => env('LARADOCS_ROUTE_PREFIX', 'docs'),
        'domain' => env('LARADOCS_ROUTE_DOMAIN'),
        'middleware' => ['web'],
        'package_middleware' => [
            EnsureDocsEnabled::class,
            SetDocsLocale::class,
            SetDocsVersion::class,
        ],
        'name' => env('LARADOCS_ROUTE_NAME', 'laradocs.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Source
    |--------------------------------------------------------------------------
    |
    | "path"             Absolute path to the directory holding your markdown.
    | "extensions"       File extensions treated as documents.
    | "ignored_patterns" fnmatch() patterns for files/dirs to skip.
    | "index"            Filename treated as a section landing page.
    |
    */

    'docs' => [
        'path' => env('LARADOCS_PATH', base_path('docs')),
        'extensions' => ['md', 'markdown'],
        // docs/README.md is the landing page, so GitHub and Laradocs share one index file
        'ignored_patterns' => ['.*', '_drafts'],
        'index' => 'README',
    ],

    /*
    |--------------------------------------------------------------------------
    | Slug / Route Generation Strategy
    |--------------------------------------------------------------------------
    |
    | "strategy" One of: filename | metadata | both.
    |   filename — slugs are derived purely from the file path.
    |   metadata — slugs come from front-matter "slug:" (falls back to filename).
    |   both     — metadata wins when present, otherwise filename.
    |
    */

    'routing' => [
        'strategy' => env('LARADOCS_ROUTING_STRATEGY', 'both'),
        'fallback' => 'filename',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tag Index Pages
    |--------------------------------------------------------------------------
    |
    | Pages declare topics with a front-matter `tags:` list. When enabled, the
    | package auto-generates a global index of every tag and a listing page per
    | tag — no extra markdown required. Hidden pages never appear in either.
    |
    | "enabled" Toggle the tag routes and pages on or off.
    | "index"   Slug of the global index, served at {prefix}/{index} (e.g. /docs/tags).
    | "prefix"  URL segment for a single tag, served at {prefix}/{tag}
    |             (e.g. /docs/tag/getting-started).
    |
    | A real document occupying either slug always wins, so these never shadow
    | hand-authored pages. Customise the look by publishing the views with
    | `php artisan vendor:publish --tag=laradocs-views` and editing the files
    | under resources/views/vendor/laradocs/tags.
    |
    */

    'tags' => [
        'enabled' => (bool) env('LARADOCS_TAGS', true),
        'index' => env('LARADOCS_TAGS_INDEX', 'tags'),
        'prefix' => env('LARADOCS_TAGS_PREFIX', 'tag'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-file Metadata Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every document unless overridden by front-matter.
    |
    */

    'metadata' => [
        'default' => [
            'order' => 999,
            'hidden' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Front-matter Linter  (docs:lint)
    |--------------------------------------------------------------------------
    |
    | "required" Front-matter fields that every document must declare.
    |             Uses the YAML key name (e.g. "updated_at", not "updatedAt").
    |             An empty array disables the missing-field check entirely.
    | "layouts"  Allowlist of recognised layout names. When non-empty, any
    |             document whose "layout:" value is not in this list is
    |             flagged. An empty array disables the layout check.
    | "icons"    Flag icon references (the "icon:" front-matter field and inline
    |             @icon() calls) that do not resolve to an SVG. This is what
    |             surfaces a missing icon dependency — e.g. the heroicons npm
    |             package not being installed. Set false to skip the check (for
    |             instance in CI without node_modules).
    |
    */

    'lint' => [
        'required' => ['title'],
        'layouts' => [],
        'icons' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Parser
    |--------------------------------------------------------------------------
    |
    | "extensions"  Which built-in feature sets to enable.
    | "highlighter" Code highlighting engine (null disables it).
    | "toc"         Min/max heading levels collected for the on-page TOC.
    |
    */

    'parser' => [
        'extensions' => [
            'gfm' => true,
            'attributes' => true,
            'footnotes' => true,
            'callouts' => true,
            'heading_anchors' => true,
            'images' => true,
            'video' => true,
            'mermaid' => true,
            'katex' => true,
            'variables' => true,
            'icons' => true,
            'macros' => true,
            'components' => true,
            'tabs' => true,
        ],
        'highlighter' => env('LARADOCS_HIGHLIGHTER', 'shiki-css'),

        /*
        | Mermaid diagram rendering. ```mermaid blocks become SVG via mermaid.js,
        | loaded lazily and only on pages that contain a diagram. Point "src" at
        | a self-hosted ESM build to avoid the CDN.
        */
        'mermaid' => [
            'src' => env(
                'LARADOCS_MERMAID_SRC',
                'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs',
            ),
        ],

        /*
        | KaTeX math rendering. $…$ becomes inline math and $$…$$ becomes
        | display (block) math. Both are extracted before CommonMark runs so
        | the parser never sees the LaTeX source.
        |
        | "js"  / "css"  CDN URLs for katex.min.js and katex.min.css.
        |        Point at a self-hosted build to avoid the CDN, or set
        |        LARADOCS_KATEX_JS / LARADOCS_KATEX_CSS.
        | "ssr"  When true, expressions are rendered to HTML on the server via
        |        a Node.js subprocess (requires the katex npm package). Falls
        |        back to client-side rendering silently when Node is absent.
        |        Set LARADOCS_KATEX_SSR=true to enable.
        */
        'katex' => [
            'js' => env(
                'LARADOCS_KATEX_JS',
                'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.js',
            ),
            'css' => env(
                'LARADOCS_KATEX_CSS',
                'https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.css',
            ),
            'ssr' => (bool) env('LARADOCS_KATEX_SSR', false),
        ],
        'unknown_variable' => 'blank', // blank | raw
        'toc' => [
            'min_level' => 2,
            'max_level' => 3,
            'min_headings' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    |
    | Controls the code-tab shorthand (```lang tab:Label) and content tabs
    | (:::tabs / --- Label).  Both are enabled via parser.extensions.tabs.
    |
    | "default_tab"   Tab label to activate on fresh page loads when no
    |                 persisted preference exists.  Matched case-insensitively.
    |                 null = first tab in each block.
    | "persist"       Store the reader's tab selection in localStorage so it
    |                 survives navigation.
    | "sync"          Switch every same-group tabs block on the page when the
    |                 reader picks a tab.
    | "default_group" Group name applied to code-tab blocks.  Content-tab blocks
    |                 default to "content" unless the author sets group="…".
    |
    */

    'tabs' => [
        'default_tab' => env('LARADOCS_TABS_DEFAULT'),
        'persist' => (bool) env('LARADOCS_TABS_PERSIST', true),
        'sync' => (bool) env('LARADOCS_TABS_SYNC', true),
        'default_group' => env('LARADOCS_TABS_GROUP', 'language'),
    ],

    /*
    |--------------------------------------------------------------------------
    | User Interface
    |--------------------------------------------------------------------------
    |
    | "theme"         Colour mode: auto | light | dark.
    | "preset"        Visual preset shipped with the package:
    |                   classic — sidebar + centered article + right TOC.
    |                   minimal — distraction-free single column, slimline header.
    |                   wide    — app-style, fills the viewport, denser nav.
    | "accent"        Any CSS colour. Drives links, active nav, focus rings.
    | "content_width" Max width of the article body. Any valid CSS length,
    |                   e.g. "56rem" (default), "80rem", "100%".
    |                   Also settable via LARADOCS_CONTENT_WIDTH env var.
    | "fonts"         Override the built-in stacks (leave null to keep defaults).
    |
    */

    'ui' => [
        'theme' => env('LARADOCS_THEME', 'auto'),
        'preset' => env('LARADOCS_UI_PRESET', 'classic'),
        'accent' => env('LARADOCS_ACCENT', '#78716c'),
        'content_width' => env('LARADOCS_CONTENT_WIDTH'),
        'fonts' => [
            'sans' => env('LARADOCS_FONT_SANS'),
            'mono' => env('LARADOCS_FONT_MONO'),
        ],
        'brand' => [
            'title' => env('LARADOCS_TITLE', 'Investbrain'),
            'tagline' => env('LARADOCS_TAGLINE', 'Documentation'),
            'logo' => env('LARADOCS_LOGO'),
            'favicon' => env('LARADOCS_FAVICON', '/favicon.svg'),
        ],

        /*
        | Header navigation. Each link is an associative array:
        |   ['label' => 'GitHub', 'url' => 'https://...', 'external' => true]
        | Quick wins via ENV:
        |   LARADOCS_GITHUB_URL — adds a "GitHub" link to the header.
        */
        'header' => [
            'links' => array_values(array_filter([
                [
                    'label' => 'Dashboard',
                    'url' => '/dashboard',
                    'external' => false,
                ],
                env('LARADOCS_GITHUB_URL', 'https://github.com/investbrainapp/investbrain') ? [
                    'label' => 'GitHub',
                    'url' => env('LARADOCS_GITHUB_URL', 'https://github.com/investbrainapp/investbrain'),
                    'external' => true,
                ] : null,
            ])),
        ],

        /*
        | Sidebar behaviour.
        */
        'sidebar' => [
            'collapsible' => true,
            'show_root' => true,
        ],

        /*
        | Footer. Set 'enabled' => false to hide entirely.
        */
        'footer' => [
            'enabled' => (bool) env('LARADOCS_FOOTER', true),
            'text' => env('LARADOCS_FOOTER_TEXT'),
            'links' => [
                // ['label' => 'Privacy', 'url' => '/privacy'],
            ],
        ],

        /*
        | Edit-this-page link rendered on every document. The url template
        | accepts the following placeholders:
        |   {file} — relative path on disk including extension (recommended)
        |   {path} — same as {file} with the .md / .markdown extension stripped
        |   {ext}  — just the extension, e.g. "md"
        |
        |   LARADOCS_EDIT_URL=https://github.com/me/app/edit/main/docs/{file}
        */
        'edit' => [
            'url' => env('LARADOCS_EDIT_URL', 'https://github.com/investbrainapp/investbrain/edit/main/docs/{file}'),
        ],

        /*
        | Source for the "Last updated" date shown at the bottom of each page.
        |
        | Available values:
        |   front_matter          — front-matter `updated_at` only (default).
        |   mtime                 — file system modification time only.
        |   front_matter_or_mtime — front-matter with mtime as fallback.
        |
        | For complete control, register a closure via the facade:
        |   Laradocs::getLastUpdatedUsing(fn (\Laradocs\Documents\Document $doc) => ...);
        | A registered closure always takes precedence over this setting.
        */
        'last_updated_source' => env('LARADOCS_LAST_UPDATED_SOURCE', 'front_matter'),

        'search' => [
            'enabled' => (bool) env('LARADOCS_SEARCH', true),
        ],

        /*
        | Global banner displayed above the header on every page.
        | "type" accepts: info | alert | danger
        | "message" may contain HTML (e.g. a link for a CTA).
        |
        |   LARADOCS_BANNER=1
        |   LARADOCS_BANNER_TYPE=info
        |   LARADOCS_BANNER_MESSAGE="<a href='/changelog'>v2 is out</a> — see what's new."
        */
        'banner' => [
            'enabled' => (bool) env('LARADOCS_BANNER', false),
            'type' => env('LARADOCS_BANNER_TYPE', 'info'),
            'message' => env('LARADOCS_BANNER_MESSAGE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics
    |--------------------------------------------------------------------------
    |
    | Drop-in analytics integrations. Each provider is opt-in: set the site
    | identifier and a small script tag is injected into every docs page.
    |
    | "fathom.site"      Your Fathom site ID (e.g. "ABCDEFGH").
    | "fathom.script"    Override the script URL (defaults to cdn.usefathom.com).
    | "fathom.spa"       SPA mode: "auto", "history", "hash" — see Fathom docs.
    | "google.measurement_id" GA4 measurement ID (e.g. "G-XXXXXXXXXX").
    | "google.anonymize_ip"   Anonymise visitor IPs (recommended in the EU).
    |
    */

    'analytics' => [
        'fathom' => [
            'site' => env('LARADOCS_FATHOM_SITE'),
            'script' => env('LARADOCS_FATHOM_SCRIPT', 'https://cdn.usefathom.com/script.js'),
            'spa' => env('LARADOCS_FATHOM_SPA'),
        ],
        'google' => [
            'measurement_id' => env('LARADOCS_GA_MEASUREMENT_ID'),
            'anonymize_ip' => (bool) env('LARADOCS_GA_ANONYMIZE_IP', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO & Social Meta
    |--------------------------------------------------------------------------
    |
    | Every page is served with rich SEO meta tags generated by
    | ralphjsmit/laravel-seo: a <title>, meta description, Open Graph and
    | Twitter cards, a canonical URL, and JSON-LD structured data. Sensible
    | values are derived automatically from each page's content and
    | front-matter — the settings below tune the site-wide defaults.
    |
    | Per-page overrides live in front-matter, either via the top-level
    | "title", "description", "image", "author" and "tags" keys or a dedicated
    | "seo:" block for finer control (robots, canonical, type, …). See the
    | "SEO" guide for the full reference.
    |
    */

    'seo' => [
        'enabled' => (bool) env('LARADOCS_SEO', true),

        // Brand name used in <title> suffixes and og:site_name.
        // Falls back to ui.brand.title when left null.
        'site_name' => env('LARADOCS_SEO_SITE_NAME', 'Investbrain'),

        // Appended to every page <title>, e.g. " · Acme Docs". When null a
        // suffix is built from the site name; set to an empty string to
        // disable the suffix entirely.
        'title_suffix' => env('LARADOCS_SEO_TITLE_SUFFIX'),

        // Fallback meta description, used when a page declares none and one
        // cannot be derived from its content. Falls back to ui.brand.tagline.
        'description' => env('LARADOCS_SEO_DESCRIPTION'),

        // Derive a description from the opening paragraph when a page omits
        // one. Disable to only ever use explicit descriptions / the fallback.
        'auto_description' => (bool) env('LARADOCS_SEO_AUTO_DESCRIPTION', true),

        // Default social / Open Graph image (absolute URL, or a path that
        // resolves under your public directory). Takes precedence over a
        // generated card; a page's own front-matter `image` wins over both.
        'image' => env('LARADOCS_SEO_IMAGE'),

        // Dynamically generated Open Graph cards. When a page declares no image
        // (neither front-matter `image` nor seo.image above), a 1200×630 card
        // is rendered on the fly from the page title + description, branded with
        // your accent colour / logo, and cached. Requires simonhamp/the-og, or
        // bind your own \Laradocs\Contracts\OgImageGenerator to take over.
        'og_image' => [
            'enabled' => (bool) env('LARADOCS_SEO_OG_IMAGE', true),

            // Card theme: "light" or "dark".
            'theme' => env('LARADOCS_SEO_OG_THEME', 'light'),

            // Optional background colour (any CSS colour). Null uses the theme's.
            'background_color' => env('LARADOCS_SEO_OG_BACKGROUND'),

            // How long (seconds) a generated card is cached. Defaults to 30 days;
            // the cache key folds in the page's mtime and your branding, so edits
            // and theme changes bust it automatically.
            'cache_ttl' => (int) env('LARADOCS_SEO_OG_TTL', 60 * 60 * 24 * 30),
        ],

        // Default author attribution for article meta + schema.
        'author' => env('LARADOCS_SEO_AUTHOR'),

        // X (formerly Twitter) handle (without the @) for twitter:site / creator tags.
        'x' => env('LARADOCS_SEO_X'),

        // X card type. Accepts: summary_large_image | summary | app | player.
        // summary_large_image is best for pages with a dedicated cover image;
        // summary renders a small thumbnail and suits text-heavy reference pages.
        'x_card' => env('LARADOCS_SEO_X_CARD', 'summary_large_image'),

        // Open Graph type emitted for documentation pages.
        'type' => env('LARADOCS_SEO_TYPE', 'article'),

        // Robots directive. Null keeps the package's crawler-friendly default;
        // set e.g. "noindex, nofollow" to keep the docs out of search engines.
        // When versioning is enabled, non-default versions are automatically
        // served "noindex, follow" (deprecated versions "noindex, nofollow")
        // unless a page overrides it via its front-matter `seo.robots`.
        'robots' => env('LARADOCS_SEO_ROBOTS'),

        // Include every version's pages in the sitemap. By default only the
        // default version is advertised, keeping duplicate older versions out
        // of search engines; set true to list all versions.
        'sitemap_all_versions' => (bool) env('LARADOCS_SEO_SITEMAP_ALL_VERSIONS', false),

        // JSON-LD structured data emitted into the document <head>.
        'schema' => [
            'article' => (bool) env('LARADOCS_SEO_SCHEMA_ARTICLE', true),
            'breadcrumbs' => (bool) env('LARADOCS_SEO_SCHEMA_BREADCRUMBS', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RSS / Atom Feed
    |--------------------------------------------------------------------------
    |
    | An XML feed is served at {prefix}/feed.xml listing the N most-recently-
    | updated visible pages, ordered by `updated_at` front-matter (falling back
    | to the file's mtime).
    |
    | "format"   Output format: rss (RSS 2.0) | atom (Atom 1.0).
    | "limit"    Maximum number of items to include.
    |
    */

    'feed' => [
        'format' => env('LARADOCS_FEED_FORMAT', 'rss'),
        'limit' => (int) env('LARADOCS_FEED_LIMIT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | robots.txt
    |--------------------------------------------------------------------------
    |
    | A default robots.txt is served at {prefix}/robots.txt. It always emits a
    | Sitemap: pointer at the package's sitemap and, by default, allows every
    | crawler.
    |
    | When `laradocs.enabled` is false the entire body is replaced with a
    | "Disallow: /" directive — so search engines keep the docs out of their
    | index without ever needing to fetch a page.
    |
    | "rules" Custom User-agent groups. Each entry is an associative array:
    |
    |   [
    |     'user_agent' => '*',                    // string or array of strings
    |     'allow'      => ['/'],                  // string or array (optional)
    |     'disallow'   => ['/private/'],          // string or array (optional)
    |   ]
    |
    | Leave the array empty to keep the default "allow everything" block.
    |
    */

    'robots' => [
        'rules' => [
            // ['user_agent' => '*', 'disallow' => ['/_laradocs/']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Powers the ⌘K command palette's full-text search over page content.
    |
    | "driver"    auto | scout | json.
    |   auto  — use Laravel Scout (Meilisearch / Typesense / Algolia) when it's
    |           installed and configured, otherwise fall back to the JSON index.
    |   scout — force Scout (falls back to JSON if Scout isn't installed).
    |   json  — always use the built-in, dependency-free JSON index.
    | "index"     Index name used by Scout engines.
    | "limit"     Maximum results returned per query.
    | "min_chars" Shortest query that triggers a search.
    | "max_chars" Cap on indexed body text per page (0 = no cap).
    | "exclude"   fnmatch slug patterns always excluded from the index.
    |               E.g. ['internal/*', 'drafts/*']
    | "include"   fnmatch slug patterns for an allow-list. When non-empty,
    |               only matching slugs are indexed regardless of other settings.
    |               E.g. ['guide/*', 'reference/*']
    | "rank"      Rank multipliers by fnmatch slug pattern. First matching
    |               pattern wins; combined with the page's own search_rank
    |               front-matter value (both default to 1.0 = no change).
    |               Values > 1.0 boost; values < 1.0 demote.
    |               E.g. ['guide/*' => 2.0, 'changelog' => 0.5]
    |               Only affects the built-in JSON engine; Scout engines use
    |               their own external ranking.
    |
    | Per-page opt-out: add `search: false` to a page's front-matter.
    | Per-page rank:    add `search_rank: 2.0` to a page's front-matter.
    | Hidden pages (`hidden: true`) are never indexed.
    |
    */

    'search' => [
        'driver' => env('LARADOCS_SEARCH_DRIVER', 'auto'),
        'index' => env('LARADOCS_SEARCH_INDEX', 'laradocs'),
        'limit' => (int) env('LARADOCS_SEARCH_LIMIT', 20),
        'min_chars' => (int) env('LARADOCS_SEARCH_MIN_CHARS', 2),
        'max_chars' => (int) env('LARADOCS_SEARCH_MAX_CHARS', 10000),
        'exclude' => [],
        'include' => [],
        'rank' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Media
    |--------------------------------------------------------------------------
    |
    | Markdown is written next to the files it points at — ![](diagram.png) —
    | but the docs directory is not public, so those sources resolve to nothing
    | once rendered. "source" says where they actually live:
    |
    |   "public"    Sources are left exactly as authored, because they already
    |               point at something public.
    |   "relative"  The file sits beside the markdown, inside the docs path.
    |   "disk"      The file lives on the filesystem disk named in "disk".
    |
    | Anything but "public" registers <prefix>/_media/<path> and rewrites the
    | sources in rendered pages to point at it.
    |
    | "types"   What may be served, in the notation the "mimetypes" validation
    |           rule uses: an exact type, or a group such as image/*. A path is
    |           judged first by what its extension maps to, and again by what
    |           the file actually contains, so a script renamed to .png is
    |           refused. Markdown falls outside every default, and a path that
    |           climbs out of the source is refused, so neither the documents
    |           nor anything above them is reachable.
    |
    | "signed"  Sign those URLs, so a copied link stops working on another site.
    |           On by default once media is served from here, because a
    |           signature without a "ttl" is deterministic: the URL stays the
    |           same between renders, so browsers and CDNs cache it as they
    |           would any other image.
    |
    |           Setting "ttl" (minutes) puts an expiry in the URL, which changes
    |           it on every render — hotlinks then go stale, at the cost of
    |           re-downloading media that would otherwise have been cached.
    |
    |           Signatures are computed over the full URL, so an app whose
    |           APP_URL or proxy headers do not match what visitors actually
    |           request will reject its own media. Turn this off if that is
    |           easier than fixing it.
    |
    |           A signature travels with the URL: it stops a link working
    |           somewhere else, it does not tell one reader from another.
    |
    */

    'media' => [
        // screenshots live in docs/images beside the Markdown that uses them
        'source' => env('LARADOCS_MEDIA_SOURCE', 'relative'),
        'disk' => env('LARADOCS_MEDIA_DISK'),

        'types' => ['image/*', 'video/*', 'audio/*', 'application/pdf'],

        'signed' => (bool) env('LARADOCS_MEDIA_SIGNED', true),
        'ttl' => env('LARADOCS_MEDIA_TTL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Rendered HTML and the document tree are cached keyed by file mtime, so
    | edits are picked up automatically. Disable to always render fresh.
    |
    */

    'cache' => [
        'enabled' => env('LARADOCS_CACHE', true),
        'store' => env('LARADOCS_CACHE_STORE'), // null = default store
        'ttl' => env('LARADOCS_CACHE_TTL', 86400),
        'key_prefix' => 'laradocs',
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAPI
    |--------------------------------------------------------------------------
    |
    | Render interactive API reference pages from an OpenAPI 3.0/3.1 spec that
    | lives alongside your docs. Disabled by default; requires the optional
    | devizzent/cebe-php-openapi package (the spec is parsed once and cached by
    | path + mtime, so edits are picked up automatically).
    |
    | "enabled"   Master switch for the OpenAPI integration.
    | "files"     Candidate spec filenames searched for inside each docs source.
    |             The first match wins (YAML and JSON are both supported).
    | "base_slug" URL segment the generated reference pages are mounted under
    |             (e.g. /docs/api).
    | "title"     Heading / nav label for the reference *landing* page (the
    |             operations index). Defaults to "Overview" so it reads as a
    |             child of the "group" section below rather than repeating it.
    |             Set to null to fall back to the spec's own info.title.
    | "group"     Sidebar group the reference pages are filed under — the
    |             section heading the whole reference lives beneath.
    | "order"     Sort weight of the reference section relative to other groups.
    | "render_markdown_descriptions"
    |             Render CommonMark in spec `description` fields rather than
    |             treating them as plain text.
    |
    | "generator" Settings for the `laradocs:openapi` command, which scaffolds a
    |             spec from your routes / FormRequests / Resources:
    |   "driver"      Which generation backend to use: auto | native | scramble.
    |                   auto     — use Scramble (dedoc/scramble ^0.13) when it's
    |                              installed, otherwise fall back to the built-in
    |                              native generator.
    |                   native   — force the built-in, dependency-free generator.
    |                   scramble — force Scramble. Errors out if the package
    |                              isn't installed rather than silently falling
    |                              back, so a misconfiguration is caught early.
    |                              Requires dedoc/scramble ^0.13 for Laravel 13.
    |                 Set LARADOCS_OPENAPI_DRIVER to override.
    |   "prefix"      Only include routes whose URI starts with this prefix.
    |   "middleware"  Only include routes carrying this middleware name.
    |   "output"      Where the generated spec is written (relative paths are
    |                 resolved from the project root).
    |   "server_url"  The base server URL recorded in the spec (defaults to
    |                 app.url when null).
    |   "title"       The spec's info.title (falls back to "title" above).
    |   "description" The spec's info.description (null omits it).
    |   "version"     The spec's info.version.
    |   "security"    Security schemes advertised in the spec, e.g. a bearer token
    |                 or API key. An empty array declares no authentication.
    |
    */

    'openapi' => [
        // renders docs/openapi.json whenever the devizzent/cebe-php-openapi parser is installed
        'enabled' => (bool) env('LARADOCS_OPENAPI', class_exists(Reader::class)),
        'files' => ['openapi.yaml', 'openapi.yml', 'openapi.json'],
        // docs/api.md already serves /docs/api
        'base_slug' => 'api-reference',
        'title' => 'API Reference',
        'group' => 'Advanced',
        'order' => 5,
        'render_markdown_descriptions' => true,

        'generator' => [
            'driver' => env('LARADOCS_OPENAPI_DRIVER', 'auto'),
            'prefix' => 'api',
            'middleware' => 'api',
            'output' => 'docs/api/openapi.yaml',
            'server_url' => env('LARADOCS_OPENAPI_SERVER_URL'),
            'title' => null,
            'description' => null,
            'version' => '1.0.0',
            'security' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Variables
    |--------------------------------------------------------------------------
    |
    | Static key => value pairs interpolated into docs via {{ key }}.
    | Dynamic values may be registered from a service provider with
    | Laradocs::variables(fn () => [...]). No closures here (config:cache).
    |
    */

    'variables' => [
        // 'app_name' => 'My Application',
    ],

    /*
    |--------------------------------------------------------------------------
    | Macros
    |--------------------------------------------------------------------------
    |
    | Reusable named blocks invoked via @docs('name', ...args). Values are
    | Blade view names. Register closures from a provider instead if needed.
    |
    */

    'macros' => [
        // 'alert' => 'laradocs::macros.alert',
    ],

    /*
    |--------------------------------------------------------------------------
    | Icons
    |--------------------------------------------------------------------------
    |
    | Inline icon rendering for page content (@icon('name')) and frontmatter
    | (icon: arrow-long-right — displayed in the sidebar and page header).
    |
    | "driver"       The default icon set. Built-in: "heroicons". Set to null
    |                to disable automatic icon rendering.
    |
    | "heroicons"    Configuration for the Heroicons set. Requires the heroicons
    |                npm package (npm install heroicons). The package is
    |                auto-detected under node_modules/heroicons; override "path"
    |                when installed elsewhere.
    |
    |   "path"       Absolute path to the heroicons package directory.
    |                Defaults to auto-detection (node_modules/heroicons).
    |   "variant"    Default variant: outline | solid | mini | micro.
    |
    | Additional icon sets can be registered in a service provider:
    |
    |   Laradocs::registerIconSet('phosphor', function (string $name, string $variant): string {
    |       return file_get_contents(resource_path("icons/phosphor/{$name}.svg")) ?: '';
    |   });
    |
    */

    'icons' => [
        'driver' => env('LARADOCS_ICONS_DRIVER', 'heroicons'),
        'heroicons' => [
            'path' => env('LARADOCS_HEROICONS_PATH'),
            'variant' => env('LARADOCS_HEROICONS_VARIANT', 'outline'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP API
    |--------------------------------------------------------------------------
    |
    | Controls the JSON API endpoints (_laradocs/api/tree and _laradocs/api/search).
    |
    | "rate_limit"  Requests per minute allowed per IP address. Override or
    |               disable programmatically via the Laradocs facade in a
    |               service provider's boot() method:
    |
    |                 Laradocs::rateLimit(false);              // disable
    |                 Laradocs::rateLimit(120);                // 120 rpm
    |                 Laradocs::rateLimit(fn ($req) => ...);   // full control
    |
    */

    'api' => [
        'rate_limit' => (int) env('LARADOCS_API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP
    |--------------------------------------------------------------------------
    |
    | Controls the Model Context Protocol endpoint. Toggled independently of the
    | rest of the docs so it can be enabled or disabled on its own.
    |
    */

    'mcp' => [
        'enabled' => env('LARADOCS_MCP', false),

        // Authentication for the MCP endpoint. Open (unauthenticated) by default.
        // Set `guard` to any Laravel auth guard name to require a valid token.
        //
        // Example – Laravel Passport "api" guard:
        //   LARADOCS_MCP_AUTH_GUARD=api
        //
        // Clients must then pass: Authorization: Bearer <token>
        //
        // See docs/guide/mcp.md for a full Laravel Passport setup guide.
        'auth' => [
            'guard' => env('LARADOCS_MCP_AUTH_GUARD'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Chat
    |--------------------------------------------------------------------------
    |
    | An answer-from-your-docs chat assistant built on the Laravel AI SDK
    | (laravel/ai). Every provider that SDK supports is available here, using
    | the credentials already in your own config/ai.php. Laradocs never asks
    | for a key of its own, so bringing your own provider and token is the
    | only arrangement there is.
    |
    | Disabled by default, and it needs both the flag below and laravel/ai
    | installed before a single route appears:
    |
    |     composer require laravel/ai
    |     LARADOCS_AI=true
    |
    | The assistant answers from the documents the reader is allowed to read.
    | It goes through the same loader every other read path uses, so a bound
    | DocumentVisibility rule filters what the assistant can see exactly as it
    | filters the navigation, search and the MCP tools: a restricted page is
    | invisible to the assistant unless the reader has the authority to see
    | it. See the "Visibility" guide.
    |
    | "provider"  Which laravel/ai provider answers. Null uses that package's
    |             own `ai.default`. A string names one provider; an array is a
    |             failover chain, either a plain list of provider names or a
    |             provider => model map:
    |               'provider' => ['anthropic' => 'claude-sonnet-5', 'openai' => null],
    | "model"     Model within the provider. Null uses the provider default.
    | "timeout"   Seconds a single answer may take before it is abandoned.
    | "instructions"
    |             Replaces the built-in system prompt outright. Leave it null
    |             to keep that prompt and append to it instead, with
    |             `Laradocs::chatContext(fn () => ...)` from a provider.
    | "history"   How many prior messages the client may replay into a request.
    |             Older messages are dropped and the newest kept, so a long
    |             thread cannot grow a prompt without bound.
    | "max_chars" Longest question the endpoint accepts, in characters. Longer
    |             ones are rejected as a validation error rather than billed.
    | "stream"    Stream the answer back a token at a time over server-sent
    |             events. Turn it off to receive one JSON response instead,
    |             which is what buffering proxies and PHP-FPM setups without
    |             output flushing need.
    | "rate_limit"
    |             Requests per minute per IP for the chat endpoint. An answer
    |             costs real money, so this is deliberately much tighter than
    |             the JSON API's limit. Set it to 0 to lift the limit.
    |
    | "auth"      Who may talk to the assistant. Open by default, matching the
    |             docs themselves.
    |   "guard"   Any Laravel auth guard name. Set it and an unauthenticated
    |             request is refused with a 401 rather than answered.
    |   "gate"    A Gate ability checked for the resolved user. A refused
    |             request gets a 403.
    |             For anything those two cannot express, register a callback
    |             with `Laradocs::chatAuthorize(fn (Request $r) => ...)`; it is
    |             consulted after both of them.
    |
    | "mcp"       Tools the assistant may call.
    |   "laradocs"
    |             Hand the assistant Laradocs' own MCP tools: search_docs,
    |             list_pages and fetch_page. On by default whenever laravel/mcp
    |             is installed, because without them the assistant has no way
    |             to read a page and can only answer from its own training.
    |             The `mcp` endpoint above is a separate, independent switch;
    |             these tools are called in process and never over HTTP.
    |   "servers" Additional MCP servers of your own, each one's tools handed
    |             to the assistant alongside the built-ins. Requires
    |             laravel/mcp. Keys name the server. A server that cannot be
    |             reached is logged and skipped rather than failing the answer.
    |
    |               'servers' => [
    |                   'billing' => [
    |                       'url' => env('BILLING_MCP_URL'),
    |                       'token' => env('BILLING_MCP_TOKEN'),
    |                       'headers' => ['X-Tenant' => 'acme'],
    |                       'timeout' => 30,
    |                       'only' => ['lookup_invoice'],
    |                       'except' => [],
    |                   ],
    |                   'local' => [
    |                       'command' => 'npx',
    |                       'args' => ['-y', '@acme/mcp-server'],
    |                   ],
    |               ],
    |
    |             "only" and "except" filter the server's advertised tools by
    |             name, and "only" wins when both are given. Everything is
    |             advertised when neither is.
    |
    | "widget"    The chat panel rendered on your docs pages. It is available
    |             anywhere else in your application too, as
    |             `<x-laradocs::ai-chat />`, which brings its own stylesheet
    |             and script along so it works off a docs page.
    |   "enabled"  Render the launcher and panel on docs pages.
    |   "position" Which corner the launcher sits in: right | left.
    |   "greeting" Opening message. Null uses the translated default.
    |
    | See docs/integrations/ai-chat.md for the full guide.
    |
    */

    'ai' => [
        'enabled' => (bool) env('LARADOCS_AI', false),

        'provider' => env('LARADOCS_AI_PROVIDER'),
        'model' => env('LARADOCS_AI_MODEL'),
        'timeout' => (int) env('LARADOCS_AI_TIMEOUT', 60),

        'instructions' => null,

        'history' => (int) env('LARADOCS_AI_HISTORY', 10),
        'max_chars' => (int) env('LARADOCS_AI_MAX_CHARS', 2000),
        'stream' => (bool) env('LARADOCS_AI_STREAM', true),
        'rate_limit' => (int) env('LARADOCS_AI_RATE_LIMIT', 10),

        'auth' => [
            'guard' => env('LARADOCS_AI_AUTH_GUARD'),
            'gate' => env('LARADOCS_AI_GATE'),
        ],

        'mcp' => [
            'laradocs' => (bool) env('LARADOCS_AI_MCP', true),
            'servers' => [],
        ],

        'widget' => [
            'enabled' => (bool) env('LARADOCS_AI_WIDGET', true),
            'position' => env('LARADOCS_AI_WIDGET_POSITION', 'right'),
            'greeting' => env('LARADOCS_AI_GREETING'),
        ],
    ],
    /*
    |--------------------------------------------------------------------------
    | llms.txt
    |--------------------------------------------------------------------------
    |
    | Controls the llmstxt.org index served at {prefix}/llms.txt: a plain-text
    | map of your documentation, one link per page, for language models and any
    | other tool that reads docs without executing JavaScript. It is built from
    | the same document tree as the sidebar and the sitemap, and cached and
    | invalidated alongside them.
    |
    | The convention points at the domain root, so opt in to serve it there too:
    |   LARADOCS_LLMS_ROOT=true
    |
    | "full"           Additionally serve {prefix}/llms-full.txt: the entire
    |                  documentation corpus in one response instead of a list
    |                  of links, so a model can load the whole site without
    |                  fetching a page at a time. Off by default — a mid-size
    |                  site's corpus runs to megabytes. Each page's markdown is
    |                  passed through variable, macro and Blade-component
    |                  interpolation (the same substitutions the HTML renderer
    |                  performs) but not the HTML renderer itself, so the
    |                  corpus stays plain text.
    | "full_max_bytes" Truncates llms-full.txt at a document boundary once the
    |                  body would exceed this many bytes, appending a notice
    |                  rather than serving a silently partial file. Set to 0 to
    |                  disable the cap.
    |
    | See docs/seo/llms.md for a full guide.
    |
    */

    'llms' => [
        'enabled' => (bool) env('LARADOCS_LLMS', true),

        // Additionally serve the same body from the domain root, at /llms.txt,
        // which is where the convention says to look. Off by default: your
        // application may already publish one describing the whole product, of
        // which the docs are only a part.
        'root' => (bool) env('LARADOCS_LLMS_ROOT', false),

        // Serve {prefix}/llms-full.txt, the whole corpus in one response.
        'full' => (bool) env('LARADOCS_LLMS_FULL', false),

        // Byte ceiling for llms-full.txt before it is truncated. 0 disables it.
        'full_max_bytes' => (int) env('LARADOCS_LLMS_FULL_MAX_BYTES', 5_000_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deploy
    |--------------------------------------------------------------------------
    |
    | Connection details for `php artisan laradocs:deploy` and friends, which
    | push your docs to (or pull them from) a hosted Laradocs site. Authenticate
    | once with `php artisan laradocs:login`; tokens are cached at the path
    | below. The client id is the hosted platform's first-party CLI client and
    | rarely needs changing.
    |
    */

    'deploy' => [
        'url' => env('LARADOCS_URL', 'https://laradocs.dev'),
        'site' => env('LARADOCS_SITE'),
        'client_id' => env('LARADOCS_CLIENT_ID', '926e3796-a4a2-487a-a3af-4e447871a7cd'),
        'redirect_port' => (int) env('LARADOCS_REDIRECT_PORT', 8788),
        'credentials' => env('LARADOCS_CREDENTIALS', storage_path('laradocs/credentials.json')),
    ],

];
