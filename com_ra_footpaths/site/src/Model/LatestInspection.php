<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Model;

\defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

/**
 * SQL for "each active path with its most recent inspection" (latest date, then latest entered).
 * Columns: path_id, parish_id, inspection_id, inspection_date, grade, inspector_id, remarks.
 */
final class LatestInspection
{
	public static function subquery(DatabaseInterface $db, string $where = ''): string
	{
		return 'SELECT p.id AS path_id, p.parish_id, li.id AS inspection_id, li.inspection_date, li.grade,'
			. ' li.inspector_id, li.remarks'
			. ' FROM ' . $db->quoteName('#__ra_footpaths_paths', 'p')
			. ' LEFT JOIN ' . $db->quoteName('#__ra_footpaths_inspections', 'li') . ' ON li.id = ('
			. 'SELECT i2.id FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'i2')
			. ' WHERE i2.path_id = p.id ORDER BY i2.inspection_date DESC, i2.id DESC LIMIT 1)'
			. ' WHERE p.state = 1' . ($where !== '' ? ' AND ' . $where : '');
	}
}
