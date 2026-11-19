<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Model;

\defined('_JEXEC') or die;

use Joomla\Database\ParameterType;

/**
 * What the "record an inspection" form needs. Saving is InspectionModel.
 */
class ReportModel extends PathModel
{
	/**
	 * Fault types in use, plus any (retired) type the inspection being changed already has.
	 *
	 * @return array<int,object> id, name
	 */
	public function getFaultTypes(?object $editing = null): array
	{
		$db = $this->getDatabase();
		$keep = array_values(array_filter(array_map(static fn ($f) => (int) $f->fault_type_id, $editing->faults ?? [])));
		$query = $db->getQuery(true)
			->select(['id', 'name'])
			->from($db->quoteName('#__ra_footpaths_fault_types'))
			->where($keep ? '(state = 1 OR id IN (' . implode(',', $keep) . '))' : 'state = 1')
			->order('name');

		return $db->setQuery($query)->loadObjectList();
	}

	/**
	 * Inspectors to choose from: active ones (only these ids when given, for an inspector recording in their own
	 * name), plus the current inspector of an inspection being changed even if no longer active.
	 *
	 * @param  int[]|null  $only
	 *
	 * @return array<int,object> id, name
	 */
	public function getInspectors(?array $only = null, int $current = 0): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['id', 'name'])
			->from($db->quoteName('#__ra_footpaths_inspectors'))
			->where('(active = 1 OR id = :current)')
			->bind(':current', $current, ParameterType::INTEGER)
			->order('name');

		if ($only !== null) {
			$query->whereIn('id', $only ?: [0]);
		}

		return $db->setQuery($query)->loadObjectList();
	}
}
