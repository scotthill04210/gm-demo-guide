# GM Demo Guide

Front-end tour, page help, and glossary for the Demo Growth Memberships demo. The reviewed copy lives in `content/default.json` (one tour, 18 help panels, 19 glossary terms, 50 ids). That file is the source of truth. Do not rewrite, renumber, or regenerate it.

Activate the plugin on the demo site. Log in on the front end, then use the **Tour Guide** button.

## What it does

- **Tour.** Twelve stops across the demo. Start it from the Tour Guide button. It does not start on its own.
- **This page.** The help note for the page you are on, when the content file has one.
- **Glossary.** The terms from the content file, with a search box.
- **Embedded screen.** The shortcode `[gm_demo_screen]` can frame another page on this same site. Nothing in the current demo uses it. Pages that already show a raw shortcode are left as they are.

## Page matching

A `page_match` object may include any of these keys. When more than one is set, all of them must match.

| Key | Meaning |
| --- | --- |
| `path` | Exact path, after trailing-slash and lowercase normalization. |
| `path_prefix` | The path must equal this prefix or begin with it. |
| `post_type` | The queried post type. `null` means the key is not used. |
| `shortcode` | The queried post content contains that shortcode. |

`/` and `/members-page/` stay exact, so they do not claim their child pages. `/my-account/` and `/my-account/orders/` are separate exact paths.

The reviewed file stores `/membership/` and `/event/` under `path`, but real URLs look like `/membership/member-3481/` and `/event/event-6250/`. Those two values are compared as prefixes. They are not rewritten in the file. A new prefix should use `path_prefix`. A `path_prefix` of `/` is ignored, because it would match every page.

`/membership/` does not match `/membership-sign-up/`, and `/event/` does not match `/events/`.

If several help panels match, the most specific one is used: an exact path beats a prefix, a longer path beats a shorter one, and a post type or shortcode adds weight.

## Targets

Each tour step may point at a CSS selector. The guide uses the first visible match. If the selector is missing, hidden, invalid, or `null`, the note is a centered card. That includes the selectors called out as fragile in `SCAN-NOTES.md` (form ids, ticket markup, the cart block, the orders table, and the member page, which has no target). A missing target does not throw and does not write to the console.

On a narrow screen, a step that does have a visible target uses a card at the top or bottom so the highlight stays visible.

## Pages the guide will not link to

The tour never builds a link to a prefix-only step (`/membership/`, `/event/`). The step text tells the person to open a member or an event. Exact-path steps can be opened with Continue.

These paths are refused as tour links and as embed URLs, including anything under them:

- `/upcoming-member-events/`
- `/tickets-checkout/`
- `/tickets-order/`
- `/finance-dashboard/`
- `/reports/`
- `/attendees/`
- `/analytic-revenue/`
- `/insert-edit-link/`
- `/edit-snippet-add-new/`
- `/edit-product/`
- `/membership-payment/`
- `/wp-admin/`
- `/wp-login.php`

`/membership-payment/` writes to the cart when it loads. The others are back-office screens or pages that show an unregistered shortcode. `democlient` is not allowed into the back office.

## Embedded screens

```
[gm_demo_screen url="/events/" title="Event calendar" height="640"]
```

`url` may be a site path such as `/events/`, or a full URL on this site. Any other URL, a blocked path, or the page the shortcode is already on, renders nothing. The framed page is loaded with `?gm_demo_embed=1`. The guide does not run inside that frame, and the shortcode does not frame a second screen from in there.

This shortcode is not the one already sitting as raw text on `/attendees/` and `/analytic-revenue/`. Those pages are unchanged.

## Install

1. Install the `gm-demo-guide.zip` asset from a [GitHub release](https://github.com/scotthill04210/gm-demo-guide/releases), or copy this folder to `wp-content/plugins/gm-demo-guide`.
2. Activate **GM Demo Guide** on the demo site. It is not forced on for the whole network, and it does not assume a blog id.
3. Log in and open the front end.

## Updates

The repository is public. WordPress checks it with [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (MIT), bundled in `vendor/plugin-update-checker/`. No token is required.

Updates prefer the latest GitHub release and download `gm-demo-guide.zip`. That archive has one top folder:

```text
gm-demo-guide/gm-demo-guide.php
```

WordPress unpacks that into `wp-content/plugins/gm-demo-guide/`, replacing the installed plugin. It does not use GitHub’s generated source archive, which would unpack as a different folder name. If a package still arrives under a name such as `gm-demo-guide-1.0.1`, the plugin renames that folder to `gm-demo-guide` before WordPress copies it. Other plugins are left alone.

The `Version` header is the release version. Tag `v1.0.1` means the header says `Version: 1.0.1`. Publishing a release on `main` runs the **Release zip** workflow and attaches `gm-demo-guide.zip`. Do not mark the release as a pre-release. The update then appears under **Plugins** (or **Network Admin > Plugins** when the plugin is network-activated). That row also has a **Check for updates** link, next to Deactivate, for anyone who can install plugin updates.

There is no settings screen. Progress is kept in the browser (`localStorage`), so the plugin does not write to the database.

## If the content file is broken

A missing file, a file over 1 MB, or JSON that does not decode turns the guide off. The front end stays up, with no PHP error from this plugin. An administrator sees a warning in the dashboard. Items with a bad id, a bad target type, or a `page_match` that has none of the four keys are skipped. The first copy of a duplicate id is kept. Unknown keys are ignored.

## Choices

- The guide shows for logged-in visitors on the front end. It stays off in the dashboard, in feeds, and inside a frame. `gm_demo_guide_should_display` can change that.
- The tour waits until someone starts it. Escape hides the current card and leaves the place saved. **End tour** clears it.
- Only the first tour in the file is offered.
- The queried post supplies `post_type` and the content used for `shortcode`.
- `gm_demo_guide_content` can replace the loaded content. The result is checked again.
- `gm_demo_guide_blocked_paths` can add paths. `/` in that list is ignored so a filter cannot block the whole site.
- The plugin does not hard-code member or event slugs. Those steps continue when the person opens a matching page.

## Checks

From this folder:

```
php -l gm-demo-guide.php
php bin/validate-content.php
```

`bin/validate-content.php` checks that the JSON decodes, that the 50 ids and the tour targets still match the reviewed file, and that prefix, exact, post type, shortcode, and embed rules behave as described above.
