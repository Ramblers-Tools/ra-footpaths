<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_footpaths\Administrator\Model\InspectorModel;

/** Save an inspector, and link or unlink their website logins. POST only, with the form token. */
class InspectorController extends BaseController
{
	public function save(): void
	{
		$this->checkToken();
		$id = $this->input->post->getInt('id', 0);
		$this->allow($id > 0 ? 'core.edit' : 'core.create');

		$name = trim($this->input->post->getString('name', ''));

		if ($name === '') {
			$this->back($id, Text::_('COM_RA_FOOTPATHS_INSPECTOR_NAME_REQUIRED'), 'error');

			return;
		}

		$parishes = (array) $this->input->post->get('parishes', [], 'array');

		try {
			$id = $this->model()->save($id, $name, (bool) $this->input->post->getInt('active', 0), $parishes);
		} catch (\Throwable $e) {
			$this->back($id, $e->getMessage(), 'error');

			return;
		}

		$this->back($id, Text::_('COM_RA_FOOTPATHS_INSPECTOR_SAVED'));
	}

	public function link(): void
	{
		$this->checkToken();
		$this->allow('core.edit');
		$id = $this->input->post->getInt('id', 0);

		try {
			$result = $this->model()->link($id, $this->input->post->getInt('user_id', 0));
		} catch (\Throwable $e) {
			$this->back($id, $e->getMessage(), 'error');

			return;
		}

		$messages = [
			'linked' => ['COM_RA_FOOTPATHS_INSPECTOR_LINKED', 'message'],
			'nogroup' => ['COM_RA_FOOTPATHS_INSPECTOR_LINKED_NO_GROUP', 'warning'],
			'missing' => ['COM_RA_FOOTPATHS_INSPECTOR_LINK_FAILED', 'error'],
		];
		$this->back($id, Text::_($messages[$result][0]), $messages[$result][1]);
	}

	public function unlink(): void
	{
		$this->checkToken();
		$this->allow('core.edit');
		$id = $this->input->post->getInt('id', 0);

		try {
			$this->model()->unlink($id, $this->input->post->getInt('user_id', 0));
		} catch (\Throwable $e) {
			$this->back($id, $e->getMessage(), 'error');

			return;
		}

		$this->back($id, Text::_('COM_RA_FOOTPATHS_INSPECTOR_UNLINKED'));
	}

	protected function model(): InspectorModel
	{
		return $this->getModel('Inspector', 'Administrator', ['ignore_request' => true]);
	}

	protected function allow(string $action): void
	{
		if (!$this->app->getIdentity()->authorise($action, 'com_ra_footpaths')) {
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

	protected function back(int $id, string $message, string $type = 'message'): void
	{
		$url = 'index.php?option=com_ra_footpaths&view=inspector' . ($id > 0 ? '&id=' . $id : '');
		$this->setRedirect(Route::_($url, false), $message, $type);
	}
}
