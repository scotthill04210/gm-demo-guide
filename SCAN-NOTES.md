# Scan notes: Demo Growth Memberships

Scan date: 2026-10-15. This was a read-only scan of network site blog ID 3, done with WP-CLI reads (`post get`, `db query SELECT`, `eval` reads) and GET requests made as the demo user `democlient`, who has the `client` role.

Session note: the democlient session was made with WP-CLI on the server, used only for GET requests from the server, and never printed or saved. Making it created one session record on that user that expires after 1 hour. That was the only write. We did not request any page known to write on load (see "Pages skipped on purpose").

## Pages covered (all fetched as democlient)

| Path | What it is | Result |
|---|---|---|
| `/` | Dashboard: shortcut buttons grouped by topic | 200, works |
| `/members-page/` | Member list with date filters and pages | 200, works |
| `/membership/{slug}/` (sample: Riverbend Solar Partners) | One member: details, locations, people, notes, dues info, pop-up edit forms, delete | 200, works |
| `/members-page/add-a-member/` | Add member form. After save it goes to the new member page (form setting checked) | 200, works |
| `/members-page/search-contacts/` | Contact list. Names link to `/people/{slug}/` | 200, works |
| `/members-page/add-a-contact/` | Add contact form. After save it goes to the new contact page | 200, works |
| `/members-page/search-locations/` | Location list | 200, works |
| `/members-page/add-a-location/` | Add location form. After save it goes to the new location page | 200, works |
| `/people-places-things/` | "All Data Types": members, locations and contacts in one list | 200, works |
| `/membership-sign-up/` | Example join form. 37 fields, 4 auto-calculated price fields, 14 fields that show or hide based on answers | 200, works |
| `/current-member-registration/` | Account sign-up for existing members | 200, works (not used in tour; page text names another brand) |
| `/events-dashboard/` | Links to member events and course groups | 200, works |
| `/events/`, `/events/category/member-events/`, `/events/category/courses/` | Event calendar in list view with search bar | 200, works |
| `/event/{slug}/` (samples: Rural Leaders Luncheon, Rising Leaders Golf Classic) | Event details, add-to-calendar, ticket picker, attendee details pop-up | 200, works |
| `/register-georgia-advisory-council-meeting/` | 5-step event registration (Company, Tickets, Attendees, Sessions, Summary) with running total, coupon box, save-and-continue, Proceed to Checkout | 200, works |
| `/product/georgia-advisory-council-meeting-registration/` | Product page for that registration | 200, works (not in tour) |
| `/convention-event-manager/` | Multi-day event manager shown inline on the page (event picker, side menu, summary boxes) | 200, works |
| `/cart/` | Cart. When empty it shows suggested items | 200, works |
| `/shop/` | Shop with 8 items (membership and sponsorship items) | 200, works (not in tour) |
| `/my-account/`, `/my-account/orders/` | Account area and order history (4 demo orders: Completed, On hold, Cancelled) | 200, works |
| `/finance-dashboard/`, `/reports/` | Link pages only. Most links go to back-office screens democlient cannot open (see below) | 200, kept out of tour and help panels |

Main menu (`#menu-main-menu`): Dashboard; Members (Search Members, Search Locations, Search Contacts, Add a Member (No Charge), Add a Location, Add a Contact, Example Member Sign Up); Events (Courses); Revenue (sub-items link to back-office screens); Reports; gear menu (Log Out, My account, Help & Support).

## Framed and embedded pages

There is no working iframe on any front-end page. Searching page content found no `<iframe>`. The rendered pages had no frames except the hidden helper frame described below.

| Page | How it is embedded | Status |
|---|---|---|
| `/attendees/` | Meant to be same-origin: an embed shortcode points to the attendee list screen on this same site | **Broken.** The shortcode is not registered, so the page shows the raw shortcode text |
| `/analytic-revenue/` | Meant to be same-origin: embed shortcode pointing to the revenue report screen on this same site | **Broken**, shows raw shortcode text |
| `/insert-edit-link/`, `/edit-snippet-add-new/`, `/edit-product/` | Meant to be same-origin: embed shortcode pointing to back-office screens on this same site | Same unregistered shortcode, so presumably broken. Not fetched, and out of scope |
| `/convention-event-manager/` | Inline content: the manager is drawn right into the page by a shortcode, not framed | Works |
| `/register-georgia-advisory-council-meeting/` | Inline content (registration shortcode) | Works |
| `/membership-sign-up/` | Inline form. It includes a hidden same-origin helper frame (`about:blank`) that the form uses to send without reloading. It is not a framed screen | Works |
| Back-office custom dashboard (theme `functions.php`) | Full-screen iframe to a **different origin**, shown only on the back-office home screen | Not on a front-end page. democlient is sent away from the back office, so prospects never see it |

