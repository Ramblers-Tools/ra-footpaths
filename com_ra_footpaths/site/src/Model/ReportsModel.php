<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/**
 * The old system's reports ("Choose a report"), for a period, some parishes and some fault types.
 *
 * Path reports: each active path's latest inspection in the period ("this inspection") and the inspection before it,
 * at any date ("previous"). Grades are the inspection's grade, which is already raised to its worst fault.
 */
class ReportsModel extends BaseDatabaseModel
{
	/** key => [title, explanation] */
	public const TYPES = [
		'all' => ['All paths', 'Every path inspected in the period, with its latest inspection.'],
		'c' => ['Class C paths', 'Paths whose latest inspection in the period was graded C.'],
		'd' => ['Class D paths', 'Paths whose latest inspection in the period was graded D.'],
		'newd' => ['New class D paths', 'Graded D in the period, but not D at the inspection before.'],
		'improved' => ['Paths that have improved', 'Latest grade in the period better than the inspection before.'],
		'worse' => ['Paths that have deteriorated', 'Latest grade in the period worse than the inspection before.'],
		'uninspected' => ['Uninspected paths', 'Paths with no inspection in the period, with their last inspection before it.'],
		'summary' => ['Classification summary', 'For each parish: paths inspected in the period and their latest grades.'],
		'faults' => ['Fault type summary', 'Faults graded B, C or D found in the period, by fault type.'],
	];

	/** Reports that list paths (the others are summaries). */
	public const PATH_TYPES = ['all', 'c', 'd', 'newd', 'improved', 'worse', 'uninspected'];

	/** @return array<int,object> active parishes: id, name */
	public function getParishes(): array
	{
		$db = $this->getDatabase();

		return $db->setQuery($db->getQuery(true)->select(['id', 'name'])->from($db->quoteName('#__ra_footpaths_parishes'))
			->where('state = 1')->order('name'))->loadObjectList('id');
	}

	/** @return array<int,object> fault types in use: id, name */
	public function getFaultTypes(): array
	{
		$db = $this->getDatabase();

		return $db->setQuery($db->getQuery(true)->select(['id', 'name'])->from($db->quoteName('#__ra_footpaths_fault_types'))
			->where('state = 1')->order('name'))->loadObjectList('id');
	}

	/**
	 * Filters from the request, checked. Dates are Y-m-d; ids only those that exist.
	 *
	 * @return array{type:string,from:string,to:string,parishes:int[],faulttypes:int[],photos:bool,run:bool}
	 */
	public function filters(\Joomla\Input\Input $input): array
	{
		$date = static function (string $v, string $default): string {
			$d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);

			return $d && $d->format('Y-m-d') === $v ? $v : $default;
		};
		$today = (new \DateTimeImmutable('today'))->format('Y-m-d');
		$from = $date($input->getString('from', ''), (new \DateTimeImmutable('today'))->modify('-12 months +1 day')->format('Y-m-d'));
		$to = $date($input->getString('to', ''), $today);

		if ($from > $to) {
			[$from, $to] = [$to, $from];
		}

		$type = $input->getCmd('type', '');
		$ids = static fn (array $v, array $known): array => array_values(array_intersect(array_unique(array_map('intval', $v)), array_keys($known)));

