<p align="center">
  <img src="PackageManager/data/icon/icon.webp" width="96" alt="PackageManager icon">
</p>
<h1 align="center">PackageManager</h1>

<p align="center">The tools behind your site. One place to manage them.</p>

PackageManager brings package installation, inspection and lifecycle controls into the AWT dashboard. Administrators can review installed package metadata, manage dependencies safely and follow every action through a persistent audit trail.

## Features

| Capability | Experience |
| --- | --- |
| Installed packages | ThemeEngine-style cards with search and filters for Plugins, Themes and Unknown types |
| Complete details | Database package metadata, readable dependency statuses and description below metadata |
| Trusted installation | ZIP picker with filename, size, clear control and explicit trust confirmation |
| Lifecycle controls | Enable, disable and remove with server-side dependency and system safeguards |
| License reader | Sandboxed popup with a link to the publisher's original document |
| Activity history | User, package, action, outcome and UTC date; recent activity dashboard widget |
| Browse | A dedicated Coming soon page for future discovery |
| Help | Dedicated help page with expandable workflows and troubleshooting |
| Appearance | Dashboard styles, light/dark themes and responsive layouts |

## Requirements

- AWT **27.0.0 or later**, with Dashboard and URPC installed at versions satisfying `manifest.json`.
- PHP compatible with the installed framework, including ZIP, JSON, fileinfo, DOM and the configured PDO driver.
- Authenticated dashboard account with **permission 0** for all pages, actions and widgets.
- Write access to AWT package, package-storage and transient-storage directories, plus database schema permissions for installation/update.
- JavaScript and a browser supporting native `<dialog>` for dashboard confirmations. Use the framework CLI for shell-based package operations.
- For 50 MB uploads, configure PHP `upload_max_filesize`, `post_max_size`, request limits and memory appropriately. The application rejects archives larger than 50 MB, more than 10,000 entries, or over 200 MB uncompressed.

## Get started

Install the release ZIP using your trusted AWT deployment process. `install.php` creates the registered `packagemanager_log` table. Existing installations must run the framework package update process so `update.php` creates the same table idempotently; copying files alone does not run migrations.

Open **Dashboard → Packages → Installed**. Choose a trusted ZIP, review the filename and size, then select **Install**. Confirm the source warning before installation. A success dialog offers **Enable package** or **Keep disabled**. Either choice returns to a fresh Installed page. Escape keeps the package disabled and refreshes the page.

Installation may execute package hooks immediately. Normal newly installed packages remain disabled until enabled. Packages declaring `system_package` or type `0` must be deployed through a trusted server process because the framework automatically enables them.

Search Installed by name, description, author or version, and combine the search with a package-type filter. Type `1` appears as **Plugins**, type `2` as **Themes**, and other visible types as **Unknown**. Only installed type groups appear in the dropdown. Type `0` packages are omitted from the directory; they remain available to dependency checks. Clear filters restores the full visible directory.

Select a card to see an overview and expand **View all metadata** for the installed database record. Cards, details, descriptions and dependency statuses all use the database; the dashboard never rereads the installed manifest.json. Package icons and previews use `/storage/<package_id>/<filename>`, taking the filename from database metadata without subdirectories. The sidebar navigation keeps its original static package icon. Custom fields not persisted by AWT are not displayed. The description appears below the metadata, followed by the package README when present. Overview, README, Dependencies and Actions links let you jump directly to a section. README discovery checks the package root case-insensitively for `README.md`, `README.markdown`, `README.html`, `README.htm` or `README`, preferring Markdown. Markdown (including tables and fenced code) and HTML are rendered with active content removed. Relative links and images resolve inside the package. Files larger than 1 MB or outside the package directory are not displayed.

Enable checks AWT compatibility and requires compatible, enabled dependencies. Disable lists any enabled packages that depend on the target. Remove requires confirmation and lists **all** installed dependents, including disabled ones. System packages cannot be disabled or removed.

