<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\Geo;

/**
 * Data for the Ramblers Tools app (AppController). Every figure is worked out here, with the same
 * rules as the website, so the app never recalculates and the two always agree. Names only for
 * people: no email, phone or membership number anywhere.
 */
class AppModel extends BaseDatabaseModel
{
	/** @var array<int,object>|null parish rows from ParishesModel, keyed by id */
	private ?array $parishRows = null;

	/** The inspector records linked to this login. @return array<int,array{id:int,name:string}> */
	public function linkedInspectors(int $userId): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['i.id', 'i.name'])
			->from($db->quoteName('#__ra_footpaths_inspector_users', 'iu'))
			->join('INNER', $db->quoteName('#__ra_footpaths_inspectors', 'i'), 'i.id = iu.inspector_id')
			->where('iu.user_id = :uid')
			->order('i.name')
			->bind(':uid', $userId, ParameterType::INTEGER);

		return array_map(static fn ($r) => ['id' => (int) $r->id, 'name' => (string) $r->name], $db->setQuery($query)->loadObjectList());
	}

	/** Published parishes those inspectors cover. @return int[] */
	public function myParishIds(int $userId): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('DISTINCT ip.parish_id')
			->from($db->quoteName('#__ra_footpaths_inspector_users', 'iu'))
			->join('INNER', $db->quoteName('#__ra_footpaths_inspector_parishes', 'ip'), 'ip.inspector_id = iu.inspector_id')
			->join('INNER', $db->quoteName('#__ra_footpaths_parishes', 'pa'), 'pa.id = ip.parish_id AND pa.state = 1')
			->where('iu.user_id = :uid')
			->order('ip.parish_id')
			->bind(':uid', $userId, ParameterType::INTEGER);

		return array_map('intval', $db->setQuery($query)->loadColumn());
	}

	/** Every published parish with its figures, plus club totals. */
	public function parishes(int $userId): array
	{
		$mine = array_flip($this->myParishIds($userId));
		$changed = $this->changed();
		$centres = [];

		foreach ($this->boundaryGeometries() as $id => $geometry) {
			$centres[$id] = Geo::centre($geometry);
		}

		$totals = ['parishes' => 0, 'paths' => 0, 'recent' => 0, 'percent' => null, 'due' => 0, 'never' => 0];
		$list = [];

		foreach ($this->parishRows() as $id => $row) {
			$figures = self::figures($row);
			$totals['parishes']++;
			$totals['paths'] += $figures['paths'];
			$totals['recent'] += $figures['recent'];
			$totals['due'] += $figures['due'];
			$totals['never'] += $figures['never'];
			$list[] = ['id' => $id, 'name' => (string) $row->name] + $figures + [
				'mine' => isset($mine[$id]),
				'changed' => $changed[$id] ?? null,
				'centre' => $centres[$id] ?? null,
			];
		}

		$totals['percent'] = $totals['paths'] ? (int) round(100 * $totals['recent'] / $totals['paths']) : null;

		return ['totals' => $totals, 'parishes' => $list];
	}

	/** Every published parish outline, simplified to about 10 m, as a GeoJSON FeatureCollection. */
	public function boundaries(): array
	{
		$rows = $this->parishRows();
		$features = [];

		foreach ($this->boundaryGeometries() as $id => $geometry) {
			if (!isset($rows[$id])) {
				continue;
			}

			$features[] = [
				'type' => 'Feature',
				'id' => $id,
				'properties' => ['name' => (string) $rows[$id]->name],
				'geometry' => Geo::simplify($geometry, 10.0),
			];
		}

		return ['type' => 'FeatureCollection', 'features' => $features];
	}

	/** One published parish with every path's full history (photo ids and sizes only), or null. */
	public function parish(int $id): ?array
	{
		$row = $this->parishRows()[$id] ?? null;

		if ($row === null) {
			return null;
		}

		$parishModel = new ParishModel(['dbo' => $this->getDatabase()]);
		$parish = $parishModel->getParish($id);
		$boundary = $parish && $parish->boundary ? json_decode($parish->boundary, true) : null;
		$rows = $parishModel->getPaths($id);
		$history = $this->history(array_map(static fn ($p) => (int) $p->id, $rows));
		$paths = [];

		foreach ($rows as $p) {
			$latest = null;

			if ($p->inspection_id) {
				$latest = [
					'inspection_id' => (int) $p->inspection_id,
					'date' => $p->inspection_date,
					'grade' => self::grade($p->grade),
					'inspector' => $p->inspector !== null ? (string) $p->inspector : null,
					'faults' => (int) $p->faults,
					'remarks' => self::text($p->remarks),
				];
			}

			$paths[] = [
				'id' => (int) $p->id,
				'name' => FootpathsHelper::pathName((string) $p->type, (string) $p->number),
				'type' => (string) $p->type,
				'number' => (string) $p->number,
				'sort_key' => (int) $p->sort_key,
				'start_gridref' => $p->start_gridref ?: null,
				'end_gridref' => $p->end_gridref ?: null,
				'length_m' => $p->length_m !== null ? (int) $p->length_m : null,
				'route' => $p->route ? self::rounded(json_decode($p->route, true)) : null,
				'latest' => $latest,
				'due' => !self::recent($p->inspection_date),
				'photos' => (int) $p->photos,
				'inspections' => $history[(int) $p->id] ?? [],
			];
		}

		return [
			'id' => $id,
			'name' => (string) $row->name,
			'changed' => $this->changed()[$id] ?? null,
			'figures' => self::figures($row),
			'boundary' => $boundary ? self::rounded($boundary) : null,
			'paths' => $paths,
		];
	}

	// ------------------------------------------------------------------------------------------

	/** @return array<int,object> ParishesModel rows (published parishes) keyed by id */
	private function parishRows(): array
	{
		if ($this->parishRows === null) {
			$this->parishRows = [];

			foreach ((new ParishesModel(['dbo' => $this->getDatabase()]))->getItems() as $row) {
				$this->parishRows[(int) $row->id] = $row;
			}
		}

		return $this->parishRows;
	}

	/** @return array<int,array> published parish outlines (full detail) keyed by id */
	private function boundaryGeometries(): array
	{
		$out = [];

		foreach ((new ParishesModel(['dbo' => $this->getDatabase()]))->getBoundaries() as $id => $json) {
			$geometry = json_decode((string) $json, true);

			if (\is_array($geometry)) {
				$out[(int) $id] = $geometry;
			}
		}

		return $out;
	}

	/** The figures in handover section 4, from a ParishesModel row. */
	private static function figures(object $row): array
	{
		$paths = (int) $row->paths;
		$recent = (int) $row->recent;

		return [
			'paths' => $paths,
			'recent' => $recent,
			'percent' => $paths ? (int) round(100 * $recent / $paths) : null,
			'never' => (int) $row->never,
			'due' => $paths - $recent,
			'grade_c' => (int) $row->grade_c,
			'grade_d' => (int) $row->grade_d,
			'last_date' => $row->last_date ?: null,
		];
	}

	/**
	 * When anything in each parish last changed: the newest inspection or photo added (their
	 * `created` times, UTC; a re-import sets them all). ISO 8601. Paths, boundaries and follow-up
	 * notes have no timestamps yet; stage 2 must add them and include them here.
	 *
	 * @return array<int,string>
	 */
	private function changed(): array
	{
		$db = $this->getDatabase();
		$sql = 'SELECT p.parish_id, MAX(x.created) AS changed FROM ('
			. 'SELECT i.path_id, i.created FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'i')
			. ' UNION ALL SELECT i.path_id, ph.created FROM ' . $db->quoteName('#__ra_footpaths_fault_photos', 'ph')
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_faults', 'f') . ' ON f.id = ph.fault_id'
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_inspections', 'i') . ' ON i.id = f.inspection_id'
			. ') AS x JOIN ' . $db->quoteName('#__ra_footpaths_paths', 'p') . ' ON p.id = x.path_id'
			. ' WHERE x.created IS NOT NULL GROUP BY p.parish_id';
		$out = [];

		foreach ($db->setQuery($sql)->loadObjectList() as $r) {
			$out[(int) $r->parish_id] = gmdate('Y-m-d\TH:i:s', strtotime($r->changed . ' UTC')) . '+00:00';
		}

		return $out;
	}

	/**
	 * Every inspection of these paths, newest first (date, then highest id), with faults, photos and
	 * follow-up notes. @return array<int,array> keyed by path id
	 */
	private function history(array $pathIds): array
	{
		if (!$pathIds) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['i.id', 'i.path_id', 'i.inspection_date', 'i.grade', 'i.remarks', 'ins.name AS inspector'])
			->from($db->quoteName('#__ra_footpaths_inspections', 'i'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_inspectors', 'ins'), 'ins.id = i.inspector_id')
			->whereIn('i.path_id', $pathIds)
			->order(['i.inspection_date DESC', 'i.id DESC']);
		$inspections = [];

		foreach ($db->setQuery($query)->loadObjectList() as $r) {
			$inspections[(int) $r->id] = [
				'path_id' => (int) $r->path_id,
				'id' => (int) $r->id,
				'date' => $r->inspection_date,
				'inspector' => $r->inspector !== null ? (string) $r->inspector : null,
				'grade' => self::grade($r->grade),
				'remarks' => self::text($r->remarks),
				'faults' => [],
			];
		}

		$faults = [];

		if ($inspections) {
			$query = $db->getQuery(true)
				->select(['f.id', 'f.inspection_id', 'f.grade', 'f.remarks', 'f.gridref', 'f.lat', 'f.lng', 'ft.name AS type'])
				->from($db->quoteName('#__ra_footpaths_faults', 'f'))
				->join('LEFT', $db->quoteName('#__ra_footpaths_fault_types', 'ft'), 'ft.id = f.fault_type_id')
				->whereIn('f.inspection_id', array_keys($inspections))
				->order('f.id');

			foreach ($db->setQuery($query)->loadObjectList() as $r) {
				$faults[(int) $r->id] = [
					'inspection_id' => (int) $r->inspection_id,
					'id' => (int) $r->id,
					'type' => $r->type !== null ? ucfirst((string) $r->type) : null,
					'grade' => \in_array($r->grade, ['A', 'B', 'C', 'D'], true) ? $r->grade : null,
					'remarks' => self::text($r->remarks),
					'gridref' => self::text($r->gridref),
					'position' => Geo::position($r->lat, $r->lng),
					'photos' => [],
					'events' => [],
				];
			}
		}

		if ($faults) {
			$ids = array_keys($faults);
			$query = $db->getQuery(true)
				->select(['id', 'fault_id', 'width', 'height'])
				->from($db->quoteName('#__ra_footpaths_fault_photos'))
				->whereIn('fault_id', $ids)
				->order('id');

			foreach ($db->setQuery($query)->loadObjectList() as $r) {
				$faults[(int) $r->fault_id]['photos'][] = [
					'id' => (int) $r->id,
					'width' => $r->width !== null ? (int) $r->width : null,
					'height' => $r->height !== null ? (int) $r->height : null,
				];
			}

			$query = $db->getQuery(true)
				->select(['e.fault_id', 'e.event_date', 'e.notes', 'r.name AS reporter'])
				->from($db->quoteName('#__ra_footpaths_fault_events', 'e'))
				->join('LEFT', $db->quoteName('#__ra_footpaths_inspectors', 'r'), 'r.id = e.reporter_id')
				->whereIn('e.fault_id', $ids)
				->order(['e.event_date', 'e.id']);

			foreach ($db->setQuery($query)->loadObjectList() as $r) {
				$faults[(int) $r->fault_id]['events'][] = [
					'date' => $r->event_date,
					'reporter' => $r->reporter !== null ? (string) $r->reporter : null,
					'notes' => self::text($r->notes),
				];
			}

			foreach ($faults as $fault) {
				$inspectionId = $fault['inspection_id'];
				unset($fault['inspection_id']);
				$inspections[$inspectionId]['faults'][] = $fault;
			}
		}

		$byPath = [];

		foreach ($inspections as $inspection) {
			$pathId = $inspection['path_id'];
			unset($inspection['path_id']);
			$byPath[$pathId][] = $inspection;
		}

		return $byPath;
	}

	/** Inspected within the last 12 months (the same test as ParishesModel's "recent"). */
	private static function recent(?string $date): bool
	{
		return $date !== null && $date !== '' && $date >= (new \DateTimeImmutable('today'))->modify('-12 months')->format('Y-m-d');
	}

	private static function grade(?string $grade): ?string
	{
		$g = FootpathsHelper::normalise($grade);

		return $g === 'N' ? null : $g;
	}

	private static function text(?string $value): ?string
	{
		$value = trim((string) $value);

		return $value === '' ? null : $value;
	}

	/** GeoJSON geometry with coordinates rounded to 5 decimal places. */
	private static function rounded(array $geometry): array
	{
		$round = static function ($c) use (&$round) {
			return isset($c[0]) && !\is_array($c[0]) ? [round((float) $c[0], 5), round((float) $c[1], 5)] : array_map($round, $c);
		};

		return ['type' => $geometry['type'] ?? '', 'coordinates' => $round($geometry['coordinates'] ?? [])];
	}
}
