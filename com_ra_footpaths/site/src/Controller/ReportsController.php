<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_footpaths\Site\Model\ReportsModel;

/** A report as a spreadsheet (CSV). Read only; the dispatcher has already checked the user may see the records. */
class ReportsController extends BaseController
{
	public function csv(): void
	{
		/** @var ReportsModel $model */
		$model = $this->getModel('Reports', 'Site', ['ignore_request' => true]);
		$filters = $model->filters($this->input);

		if (!$filters['run']) {
			throw new \RuntimeException('Choose a report first.', 400);
		}

		$out = fopen('php://temp', 'w+');
		// Byte order mark, so Excel reads the file as UTF-8.
		fwrite($out, "\xEF\xBB\xBF");

		foreach ($model->getCsv($filters) as $row) {
			// A cell starting with = + - @ would be run as a formula by a spreadsheet: make it plain text.
			fputcsv($out, array_map(static fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v, $row), ',', '"', '');
		}

		rewind($out);
		$csv = stream_get_contents($out);
		fclose($out);

		$name = 'footpaths-' . $filters['type'] . '-' . $filters['from'] . '-to-' . $filters['to'] . '.csv';
		$this->app->clearHeaders();
		$this->app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
		$this->app->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true);
		$this->app->setHeader('Cache-Control', 'private, no-store', true);
		$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$this->app->sendHeaders();
		echo $csv;
		$this->app->close();
	}
}
