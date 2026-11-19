<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Shared rules: who may see footpath records, where photos are kept, grade names and colours.
 */
final class FootpathsHelper
{
	/** Grade letter => [label, colour]. N means the path has never been inspected. */
	public const GRADES = [
		'A' => ['Good', '#2e7d32'],
		'B' => ['Minor problems', '#f9a825'],
		'C' => ['Significant problems', '#ef6c00'],
		'D' => ['Serious problem', '#c62828'],
		'N' => ['Not inspected', '#757575'],
	];

	/**
	 * Inspectors (members of the group chosen in the component options) and Super Users.
	 * Without a group chosen, only Super Users get in.
	 */
	public static function canView(?User $user): bool
	{
		if ($user === null || $user->guest) {
			return false;
		}

		if ($user->authorise('core.admin')) {
			return true;
		}

		if (self::canManage($user)) {
			return true;
		}

		$group = self::inspectorGroup();

		return $group > 0 && \in_array($group, array_map('intval', $user->getAuthorisedGroups()), true);
	}

	/**
	 * Footpath admins: Super Users and members of the group chosen in Options ("Footpath admins group"). They can
	 * see the records and manage inspectors (logins, parishes) from the website as well as the administrator.
	 */
	public static function canManage(?User $user): bool
	{
		if ($user === null || $user->guest) {
			return false;
		}

		if ($user->authorise('core.admin')) {
			return true;
		}

		$group = (int) ComponentHelper::getParams('com_ra_footpaths')->get('manager_group', 0);

		return $group > 0 && \in_array($group, array_map('intval', $user->getAuthorisedGroups()), true);
	}

	/** @var array<int,int[]> user id => inspector ids */
	private static array $mine = [];

	/**
	 * The inspector records linked to this login (usually one).
	 *
	 * @return int[]
	 */
	public static function myInspectorIds(?User $user): array
	{
		$userId = $user === null || $user->guest ? 0 : (int) $user->id;

		if ($userId <= 0) {
			return [];
		}

		if (!isset(self::$mine[$userId])) {
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true)
				->select('inspector_id')
				->from($db->quoteName('#__ra_footpaths_inspector_users'))
				->where('user_id = :uid')
				->bind(':uid', $userId, ParameterType::INTEGER);
			self::$mine[$userId] = array_map('intval', $db->setQuery($query)->loadColumn());
		}