		return [
			'type' => isset(self::TYPES[$type]) ? $type : '',
			'from' => $from,
			'to' => $to,
			'parishes' => $ids((array) $input->get('parishes', [], 'array'), $this->getParishes()),
			'faulttypes' => $ids((array) $input->get('faulttypes', [], 'array'), $this->getFaultTypes()),
			'photos' => (bool) $input->getInt('photos', 0),
			'run' => isset(self::TYPES[$type]),
		];
	}

	/**
	 * A path report: parish name => list of paths, each with ->current, ->previous (objects or null) and ->faults.
	 *
	 * @return array<string,array<int,object>>
	 */
	public function getPathReport(array $f): array
	{
		$db = $this->getDatabase();
		$uninspected = $f['type'] === 'uninspected';
		$i = $db->quoteName('#__ra_footpaths_inspections');
		$from = $db->quote($f['from']);
		$to = $db->quote($f['to']);
		$latest = static fn (string $alias, string $where): string => '(SELECT ' . $alias . '.id FROM ' . $i . ' AS ' . $alias
			. ' WHERE ' . $alias . '.path_id = p.id AND ' . $where . ' ORDER BY ' . $alias . '.inspection_date DESC, ' . $alias . '.id DESC LIMIT 1)';

		// Uninspected: no inspection in the period; "current" is then the last one before it.
		$sql = 'SELECT p.id, p.type, p.number, pa.name AS parish,'
			. ' ' . $latest('i1', 'i1.inspection_date BETWEEN ' . $from . ' AND ' . $to) . ' AS in_period,'
			. ' ' . $latest('i2', 'i2.inspection_date < ' . $from) . ' AS before_period'
			. ' FROM ' . $db->quoteName('#__ra_footpaths_paths', 'p')
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_parishes', 'pa') . ' ON pa.id = p.parish_id AND pa.state = 1'
			. ' WHERE p.state = 1' . $this->parishWhere($f, 'p.parish_id')
			. ' ORDER BY pa.name, p.sort_key, p.number';
		$paths = $db->setQuery($sql)->loadObjectList();

		$paths = array_values(array_filter($paths, static fn ($p) => $uninspected ? $p->in_period === null : $p->in_period !== null));

		foreach ($paths as $p) {
			$p->current_id = (int) ($uninspected ? $p->before_period : $p->in_period) ?: null;
		}

		$current = $this->inspections(array_filter(array_column($paths, 'current_id')));
		$previous = $uninspected ? [] : $this->previous($current);
		$faults = $this->faults(array_keys($current), $uninspected ? [] : $f['faulttypes'], $f['photos']);
		$out = [];

		foreach ($paths as $p) {
			$p->name = FootpathsHelper::pathName((string) $p->type, (string) $p->number);
			$p->current = $p->current_id ? $current[$p->current_id] : null;
			$p->previous = $p->current ? ($previous[$p->current->id] ?? null) : null;
			$p->faults = $p->current ? ($faults[$p->current->id] ?? []) : [];

			if (!$this->keep($f, $p)) {
				continue;
			}

			$out[$p->parish][] = $p;
		}

		return $out;
	}

	/**
	 * Classification summary: one row per parish (and a total): paths, inspected, A to D.
	 *
	 * @return array<int,object>
	 */
	public function getSummary(array $f): array
	{
		$db = $this->getDatabase();
		$latest = '(SELECT i1.grade FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'i1')
			. ' WHERE i1.path_id = p.id AND i1.inspection_date BETWEEN ' . $db->quote($f['from']) . ' AND ' . $db->quote($f['to'])
			. ' ORDER BY i1.inspection_date DESC, i1.id DESC LIMIT 1)';
		$sql = 'SELECT pa.name AS parish, COUNT(*) AS paths, COUNT(g) AS inspected,'
			. " SUM(g = 'A') AS a, SUM(g = 'B') AS b, SUM(g = 'C') AS c, SUM(g = 'D') AS d"
			. ' FROM (SELECT p.parish_id, ' . $latest . ' AS g FROM ' . $db->quoteName('#__ra_footpaths_paths', 'p')
			. ' WHERE p.state = 1' . $this->parishWhere($f, 'p.parish_id') . ') AS t'
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_parishes', 'pa') . ' ON pa.id = t.parish_id AND pa.state = 1'
			. ' GROUP BY pa.id, pa.name ORDER BY pa.name';
		$rows = $db->setQuery($sql)->loadObjectList();
		$total = (object) ['parish' => 'Total', 'paths' => 0, 'inspected' => 0, 'a' => 0, 'b' => 0, 'c' => 0, 'd' => 0, 'total' => true];

		foreach ($rows as $row) {
			foreach (['paths', 'inspected', 'a', 'b', 'c', 'd'] as $k) {
				$row->$k = (int) $row->$k;
				$total->$k += $row->$k;
			}
		}

		if (\count($rows) > 1) {
			$rows[] = $total;
		}

		return $rows;
	}

	/**
	 * Fault type summary: faults graded B, C or D on inspections in the period (every inspection, not only the latest).
	 *
	 * @return array<int,object> type, b, c, d, total; most first
	 */
	public function getFaultSummary(array $f): array
	{
		$db = $this->getDatabase();
		$sql = 'SELECT COALESCE(ft.name, ' . $db->quote('(no type)') . ') AS type,'
			. " SUM(t.g = 'B') AS b, SUM(t.g = 'C') AS c, SUM(t.g = 'D') AS d, COUNT(*) AS total"
			. ' FROM (SELECT f.fault_type_id, COALESCE(f.grade, i.grade) AS g'
			. ' FROM ' . $db->quoteName('#__ra_footpaths_faults', 'f')
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_inspections', 'i') . ' ON i.id = f.inspection_id'
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_paths', 'p') . ' ON p.id = i.path_id'
			. ' WHERE i.inspection_date BETWEEN ' . $db->quote($f['from']) . ' AND ' . $db->quote($f['to'])
			. $this->parishWhere($f, 'p.parish_id')
			. ($f['faulttypes'] ? ' AND f.fault_type_id IN (' . implode(',', array_map('intval', $f['faulttypes'])) . ')' : '')
			. ') AS t'
			. ' LEFT JOIN ' . $db->quoteName('#__ra_footpaths_fault_types', 'ft') . ' ON ft.id = t.fault_type_id'
			. " WHERE t.g IN ('B', 'C', 'D')"
			. ' GROUP BY t.fault_type_id, ft.name ORDER BY total DESC, type';
		$rows = $db->setQuery($sql)->loadObjectList();

		foreach ($rows as $row) {
			foreach (['b', 'c', 'd', 'total'] as $k) {
				$row->$k = (int) $row->$k;
			}
		}

		return $rows;
	}

	/**
	 * Spreadsheet rows (with a header row) for the report.
	 *
	 * @return array<int,array<int,string|int>>
	 */
	public function getCsv(array $f): array
	{
		if ($f['type'] === 'summary') {
			$rows = [['Parish', 'Paths', 'Inspected', 'Inspected %', 'A', 'B', 'C', 'D', 'Not inspected']];

			foreach ($this->getSummary($f) as $r) {
				$rows[] = [$r->parish, $r->paths, $r->inspected, $r->paths ? round(100 * $r->inspected / $r->paths) : 0,
					$r->a, $r->b, $r->c, $r->d, $r->paths - $r->inspected];
			}

			return $rows;
		}

		if ($f['type'] === 'faults') {
			$rows = [['Fault type', 'B', 'C', 'D', 'Total']];

			foreach ($this->getFaultSummary($f) as $r) {
				$rows[] = [$r->type, $r->b, $r->c, $r->d, $r->total];
			}

			return $rows;
		}

		$uninspected = $f['type'] === 'uninspected';
		$rows = [$uninspected
			? ['Parish', 'Path', 'Last inspected', 'Grade', 'Inspector', 'Remarks', 'Faults']
			: ['Parish', 'Path', 'Previous grade', 'Previous date', 'Grade', 'Date', 'Inspector', 'Remarks', 'Faults']];

		foreach ($this->getPathReport($f) as $parish => $paths) {
			foreach ($paths as $p) {
				$c = $p->current;
				$faults = implode("\n", array_map([self::class, 'faultText'], $p->faults));
				$rows[] = $uninspected
					? [$parish, $p->name, $c->inspection_date ?? 'Never', $c->grade ?? '', $c->inspector ?? '', trim((string) ($c->remarks ?? '')), $faults]
					: [$parish, $p->name, $p->previous->grade ?? '', $p->previous->inspection_date ?? '', $c->grade, (string) $c->inspection_date,
						(string) $c->inspector, trim((string) $c->remarks), $faults];
			}
		}

		return $rows;
	}

	/** "Stile [C] (SJ 900 800) broken. 3 Jan 2026 Jo Bloggs: reported" as one line per fault, for the spreadsheet. */
	public static function faultText(object $fault): string
	{
		$text = ucfirst((string) ($fault->type ?: 'Fault')) . ' [' . $fault->grade . ']'
			. ($fault->gridref ? ' (' . $fault->gridref . ')' : '')
			. (trim((string) $fault->remarks) !== '' ? ' ' . trim((string) $fault->remarks) : '');

		foreach ($fault->events as $e) {
			$text .= '; ' . $e->event_date . ' ' . ($e->reporter ?: '') . ': ' . trim((string) $e->notes);
		}

		return preg_replace('/\s+/', ' ', $text);
	}

	private function keep(array $f, object $p): bool
	{
		$cur = $p->current->grade ?? null;
		$prev = $p->previous->grade ?? null;

		// A fault type filter keeps only paths whose inspection found one of those faults.
		if ($f['faulttypes'] && $f['type'] !== 'uninspected' && !$p->faults) {
			return false;
		}

		return match ($f['type']) {
			'c' => $cur === 'C',
			'd' => $cur === 'D',
			'newd' => $cur === 'D' && $prev !== 'D',
			'improved' => $prev !== null && $cur < $prev,
			'worse' => $prev !== null && $cur > $prev,
			default => true,
		};
	}

	private function parishWhere(array $f, string $column): string
	{
		return $f['parishes'] ? ' AND ' . $column . ' IN (' . implode(',', array_map('intval', $f['parishes'])) . ')' : '';
	}

	/** @return array<int,object> id => inspection with inspector name */
	private function inspections(array $ids): array
	{
		if (!$ids) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['i.id', 'i.path_id', 'i.inspection_date', 'i.grade', 'i.remarks', 'ins.name AS inspector'])
			->from($db->quoteName('#__ra_footpaths_inspections', 'i'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_inspectors', 'ins'), 'ins.id = i.inspector_id')
			->whereIn('i.id', array_values($ids));

		return $db->setQuery($query)->loadObjectList('id');
	}

	/**
	 * The inspection before each of these (same path, earlier date, or same date entered earlier).
	 *
	 * @param  array<int,object>  $current
	 *
	 * @return array<int,object> current inspection id => previous inspection
	 */
	private function previous(array $current): array
	{
		if (!$current) {
			return [];
		}

		$db = $this->getDatabase();
		$i = $db->quoteName('#__ra_footpaths_inspections');
		$sql = 'SELECT c.id AS current_id, pv.id, pv.inspection_date, pv.grade FROM ' . $i . ' AS c'
			. ' JOIN ' . $i . ' AS pv ON pv.id = (SELECT x.id FROM ' . $i . ' AS x WHERE x.path_id = c.path_id'
			. ' AND (x.inspection_date < c.inspection_date OR (x.inspection_date = c.inspection_date AND x.id < c.id))'
			. ' ORDER BY x.inspection_date DESC, x.id DESC LIMIT 1)'
			. ' WHERE c.id IN (' . implode(',', array_map('intval', array_keys($current))) . ')';

		return $db->setQuery($sql)->loadObjectList('current_id');
	}

	/**
	 * Faults of these inspections, each with ->events and ->photos (ids, only if asked for).
	 *
	 * @return array<int,array<int,object>> inspection id => faults
	 */
	private function faults(array $inspectionIds, array $types, bool $photos): array
	{
		if (!$inspectionIds) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['f.id', 'f.inspection_id', 'COALESCE(f.grade, i.grade) AS grade', 'ft.name AS type', 'f.gridref', 'f.remarks'])
			->from($db->quoteName('#__ra_footpaths_faults', 'f'))
			->join('INNER', $db->quoteName('#__ra_footpaths_inspections', 'i'), 'i.id = f.inspection_id')
			->join('LEFT', $db->quoteName('#__ra_footpaths_fault_types', 'ft'), 'ft.id = f.fault_type_id')
			->whereIn('f.inspection_id', array_values($inspectionIds))
			->order('f.id');

		if ($types) {
			$query->whereIn('f.fault_type_id', $types);
		}

		$faults = $db->setQuery($query)->loadObjectList('id');

		if (!$faults) {
			return [];
		}

		foreach ($faults as $fault) {
			$fault->events = [];
			$fault->photos = [];
		}

		$query = $db->getQuery(true)
			->select(['e.fault_id', 'e.event_date', 'e.notes', 'COALESCE(r.name, u.name) AS reporter'])
			->from($db->quoteName('#__ra_footpaths_fault_events', 'e'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_inspectors', 'r'), 'r.id = e.reporter_id')
			->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = e.created_by')
			->whereIn('e.fault_id', array_keys($faults))
			->order(['e.event_date', 'e.id']);

		foreach ($db->setQuery($query)->loadObjectList() as $event) {
			$faults[$event->fault_id]->events[] = $event;
		}

		if ($photos) {
			$query = $db->getQuery(true)->select(['id', 'fault_id'])->from($db->quoteName('#__ra_footpaths_fault_photos'))
				->whereIn('fault_id', array_keys($faults))->order('id');

			foreach ($db->setQuery($query)->loadObjectList() as $photo) {
				$faults[$photo->fault_id]->photos[] = (int) $photo->id;
			}
		}

		$out = [];

		foreach ($faults as $fault) {
			$out[$fault->inspection_id][] = $fault;
		}

		return $out;
	}
}
