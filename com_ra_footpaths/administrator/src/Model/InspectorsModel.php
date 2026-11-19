<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/** Inspectors with their parishes, website logins and how recently they inspected. */
class InspectorsModel extends BaseDatabaseModel
{
	/** @return array{search:string,status:string,login:string,parish:int} */
	public function getFilters(): array
	{
		$app = Factory::getApplication();
		$ctx = 'com_ra_footpaths.inspectors.';
		$status = (string) $app->getUserStateFromRequest($ctx . 'status', 'filter_status', 'active', 'cmd');
		$login = (string) $app->getUserStateFromRequest($ctx . 'login', 'filter_login', '', 'cmd');

		return [
			'search' => trim((string) $app->getUserStateFromRequest($ctx . 'search', 'filter_search', '', 'string')),
			'status' => \in_array($status, ['active', 'inactive', 'recent', 'all'], true) ? $status : 'active',
			'login' => \in_array($login, ['linked', 'unlinked'], true) ? $login : '',
			'parish' => (int) $app->getUserStateFromRequest($ctx . 'parish', 'filter_parish', 0, 'int'),
		];
	}

	/** @return array<int,string> parish id => name */
	public function getParishes(): array
	{
		$db = $this->getDatabase();

		return $db->setQuery(
			$db->getQuery(true)->select(['id', 'name'])->from($db->quoteName('#__ra_footpaths_parishes'))->order('name')
		)->loadAssocList('id', 'name');
	}

	/**
	 * @param  array{search:string,status:string,login:string,parish:int}  $f
	 *
	 * @return list<object> each with parishes (names), logins (users) and matches (unlinked users with the same name)
	 */
	public function getItems(array $f): array
	{
		$db = $this->getDatabase();
		$since = (new \DateTimeImmutable('-12 months'))->format('Y-m-d');
		$q = $db->getQuery(true)
			->select(['i.id', 'i.name', 'i.active', 'i.site_managed', 'i.legacy_id'])
			->select('(SELECT MAX(x.inspection_date) FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'x') . ' WHERE x.inspector_id = i.id) AS last_inspection')
			->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'y') . ' WHERE y.inspector_id = i.id AND y.inspection_date >= :since) AS recent')
			->from($db->quoteName('#__ra_footpaths_inspectors', 'i'))
			->bind(':since', $since)
			->order('i.name');

		if ($f['status'] === 'active') {
			$q->where('i.active = 1');
		} elseif ($f['status'] === 'inactive') {
			$q->where('i.active = 0');
		} elseif ($f['status'] === 'recent') {
			$q->having('recent > 0');
		}

		if ($f['search'] !== '') {
			$like = '%' . $f['search'] . '%';
			$q->where('i.name LIKE :search')->bind(':search', $like);
		}

		$linked = 'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ra_footpaths_inspector_users', 'iu') . ' WHERE iu.inspector_id = i.id)';

		if ($f['login'] === 'linked') {
			$q->where($linked);
		} elseif ($f['login'] === 'unlinked') {
			$q->where('NOT ' . $linked);
		}

		if ($f['parish'] > 0) {
			$q->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ra_footpaths_inspector_parishes', 'ip') . ' WHERE ip.inspector_id = i.id AND ip.parish_id = :parish)')
				->bind(':parish', $f['parish'], ParameterType::INTEGER);
		}

		$items = $db->setQuery($q)->loadObjectList('id');

		if (!$items) {
			return [];
		}

		foreach ($items as $item) {
			$item->parishes = [];
			$item->logins = [];
			$item->matches = [];
		}

		$ids = array_map('intval', array_keys($items));

		$rows = $db->setQuery(
			$db->getQuery(true)
				->select(['ip.inspector_id', 'p.name'])
				->from($db->quoteName('#__ra_footpaths_inspector_parishes', 'ip'))
				->join('INNER', $db->quoteName('#__ra_footpaths_parishes', 'p'), 'p.id = ip.parish_id')
				->whereIn('ip.inspector_id', $ids)
				->order('p.name')
		)->loadObjectList();

		foreach ($rows as $r) {
			$items[$r->inspector_id]->parishes[] = $r->name;
		}

		foreach (self::logins($ids) as $r) {
			$items[$r->inspector_id]->logins[] = $r;
		}

		// Suggest a website account with exactly the same name for an inspector with no login.
		$byName = self::usersByName();

		foreach ($items as $item) {
			if (!$item->logins) {
				$item->matches = $byName[FootpathsHelper::nameKey((string) $item->name)] ?? [];
			}
		}

		return array_values($items);
	}

	/** How many active inspectors, and how many who inspected in the last 12 months, have no website login. */
	public function getUnlinkedCounts(): array
	{
		$db = $this->getDatabase();
		$since = (new \DateTimeImmutable('-12 months'))->format('Y-m-d');
		$noLogin = 'NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ra_footpaths_inspector_users', 'iu') . ' WHERE iu.inspector_id = i.id)';

		$active = (int) $db->setQuery(
			$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__ra_footpaths_inspectors', 'i'))->where('i.active = 1')->where($noLogin)
		)->loadResult();

		$recent = (int) $db->setQuery(
			$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__ra_footpaths_inspectors', 'i'))->where($noLogin)
				->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'x') . ' WHERE x.inspector_id = i.id AND x.inspection_date >= :since)')
				->bind(':since', $since)
		)->loadResult();

		return ['active' => $active, 'recent' => $recent];
	}

	/**
	 * Website logins of these inspectors, with whether each is in the inspectors group.
	 *
	 * @param  int[]  $inspectorIds
	 *
	 * @return list<object> inspector_id, id, name, username, email, block, in_group
	 */
	public static function logins(array $inspectorIds): array
	{
		if (!$inspectorIds) {
			return [];
		}

		$db = Factory::getContainer()->get('DatabaseDriver');
		$group = FootpathsHelper::inspectorGroup();

		return $db->setQuery(
			$db->getQuery(true)
				->select(['iu.inspector_id', 'u.id', 'u.name', 'u.username', 'u.email', 'u.block'])
				->select('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__user_usergroup_map', 'm') . ' WHERE m.user_id = u.id AND m.group_id = :group) AS in_group')
				->from($db->quoteName('#__ra_footpaths_inspector_users', 'iu'))
				->join('INNER', $db->quoteName('#__users', 'u'), 'u.id = iu.user_id')
				->whereIn('iu.inspector_id', array_map('intval', $inspectorIds))
				->bind(':group', $group, ParameterType::INTEGER)
				->order('u.name')
		)->loadObjectList();
	}

	/** @return array<string,list<object>> website accounts by FootpathsHelper::nameKey() of their name */
	public static function usersByName(): array
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$users = $db->setQuery(
			$db->getQuery(true)->select(['id', 'name', 'username', 'email', 'block'])->from($db->quoteName('#__users'))->order('id')
		)->loadObjectList();

		$byName = [];

		foreach ($users as $u) {
			$byName[FootpathsHelper::nameKey((string) $u->name)][] = $u;
		}

		return $byName;
	}
}
