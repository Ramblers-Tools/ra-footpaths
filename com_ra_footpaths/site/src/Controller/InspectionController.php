<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Site\Model\InspectionModel;

/**
 * Saving inspections and follow-up notes. POST only, with the form token; the dispatcher has already checked that the
 * user may see the records, and the model checks whose inspection or note it is.
 *
 * save answers in JSON (the form sends photos with fetch, so it can shrink them first); the others redirect.
 */
class InspectionController extends BaseController
{
	public function save(): void
	{
		if (!$this->checkToken('post', false)) {
			$this->json(403, ['error' => 'Your session has run out. Copy anything you typed, then reload the page and sign in again.']);
		}

		$post = $this->input->post;
		$user = $this->app->getIdentity();
		$id = $post->getInt('id', 0);
		$pathId = $post->getInt('path_id', 0);
		$faults = json_decode($post->getRaw('faults', '[]'), true);
		$in = [
			'date' => $post->getString('date', ''),
			'inspector' => $post->getInt('inspector', 0),
			'grade' => $post->getCmd('grade', ''),
			'remarks' => $post->getRaw('remarks', ''),
		];

		try {
			$model = $this->model();
			$data = $model->validate($user, $id, $pathId, $in, \is_array($faults) ? $faults : [], (array) $this->input->files->get('photos', [], 'raw'));
			// Ask about another inspection on the same day only for a new one, or when the date is changed.
			$dateChanged = $data['existing'] === null || $data['existing']->inspection_date !== $data['inspection_date'];
			$sameDay = $dateChanged ? $model->countSameDay($pathId, $data['inspection_date'], $id) : 0;

			if ($sameDay > 0 && !$post->getInt('same_day_ok', 0)) {
				$this->json(200, ['sameDay' => $sameDay]);
			}

			$id = $model->save($user, $data);
		} catch (\RuntimeException $e) {
			$code = \in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 400;
			$this->json($code, ['error' => $e->getMessage()]);
		} catch (\Throwable $e) {
			Log::add('RA Footpaths save failed: ' . $e->getMessage(), Log::ERROR, 'com_ra_footpaths');
			$this->json(500, ['error' => 'Something went wrong and the inspection was not saved. Please tell the webmaster.']);
		}

		$this->flash($post->getInt('id', 0) > 0 ? 'Inspection updated.' : 'Inspection saved. Thank you.');
		$this->json(200, ['ok' => true, 'redirect' => RouteHelper::url('view=path&id=' . $pathId, false) . '#rafp-inspection-' . $id]);
	}

	public function delete(): void
	{
		$this->checkToken();
		$id = $this->input->post->getInt('id', 0);
		$pathId = $this->input->post->getInt('path_id', 0);

		try {
			$pathId = $this->model()->delete($this->app->getIdentity(), $id);
		} catch (\RuntimeException $e) {
			$this->setRedirect(RouteHelper::url('view=report&id=' . $pathId . '&inspection=' . $id, false), $e->getMessage(), 'error');

			return;
		}

		$this->setRedirect(RouteHelper::url('view=path&id=' . $pathId, false), 'Inspection deleted.');
	}

	/** Add a follow-up note to a fault, or change one (note_id). */
	public function note(): void
	{
		$this->checkToken();
		$post = $this->input->post;
		$pathId = $post->getInt('path_id', 0);

		try {
			$pathId = $this->model()->saveNote($this->app->getIdentity(), $post->getInt('note_id', 0), $post->getInt('fault_id', 0),
				$post->getString('date', ''), $post->getRaw('notes', ''));
		} catch (\RuntimeException $e) {
			$this->setRedirect(RouteHelper::url('view=path&id=' . $pathId, false), $e->getMessage(), 'error');

			return;
		}

		$this->setRedirect(RouteHelper::url('view=path&id=' . $pathId, false) . '#rafp-fault-' . $post->getInt('fault_id', 0), 'Note saved.');
	}

	public function deletenote(): void
	{
		$this->checkToken();
		$pathId = $this->input->post->getInt('path_id', 0);

		try {
			$pathId = $this->model()->deleteNote($this->app->getIdentity(), $this->input->post->getInt('note_id', 0));
		} catch (\RuntimeException $e) {
			$this->setRedirect(RouteHelper::url('view=path&id=' . $pathId, false), $e->getMessage(), 'error');

			return;
		}

		$this->setRedirect(RouteHelper::url('view=path&id=' . $pathId, false) . '#rafp-fault-' . $this->input->post->getInt('fault_id', 0), 'Note deleted.');
	}

	private function model(): InspectionModel
	{
		return $this->getModel('Inspection', 'Site', ['ignore_request' => true]);
	}

	/** A message for the next page (the form moves there itself after a JSON reply). */
	private function flash(string $message): void
	{
		$this->app->enqueueMessage($message, 'success');
		$this->app->getSession()->set('application.queue', $this->app->getMessageQueue());
	}

	/** @return never */
	private function json(int $status, array $body): void
	{
		$this->app->clearHeaders();
		$this->app->setHeader('Status', (string) $status, true);
		$this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		$this->app->setHeader('Cache-Control', 'no-store', true);
		$this->app->sendHeaders();
		echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		$this->app->close();
	}
}
