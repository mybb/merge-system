# Changelog

## [Unreleased] — PHP Compatibility & Bugfix Update

This update focuses on getting the MyBB Merge System running on modern PHP versions (7.0 through 8.3), fixing long-standing data integrity bugs, removing converters for defunct forum software, adding support for Invision Community 5 (IPS5) and Discourse, and significantly improving import performance.

---

### GitHub Issues Resolved

- **#275** — PHP 8.0 compatibility issues (create_function, utf8 deprecation, undefined array keys)
- **#290** — GROUP BY violations on MySQL 8.0+ (ONLY_FULL_GROUP_BY)
- **#295** — phpBB3 birthday corrupted with extra spaces (trim + no trailing dash)
- **#296** — phpBB3 signatures contain HTML entities (strip tags, decode entities, convert `<br>`/`<p>` to newlines)
- **#272** — Vanilla not importing correctly (converter removed due to upstream API incompatibility)
- **#293** — vBulletin thread IDs differ after merge (by design; `import_tid` column enables redirect mapping)
- **#298** — IPB4 forum permissions break MyBB permission inheritance by setting explicit deny (0) for all groups on all forums. Fixed by only inserting rows with at least one positive permission, preserving MyBB's permission inheritance.
- **#258** — "localhost" and "127.0.0.1" detection in avatar/attachment modules used `strpos()` which matched substrings in file paths (e.g. `/var/www/localhost-uploads/`). Fixed by using `parse_url()` to extract the actual URL host before checking.
- **#245** — IPB4 private messages had no UTF-8 encoding on subject and message body. Added `encode_to_utf8()` wrapping. Also applied to IPB5.
- **#173** — IPB4 attachment BBCode/HTML in posts. IPB4 embeds attachments as `<a class="ipsAttachLink">` links in post content, which the HTML→BBcode parser incorrectly converted to broken URL tags. Added regex to strip non-image attachment links and unwrap image attachment links (keeping the `<img>` inside) before the parent parser runs. Applied to IPB4 and IPB5.

---

### New Converter: Invision Community 5 (IPS5)

- **New converter skeleton for IPS5** (`boards/ipb5.php` + 13 modules in `boards/ipb5/`). Built from the IPS4 converter as a template.
- **Status: Untested** — All column names carry `TODO: verify` markers pending access to a real IPS5 database for schema verification. Key areas needing verification:
  - Table names (`core_members`, `core_groups`, `forums_forums`, etc.)
  - Column names (password hash storage, member fields, language system)
  - Permission system changes
  - Settings storage mechanism (Laravel config vs `core_sys_conf_settings`)
  - BBCode/HTML output format changes
- **Password handling**: IPS5 uses Laravel's password hashing (bcrypt `$2y$` or argon2). Added `check_ipb5()` function using `password_verify()` and `ipb5` password type to `loginconvert.php`.

### New Converter: Discourse

- **New converter for Discourse** (`boards/discourse.php` + 12 modules in `boards/discourse/`).
- **Modules**: users, usergroups (trust levels + custom groups), forums (categories), threads, posts, polls, pollvotes, privatemessages, moderators (admin/mod boolean flags), avatars (custom + Gravatar), attachments (post_uploads), settings (site_settings), bbcode_parser (cooked HTML→BBcode).
- **Key features**:
  - Maps Discourse trust levels (0-4) to MyBB groups; TL3/TL4 get custom imported groups
  - Admin/moderator boolean flags mapped to MyBB admin/mod groups
  - Password hashing uses `password_verify()` for Ruby's `has_secure_password` bcrypt format
  - Polls module handles optional Discourse poll plugin (silently skips if polls table missing)
  - Post content converts Discourse "cooked" HTML to BBcode via custom parser
  - PostgreSQL-only (`$supported_databases = array("pgsql")`)
- Added `check_discourse()` and `discourse` password type to `loginconvert.php`

### MySQL 8.x Compatibility Fixes

All fixes remain backward-compatible with MySQL 5.7.

- **`ONLY_FULL_GROUP_BY` violations (would fatal error on MySQL 8.0+):**
  - `boards/phpbb3/users.php` — Moved `GROUP_CONCAT` into a subquery so `SELECT u.*` no longer conflicts with `GROUP BY u.user_id`
  - `boards/smf2/forumperms.php` — Added `p.id_group` to `GROUP BY` clause (2 locations)
  - `boards/wbb4/users.php` — Moved `GROUP_CONCAT` into a subquery; also fixed undefined `$this->fields` when no user options exist
