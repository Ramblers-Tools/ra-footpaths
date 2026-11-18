<?php
/**
 * RA Footpaths: import the old Footpath Inspections Database.
 *
 * Reads a copy of the legacy database (loaded from an SQL export into its own schema, never the live
 * host) and the legacy photo folder, and fills the #__ra_footpaths_* tables. Repeatable: --replace
 * empties the tables first. --dry-run does everything inside a transaction that is rolled back and
 * copies no files. Ends with a check of counts against the source.
 *
 * Usage (on the web server, or in the web container):
 *   php import-legacy.php --site=/var/www/html --legacy-db=fp_legacy --photos=/path/to/database/images [--replace] [--dry-run]
 *
 * Legacy database login: LEGACY_DB_USER / LEGACY_DB_PASS / LEGACY_DB_HOST environment variables,
 * defaulting to the Joomla site's own login (which then needs SELECT on the legacy schema).
 *
 * What is brought across: areas, parishes (with boundaries), paths (with routes), fault types,
 * inspectors (name and active flag only: no email, phone or membership number), parish allocations,
 * inspections, faults, photos and fault follow-up notes. Legacy ids are kept in legacy_id.
 * Coordinates: the old tables store points as "lat lng"; they are swapped to "lng lat" (x = longitude)
 * so ST_AsGeoJSON gives standard GeoJSON.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	exit("Command line only.\n");
}

$opts = getopt('', ['site:', 'legacy-db:', 'photos:', 'replace', 'dry-run', 'help']);

if (isset($opts['help']) || empty($opts['site']) || empty($opts['legacy-db'])) {
	fwrite(STDERR, "Usage: php import-legacy.php --site=<joomla root> --legacy-db=<schema> [--photos=<legacy images folder>] [--replace] [--dry-run]\n");
	exit(1);
}

$site = rtrim($opts['site'], '/');
$legacyDb = $opts['legacy-db'];
$photoSource = isset($opts['photos']) ? rtrim($opts['photos'], '/') : '';
$replace = isset($opts['replace']);
$dryRun = isset($opts['dry-run']);

if (!preg_match('/^[A-Za-z0-9_-]+$/', $legacyDb)) {
	exit("Bad legacy schema name.\n");
}

define('_JEXEC', 1);
require $site . '/configuration.php';
$cfg = new JConfig();
$p = $cfg->dbprefix;

$dsn = 'mysql:host=' . $cfg->host . ';dbname=' . $cfg->db . ';charset=utf8mb4';
$db = new PDO($dsn, $cfg->user, $cfg->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$ldsn = 'mysql:host=' . (getenv('LEGACY_DB_HOST') ?: $cfg->host) . ';dbname=' . $legacyDb . ';charset=utf8mb4';
$legacy = new PDO($ldsn, getenv('LEGACY_DB_USER') ?: $cfg->user, getenv('LEGACY_DB_PASS') !== false ? getenv('LEGACY_DB_PASS') : $cfg->password, [
	PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
// The legacy tables are latin1, but the old app wrote UTF-8 bytes into them ("set names utf8").
$legacy->exec("SET NAMES latin1");

$t = static fn (string $name): string => '`' . $p . 'ra_footpaths_' . $name . '`';
$report = [];
$warn = [];
$say = static function (string $msg): void {
	fwrite(STDOUT, $msg . "\n");
};

/** Legacy text: stored as UTF-8 bytes in latin1 columns; make sure we hand on valid UTF-8. */
$text = static function ($value): ?string {
	if ($value === null) {
		return null;
	}

	$value = (string) $value;

	if (!mb_check_encoding($value, 'UTF-8')) {
		$value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
	}

	return trim($value);
};

/** "lat lng" pairs to "lng lat". */
$swap = static function (?string $wkt): ?string {
	if ($wkt === null || $wkt === '') {
		return null;
	}

	return preg_replace('/(-?\d+(?:\.\d+)?(?:[eE]-?\d+)?) (-?\d+(?:\.\d+)?(?:[eE]-?\d+)?)/', '$2 $1', $wkt);
};

$grade = static function ($g): ?string {
	$g = strtoupper(trim((string) $g));

	return in_array($g, ['A', 'B', 'C', 'D'], true) ? $g : null;
};

