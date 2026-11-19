<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/**
 * One inspector: details, parishes and website logins. Linking a login puts that user in the inspectors group (set in
 * Options); unlinking takes them out again unless they are still linked to another inspector.
 */
class InspectorModel extends BaseDatabaseModel
{
	public function getItem(int $id): ?object
	{
		if ($id <= 0) {
			return null;
		}

		$db = $this->getDatabase();
		$since = (new \DateTimeImmutable('-12 months'))->format('Y-m-d');
		$item = $db->setQuery(
			$db->getQuery(true)
				->select(['i.id', 'i.name', 'i.email', 'i.phone', 'i.membership_no', 'i.active', 'i.site_managed', 'i.legacy_id'])
				->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'x') . ' WHERE x.inspector_id = i.id) AS inspections')
				->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'y') . ' WHERE y.inspector_id = i.id AND y.inspection_date >= :since) AS recent')
				->select('(SELECT MAX(z.inspection_date) FROM ' . $db->quoteName('#__ra_footpaths_inspections', 'z') . ' WHERE z.inspector_id = i.id) AS last_inspection')
				->from($db->quoteName('#__ra_footpaths_inspectors', 'i'))
				->where('i.id = :id')
				->bind(':id', $id, ParameterType::INTEGER)
				->bind(':since', $since)
		)->loadObject();

		return $item ?: null;
	}

	/** @return list<object> */
	public function getLogins(int $id): array
	{
		return $id > 0 ? InspectorsModel::logins([$id]) : [];
	}

	/** Website accounts with the inspector's name or email that are not linked to them yet. */
	public function getMatches(object $item, array $logins): array
	{
		$linked = array_map(static fn ($l) => (int) $l->id, $logins);
		$same = InspectorsModel::merge(InspectorsModel::usersByName()[FootpathsHelper::nameKey((string) $item->name)] ?? [],
			InspectorsModel::usersByEmail()[strtolower(trim((string) $item->email))] ?? []);

		return array_values(array_filter($same, static fn ($u) => !\in_array((int) $u->id, $linked, true)));
	}

	/** Up to 25 website accounts whose name, username or email contains $text. */
	public function searchUsers(string $text, array $logins): array
	{
		$text = trim($text);

		if (mb_strlen($text) < 2) {
			return [];
		}

		$db = $this->getDatabase();
		$like = '%' . $text . '%';
		$q = $db->getQuery(true)
			->select(['id', 'name', 'username', 'email', 'block'])
			->from($db->quoteName('#__users'))
			->where('(name LIKE :a OR username LIKE :b OR email LIKE :c)')
			->bind(':a', $like)->bind(':b', $like)->bind(':c', $like)
			->order('name')
			->setLimit(25);

		$linked = array_map(static fn ($l) => (int) $l->id, $logins);

		if ($linked) {
			$q->whereNotIn('id', $linked);
		}

		return $db->setQuery($q)->loadObjectList();
	}

	/**
	 * Every parish, whether this inspector covers it, and the other active inspectors who do.
	 *
	 * @return list<object> id, name, mine (bool), others (string[])
	 */
	public function getParishes(int $id): array
	{
		$db = $this->getDatabase();
		$parishes = $db->setQuery(
			$db->getQuery(true)->select(['id', 'name'])->from($db->quoteName('#__ra_footpaths_parishes'))->order('name')
		)->loadObjectList('id');

		foreach ($parishes as $p) {
			$p->mine = false;
			$p->others = [];
		}

		$rows = $db->setQuery(
			$db->getQuery(true)
				->select(['ip.parish_id', 'ip.inspector_id', 'i.name', 'i.active'])
				->from($db->quoteName('#__ra_footpaths_inspector_parishes', 'ip'))
				->join('INNER', $db->quoteName('#__ra_footpaths_inspectors', 'i'), 'i.id = ip.inspector_id')
				->order('i.name')
		)->loadObjectList();

		foreach ($rows as $r) {
			if (!isset($parishes[$r->parish_id])) {
				continue;
			}

			if ((int) $r->inspector_id === $id) {
				$parishes[$r->parish_id]->mine = true;
			} elseif ((int) $r->active === 1) {
				$parishes[$r->parish_id]->others[] = $r->name;
			}
		}

		return array_values($parishes);
	}

	/**
	 * Save name, contact details, active flag and parishes. Marks the inspector as managed here, so a re-import from
	 * the old system leaves them alone.
	 *
	 * @param  array{email?:string,phone?:string,membership_no?:string}  $contact
	 * @param  int[]  $parishIds
	 *
	 * @return int the inspector's id
	 *
	 * @throws \InvalidArgumentException when the email address is not valid
	 */
	public function save(int $id, string $name, bool $active, array $parishIds, array $contact = []): int
	{
		$db = $this->getDatabase();
		$email = trim((string) ($contact['email'] ?? ''));

		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new \InvalidArgumentException(\Joomla\CMS\Language\Text::_('COM_RA_FOOTPATHS_INSPECTOR_EMAIL_INVALID'));
		}

		$clean = static fn (string $key, int $max): ?string => ($v = mb_substr(trim((string) ($contact[$key] ?? '')), 0, $max)) === '' ? null : $v;
		$row = (object) [
			'name' => mb_substr($name, 0, 100),
			'email' => $email === '' ? null : mb_substr($email, 0, 100),
			'phone' => $clean('phone', 30),
			'membership_no' => $clean('membership_no', 20),
			'active' => $active ? 1 : 0,
			'site_managed' => 1,
		];

		$valid = $db->setQuery($db->getQuery(true)->select('id')->from($db->quoteName('#__ra_footpaths_parishes')))->loadColumn();
		$parishIds = array_values(array_intersect(array_unique(array_map('intval', $parishIds)), array_map('intval', $valid)));

		$db->transactionStart();

		try {
			if ($id > 0) {
				$row->id = $id;
				$db->updateObject('#__ra_footpaths_inspectors', $row, 'id', true);
			} else {
				$db->insertObject('#__ra_footpaths_inspectors', $row, 'id');
				$id = (int) $row->id;
			}

			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName('#__ra_footpaths_inspector_parishes'))->where('inspector_id = :id')->bind(':id', $id, ParameterType::INTEGER)
			)->execute();

			foreach ($parishIds as $parishId) {
				$link = (object) ['inspector_id' => $id, 'parish_id' => $parishId];
				$db->insertObject('#__ra_footpaths_inspector_parishes', $link);
			}

			$db->transactionCommit();
		} catch (\Throwable $e) {
			$db->transactionRollback();
			throw $e;
		}

		return $id;
	}

	/**
	 * Link a website login to the inspector and put it in the inspectors group.
	 *
	 * @return string 'linked', 'nogroup' (linked, but Joomla refused the group change: only a Super User can change a
	 *                Super User) or 'missing' (no such user or inspector)
	 */
	public function link(int $id, int $userId): string
	{
		$db = $this->getDatabase();
		$exists = (int) $db->setQuery(
			$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__users'))->where('id = :u')->bind(':u', $userId, ParameterType::INTEGER)
		)->loadResult();

		if (!$exists || !$this->getItem($id)) {
			return 'missing';
		}

		$db->setQuery(
			'INSERT IGNORE INTO ' . $db->quoteName('#__ra_footpaths_inspector_users') . ' (inspector_id, user_id) VALUES (' . (int) $id . ', ' . (int) $userId . ')'
		)->execute();

		$group = FootpathsHelper::inspectorGroup();

		if ($group > 0) {
			try {
				UserHelper::addUserToGroup($userId, $group);
			} catch (\Throwable $e) {
				// Checked below.
			}

			if (!\in_array($group, array_map('intval', UserHelper::getUserGroups($userId)), true)) {
				return 'nogroup';
			}
		}

		return 'linked';
	}

	/** Unlink a login. Takes the user out of the inspectors group unless they are still linked to another inspector. */
	public function unlink(int $id, int $userId): void
	{
		$db = $this->getDatabase();
		$db->setQuery(
			$db->getQuery(true)->delete($db->quoteName('#__ra_footpaths_inspector_users'))
				->where('inspector_id = :i')->where('user_id = :u')
				->bind(':i', $id, ParameterType::INTEGER)->bind(':u', $userId, ParameterType::INTEGER)
		)->execute();

		$stillLinked = (int) $db->setQuery(
			$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__ra_footpaths_inspector_users'))->where('user_id = :u')->bind(':u', $userId, ParameterType::INTEGER)
		)->loadResult();

		$group = FootpathsHelper::inspectorGroup();

		if ($group > 0 && !$stillLinked) {
			$groups = UserHelper::getUserGroups($userId);

			// Joomla refuses to leave a user with no groups at all.
			if (\in_array($group, array_map('intval', $groups), true) && \count($groups) > 1) {
				UserHelper::removeUserFromGroup($userId, $group);
			}
		}
	}
}
