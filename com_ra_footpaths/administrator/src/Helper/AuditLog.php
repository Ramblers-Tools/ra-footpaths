<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * The change log (#__ra_footpaths_log): who added, changed or deleted an inspection, fault, photo or follow-up note,
 * and when. details holds the record before and/or after, as JSON. The app's "changed" times and the importer's
 * "site records exist" check read it too.
 */
final class AuditLog
{
	/** @param array<string,mixed> $details */
	public static function add(DatabaseInterface $db, string $action, string $itemType, ?int $itemId, ?int $pathId, array $details = [], ?int $userId = null): void
	{
		if ($userId === null) {
			$user = Factory::getApplication()->getIdentity();
			$userId = $user && !$user->guest ? (int) $user->id : null;
		}

		$row = (object) [
			'created' => Factory::getDate()->toSql(),
			'user_id' => $userId,
			'action' => $action,
			'item_type' => $itemType,
			'item_id' => $itemId,
			'path_id' => $pathId,
			'details' => $details ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : null,
		];

		$db->insertObject('#__ra_footpaths_log', $row);
	}
}
