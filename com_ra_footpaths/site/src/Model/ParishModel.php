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

/** One parish: its boundary, its paths with their latest inspection, and its inspectors. */
class ParishModel extends BaseDatabaseModel
{
	public function getParish(int $id): ?object
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['pa.id', 'pa.name', 'ar.name AS area', 'ST_AsGeoJSON(pa.boundary) AS boundary'])
			->from($db->quoteName('#__ra_footpaths_parishes', 'pa'))
			->join('LEFT', $db->quoteName('#__ra_footpaths_areas', 'ar'), 'ar.id = pa.area_id')
			->where('pa.id = :id')
			->where('pa.state = 1')
			->bind(':id', $id, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/** @return array<int,object> */
	public function getPaths(int $parishId): array
	{
		$db = $this->getDatabase();
		$latest = LatestInspection::subquery($db, 'p.parish_id = ' . (int) $parishId);

		$sql = 'SELECT p.id, p.type, p.number, p.length_m, p.start_gridref, p.end_gridref,'
			. ' ST_AsGeoJSON(p.route) AS route,'
			. ' lp.inspection_id, lp.inspection_date, lp.grade, lp.remarks, ins.name AS inspector,'
			. ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__ra_footpaths_faults', 'f') . ' WHERE f.inspection_id = lp.inspection_id) AS faults,'
			// Photos from every inspection of the path, not just the latest.
			. ' (SELECT COUNT(*) FROM ' . $db->quoteName('#__ra_footpaths_fault_photos', 'ph')
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_faults', 'f2') . ' ON f2.id = ph.fault_id'
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_inspections', 'i2') . ' ON i2.id = f2.inspection_id'
			. ' WHERE i2.path_id = p.id) AS photos'
			. ' FROM (' . $latest . ') AS lp'
			. ' JOIN ' . $db->quoteName('#__ra_footpaths_paths', 'p') . ' ON p.id = lp.path_id'
			. ' LEFT JOIN ' . $db->quoteName('#__ra_footpaths_inspectors', 'ins') . ' ON ins.id = lp.inspector_id'
			. ' ORDER BY p.sort_key, p.number';

		return $db->setQuery($sql)->loadObjectList();
	}

	/** @return string[] names of the inspectors who look after this parish */
	public function getInspectors(int $parishId): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('i.name')
			->from($db->quoteName('#__ra_footpaths_inspector_parishes', 'ip'))
			->join('INNER', $db->quoteName('#__ra_footpaths_inspectors', 'i'), 'i.id = ip.inspector_id')
			->where('ip.parish_id = :id')
			->where('i.active = 1')
			->order('i.name')
			->bind(':id', $parishId, ParameterType::INTEGER);

		return $db->setQuery($query)->loadColumn();
	}
}
