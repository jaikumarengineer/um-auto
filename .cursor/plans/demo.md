# Quality demo — three user levels

## Goal

Keep the current user screen and `user_type` column. Add two department roles and enforce them on the menu and on the URL.

| Level | `user_type` | Label on the user form |
| --- | --- | --- |
| Master admin | `admin` | Master admin |
| Purchase department | `purchase` | Purchase department |
| Incoming department | `incoming` | Incoming department |

Existing `admin` accounts stay master admins. No password reset and no rename of the stored value.

`sub_admin` and `staff` stay in the database so current logins still open the panel. The user form stops offering them. They keep full access until a master admin changes the role. `user` stays outside the admin panel, which is already how `isAdmin()` works.

Spatie Permission is installed and unused. Leave it unused for this update. Three fixed departments fit the existing role field.

## What each role can open

Master admin sees the whole admin: dashboard, users, settings, purchase, incoming, analysis, and alerts.

Purchase department sees only purchase:

- Purchase order list, create, edit, and the printable PO format
- Purchase invoice list, create, edit, and the printable PI format
- Analysis limited to PO vs PI: ordered, invoiced, balance, overdue orders, and the PO/PI chart
- Alerts limited to the day summary for purchase and the PO vs PI alert

Incoming department sees only incoming:

- Approved vendors, parts, inspection standards, inspection reports, sampling plans
- Analysis limited to batches and parts: history tree, received vs inspected, standard vs report, vendor quality, lots waiting inspection
- Alerts limited to the incoming day summary, lots waiting inspection, and quality holds

Purchase still needs approved vendors and parts inside the purchase-order form. That form can read those records. Purchase cannot open the vendor or part screens, and cannot create or edit them.

Users, settings, server tools, and the country list stay on master admin. Profile stays available to every signed-in role.

## Home after login

- Master admin → dashboard
- Purchase → purchase order list
- Incoming → approved vendors

A direct URL outside the role returns 403. Hiding the sidebar item is not enough.

## How to enforce it

1. Extend `users.user_type` with `purchase` and `incoming`. The column is an enum today, so this is a migration.
2. Treat `admin`, `sub_admin`, `staff`, `purchase`, and `incoming` as panel users in `User::isAdmin()`, so department users pass `admin.check`.
3. Add `User::isMaster()`, `User::isPurchase()`, and `User::isIncoming()`. Master means `admin`, `sub_admin`, or `staff`.
4. Add one middleware, `quality.role`, that accepts `master`, `purchase`, or `incoming`. Master always passes. Put it on the Quality route groups in `Modules/Quality/routes/web.php`:
   - purchase routes, including both format pages: `purchase`
   - incoming routes: `incoming`
   - analysis and alerts: any of the three, then the page itself limits the data
5. Filter `Navigation.php` with the same rules. Purchase does not see Incoming, Users, or Settings. Incoming does not see Purchase, Users, or Settings.
6. On the user add/edit page, the role list becomes Master admin, Purchase department, and Incoming department. Only a master admin can open that page.
7. Analysis and alerts read the role and render that role’s slice. The “Build today’s alerts” button stays on master admin. Department users read their own rows. Alert types already stored:
   - purchase: `daily_summary` (purchase counts only) and `po_vs_pi`, plus `overdue_po`
   - incoming: incoming counts, `uninspected_lots`, `quality_hold`
   - master: all of the above

## Demo accounts

Master admin creates the two department users from Users, or a seeder adds them when they are missing:

- one purchase user
- one incoming user

Passwords come from the person running the demo. Do not hard-code them.

## Checks before calling it done

- Purchase user opens a purchase order and an invoice, and gets 403 on vendors, standards, reports, users, and settings.
- Incoming user opens vendors and a report, and gets 403 on purchase orders, invoices, users, and settings.
- Master admin opens every quality screen and the user form.
- Purchase order form still lists approved vendors and parts for a purchase user.
- Sidebar matches what the URL allows.
- Existing admin login still lands on the dashboard with full access.
