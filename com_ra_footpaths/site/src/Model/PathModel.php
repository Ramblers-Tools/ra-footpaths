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

/** One path with its whole inspection history: faults, photos and follow-up notes. */
class PathModel extends BaseDatabaseModel
{
	public function getPath(int $id): ?object
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				'p.id', 'p.type', 'p.number', 'p.length_m', 'p.start_gridref', 'p.end_gridref', 'p.ha_code',
				'ST_AsGeoJSON(p.route) AS route', 'p.parish_id', 'pa.name AS parish',
			])
			->from($db->quoteName('#__ra_footpaths_paths', 'p'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_parishes', 'pa'), 'pa.id = p.parish_id')
			->where('p.id = :id')
			->where('p.state = 1')
			->bind(':id', $id, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Inspections newest first, each with ->faults; each fault with ->photos and ->events.
	 *
	 * @return array<int,object>
	 */
	public function getInspections(int $pathId): array
	{
		$db = $this->getDatabase();

		$query = $db->getQuery(true)
			->select(['i.id', 'i.inspection_date', 'i.grade', 'i.remarks', 'i.inspector_id', 'ins.name AS inspector'])
			->from($db->quoteName('#__ra_footpaths_inspections', 'i'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_inspectors', 'ins'), 'ins.id = i.inspector_id')
			->where('i.path_id = :id')
			->order(['i.inspection_date DESC', 'i.id DESC'])
			->bind(':id', $pathId, ParameterType::INTEGER);
		$inspections = $db->setQuery($query)->loadObjectList('id');

		foreach ($inspections as $inspection) {
			$inspection->faults = [];
		}

		if (!$inspections) {
			return [];
		}

		$ids = array_map('intval', array_keys($inspections));
		$query = $db->getQuery(true)
			->select(['f.id', 'f.inspection_id', 'f.grade', 'f.remarks', 'f.gridref', 'f.lat', 'f.lng', 'ft.name AS type'])
			->from($db->quoteName('#__ra_footpaths_faults', 'f'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_fault_types', 'ft'), 'ft.id = f.fault_type_id')
			->whereIn('f.inspection_id', $ids)
			->order('f.id');
		$faults = $db->setQuery($query)->loadObjectList('id');

		foreach ($faults as $fault) {
			$fault->photos = [];
			$fault->events = [];
		}

		if ($faults) {
			$faultIds = array_map('intval', array_keys($faults));

			$query = $db->getQuery(true)
				->select(['id', 'fault_id', 'width', 'height'])
				->from($db->quoteName('#__ra_footpaths_fault_photos'))
				->whereIn('fault_id', $faultIds)
				->order('id');

			foreach ($db->setQuery($query)->loadObjectList() as $photo) {
				$faults[$photo->fault_id]->photos[] = $photo;
			}

			$query = $db->getQuery(true)
				->select(['e.id', 'e.fault_id', 'e.event_date', 'e.notes', 'e.reporter_id', 'e.created_by', 'COALESCE(r.name, u.name) AS reporter'])
				->from($db->quoteName('#__ra_footpaths_fault_events', 'e'))
				->join('LEFT', $db->quoteName('#__ra_footpaths_inspectors', 'r'), 'r.id = e.reporter_id')
				->join('LEFT', $db->quoteName('#__users', 'u'), 'u.id = e.created_by')
				->whereIn('e.fault_id', $faultIds)
				->order(['e.event_date', 'e.id']);

			foreach ($db->setQuery($query)->loadObjectList() as $event) {
				$faults[$event->fault_id]->events[] = $event;
			}

			foreach ($faults as $fault) {
				$inspections[$fault->inspection_id]->faults[] = $fault;
			}
		}

		return array_values($inspections);
	}
}