## Fragile selectors

- `#menu-main-menu`, `.dashboard-row`: stable theme markup, present on every page.
- `.wpv-filter-form`, `.js-wpv-view-layout`: present on all list pages. If a list page ever gets a second list, these will match more than once.
- `form.cred-form`: only one on the Add a Member page. The member page has many (one per pop-up), so don't use it there. The form IDs carry counter suffixes (`cred_form_131_1_1`) and are fragile.
- `#gform_wrapper_2`: tied to form ID 2. It changes if the form is rebuilt or imported again.
- `.tribe-events-calendar-list`, `.tribe-tickets__tickets-wrapper`: depend on the event/ticket templates. The ticket block appears only when an event has tickets.
- `.cem-registration-wrap`: tied to the registration page. The slug `/register-georgia-advisory-council-meeting/` does not match its title ("Georgia Propane Fall Reunion"), so the slug may change.
- `.wc-block-cart`: block-based cart. It changes if the cart page goes back to the classic layout.
- `table.account-orders-table`: shows only when the user has at least one order.
- Member page step (`tour-main-05`) uses `target: null`. The section headings (Member Details, Locations, People, Member Notes, Member Financials) are plain `<h4>` with no IDs or classes.
- `page_match.path` values `/membership/` and `/event/` are **prefixes** (real URLs are `/membership/member-3481/`, `/event/event-6250/`). The matcher must do prefix matching for those, and exact matching for `/` and `/members-page/` (which is also a prefix of its child pages).

## Broken or blocked (left out of tour and help)

- `/upcoming-member-events/`, `/tickets-checkout/`, `/tickets-order/`: show raw shortcode text because the shortcodes are not registered. Ticket buying still works, through the event page, the cart and checkout.
- Finance Dashboard's "Online Orders" button leads to a missing page (404).
- Dashboard and Reports buttons that link into the back office (All Tickets, Attendees, List All Events, Orders, Coupons, Analytic Reports, Event APP Alerts, Edit Users): when tested, democlient got "Sorry, you are not allowed to access this page" (attendee and ticket screens, 403) or was sent to the front page with that message (event list, order list).
- The Events Dashboard hides "Add an Event" and the ticket links from democlient.

## Pages skipped on purpose

- `/membership-payment/`: its template adds a product to the cart when the page loads (a write).
- "Add an Event" link (`post-new.php`): opening it creates a draft (a write).
- Settings, users, plugins, demo/reset tooling and everything else back-office related were out of scope.

## Unverified

- What happens after any form is submitted (no POSTs were made). The add-member, add-contact and add-location redirects come from the forms' saved settings. The sign-up form's "asked to pay" step comes from its on-page text and its redirect confirmation setting.
- Whether checkout takes real payments or test payments. The tour stops at the cart and does not tell people to pay.
- The single order view page (`/my-account/view-order/{id}/`): the link exists, but the page was not fetched.
- What the Addresses and Account details tabs in My account show.
- What the Convention Event Manager sections show once their data loads (the page loads them with scripts after it opens, and the first view shows "Loading..." and dashes).
- Contact (`/people/{slug}/`) and location single pages: links exist, but the pages were not fetched.
- Whether the "Analytic Reports" links on Finance Dashboard and Reports block democlient. They were not fetched, but they are the same kind of back-office links that were blocked.
- How the list filters behave live (they reload with scripts). Only the controls were confirmed.

## Content hygiene

- Several live pages contain the old brand names in their text (sign-up page heading, current-member registration text, footer, some event and course labels such as the courses heading on the Events Dashboard). The guide content does not repeat them. Change the site text if prospects should not see them.
- Contact and location lists show demo email addresses. None are quoted here.