/** Insert rows in batches. $rows: list of assoc arrays with the same keys. $raw: columns holding SQL expressions with one ? each. */
$insert = static function (string $table, array $rows, array $raw = []) use ($db): void {
	if (!$rows) {
		return;
	}

	$cols = array_keys($rows[0]);
	$one = '(' . implode(',', array_map(static fn ($c) => $raw[$c] ?? '?', $cols)) . ')';

	foreach (array_chunk($rows, 400) as $chunk) {
		$sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $cols) . '`) VALUES ' . implode(',', array_fill(0, count($chunk), $one));
		$values = [];

		foreach ($chunk as $row) {
			foreach ($cols as $c) {
				$values[] = $row[$c];
			}
		}

		$db->prepare($sql)->execute($values);
	}
};

$ids = static function (string $table) use ($db, $t): array {
	return $db->query('SELECT legacy_id, id FROM ' . $t($table) . ' WHERE legacy_id IS NOT NULL')->fetchAll(PDO::FETCH_KEY_PAIR);
};

$tables = ['fault_events', 'fault_photos', 'faults', 'inspections', 'fault_types', 'inspector_parishes', 'inspectors', 'paths', 'parishes', 'areas'];

$existing = (int) $db->query('SELECT COUNT(*) FROM ' . $t('inspections'))->fetchColumn();

if ($existing > 0 && !$replace) {
	exit("The RA Footpaths tables already hold data. Run again with --replace to empty them and import afresh.\n");
}

$say(($dryRun ? 'DRY RUN: ' : '') . 'Importing from ' . $legacyDb . ' into ' . $cfg->db . ' (prefix ' . $p . ')');

// TRUNCATE commits on its own, so for a dry run use DELETE inside the transaction instead.
$db->beginTransaction();

