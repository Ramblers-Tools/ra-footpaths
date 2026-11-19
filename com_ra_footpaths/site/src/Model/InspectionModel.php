<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\AuditLog;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\PhotoStore;

/**
 * Saving, changing and deleting inspections (with their faults and photos) and follow-up notes on faults.
 * Every change is checked against the user (FootpathsHelper::canActFor / canEditNote) and written to the change log.
 * Problems the user can fix come back as \RuntimeException with plain wording; code 403 when not allowed.
 */
class InspectionModel extends BaseDatabaseModel
{
	private const ORDER = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];

	/** One inspection as stored, or null. */
	public function getInspection(int $id): ?object
	{
		if ($id <= 0) {
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['id', 'path_id', 'inspector_id', 'inspection_date', 'grade', 'grade_recorded', 'remarks', 'legacy_id'])
			->from($db->quoteName('#__ra_footpaths_inspections'))
			->where('id = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * The inspection with its faults, each with photo ids and how many follow-up notes it has: for the edit form and
	 * the change log.
	 */
	public function getFull(int $id): ?object
	{
		$inspection = $this->getInspection($id);

		if ($inspection === null) {
			return null;
		}

		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['f.id', 'f.fault_type_id', 'f.grade', 'f.remarks', 'f.gridref', 'f.lat', 'f.lng'])
			->select('(SELECT COUNT(*) FROM ' . $db->quoteName('#__ra_footpaths_fault_events', 'e') . ' WHERE e.fault_id = f.id) AS notes')
			->from($db->quoteName('#__ra_footpaths_faults', 'f'))
			->where('f.inspection_id = :id')
			->order('f.id')
			->bind(':id', $id, ParameterType::INTEGER);
		$faults = $db->setQuery($query)->loadObjectList('id');

		foreach ($faults as $f) {
			$f->photos = [];
		}

		if ($faults) {
			$query = $db->getQuery(true)
				->select(['id', 'fault_id'])
				->from($db->quoteName('#__ra_footpaths_fault_photos'))
				->whereIn('fault_id', array_map('intval', array_keys($faults)))
				->order('id');

			foreach ($db->setQuery($query)->loadObjectList() as $p) {
				$faults[$p->fault_id]->photos[] = (int) $p->id;
			}
		}

		$inspection->faults = array_values($faults);

		return $inspection;
	}

	/** How many other inspections of this path are on this date. */
	public function countSameDay(int $pathId, string $date, int $excludeId): int
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('COUNT(*)')
			->from($db->quoteName('#__ra_footpaths_inspections'))
			->where('path_id = :path')
			->where('inspection_date = :date')
			->where('id <> :id')
			->bind(':path', $pathId, ParameterType::INTEGER)
			->bind(':date', $date)
			->bind(':id', $excludeId, ParameterType::INTEGER);

		return (int) $db->setQuery($query)->loadResult();
	}

	/**
	 * Check what the form sent, before anything is saved.
	 *
	 * @param  array<string,mixed>  $in      date, inspector, grade, remarks
	 * @param  list<array>          $faults  id, type, grade, remarks, gridref, lat, lng, keep (photo ids), key
	 * @param  array                $files   uploaded photos: key => list of uploads
	 *
	 * @return array the cleaned inspection, ready for save()
	 */
	public function validate(User $user, int $id, int $pathId, array $in, array $faults, array $files): array
	{
		$db = $this->getDatabase();
		$existing = null;

		if ($id > 0) {
			$existing = $this->getFull($id);

			if ($existing === null || (int) $existing->path_id !== $pathId) {
				throw new \RuntimeException('That inspection no longer exists.', 404);
			}

			if (!FootpathsHelper::canActFor($user, $existing->inspector_id === null ? null : (int) $existing->inspector_id)) {
				throw new \RuntimeException('You can only change your own inspections.', 403);
			}
		}

		$path = (int) $db->setQuery(
			$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__ra_footpaths_paths'))
				->where('id = :id')->where('state = 1')->bind(':id', $pathId, ParameterType::INTEGER)
		)->loadResult();

		if (!$path) {
			throw new \RuntimeException('That path is not in the list of paths.', 404);
		}

		// Inspector: must exist, be the user's own (unless a footpath admin), and be active unless it's unchanged.
		$inspectorId = (int) ($in['inspector'] ?? 0);
		$inspector = $inspectorId > 0 ? $db->setQuery(
			$db->getQuery(true)->select(['id', 'active'])->from($db->quoteName('#__ra_footpaths_inspectors'))
				->where('id = :id')->bind(':id', $inspectorId, ParameterType::INTEGER)
		)->loadObject() : null;
		$unchanged = $existing !== null && (int) $existing->inspector_id === $inspectorId;

		if ($inspector === null || (!(int) $inspector->active && !$unchanged)) {
			throw new \RuntimeException('Choose the inspector.');
		}

		if (!FootpathsHelper::canActFor($user, $inspectorId)) {
			throw new \RuntimeException('You can only record inspections in your own name.', 403);
		}

		$date = (string) ($in['date'] ?? '');
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		$today = Factory::getDate('now', Factory::getApplication()->get('offset', 'UTC'))->format('Y-m-d', true);

		if ($parsed === false || $parsed->format('Y-m-d') !== $date || $date > $today || $date < '2000-01-01') {
			throw new \RuntimeException('Give the date you walked the path (not in the future).');
		}

		$chosen = strtoupper((string) ($in['grade'] ?? ''));

		if (!isset(self::ORDER[$chosen])) {
			throw new \RuntimeException('Choose the overall condition (A to D).');
		}

		$types = array_map('intval', $db->setQuery(
			$db->getQuery(true)->select('id')->from($db->quoteName('#__ra_footpaths_fault_types'))
		)->loadColumn());

		$oldFaults = [];

		foreach ($existing->faults ?? [] as $f) {
			$oldFaults[(int) $f->id] = $f;
		}

		$clean = [];
		$final = $chosen;

		foreach (array_values($faults) as $n => $f) {
			$label = 'Fault ' . ($n + 1);

			if (!\is_array($f)) {
				throw new \RuntimeException($label . ': the form sent something unexpected. Please reload the page.');
			}

			$faultId = (int) ($f['id'] ?? 0);

			if ($faultId > 0 && !isset($oldFaults[$faultId])) {
				throw new \RuntimeException($label . ' is not part of this inspection any more. Please reload the page.');
			}

			$type = (int) ($f['type'] ?? 0);

			if (!\in_array($type, $types, true)) {
				throw new \RuntimeException($label . ': choose the type of fault.');
			}

			$grade = strtoupper((string) ($f['grade'] ?? ''));

			// New faults are B to D. A fault from the old system may have had none (or A): it may stay as it was.
			if (!\in_array($grade, ['B', 'C', 'D'], true)) {
				$old = $faultId > 0 ? strtoupper((string) $oldFaults[$faultId]->grade) : null;

				if ($faultId > 0 && ($grade === '' || $grade === $old)) {
					$grade = $old === '' ? null : $old;
				} else {
					throw new \RuntimeException($label . ': choose how bad it is (B, C or D).');
				}
			}

			if ($grade !== null && isset(self::ORDER[$grade]) && self::ORDER[$grade] > self::ORDER[$final]) {
				$final = $grade;
			}

			$lat = isset($f['lat']) && is_numeric($f['lat']) ? (float) $f['lat'] : null;
			$lng = isset($f['lng']) && is_numeric($f['lng']) ? (float) $f['lng'] : null;

			// Roughly Great Britain; anything else is a mistake (or the swapped coordinates of old).
			if ($lat === null || $lng === null || $lat < 49 || $lat > 61 || $lng < -9 || $lng > 3) {
				$lat = $lng = null;
			}

			$keep = [];

			foreach ((array) ($f['keep'] ?? []) as $photoId) {
				if ($faultId > 0 && \in_array((int) $photoId, $oldFaults[$faultId]->photos, true)) {
					$keep[] = (int) $photoId;
				}
			}

			$key = (string) ($f['key'] ?? '');
			$uploads = [];

			foreach ($key !== '' && isset($files[$key]) && \is_array($files[$key]) ? $files[$key] : [] as $upload) {
				if (\is_array($upload) && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
					$uploads[] = $upload;
				}
			}

			if (\count($keep) + \count($uploads) > PhotoStore::MAX_PER_FAULT) {
				throw new \RuntimeException($label . ': at most ' . PhotoStore::MAX_PER_FAULT . ' photos per fault.');
			}

			$clean[] = [
				'id' => $faultId,
				'fault_type_id' => $type,
				'grade' => $grade,
				'remarks' => mb_substr(trim((string) ($f['remarks'] ?? '')), 0, 2000),
				'gridref' => mb_substr(trim((string) ($f['gridref'] ?? '')), 0, 60),
				'lat' => $lat,
				'lng' => $lng,
				'keep' => $keep,
				'uploads' => $uploads,
			];
		}

		return [
			'id' => $id,
			'path_id' => $pathId,
			'inspector_id' => $inspectorId,
			'inspection_date' => $date,
			'grade_recorded' => $chosen,
			'grade' => $final,
			'remarks' => mb_substr(trim((string) ($in['remarks'] ?? '')), 0, 5000),
			'faults' => $clean,
			'existing' => $existing,
		];
	}

	/**
	 * Save a checked inspection (from validate()) with its faults and photos, all or nothing.
	 *
	 * @return int the inspection's id
	 */
	public function save(User $user, array $data): int
	{
		$db = $this->getDatabase();
		$now = Factory::getDate()->toSql();
		$existing = $data['existing'];
		$newFiles = [];
		$oldFiles = [];

		$db->transactionStart();

		try {
			$row = (object) [
				'path_id' => $data['path_id'],
				'inspector_id' => $data['inspector_id'],
				'inspection_date' => $data['inspection_date'],
				'grade' => $data['grade'],
				'grade_recorded' => $data['grade_recorded'],
				'remarks' => $data['remarks'],
			];

			if ($existing) {
				$row->id = (int) $existing->id;
				$row->modified = $now;
				$row->modified_by = (int) $user->id;
				$db->updateObject('#__ra_footpaths_inspections', $row, 'id', true);
				$id = (int) $existing->id;
			} else {
				$row->created = $now;
				$row->created_by = (int) $user->id;
				$db->insertObject('#__ra_footpaths_inspections', $row, 'id');
				$id = (int) $row->id;
			}

			// Faults removed on the form go, with their photos and notes.
			$sent = array_filter(array_map(static fn ($f) => $f['id'], $data['faults']));

			foreach ($existing->faults ?? [] as $old) {
				if (!\in_array((int) $old->id, $sent, true)) {
					$oldFiles = array_merge($oldFiles, $this->deleteFault((int) $old->id));
				}
			}

			foreach ($data['faults'] as $f) {
				$fault = (object) [
					'inspection_id' => $id,
					'fault_type_id' => $f['fault_type_id'],
					'grade' => $f['grade'],
					'remarks' => $f['remarks'],
					'gridref' => $f['gridref'],
					'lat' => $f['lat'],
					'lng' => $f['lng'],
				];

				if ($f['id'] > 0) {
					$fault->id = $f['id'];
					$db->updateObject('#__ra_footpaths_faults', $fault, 'id', true);
					$faultId = $f['id'];
					$oldFiles = array_merge($oldFiles, $this->deletePhotos($faultId, $f['keep']));
				} else {
					$db->insertObject('#__ra_footpaths_faults', $fault, 'id');
					$faultId = (int) $fault->id;
				}

				foreach ($f['uploads'] as $upload) {
					$stored = PhotoStore::save($upload, $faultId);
					$newFiles[] = $stored['file'];
					$photo = (object) ($stored + ['fault_id' => $faultId, 'created' => $now]);
					$db->insertObject('#__ra_footpaths_fault_photos', $photo);
				}
			}

			AuditLog::add($db, $existing ? 'update' : 'add', 'inspection', $id, $data['path_id'], [
				'before' => $existing ? $this->snapshot($existing) : null,
				'after' => $this->snapshot($this->getFull($id)),
			], (int) $user->id);

			$db->transactionCommit();
		} catch (\Throwable $e) {
			$db->transactionRollback();

			foreach ($newFiles as $file) {
				PhotoStore::remove($file);
			}

			throw $e;
		}

		foreach ($oldFiles as $file) {
			PhotoStore::remove($file);
		}

		return $id;
	}

	/**
	 * Delete an inspection with its faults, photos and notes.
	 *
	 * @return int the path's id
	 */
	public function delete(User $user, int $id): int
	{
		$existing = $this->getFull($id);

		if ($existing === null) {
			throw new \RuntimeException('That inspection no longer exists.', 404);
		}

		if (!FootpathsHelper::canActFor($user, $existing->inspector_id === null ? null : (int) $existing->inspector_id)) {
			throw new \RuntimeException('You can only delete your own inspections.', 403);
		}

		$db = $this->getDatabase();
		$files = [];
		$db->transactionStart();

		try {
			foreach ($existing->faults as $f) {
				$files = array_merge($files, $this->deleteFault((int) $f->id));
			}

			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName('#__ra_footpaths_inspections'))->where('id = :id')->bind(':id', $id, ParameterType::INTEGER)
			)->execute();

			AuditLog::add($db, 'delete', 'inspection', $id, (int) $existing->path_id, ['before' => $this->snapshot($existing)], (int) $user->id);
			$db->transactionCommit();
		} catch (\Throwable $e) {
			$db->transactionRollback();

			throw $e;
		}

		foreach ($files as $file) {
			PhotoStore::remove($file);
		}

		return (int) $existing->path_id;
	}

	/** A follow-up note with the fault's path, or null. */
	public function getNote(int $id): ?object
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['e.id', 'e.fault_id', 'e.event_date', 'e.notes', 'e.reporter_id', 'e.created_by', 'i.path_id'])
			->from($db->quoteName('#__ra_footpaths_fault_events', 'e'))
			->join('INNER', $db->quoteName('#__ra_footpaths_faults', 'f'), 'f.id = e.fault_id')
			->join('INNER', $db->quoteName('#__ra_footpaths_inspections', 'i'), 'i.id = f.inspection_id')
			->where('e.id = :id')
			->bind(':id', $id, ParameterType::INTEGER);

		return $db->setQuery($query)->loadObject() ?: null;
	}

	/**
	 * Add a follow-up note to a fault, or change one (when $noteId > 0).
	 *
	 * @return int the path's id
	 */
	public function saveNote(User $user, int $noteId, int $faultId, string $date, string $notes): int
	{
		if (!FootpathsHelper::recordingOn()) {
			throw new \RuntimeException(FootpathsHelper::recordingOffMessage(), 403);
		}

		$db = $this->getDatabase();
		$notes = mb_substr(trim($notes), 0, 2000);
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
		$today = Factory::getDate('now', Factory::getApplication()->get('offset', 'UTC'))->format('Y-m-d', true);

		if ($parsed === false || $parsed->format('Y-m-d') !== $date || $date > $today || $date < '2000-01-01') {
			throw new \RuntimeException('Give the date of the note (not in the future).');
		}

		if ($notes === '') {
			throw new \RuntimeException('Write the note first.');
		}

		if ($noteId > 0) {
			$note = $this->getNote($noteId);

			if ($note === null) {
				throw new \RuntimeException('That note no longer exists.', 404);
			}

			if (!FootpathsHelper::canEditNote($user, $note->created_by === null ? null : (int) $note->created_by, $note->reporter_id === null ? null : (int) $note->reporter_id)) {
				throw new \RuntimeException('You can only change your own notes.', 403);
			}

			$row = (object) ['id' => $noteId, 'event_date' => $date, 'notes' => $notes, 'modified' => Factory::getDate()->toSql()];
			$db->updateObject('#__ra_footpaths_fault_events', $row, 'id');
			AuditLog::add($db, 'update', 'note', $noteId, (int) $note->path_id, [
				'before' => ['date' => $note->event_date, 'notes' => $note->notes],
				'after' => ['date' => $date, 'notes' => $notes],
			], (int) $user->id);

			return (int) $note->path_id;
		}

		$pathId = (int) $db->setQuery(
			$db->getQuery(true)->select('i.path_id')
				->from($db->quoteName('#__ra_footpaths_faults', 'f'))
				->join('INNER', $db->quoteName('#__ra_footpaths_inspections', 'i'), 'i.id = f.inspection_id')
				->where('f.id = :id')->bind(':id', $faultId, ParameterType::INTEGER)
		)->loadResult();

		if (!$pathId) {
			throw new \RuntimeException('That fault no longer exists.', 404);
		}

		if (!FootpathsHelper::canView($user)) {
			throw new \RuntimeException('Not allowed.', 403);
		}

		$mine = FootpathsHelper::myInspectorIds($user);
		$row = (object) [
			'fault_id' => $faultId,
			'event_date' => $date,
			'reporter_id' => $mine[0] ?? null,
			'notes' => $notes,
			'created' => Factory::getDate()->toSql(),
			'created_by' => (int) $user->id,
		];
		$db->insertObject('#__ra_footpaths_fault_events', $row, 'id');
		AuditLog::add($db, 'add', 'note', (int) $row->id, $pathId, ['after' => ['fault' => $faultId, 'date' => $date, 'notes' => $notes]], (int) $user->id);

		return $pathId;
	}

	/** @return int the path's id */
	public function deleteNote(User $user, int $noteId): int
	{
		$note = $this->getNote($noteId);

		if ($note === null) {
			throw new \RuntimeException('That note no longer exists.', 404);
		}

		if (!FootpathsHelper::canEditNote($user, $note->created_by === null ? null : (int) $note->created_by, $note->reporter_id === null ? null : (int) $note->reporter_id)) {
			throw new \RuntimeException('You can only delete your own notes.', 403);
		}

		$db = $this->getDatabase();
		$db->setQuery(
			$db->getQuery(true)->delete($db->quoteName('#__ra_footpaths_fault_events'))->where('id = :id')->bind(':id', $noteId, ParameterType::INTEGER)
		)->execute();
		AuditLog::add($db, 'delete', 'note', $noteId, (int) $note->path_id, [
			'before' => ['fault' => (int) $note->fault_id, 'date' => $note->event_date, 'notes' => $note->notes],
		], (int) $user->id);

		return (int) $note->path_id;
	}

	/**
	 * Delete a fault's rows (notes, photos, the fault). Returns its photo files, to remove once the change is committed.
	 *
	 * @return string[]
	 */
	private function deleteFault(int $faultId): array
	{
		$db = $this->getDatabase();
		$files = $this->deletePhotos($faultId, []);

		foreach (['#__ra_footpaths_fault_events' => 'fault_id', '#__ra_footpaths_faults' => 'id'] as $table => $column) {
			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName($table))->where($db->quoteName($column) . ' = :id')->bind(':id', $faultId, ParameterType::INTEGER)
			)->execute();
		}

		return $files;
	}

	/**
	 * Delete a fault's photo rows except those kept. Returns their files.
	 *
	 * @param  int[]  $keep
	 *
	 * @return string[]
	 */
	private function deletePhotos(int $faultId, array $keep): array
	{
		$db = $this->getDatabase();
		$query = $db->getQuery(true)
			->select(['id', 'file'])
			->from($db->quoteName('#__ra_footpaths_fault_photos'))
			->where('fault_id = :id')
			->bind(':id', $faultId, ParameterType::INTEGER);

		if ($keep) {
			$query->whereNotIn('id', $keep);
		}

		$rows = $db->setQuery($query)->loadObjectList();

		if ($rows) {
			$db->setQuery(
				$db->getQuery(true)->delete($db->quoteName('#__ra_footpaths_fault_photos'))->whereIn('id', array_map(static fn ($r) => (int) $r->id, $rows))
			)->execute();
		}

		return array_map(static fn ($r) => (string) $r->file, $rows);
	}

	/** What goes in the change log for an inspection. */
	private function snapshot(?object $i): ?array
	{
		if ($i === null) {
			return null;
		}

		return [
			'inspector' => $i->inspector_id === null ? null : (int) $i->inspector_id,
			'date' => $i->inspection_date,
			'grade' => $i->grade,
			'grade_recorded' => $i->grade_recorded,
			'remarks' => $i->remarks,
			'faults' => array_map(static fn ($f) => [
				'id' => (int) $f->id,
				'type' => $f->fault_type_id === null ? null : (int) $f->fault_type_id,
				'grade' => $f->grade,
				'remarks' => $f->remarks,
				'gridref' => $f->gridref,
				'lat' => $f->lat === null ? null : (float) $f->lat,
				'lng' => $f->lng === null ? null : (float) $f->lng,
				'photos' => $f->photos,
			], $i->faults),
		];
	}
}
