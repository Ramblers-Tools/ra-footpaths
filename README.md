# RA Footpaths (com_ra_footpaths)

Joomla 5 component for footpath inspections, replacing East Cheshire Ramblers' old Footpath Inspections Database
(plain PHP, 2002 to 2026). Plan and decisions: `~/personal-projects/briefs/ra-footpaths/brief.md` (local only).

## Stage 1: read-only demo (version 0.4.0)

- **Parishes** (menu item type "Footpath parishes"): a Map / List switch (remembered in the browser). Map: every
  parish boundary, named and shaded by the share of its paths inspected in the last 12 months; click one for its
  figures and a link. List: path count, inspected in the last 12 months, never inspected, latest grades and last
  inspection date, with a name filter. An inspector sees "Your parish(es)" at the top (figures and how many paths are
  due), their parishes marked "yours" in the list and outlined on the map, which opens on them.
- **Parish**: Ordnance Survey map (opens on Explorer/Landranger) with the parish boundary and each path coloured by
  its latest grade, and the list of paths with grade, date, inspector, fault count and remarks, and a camera icon with
  the number of photos (from every inspection). Filters: name, grade, "not inspected for 12 months", "with photos".
- **Path**: facts, a map with this path highlighted, its fault positions and the parish's other paths in grey, and
  the full inspection history: faults, photos (thumbnails; click for the original in the ECR template's photo popup, with arrows through all the
  path's photos, or a new tab under other templates) and follow-up notes.
- **Admin**: counts per table, and Options (inspector group, photo folder).

Nothing can be added or changed yet. That is stage 2.

### Who can see it

Members of the user group chosen in Options ("Footpath inspectors group"), and Super Users. The check is made for every
request in the site dispatcher, so a direct URL is refused too (guests are sent to log in, others get a 403). Give the
menu item an access level containing that group so nobody else sees it in the menu.

### Grades

A good, B minor problems, C significant problems, D serious problem, N not inspected. An imported inspection's grade
is what the old system showed: its own grade (A when it had none) raised to the worst grade of its faults. The grade
as entered is kept in `grade_recorded`.

### Photos

Kept in the folder set in Options and served only through `task=photo.show`, after the access check, with
`Cache-Control: private, no-store` (component pages are not cached either). The default, `<site>/private/footpath-photos`
with a `.htaccess` that refuses direct access, is for dev only: `.htaccess` works on Apache alone. **On live, set the
photo folder in Options to one outside the web root.** The importer warns when the folder is inside the site and stops
if it can't write the `.htaccess`.

Thumbnails (480 px wide, turned upright, no EXIF) are made on first request in `thumbs/`, written to a temporary file
and renamed into place. Images over 40 megapixels or 25 MB are refused, and if a thumbnail can't be made the request
gets a 404 rather than the original. The imported originals carry no EXIF or GPS (the old system re-saved them; checked
on all 1,163), but new uploads may, so stage 2 must strip it on upload.

### Maps

Drawn with the Ramblers library map (`RLeafletMap`, command `ra.display.footpaths` in `media/js/footpaths-map.js`), so
they get the same layers as walk maps and the club OS key that RA Tweaks swaps in.

### Endpoints for the Ramblers Tools app (0.4.0)

Read-only JSON for the app, at `index.php?option=com_ra_footpaths&format=json&task=app.<name>`:

| Task | Answer |
|---|---|
| `app.me` | `inspector` (true/false), `inspectors` (`[{id, name}]` linked to the user), `my_parishes` (ids), `server_time` |
| `app.parishes` | `totals`, and every parish with its figures, `mine`, `changed` and `centre` (`[lng, lat]`) |
| `app.boundaries` | every parish outline as a GeoJSON FeatureCollection, simplified to about 10 m. ETag / 304 |
| `app.parish&id=N` | one parish: figures, `changed`, full outline, every path with its latest inspection and whole history (faults with `position` `[lng, lat]`, photo ids, follow-up events). ETag / 304 |
| `app.photo&id=N&size=thumb\|full` | the JPEG (thumb is the default) |

- **Sign-in:** the app's token from com_ra_tools (**4.4.0 or later**, which adds token scopes), sent as
  `Authorization: Bearer <token>` or `X-Ramblers-Token: <token>`. Never the query string. The Joomla session is
  ignored. The token needs the `footpaths` scope. Tokens issued before 4.4.0 have only `bookings`, so the user signs
  in again.
- **Who:** the same rule as the website (inspector group or Super User), checked on every request.
- **Errors:** `401 {"error":"invalid_token"}` (none, unknown, expired, revoked), `403 insufficient_scope`,
  `403 forbidden` (not an inspector; `app.me` answers 200 with `"inspector": false` instead), `404 not_found`,
  `405` for anything but GET, `503 unavailable` if com_ra_tools is too old.
- **Headers:** `Cache-Control: no-store` on everything, no CORS.
- **`changed`** is the latest inspection or photo added to the parish. Stage 2 must also stamp edits to paths,
  boundaries and fault events.
- Only published parishes and paths, and the photos on them, are returned.

Code: `site/src/Controller/AppController.php`, `site/src/Model/AppModel.php`, `site/src/Helper/Geo.php`. The app's
side of the contract is in the app repo's footpaths handover.

## Importing the old database

1. Load an SQL export of the old database into its own schema on the same database server (never connect to the live
   host). The export needs `SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"` so inspector 0 ("Unknown") keeps its id.
2. Copy the old `footpaths/database/images` folder somewhere the web server's PHP can read.
3. Dry run, then the real thing:

   ```
   LEGACY_DB_USER=... LEGACY_DB_PASS=... php tools/import-legacy.php --site=/path/to/joomla --legacy-db=fp_legacy --photos=/path/to/images --dry-run
   LEGACY_DB_USER=... LEGACY_DB_PASS=... php tools/import-legacy.php --site=/path/to/joomla --legacy-db=fp_legacy --photos=/path/to/images --replace
   ```

4. Drop the legacy schema and delete the copied images folder afterwards: the schema holds volunteers' contact details.

The importer copies photos before the database changes are committed, so a failed run can leave the photo folder
holding the new files with the old data. It's a one-off dev tool: rerun it with `--replace` until it succeeds.

Inspectors come across with name and active flag only (no email, phone or membership number).

Website logins are linked to inspectors in `#__ra_footpaths_inspector_users` (one inspector can have several logins,
for example a personal and a club address). There is no screen for this yet (stage 2), so add rows by SQL. The
importer keeps these links across a `--replace` re-import, matching on the old inspector id.

Result on the dev site (18 Nov 2026 export):

| | Old system | Imported |
|---|---|---|
| Parishes | 38 | 38 |
| Paths | 1,311 | 1,311 (1,307 active; 4 have no route line) |
| Inspectors | 111 (incl. "Unknown") | 111 |
| Inspections | 26,428 | 26,375 |
| Faults | 9,110 | 9,057 |
| Photos | 1,163 | 1,163 |
| Fault notes | 74 | 73 |

Not imported: 53 inspections (and their 53 faults, 1 note) belong to paths that no longer exist in the old system.
Path 1671 points at parish 3, which does not exist, so it shows in no parish.

## Dev

- `tools/devsync.sh [site root]` copies the component into a local site without the installer (default: the ECR dev copy),
  and resets Joomla's media version so browsers fetch the new CSS and JS. Use the zip and the installer when the
  manifest or SQL changes (database changes go in `administrator/sql/updates/mysql/<version>.sql`): `cd com_ra_footpaths && zip -r ../com_ra_footpaths.zip .`
- `tools/dev-setup.php <parent menu item id> <user ids>` (dev only): creates the "Footpath inspectors" group and
  access level, adds the users, sets the component option and adds an "Inspection Records" menu item.