Removal deletes code and resource records while retaining owned storage and database tables. Permanent purging and package updates are not dashboard operations in this release.

## Routes and permissions

Every route requires an authenticated account with permission **0**. Package navigation and the recent-activity widget are hidden from other accounts, with authorization also enforced on the server. Mutations use POST and the framework's default CSRF validation.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/dashboard/packages?q=example&type=plugins` | Search/filter Installed; upload and post-install prompt |
| GET | `/dashboard/packages/browse` | Browse placeholder |
| GET | `/dashboard/packages/{package_id}` | Manifest, description, license and package actions |
| GET | `/dashboard/packages/logs?page=1` | Paginated audit history; 25 entries per page |
| GET | `/dashboard/packages/help` | Workflows, permissions and troubleshooting |
| POST | `/dashboard/packages/install` | Confirmed ZIP installation |
| POST | `/dashboard/packages/action` | Enable, disable or confirmed removal |

The two main tabs have separate routes. The Installed and Browse tabs sit in the header. Help is a borderless header button and sidebar link; Logs is available from the sidebar. The widget key is `PackageManager/recent-actions`, with permission 0 and its own authorization check.

## Framework CLI

Package commands are provided by AWT itself. PackageManager does not register additional commands. Use the framework's CLI help for the commands supported by your installed AWT version. The PackageManager audit trail covers actions performed through its dashboard.

## Audit history and recovery

Actions write an audit entry before mutation and update its outcome afterward. Entries retain actor and package names after deletion; deleting an account nulls its ID while keeping the historical name. Failed actions are logged once an operation starts. Authentication, CSRF and malformed-request failures rejected before an operation are not package-action entries. Logs begin after migration; earlier activity is not reconstructed.

A **pending** entry may indicate an interrupted request. Installation and removal combine database and filesystem work in the framework and cannot be rolled back atomically. Failures can leave partial files or records. Review Installed, the audit details and server logs before retrying, and restore a backup or repair the deployment as needed. PackageManager serializes its own mutations with a shared lock; external deployments must be coordinated separately.

If uploads fail, check PHP request limits, ZIP support, disk permissions and database connectivity. If logs or actions fail after an upgrade, verify that the update hook ran successfully. Reload a page to renew an expired CSRF token. If a license cannot be embedded, use **Open original license**; the publisher may prohibit framing.

## Development and release

`main.php` is the package entry point. Runtime APIs live in `runtime/`, request handling in `controllers/`, validation and persistence in `service/`, schema changes in `migrations/`, widgets in `widgets/`, and views, partials and assets in `views/`.

Repository checks:

```bash
php tests/integration.php
python3 -B -m unittest discover -s tests -p 'test_release.py'
find PackageManager -name '*.php' ! -path '*/views/*' -print0 | xargs -0 -n1 php -l
```

The integration suite uses an isolated temporary SQLite database, real framework services and Dashboard templates. It discovers sibling AWT checkouts by default; set `AWT_FRAMEWORK_ROOT` to the framework repository and `AWT_DASHBOARD_ROOT` to Dashboard's inner package directory when needed. It never changes a running installation.

Use GitHub Actions **Publish package → Run workflow**, then choose `major`, `minor` or `patch`. The workflow finds the highest stable **published release** version, accepting `1.2.3` and `v1.2.3`; drafts, prereleases and unrelated tags are ignored. The first release is always `1.0.0`.

The builder updates `manifest.json` in a staging copy and produces `PackageManager-<version>.zip`, with `manifest.json` and `main.php` directly at the ZIP root. Release runs are serialized across branches. Existing tags, including the equivalent prefixed version, are rejected; releases and tags are never overwritten. The checked-out manifest stays unchanged.

## Third-party software

README Markdown rendering uses [Parsedown 1.8.0](https://github.com/erusev/parsedown/releases/tag/1.8.0), bundled under the MIT license. Its license accompanies the source in `vendor/parsedown/`. HTML is sanitized before it reaches the dashboard; package scripts, embedded frames, styles and event handlers are omitted.