		return self::$mine[$userId];
	}

	/** Recording inspections: inspectors linked to an inspector record (in their own name), and footpath admins (anyone's). */
	/** Option "Recording on the website". Off: nobody saves, changes or deletes inspections or notes. */
	public static function recordingOn(): bool
	{
		return (bool) ComponentHelper::getParams('com_ra_footpaths')->get('recording', 1);
	}

	/** What inspectors see instead of the Record button while recording is off. */
	public static function recordingOffMessage(): string
	{
		$text = trim((string) ComponentHelper::getParams('com_ra_footpaths')->get('recording_off_message', ''));

		return $text !== '' ? $text : 'Inspections can\'t be recorded on this website yet. Please keep using the old system for now.';
	}

	/**
	 * The note at the top of the parishes page while recording is off (Options), with {date} replaced by the date the
	 * records were last copied from the old system. Empty while recording is on.
	 */
	public static function readOnlyNote(): string
	{
		if (self::recordingOn()) {
			return '';
		}

		$text = trim((string) ComponentHelper::getParams('com_ra_footpaths')->get('readonly_note', ''));
		$text = $text !== '' ? $text : 'Records copied from the old Footpath Inspections Database on {date}. Keep recording'
			. ' inspections in the old system for now: anything added there since then is not shown here yet.';
		$date = self::lastImport();

		// No import yet: drop "on {date}" rather than show a blank.
		return $date !== null ? str_replace('{date}', $date, $text) : str_replace([' on {date}', '{date}'], '', $text);
	}

	/** "19 Nov 2026": when the records were last copied from the old system, or null if never. */
	public static function lastImport(): ?string
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		// The importer logs each run; records imported before it did so carry the import time as their created date.
		$when = $db->setQuery("SELECT MAX(created) FROM #__ra_footpaths_log WHERE action = 'import'")->loadResult()
			?: $db->setQuery('SELECT MAX(created) FROM #__ra_footpaths_inspections WHERE legacy_id IS NOT NULL')->loadResult();

		return $when ? HTMLHelper::_('date', $when, 'j M Y') : null;
	}

	public static function canRecord(?User $user): bool
	{
		if (!self::recordingOn()) {
			return false;
		}

		return self::canManage($user) || (self::canView($user) && self::myInspectorIds($user) !== []);
	}

	/** Recording, editing or deleting an inspection in this inspector's name: their own, or anyone's for footpath admins. */
	public static function canActFor(?User $user, ?int $inspectorId): bool
	{
		if (!self::recordingOn()) {
			return false;
		}

		if (self::canManage($user)) {
			return true;
		}

		return $inspectorId !== null && $inspectorId > 0 && self::canView($user)
			&& \in_array($inspectorId, self::myInspectorIds($user), true);
	}

	/** Changing or deleting a follow-up note: whoever added it (or the inspector it's under), and footpath admins. */
	public static function canEditNote(?User $user, ?int $createdBy, ?int $reporterId): bool
	{
		if (!self::recordingOn()) {
			return false;
		}

		if (self::canManage($user)) {
			return true;
		}

		if (!self::canView($user)) {
			return false;
		}

		return ($createdBy !== null && $createdBy > 0 && $createdBy === (int) $user->id)
			|| ($reporterId !== null && \in_array($reporterId, self::myInspectorIds($user), true));
	}

	/** The user group whose members can see the footpath records (0 when none is chosen). */
	public static function inspectorGroup(): int
	{
		return (int) ComponentHelper::getParams('com_ra_footpaths')->get('inspector_group', 0);
	}

	/** A name reduced for matching an inspector to a website account: lower case, letters and single spaces only. */
	public static function nameKey(string $name): string
	{
		$name = mb_strtolower(trim($name));
		$name = (string) preg_replace('/[^\p{L}\s]/u', '', $name);

		return trim((string) preg_replace('/\s+/u', ' ', $name));
	}

	/** Folder holding the photo files; outside the web root on a live host. */
	public static function photoPath(): string
	{
		$path = trim((string) ComponentHelper::getParams('com_ra_footpaths')->get('photo_path', ''));

		if ($path === '') {
			$path = JPATH_ROOT . '/private/footpath-photos';
		}

		return rtrim($path, '/');
	}

	public static function gradeLabel(?string $grade): string
	{
		return self::GRADES[self::normalise($grade)][0];
	}

	public static function gradeColour(?string $grade): string
	{
		return self::GRADES[self::normalise($grade)][1];
	}

	public static function normalise(?string $grade): string
	{
		$grade = strtoupper(trim((string) $grade));

		return isset(self::GRADES[$grade]) ? $grade : 'N';
	}

	/** Small coloured chip for a grade. */
	public static function gradeChip(?string $grade): string
	{
		$g = self::normalise($grade);
		$label = htmlspecialchars(self::GRADES[$g][0], ENT_QUOTES, 'UTF-8');

		return '<span class="rafp-grade rafp-grade-' . strtolower($g) . '" title="' . $label . '">'
			. ($g === 'N' ? '&ndash;' : $g) . '<span class="visually-hidden"> ' . $label . '</span></span>';
	}

	/** "FP 12" style name. */
	public static function pathName(string $type, string $number): string
	{
		return trim($type . ' ' . $number);
	}

	/** Sort key so FP 2 comes before FP 10, and 12A after 12 (same idea as the old system). */
	public static function sortKey(string $type, string $number): int
	{
		$types = ['FP' => 0, 'BR' => 1, 'RB' => 2, 'BY' => 3, 'RP' => 4, 'BW' => 5];

		if (!preg_match('/(0\.)*([A-Za-z]?)(\d+)\(*([A-Za-z]*)/', $number, $m)) {
			return 99999999;
		}

		$index = (int) $m[3] * 100;

		if ($m[4] !== '') {
			$index += 10;
			$suffix = strtoupper($m[4]);

			for ($i = 0; $i < \strlen($suffix); $i++) {
				$index += \ord($suffix[$i]) - \ord('A');
			}
		}

		if ($m[2] !== '') {
			$index += (\ord(strtoupper($m[2])) - \ord('A') + 1) * 1000000;
		}

		return $index + ($types[strtoupper($type)] ?? 6);
	}
}
