# AJAX Resource Filter

AJAX Resource Filter with Taxonomy — a lightweight WordPress plugin that registers a `resource` custom post type and provides an AJAX-powered front-end filter (year, model) plus search and REST-ready responses.

## Features

- Registers `resource` custom post type with archive at `/resources/`.
- Adds two taxonomies: `resource-year` (Years) and `resource-model` (Models).
- Shortcode-driven AJAX filter UI: `[resource_filter]`.
- REST enhancements: returns `resource_years`, `resource_models`, `featured_image_url`, and `excerpt_plain` in REST responses.
- Search integration via `/resources/?c=search+term` and rewrite rule for `/resources/search/{term}`.

## Installation

1. Download the plugin folder into `wp-content/plugins/ajax-resource-filter`.
2. Activate the plugin from the WordPress admin Plugins screen.
3. (Optional) Visit Settings → Permalinks and click "Save Changes" to ensure rewrite rules are flushed (or activate the plugin once to flush automatically).

## Usage

- Add the shortcode to any page or post where you want the filter UI to appear:

  [resource_filter]

- Shortcode attributes:
  - `posts_per_page` (int) — number of resources to show per page. Default: `12`.

- Search: the filter UI search uses a query parameter `c`. Linking to the archive with `?c=term` will show search results, for example: `/resources/?c=brakes`.

## Shortcode Example

Place the shortcode on a page (e.g. "Resources"):

[resource_filter posts_per_page="8"]

The plugin renders a fully styled filter UI with:

- Sidebar filters for Year and Model
- Search box
- Sorting (Newest/Oldest, Title A–Z/Z–A)
- AJAX-loaded results and pagination

## REST API

The `resource` CPT is exposed in the WP REST API. Notable fields added to the REST response:

- `resource_years`: array of assigned Year terms (id, name, slug)
- `resource_models`: array of assigned Model terms (id, name, slug)
- `featured_image_url`: absolute URL string for the featured image
- `excerpt_plain`: a plain-text excerpt

Example request:

GET /wp-json/wp/v2/resource

Use query args for standard WP REST filters (per_page, page, search). The plugin also supports archive endpoints and taxonomy queries.

## Template / Integration Notes

- The shortcode outputs markup and inline styles; you can override appearance with your theme CSS.
- The container includes `data-archive-url` pointing to the resources archive which the JS uses for AJAX queries.

## Troubleshooting

- If filters or permalinks return 404, re-save Permalinks in Settings.
- Ensure the theme supports `post-thumbnails` if you want featured images to appear.

## Changelog

- 1.2 — Improved REST fields and UI tweaks
- 1.0 — Initial release

## Author

Towfique Elahe — https://towfiqueelahe.com/

## License

This plugin is released under the terms in the repository `LICENSE` file. See the `LICENSE` in this repository for details.
