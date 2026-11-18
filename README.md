# RA Footpaths (com_ra_footpaths)

Joomla 5 component for footpath inspections, replacing East Cheshire Ramblers' old Footpath Inspections Database
(plain PHP, 2002 to 2026). Plan and decisions: `~/personal-projects/briefs/ra-footpaths/brief.md` (local only).

## Stage 1: read-only demo (version 0.1.0)

- **Parishes** (menu item type "Footpath parishes"): a Map / List switch (remembered in the browser). Map: every
  parish boundary, named and shaded by the share of its paths inspected in the last 12 months; click one for its
  figures and a link. List: path count, inspected in the last 12 months, never inspected, latest grades and last
  inspection date, with a name filter.
- **Parish**: Ordnance Survey map (opens on Explorer/Landranger) with the parish boundary and each path coloured by
  its latest grade, and the list of paths with grade, date, inspector, fault count and remarks. Filters: name,
  grade, "not inspected for 12 months".
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

Kept in the folder set in Options (default `<site>/private/footpath-photos`, with a `.htaccess` that refuses direct
access) and served only through `task=photo.show`, after the access check. Thumbnails (480 px wide, turned upright,
no EXIF) are made on first request in `thumbs/`. The originals still carry their EXIF data, including GPS on some:
strip or re-encode them before go-live.

### Maps

Drawn with the Ramblers library map (`RLeafletMap`, command `ra.display.footpaths` in `media/js/footpaths-map.js`), so
they get the same layers as walk maps and the club OS key that RA Tweaks swaps in.

## Importing the old database

1. Load an SQL export of the old database into its own schema on the same database server (never connect to the live
   host). The export needs `SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"` so inspector 0 ("Unknown") keeps its id.
2. Copy the old `footpaths/database/images` folder somewhere the web server's PHP can read.
3. Dry run, then the real thing:

   ```
   LEGACY_DB_USER=... LEGACY_DB_PASS=... php tools/import-legacy.php --site=/path/to/joomla --legacy-db=fp_legacy --photos=/path/to/images --dry-run
   LEGACY_DB_USER=... LEGACY_DB_PASS=... php tools/import-legacy.php --site=/path/to/joomla --legacy-db=fp_legacy --photos=/path/to/images --replace
   ```

4. Drop the legacy schema and delete the copied images folder afterwards: they hold contact details and GPS.

Inspectors come across with name and active flag only (no email, phone or membership number).

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

- `tools/devsync.sh [site root]` copies the component into a local site without the installer (default: the ECR dev copy).
  Use the zip and the installer when the manifest or SQL changes: `cd com_ra_footpaths && zip -r ../com_ra_footpaths.zip .`
- `tools/dev-setup.php <parent menu item id> <user ids>` (dev only): creates the "Footpath inspectors" group and
  access level, adds the users, sets the component option and adds an "Inspection Records" menu item.
