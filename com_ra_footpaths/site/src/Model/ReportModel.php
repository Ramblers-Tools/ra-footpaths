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
 * The "record an inspection" form. Demo only for now: it reads what the form needs and saves nothing.
 */
class ReportModel extends PathModel
{
	/** @return array<int,object> id, name */
	public function getFaultTypes(): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['id', 'name'])
			->from($db->quoteName('#__ra_footpaths_fault_types'))
			->where('state = 1')
			->order('name');

		return $db->setQuery($query)->loadObjectList();
	}

	/** @return array<int,object> id, name: active inspectors */
	public function getInspectors(): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['id', 'name'])
			->from($db->quoteName('#__ra_footpaths_inspectors'))
			->where('active = 1')
			->order('name');

		return $db->setQuery($query)->loadObjectList();
	}

	/**
	 * Inspectors linked to this website login.
	 *
	 * @return int[]
	 */
	public function getMyInspectorIds(int $userId): array
	{
		if ($userId <= 0) {
			return [];
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('inspector_id')
			->from($db->quoteName('#__ra_footpaths_inspector_users'))
			->where('user_id = :uid')
			->bind(':uid', $userId, ParameterType::INTEGER);

		return array_map('intval', $db->setQuery($query)->loadColumn());
	}
}
