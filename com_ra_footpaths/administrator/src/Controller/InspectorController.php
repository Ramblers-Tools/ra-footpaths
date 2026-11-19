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
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\AuditLog;
use Ramblers\Component\Ra_footpaths\Administrator\Model\InspectorModel;

/**
 * Save an inspector, and link or unlink their website logins (POST, with the form token); export the list as CSV
 * (GET, with the token in the link).
 */
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
			$id = $this->model()->save($id, $name, (bool) $this->input->post->getInt('active', 0), $parishes, [
				'email' => $this->input->post->getString('email', ''),
				'phone' => $this->input->post->getString('phone', ''),
				'membership_no' => $this->input->post->getString('membership_no', ''),
			]);
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

	/** The inspectors the list's filters show, with contact details, as a CSV download. Logged in the change log. */
	public function export(): void
	{
		$this->checkToken('get');
		$this->allow('core.manage');

		$model = $this->getModel('Inspectors', 'Administrator', ['ignore_request' => true]);
		$filters = $model->getFilters();
		$rows = $model->getExport($filters);
		AuditLog::add(Factory::getContainer()->get('DatabaseDriver'), 'export', 'inspectors', null, null, ['rows' => \count($rows) - 1, 'filters' => $filters]);

		$out = fopen('php://temp', 'w+');
		// Byte order mark, so Excel reads the file as UTF-8.
		fwrite($out, "\xEF\xBB\xBF");

		foreach ($rows as $row) {
			// A cell starting with = + - @ would be run as a formula by a spreadsheet: make it plain text.
			fputcsv($out, array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $row), ',', '"', '');
		}

		rewind($out);
		$csv = stream_get_contents($out);
		fclose($out);

		$this->app->clearHeaders();
		$this->app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
		$this->app->setHeader('Content-Disposition', 'attachment; filename="footpath-inspectors-' . gmdate('Y-m-d') . '.csv"', true);
		$this->app->setHeader('Cache-Control', 'private, no-store', true);
		$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$this->app->sendHeaders();
		echo $csv;
		$this->app->close();
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