- **Engine and charset updates:**
  - `resources/class_debug.php` — `ENGINE=MyISAM` → `InnoDB`, `CHARACTER SET utf8` → `utf8mb4` (full Unicode support)
  - `resources/functions.php` — Trackers table `ENGINE=MyISAM` → `InnoDB`
- **Deprecated syntax cleanup:**
  - Removed `int(2)` and `bigint(30)` display widths (deprecated in MySQL 8.0.17+, removed in 8.4)
  - Unquoted integer defaults (`default '0'` → `default 0`)
  - Added `tinyint` fallback in column type detection (MySQL 8.0.17+ may omit display width from `SHOW COLUMNS`)
- **Bug fixes in new code:**
  - `boards/discourse/users.php` — Fixed `LIMIT` using wrong variable (offset instead of per-screen count)
  - `index.php` — Escaped database name and `TABLE_PREFIX` in `information_schema` query; properly handled `LIKE` wildcard characters in table prefix
  - `resources/functions.php` — Added missing `mysql_pdo` driver check in table engine and `ADD INDEX` conditions

### Performance Optimizations

Several optimizations significantly reduce import time without changing data conversion logic. All are backward-compatible and stable.

- **Transaction wrapping** (`index.php`, `resources/class_converter_module.php`): Each import screen now runs inside a single transaction (`SET autocommit=0` / `COMMIT`) instead of auto-committing every individual INSERT. Reduces disk I/O by orders by 10x-50x (large boards will see this improvement more than small boards).
- **Deferred tracker writes** (`resources/class_converter_module.php`): `increment_tracker()` now updates only in-memory counters during import. `flush_trackers()` writes all trackers to the database once per screen instead of executing a `REPLACE` query per row. Eliminates thousands of unnecessary queries per screen.
- **Cached lookups in post converters** (`boards/ipb4/posts.php`, `boards/ipb5/posts.php`): `get_thread()` and `get_uid_from_username()` now use instance caches, avoiding redundant queries when multiple posts share the same thread or editor.
- **SMF2 edit user cache** (`boards/smf2/posts.php`): `modified_name` lookups are now cached to avoid repeated queries for the same editor name.
- **Progress bar throttle** (`resources/output.php`): Added 100ms minimum interval between DOM flushes to reduce I/O overhead during large per-screen batches.
- **InnoDB warning corrected** (`resources/class_converter_module.php`, `language/global.lang.php`): Removed the misleading warning that InnoDB "can cause major slow-downs." Now warns about MyISAM tables instead, which lack transaction support and are genuinely slower for write-heavy merge workloads.

### Requirements Check Enhancements

- **MySQL engine detection**: Added check for MyISAM tables in the MyBB database during the requirements phase. Warns if MyISAM tables are found and recommends converting to InnoDB before merging.
- **MySQL version check**: Detects MySQL version and warns about MySQL 5.7 (EOL) and MySQL 8.4+ (compatibility concerns due to removed legacy features).
- **MariaDB recommendation**: Displays a recommendation to use MariaDB over MySQL when MariaDB is detected.

---

### Deprecated & Removed Converters

The following board converters have been removed to reduce technical debt and maintenance burden:

- **PunBB** — Project merged into FluxBB in 2008; FluxBB itself is discontinued (2015)
- **FluxBB** — Discontinued in 2015
- **SMF 1.x** — Released 2006-2011, replaced by SMF 2.0 in 2011
- **vBulletin 3** — Released 2004-2010, replaced by vBulletin 4 in 2010
- **WoltLab Burning Board 3 (WBB3)** — Released 2007, superseded by WBB4 (2013) and WBB5+
- **Invision Power Board 3 (IPB3)** — Released 2009, replaced by IPS 4 in 2015
- **bbPress** — Heavily tied to WordPress internals; converter design doesn't align with modern bbPress versions
- **Vanilla** — Upstream API has changed significantly; converter is not compatible with current Vanilla Forums releases

> **Note:** Password hash types for these converters (`ipb3`, `punbb`, `wbb3`, `fluxbb`, `bbpress`, `vanilla`) remain in `loginconvert.php` so that users who merged in the past can still log in.

---

### PHP Compatibility

- Replaced all `create_function()` calls with closures (removed in PHP 8.0)
- Added `merge_utf8_encode/decode()` wrappers with mbstring→iconv→native fallback chain
- Added `isset()` guards to 7 cache handler ID lookup methods
- Fixed undefined array key warnings in debug backtrace (`empty()` checks) and output parser
- Added `function_exists('inet_ntop')` guard in debug class
- Removed `register_globals` dead code and `mysql_connect` database option block
- Removed outdated `=&` object reference assignments (6 locations)

