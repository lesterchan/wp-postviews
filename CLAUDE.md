# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

Counts how many times each post is viewed and renders the number: a template
tag `the_views()`, a `[views]` shortcode, an admin Views column, a sidebar
widget, several "most viewed" listing tags, and a WP-Stats section. Settings
under Settings, two tabs (Settings / Templates).

## Data

* `wp_postviews_options` (settings) and `wp_postviews_version` (the `plugin` and
  `db` upgrade markers, and nothing else) — folded in from `views_options` and
  `views_version`, both of which the released 1.78.1 ships. Keep the markers out
  of the settings array: one in there has to be rescued from the stored value on
  every save, because the settings form never posts it.
* **The counts themselves are post meta under the key `views`** — unprefixed,
  and it must stay that way. It is the key every existing site's data is under
  and every third-party query sorts by. `uninstall.php` uses
  `delete_post_meta_by_key( 'views' )`.
* It contributes a section to **WP-Stats**, a separate plugin, by answering the
  `wp_stats_sections` filter. See below.

## WP-Stats coupling

`WP_PostViews_WPStats` hooks `wp_stats_sections` and returns one entry keyed
`wp_postviews`. Two rules that look like details and are not:

* **Read the toggle through `WP_PostViews_Options::get( 'stats_display' )`,
  never `get_option( 'stats_display' )`.** A raw read cannot distinguish "a
  sibling already migrated and deleted the shared row" from "the site opted
  out", and would turn a fresh install's section off.
* **`uninstall.php` must not delete the shared `stats_display` /
  `stats_mostlimit` rows.** They were never this plugin's to own — WP-Stats and
  several companion plugins all wrote into them — so the migration deletes them
  once it has folded them in, and uninstall leaves them alone, because a sibling
  that has not upgraded is still reading them. Keep it that way.

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
  script tag went straight into the stock template's `title="…"` attribute —
  a stored XSS the browser suite found. Truncate first, then escape, or entities
  count toward the character limit and a title can be cut mid-entity.
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
  to ship.

## Migrations, and why they are tested through a browser

`maybe_upgrade()` hangs off `init` at priority 1, **not `admin_init`**, and that
is deliberate: until it has run the plugin is reading defaults over a row
nothing has written, and it is *visitors* who would be looking at a stock
template in the meantime. So the migration does not wait for an administrator to
log in, and `tests/e2e/upgrade.spec.js` drives the first case from the front end.

Four things its fixtures rely on:

* **A `wp eval` call is itself an upgrade request**, because WP-CLI reaches
  `init` like any other request. Seed the fixture and read it back inside *one*
  call; a second call finds the rows already migrated, and the browser request
  then has nothing left to do.
* **Read rows raw** — `WP_PostViews_Options::all()` merges over the defaults, so
  it cannot tell a written row from an absent one, which is exactly the state a
  migration that read, deleted and never wrote leaves behind.
* **Seed the shipped defaults**, because a customised fixture's migrated result
  differs from the defaults and so lands whatever the read before it did.
* **The unslashing is gated on the legacy version marker, and both directions
  are pinned.** Up to 1.78.1 the settings screen wrote `$_POST` straight through,
  so templates were stored slashed and every read stripped a layer back off;
  2.0.0 unslashes on save, so rows on disk are corrected once — and *only* once,
  or a template holding a Windows path loses its separators on the second pass.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`test-counter.php` covers both counting paths and the preview guard;
`test-migration.php` the `views_options` fold-in; `test-multisite.php` exists
because `test_uninstall_removes_only_our_data` skips its network branch
deliberately — the coverage is in the multisite class, not missing.

`tests/e2e/` is the biggest suite here and **has never been audited for
near-duplicate padding** — a six-way parametrised loop is six tests by the count
and one test by what it proves. Read it against `includes/` before believing it
is comprehensive.
