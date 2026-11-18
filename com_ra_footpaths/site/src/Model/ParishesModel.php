<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/** Every parish with a summary of its paths' latest inspections. */
class ParishesModel extends BaseDatabaseModel
{
	/** @return array<int,object> */
	public function getItems(): array
	{
		$db = $this->getDatabase();
		$latest = LatestInspection::subquery($db);

		$sql = 'SELECT pa.id, pa.name, ar.name AS area,'
			. ' COUNT(lp.path_id) AS paths,'
			. ' SUM(lp.inspection_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)) AS recent,'
			. ' SUM(lp.inspection_date IS NULL) AS never,'
			. ' SUM(lp.grade = ' . $db->quote('A') . ') AS grade_a,'
			. ' SUM(lp.grade = ' . $db->quote('B') . ') AS grade_b,'
			. ' SUM(lp.grade = ' . $db->quote('C') . ') AS grade_c,'
			. ' SUM(lp.grade = ' . $db->quote('D') . ') AS grade_d,'
			. ' MAX(lp.inspection_date) AS last_date'
			. ' FROM ' . $db->quoteName('#__ra_footpaths_parishes', 'pa')
			. ' LEFT JOIN ' . $db->quoteName('#__ra_footpaths_areas', 'ar') . ' ON ar.id = pa.area_id'
			. ' LEFT JOIN (' . $latest . ') AS lp ON lp.parish_id = pa.id'
			. ' WHERE pa.state = 1'
			. ' GROUP BY pa.id, pa.name, ar.name'
			. ' ORDER BY pa.name';

		return $db->setQuery($sql)->loadObjectList();
	}
}
