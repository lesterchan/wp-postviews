# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-PostViews follows `_standards/STANDARDS.md` in the parent folder, which is
the contract for all nineteen plugins in the collection. Where this file and
that one disagree, that one wins.

## What it is

Counts how many times each post is viewed and renders the number: a template
tag `the_views()`, a `[views]` shortcode, an admin Views column, a sidebar
widget, several "most viewed" listing tags, and a WP-Stats section. Settings
under Settings, two tabs (Settings / Templates).

## Data

* `wp_postviews_options` (settings) and `wp_postviews_version` (markers) —
  folded in from `views_options` and `views_version`, both of which the released
  1.78.1 ships.
* **The counts themselves are post meta under the key `views`** — unprefixed,
  and it must stay that way. It is the key every existing site's data is under
  and every third-party query sorts by. `uninstall.php` uses
  `delete_post_meta_by_key( 'views' )`.
* It is one of the seven WP-Stats plugins (§13). See below.

## WP-Stats coupling

`WP_PostViews_WPStats` hooks `wp_stats_sections` and returns one entry keyed
`wp_postviews`. Two rules that look like details and are not:

* **Read the toggle through `WP_PostViews_Options::get( 'stats_display' )`,
  never `get_option( 'stats_display' )`.** A raw read cannot distinguish "a
  sibling already migrated and deleted the shared row" from "the site opted
  out", and would turn a fresh install's section off.
* **`uninstall.php` must not delete the shared `stats_display` /
  `stats_mostlimit` rows.** Up to six siblings that have not upgraded are still
  reading them. wp-polls and wp-downloadmanager currently get this wrong on
  uninstall and wp-useronline in its migration (`_standards/RESUME.md`); this
  plugin does not — keep it that way.

The three separately switchable panels this plugin used to own collapse into one
Views section, because WP-Stats now collects whole sections. The Upgrade Notice
warns that the most-viewed *pages* list therefore appears beside the posts list.

## Traps

* **Two counting paths, chosen by `WP_CACHE`.** Without a page cache the count
  increments during `wp_head`; with one, that would only ever record the request
  that generated the cached page, so a small script posts to `admin-ajax.php`.
  `using_ajax()` decides.
* **`should_count()` holds the `is_preview()` check, and it lives there for a
  reason.** `process()` checked it and `enqueue()` did not, so on any site using
  the AJAX path a preview *was* counted — an author refreshing a draft inflated
  its own figures. Two places deciding one fact and only one of them told. Fixed
  in `cedcb8d`; do not push the check back out to the callers.
* **`render_item()` escapes the title unconditionally, and the order matters.**
  Until 2.0.0 the only escaping a title got was the `htmlentities()` inside
  `snippet_text()`, which runs when `$chars > 0` — so escaping was a *side
  effect of truncation*, and truncation is off by default. A post titled with a
  script tag went straight into the stock template's `title="…"` attribute. This
  was one of the two stored XSS the e2e sweep found (§7.2.4). Truncate first,
  then escape, or entities count toward the character limit and a title can be
  cut mid-entity.
* **The AJAX action is `wp_postviews`, not `postviews`.** A bare generic noun is
  something any plugin could claim and neither WordPress nor `admin-ajax.php`
  detects the collision.
* **The bot list is deliberately not narrowed.** Some entries are far broader
  than their names suggest — `Sogou` matches the bare substring `spider`, which
  hits ordinary browsers. It has been this way for years and tightening it would
  silently change the counts on every site with bot exclusion on.
* **`the_views` was renamed to `wp_postviews_the_views` and there is no shim.**
  It had been public since 1.78.1 and is very probably in somebody's theme. It
  fails silently: the count still appears, the site's custom wrapper does not.
  The template *function* `the_views()` is unchanged.
* **`the_views()` echoes the stored template unescaped, on purpose.** The
  template is HTML by design and is `wp_kses_post()`'d by `WP_PostViews_Settings`
  on save. The `phpcs:ignore` on that line carries the reason.
* **The `[views]` shortcode and the admin Views column deliberately bypass
  `should_be_displayed()`** — both are explicit requests for the number, not a
  themed sidebar the gate governs.
* **`WP_PostViews_Display::should_be_displayed()` must survive**, even though the
  six Display Options settings behind it are gone. The 2.0.0 Upgrade Notice
  names that method and the `wp_postviews_should_display` filter as the
  documented replacement, so removing it breaks a promise in the release about
  to ship. `_standards/RESUME.md` task #19 says the same.

## Tests

`test-counter.php` covers both counting paths and the preview guard;
`test-migration.php` the `views_options` fold-in; `test-multisite.php` exists
because `test_uninstall_removes_only_our_data` skips its network branch
deliberately — the coverage is in the multisite class, not missing.

`tests/e2e/` is nine specs and 84 tests, the largest unverified suite in the
collection. `_standards/RESUME.md` flags it specifically: **it was never checked
for near-duplicate padding.** Audit it against `includes/` before believing it is
comprehensive.