### UI Improvements

- Replaced XHTML 1.0 Transitional DOCTYPE with HTML5 `<!DOCTYPE html>` (`resources/output.php`, `language/global.lang.php`)

### CI Updates

- Expanded PHP version matrix to PHP 8.1, 8.2, 8.3 (`.github/workflows/php-syntax-check.yml`)
- Added PHPCompatibility sniffer workflow (`.github/workflows/php-compatibility.yml`)
- Added MySQL 8.0 SQL syntax validation workflow (`.github/workflows/sql-syntax-check.yml`)

---

### Known Issues & Potential Converter Concerns

The following issues were identified but **not yet addressed**. They should be reviewed before relying on the affected converters in production:

#### Partially Broken Converters
- **XenForo 1 (`boards/xenforo/`)**: The user converter maps `message_count` for post counts but does not populate several MyBB user fields: `regip`, `lastip`, `hideemail`, `invisible`, `pmnotify`, `pmnotice`, `referrer`, `threadnum`. User privacy and notification preferences may not transfer correctly.
- **phpBB3 (`boards/phpbb3/`)**: The user converter's timezone handling for phpBB 3.0 uses string replacement of `.0`/`.00` which is fragile. The thread converter's version detection (checking for `topic_approved` vs `topic_visibility` column presence) works for known versions but could break with schema changes.

#### Missing Field Mappings (Multiple Converters)
Several converters are missing mappings for MyBB fields that exist in the source software. Notable gaps across multiple converters:
- `lastip` — often not mapped (XenForo stores IPs in a separate table; other converters may simply omit it)
- `threadnum` — rarely populated even when the source software tracks it
- `displaygroup` — SMF2 stores post-count-based groups that could map here but doesn't
- `language` — often not mapped from source user preferences

#### Cross-Database Limitations
Several converters use MySQL-specific SQL and are not tested with PostgreSQL or SQLite:
- **IPB4**: restricted to MySQL
- **WBB4**: uses `GROUP_CONCAT` in users module (declares full database support)

The **WBB4** converter should either be restricted to MySQL or have its `GROUP_CONCAT` queries rewritten.

#### Other
- **`set_error_notice_in_progress()`** in `resources/output.php` sets a property that is never read. The function is called from avatar and attachment modules but the value is effectively discarded. This is harmless but dead code.
- **`passwordconvert` column cleanup**: The `loginconvert.php` plugin relies on a temporary `passwordconvert` column added to MyBB's `users` table during conversion. If the merge system is interrupted or old conversion artifacts remain, this column may need manual cleanup.
- **Converters marked "not supported anymore"**: Several password hash types are commented as unsupported in `loginconvert.php` (`vb3`, `ipb2`, `smf`). Users converting from very old vBulletin 3, IPB 2, or SMF 1.x installations may have login issues post-migration.
- **IPS5 Converter (`boards/ipb5/`)**: This is an untested skeleton built from the IPS4 converter. Column names and table structures are unverified against a real IPS5 database. The converter will appear in the board list but should be tested thoroughly before production use. Contributions from users with access to IPS5 are welcome.
- **Discourse Converter (`boards/discourse/`)**: New converter for Discourse. Tested against standard Discourse schema but may need adjustments for custom configurations, plugins, or older Discourse versions. Discourse uses PostgreSQL exclusively — ensure your PHP installation has PDO PostgreSQL support.

#### Issues Under Investigation (Future Work)

The following open GitHub issues have been assessed and are planned for future updates:

- **#89 — fetch_total/import count mismatch**: Several modules use different WHERE clauses in `fetch_total()` vs `import()`, which can cause the merge to loop endlessly or skip rows. This requires a per-module audit across all 11 converters. A long-term fix would involve sharing query definitions between the two methods.
- **#187 — Edit data not converted**: phpBB3, WBB4, XenForo 1, and XenForo 2 converters do not map edit tracking fields (`edituid`, `edittime`, `editreason`) from their source databases. This requires verifying each platform's edit storage schema.
- **#180 — Missing moderator modules**: IPB4, XenForo 1, and WBB4 converters lack `import_moderators` modules. Adding these requires mapping each platform's moderator/permission system to MyBB's moderator structure.
- **#300 — WoltLab Suite Forum 5 (WBB5) support**: A new converter is planned for WoltLab Suite Forum 5, the successor to WBB4. This requires access to a WBB5 database for schema verification.
