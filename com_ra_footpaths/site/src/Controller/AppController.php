<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\User\User;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\PhotoFile;
use Ramblers\Component\Ra_footpaths\Site\Model\AppModel;
use Ramblers\Component\Ra_tools\Site\Helpers\AppToken;

/**
 * Read-only footpath records for the Ramblers Tools app. Base:
 * index.php?option=com_ra_footpaths&format=json&task=app.<name>
 *
 *   app.me            who is signed in, whether they are an inspector, their own parishes
 *   app.parishes      every parish with its figures, `changed` time and label point
 *   app.boundaries    every parish outline, simplified (ETag / 304)
 *   app.parish&id=N   one parish: figures, full outline, every path with its whole history (ETag / 304)
 *   app.photo&id=N&size=thumb|full   the image itself
 *
 * Signed in with the app's bearer token only (com_ra_tools AppToken, scope "footpaths"); the Joomla
 * session is ignored, so a logged-in browser gets nothing. GET only, no-store, no CORS. The access
 * rule is the website's (FootpathsHelper::canView), checked on every request. Contract: the app
 * repo's footpaths handover and this component's README.
 */
class AppController extends BaseController
{
	/** The signed-in user, or null after an error answer has been sent. */
	private function signedIn(bool $inspectorOnly = true): ?User
	{
		if (!class_exists(AppToken::class)) {
			// com_ra_tools 4.4.0 or later is needed for the app's sign-in.
			$this->json(503, ['error' => 'unavailable']);

			return null;
		}

		if ($this->input->getMethod() !== 'GET') {
			$this->json(405, ['error' => 'invalid_request']);

			return null;
		}

		$user = AppToken::user('footpaths', $error);

		if ($user === null) {
			$this->json($error === 'insufficient_scope' ? 403 : 401, ['error' => $error ?? 'invalid_token']);

			return null;
		}

		if ($inspectorOnly && !FootpathsHelper::canView($user)) {
			$this->json(403, ['error' => 'forbidden']);

			return null;
		}

		return $user;
	}

	private function model(): AppModel
	{
		return new AppModel(['dbo' => Factory::getContainer()->get('DatabaseDriver')]);
	}

	public function me(): void
	{
		$user = $this->signedIn(false);

		if ($user === null) {
			return;
		}

		$inspector = FootpathsHelper::canView($user);
		$model = $this->model();

		$this->json(200, [
			'inspector' => $inspector,
			'inspectors' => $inspector ? $model->linkedInspectors((int) $user->id) : [],
			'my_parishes' => $inspector ? $model->myParishIds((int) $user->id) : [],
			'server_time' => gmdate('Y-m-d\TH:i:s') . '+00:00',
		]);
	}

	public function parishes(): void
	{
		$user = $this->signedIn();

		if ($user !== null) {
			$this->json(200, $this->model()->parishes((int) $user->id));
		}
	}

	public function boundaries(): void
	{
		if ($this->signedIn() !== null) {
			$this->json(200, $this->model()->boundaries(), true);
		}
	}

	public function parish(): void
	{
		if ($this->signedIn() === null) {
			return;
		}

		$parish = $this->model()->parish($this->input->getInt('id', 0));

		if ($parish === null) {
			$this->json(404, ['error' => 'not_found']);

			return;
		}

		$this->json(200, $parish, true);
	}

	public function photo(): void
	{
		if ($this->signedIn() === null) {
			return;
		}

		$path = PhotoFile::find($this->input->getInt('id', 0), $this->input->getCmd('size', 'thumb') !== 'full');

		if ($path === null || !PhotoFile::send($this->app, $path)) {
			$this->json(404, ['error' => 'not_found']);
		}
	}

	/**
	 * Sends JSON and ends the request. With $etag, answers 304 (no body) when the app already has
	 * this exact content: it keeps its own copy, so no-store still applies to everything else.
	 */
	private function json(int $status, $body, bool $etag = false): void
	{
		$json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$this->app->clearHeaders();
		$this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		$this->app->setHeader('Cache-Control', 'no-store', true);
		$this->app->setHeader('Pragma', 'no-cache', true);
		$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);

		if ($etag && $status === 200) {
			$tag = '"' . hash('sha256', $json) . '"';
			$this->app->setHeader('ETag', $tag, true);
			$sent = array_map(static fn ($t) => trim(preg_replace('/^W\//', '', trim($t))), explode(',', (string) $this->input->server->getString('HTTP_IF_NONE_MATCH', '')));

			if (\in_array($tag, $sent, true)) {
				$this->app->setHeader('Status', '304', true);
				$this->app->sendHeaders();
				$this->app->close();
			}
		}

		$this->app->setHeader('Status', (string) $status, true);
		$this->app->sendHeaders();
		echo $json;
		$this->app->close();
	}
}