try {
	// Website logins linked to inspectors are set on the site, not in the old system: keep them across a re-import.
	$logins = $db->query('SELECT i.legacy_id, u.user_id FROM ' . $t('inspector_users') . ' u JOIN ' . $t('inspectors') . ' i ON i.id = u.inspector_id WHERE i.legacy_id IS NOT NULL')->fetchAll(PDO::FETCH_ASSOC);
	$db->exec('DELETE FROM ' . $t('inspector_users'));

	foreach ($tables as $name) {
		$db->exec('DELETE FROM ' . $t($name));
	}

	$now = gmdate('Y-m-d H:i:s');

	// Areas.
	$rows = [];
	foreach ($legacy->query('SELECT area_number, area_name, active FROM areas ORDER BY area_number') as $r) {
		$rows[] = ['name' => $text($r['area_name']), 'state' => (int) $r['active'] ? 1 : 0, 'legacy_id' => (int) $r['area_number']];
	}
	$insert($t('areas'), $rows);
	$areaIds = $ids('areas');
	$report['areas'] = [count($rows), count($rows)];

	// Parishes.
	$rows = [];
	foreach ($legacy->query('SELECT parish_number, parish_name, area, CE_code, pnfs_code, ST_AsText(parish_boundary) AS wkt FROM parishes ORDER BY parish_number') as $r) {
		$rows[] = [
			'area_id' => $areaIds[(int) $r['area']] ?? null,
			'name' => $text($r['parish_name']),
			'boundary' => $swap($r['wkt']),
			'ce_code' => $r['CE_code'] !== null ? (int) $r['CE_code'] : null,
			'pnfs_code' => $text($r['pnfs_code']),
			'state' => 1,
			'legacy_id' => (int) $r['parish_number'],
		];
	}
	$insert($t('parishes'), $rows, ['boundary' => 'ST_GeomFromText(?)']);
	$parishIds = $ids('parishes');
	$report['parishes'] = [(int) $legacy->query('SELECT COUNT(*) FROM parishes')->fetchColumn(), count($rows)];

	// Paths. Sort key the same way the old system ordered them.
	$sortKey = static function (string $type, string $number): int {
		$types = ['FP' => 0, 'BR' => 1, 'RB' => 2, 'BY' => 3, 'RP' => 4, 'BW' => 5];

		if (!preg_match('/(0\.)*([A-Za-z]?)(\d+)\(*([A-Za-z]*)/', $number, $m)) {
			return 99999999;
		}

		$index = (int) $m[3] * 100;

		if ($m[4] !== '') {
			$index += 10;
			foreach (str_split(strtoupper($m[4])) as $ch) {
				$index += ord($ch) - ord('A');
			}
		}

		if ($m[2] !== '') {
			$index += (ord(strtoupper($m[2])) - ord('A') + 1) * 1000000;
		}

		return $index + ($types[strtoupper($type)] ?? 6);
	};

	$rows = [];
	foreach ($legacy->query('SELECT right_of_way_id, parish, type, number, ST_AsText(path_route) AS wkt, pnfs_code, ha_code, start, end, length, active FROM rights_of_way ORDER BY right_of_way_id') as $r) {
		if (!isset($parishIds[(int) $r['parish']])) {
			$warn[] = 'Path ' . $r['right_of_way_id'] . ' has unknown parish ' . $r['parish'];
		}

		$rows[] = [
			'parish_id' => $parishIds[(int) $r['parish']] ?? null,
			'type' => (string) $text($r['type']),
			'number' => (string) $text($r['number']),
			'sort_key' => $sortKey((string) $r['type'], (string) $r['number']),
			'route' => $swap($r['wkt']),
			'ha_code' => $text($r['ha_code']),
			'pnfs_code' => $text($r['pnfs_code']),
			'start_gridref' => $text($r['start']),
			'end_gridref' => $text($r['end']),
			'length_m' => $r['length'] !== null ? (int) $r['length'] : null,
			'state' => (int) $r['active'] ? 1 : 0,
			'legacy_id' => (int) $r['right_of_way_id'],
		];
	}
	$insert($t('paths'), $rows, ['route' => 'ST_GeomFromText(?)']);
	$pathIds = $ids('paths');
	$report['paths'] = [(int) $legacy->query('SELECT COUNT(*) FROM rights_of_way')->fetchColumn(), count($rows)];
	$noRoute = count(array_filter($rows, static fn ($r) => $r['route'] === null));
	if ($noRoute) {
		$warn[] = $noRoute . ' paths have no route line in the old system';
	}

	// Fault types.
	$rows = [];
	foreach ($legacy->query('SELECT fault_type_id, fault_type_name FROM fault_types ORDER BY fault_type_name') as $r) {
		$rows[] = ['name' => (string) $text($r['fault_type_name']), 'state' => 1, 'legacy_id' => (int) $r['fault_type_id']];
	}
	$insert($t('fault_types'), $rows);
	$typeIds = $ids('fault_types');
	$report['fault types'] = [count($rows), count($rows)];

	// Inspectors: name and active flag only. Contact details stay in the old system / admins' list.
	$rows = [];
	foreach ($legacy->query('SELECT inspector_id, inspector_name, active FROM inspectors ORDER BY inspector_id') as $r) {
		$rows[] = ['name' => (string) $text($r['inspector_name']), 'active' => (int) $r['active'] ? 1 : 0, 'legacy_id' => (int) $r['inspector_id']];
	}
	$insert($t('inspectors'), $rows);
	$inspectorIds = $ids('inspectors');
	$report['inspectors'] = [(int) $legacy->query('SELECT COUNT(*) FROM inspectors')->fetchColumn(), count($rows)];

	$rows = [];
	foreach ($logins as $l) {
		if (isset($inspectorIds[(int) $l['legacy_id']])) {
			$rows[] = ['inspector_id' => $inspectorIds[(int) $l['legacy_id']], 'user_id' => (int) $l['user_id']];
		}
	}
	$insert($t('inspector_users'), $rows);
	$report['website logins kept'] = [count($logins), count($rows)];

	// Parish allocations.
	$rows = [];
	foreach ($legacy->query('SELECT DISTINCT inspector, parish FROM parish_inspectors') as $r) {
		if (isset($inspectorIds[(int) $r['inspector']], $parishIds[(int) $r['parish']])) {
			$rows[] = ['inspector_id' => $inspectorIds[(int) $r['inspector']], 'parish_id' => $parishIds[(int) $r['parish']]];
		}
	}
	$insert($t('inspector_parishes'), $rows);
	$report['parish allocations'] = [(int) $legacy->query('SELECT COUNT(*) FROM (SELECT DISTINCT inspector, parish FROM parish_inspectors) x')->fetchColumn(), count($rows)];

	// Inspections. Grade = what the old system showed: own grade (A if none) raised to the worst fault grade.
	$worst = $legacy->query("SELECT inspection, MAX(classification) FROM inspection_faults WHERE classification IN ('A','B','C','D') GROUP BY inspection")->fetchAll(PDO::FETCH_KEY_PAIR);
	$rows = [];
	$orphans = 0;
	foreach ($legacy->query('SELECT inspection_id, right_of_way_id, inspector_id, inspection_date, classification, remarks FROM inspections ORDER BY inspection_id') as $r) {
		if (!isset($pathIds[(int) $r['right_of_way_id']])) {
			$orphans++;
			continue;
		}

		$own = $grade($r['classification']);
		$shown = $own ?? 'A';
		$faultWorst = $worst[$r['inspection_id']] ?? null;

		if ($faultWorst !== null && $faultWorst > $shown) {
			$shown = $faultWorst;
		}

		$date = $r['inspection_date'];
		$rows[] = [
			'path_id' => $pathIds[(int) $r['right_of_way_id']],
			'inspector_id' => $r['inspector_id'] !== null ? ($inspectorIds[(int) $r['inspector_id']] ?? null) : null,
			'inspection_date' => ($date && $date !== '0000-00-00') ? $date : null,
			'grade' => $shown,
			'grade_recorded' => $own,
			'remarks' => $text($r['remarks']),
			'created' => $now,
			'created_by' => null,
			'legacy_id' => (int) $r['inspection_id'],
		];
	}
	$insert($t('inspections'), $rows);
	$inspectionIds = $ids('inspections');
	$report['inspections'] = [(int) $legacy->query('SELECT COUNT(*) FROM inspections')->fetchColumn(), count($rows)];
	if ($orphans) {
		$warn[] = $orphans . ' inspections skipped: their path no longer exists in the old system';
	}

	// Faults.
	$rows = [];
	$photos = [];
	$orphans = 0;
	foreach ($legacy->query('SELECT inspection_fault_id, inspection, type, classification, remarks, gridref, lat, lng, photo FROM inspection_faults ORDER BY inspection_fault_id') as $r) {
		if (!isset($inspectionIds[(int) $r['inspection']])) {
			$orphans++;
			continue;
		}

		$lat = $r['lat'] !== null ? (float) $r['lat'] : null;
		$lng = $r['lng'] !== null ? (float) $r['lng'] : null;

		if ($lat === 0.0 || $lng === 0.0) {
			$lat = $lng = null;
		}

		$rows[] = [
			'inspection_id' => $inspectionIds[(int) $r['inspection']],
			'fault_type_id' => $typeIds[(int) $r['type']] ?? null,
			'grade' => $grade($r['classification']),
			'remarks' => $text($r['remarks']),
			'gridref' => $text($r['gridref']),
			'lat' => $lat,
			'lng' => $lng,
			'legacy_id' => (int) $r['inspection_fault_id'],
		];

		if (trim((string) $r['photo']) !== '') {
			$photos[(int) $r['inspection_fault_id']] = basename(trim((string) $r['photo']));
		}
	}
	$insert($t('faults'), $rows);
	$faultIds = $ids('faults');
	$report['faults'] = [(int) $legacy->query('SELECT COUNT(*) FROM inspection_faults')->fetchColumn(), count($rows)];
	if ($orphans) {
		$warn[] = $orphans . ' faults skipped: their inspection was skipped';
	}

	// Photos.
	$photoDir = $site . '/private/footpath-photos';
	$params = $db->query("SELECT params FROM `{$p}extensions` WHERE element = 'com_ra_footpaths' AND type = 'component'")->fetchColumn();
	$configured = $params ? trim((string) (json_decode((string) $params, true)['photo_path'] ?? '')) : '';
	if ($configured !== '') {
		$photoDir = rtrim($configured, '/');
	}

	$rows = [];
	$missing = [];
	if ($photoSource !== '') {
		if (!$dryRun && !is_dir($photoDir) && !mkdir($photoDir, 0755, true)) {
			throw new RuntimeException('Cannot create photo folder ' . $photoDir);
		}

		if (!$dryRun && str_starts_with(realpath($photoDir) ?: $photoDir, realpath($site) ?: $site)) {
			// Inside the website folder: refuse direct web access (Apache). Photos go through the component.
			file_put_contents($photoDir . '/.htaccess', "Require all denied\n");
			file_put_contents($photoDir . '/index.html', '');
		}

		foreach ($photos as $legacyFault => $file) {
			$src = $photoSource . '/' . $file;

			if (!isset($faultIds[$legacyFault])) {
				continue;
			}

			if (!is_file($src)) {
				$missing[] = $file;
				continue;
			}

			$size = @getimagesize($src);

			if ($size === false) {
				$missing[] = $file . ' (not an image)';
				continue;
			}

			$name = 'fault-' . $legacyFault . '.jpg';

			if (!$dryRun && !copy($src, $photoDir . '/' . $name)) {
				throw new RuntimeException('Copy failed: ' . $src);
			}

			$rows[] = [
				'fault_id' => $faultIds[$legacyFault],
				'file' => $name,
				'width' => (int) $size[0],
				'height' => (int) $size[1],
				'bytes' => (int) filesize($src),
				'created' => $now,
			];
		}
		$insert($t('fault_photos'), $rows);
	} else {
		$warn[] = 'No --photos folder given: photos not imported';
	}
	$report['photos'] = [count($photos), count($rows)];
	if ($missing) {
		$warn[] = count($missing) . ' photos listed in the old database but missing from the folder: ' . implode(', ', array_slice($missing, 0, 10)) . (count($missing) > 10 ? ', ...' : '');
	}

	// Fault follow-up notes.
	$rows = [];
	foreach ($legacy->query('SELECT event_id, fault, date, reporter, notes FROM fault_events ORDER BY event_id') as $r) {
		if (!isset($faultIds[(int) $r['fault']])) {
			$warn[] = 'Fault note ' . $r['event_id'] . ' skipped: fault ' . $r['fault'] . ' not imported';
			continue;
		}

		$rows[] = [
			'fault_id' => $faultIds[(int) $r['fault']],
			'event_date' => ($r['date'] && $r['date'] !== '0000-00-00') ? $r['date'] : null,
			'reporter_id' => $inspectorIds[(int) $r['reporter']] ?? null,
			'notes' => $text($r['notes']),
			'legacy_id' => (int) $r['event_id'],
		];
	}
	$insert($t('fault_events'), $rows);
	$report['fault notes'] = [(int) $legacy->query('SELECT COUNT(*) FROM fault_events')->fetchColumn(), count($rows)];

	// Checks.
	$noInspector = (int) $db->query('SELECT COUNT(*) FROM ' . $t('inspections') . ' WHERE inspector_id IS NULL')->fetchColumn();
	if ($noInspector) {
		$warn[] = $noInspector . ' inspections have no inspector (none recorded, or inspector not found)';
	}

	if ($dryRun) {
		$db->rollBack();
	} else {
		$db->commit();
	}
} catch (Throwable $e) {
	if ($db->inTransaction()) {
		$db->rollBack();
	}

	fwrite(STDERR, 'Import failed, nothing changed: ' . $e->getMessage() . "\n");
	exit(2);
}

$say('');
$say(str_pad('Table', 22) . str_pad('Old system', 12, ' ', STR_PAD_LEFT) . str_pad('Imported', 12, ' ', STR_PAD_LEFT));
foreach ($report as $name => [$source, $done]) {
	$say(str_pad($name, 22) . str_pad((string) $source, 12, ' ', STR_PAD_LEFT) . str_pad((string) $done, 12, ' ', STR_PAD_LEFT) . ($source !== $done ? '   <- differs, see notes' : ''));
}

if ($warn) {
	$say('');
	$say('Notes:');
	foreach ($warn as $w) {
		$say(' - ' . $w);
	}
}

$say('');
$say($dryRun ? 'Dry run: nothing was saved and no files were copied.' : 'Import saved. Photos in ' . $photoDir);
