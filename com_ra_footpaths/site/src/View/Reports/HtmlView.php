<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Reports;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Site\Model\ReportsModel;

/** Choose a report, then see it on the page, print it (or save it as a PDF from the print window), or download it. */
class HtmlView extends BaseHtmlView
{
	protected array $filters = [];

	/** @var array<int,object> */
	protected array $parishes = [];

	/** @var array<int,object> */
	protected array $faultTypes = [];

	/** Path reports: parish => paths. */
	protected array $paths = [];

	/** Summaries: rows. */
	protected array $rows = [];

	public function display($tpl = null): void
	{
		/** @var ReportsModel $model */
		$model = $this->getModel();
		$this->filters = $model->filters(Factory::getApplication()->getInput());
		$this->parishes = $model->getParishes();
		$this->faultTypes = $model->getFaultTypes();
		$type = $this->filters['type'];

		if ($type === 'summary') {
			$this->rows = $model->getSummary($this->filters);
		} elseif ($type === 'faults') {
			$this->rows = $model->getFaultSummary($this->filters);
		} elseif ($type !== '') {
			$this->paths = $model->getPathReport($this->filters);
		}

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site')->useScript('com_ra_footpaths.reports');

		$this->getDocument()->setTitle($type !== '' ? $this->title() : 'Footpath reports');
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}

	public function title(): string
	{
		return ReportsModel::TYPES[$this->filters['type']][0] ?? 'Footpath reports';
	}

	/** "1 Dec 2025 to 30 Nov 2026" */
	public function period(): string
	{
		return self::date($this->filters['from']) . ' to ' . self::date($this->filters['to']);
	}

	/** What the report covers, in words, for the heading. */
	public function scope(): string
	{
		$names = fn (array $ids, array $all): string => implode(', ', array_map(static fn ($id) => ucfirst((string) $all[$id]->name), $ids));
		$parts = [$this->filters['parishes'] ? $names($this->filters['parishes'], $this->parishes) : 'All parishes'];

		if ($this->filters['faulttypes'] && !\in_array($this->filters['type'], ['summary', 'uninspected'], true)) {
			$parts[] = 'Faults: ' . $names($this->filters['faulttypes'], $this->faultTypes);
		}

		return implode('. ', $parts) . '.';
	}

	public function csvUrl(): string
	{
		return RouteHelper::url('task=reports.csv&' . $this->query());
	}

	public function pathUrl(int $id): string
	{
		return RouteHelper::url('view=path&id=' . $id);
	}

	public function photoUrl(int $id, bool $thumb): string
	{
		return RouteHelper::url('task=photo.show&id=' . $id . ($thumb ? '&size=thumb' : ''));
	}

	public function count(): int
	{
		return array_sum(array_map('count', $this->paths));
	}

	public static function date(?string $d): string
	{
		return $d ? HTMLHelper::_('date', $d, 'j M Y') : '';
	}

	public static function percent(int $n, int $of): string
	{
		return $of > 0 ? round(100 * $n / $of) . '%' : '';
	}

	/** The chosen filters as a query string (for the CSV link). */
	private function query(): string
	{
		$f = $this->filters;

		return http_build_query(['type' => $f['type'], 'from' => $f['from'], 'to' => $f['to'], 'parishes' => $f['parishes'],
			'faulttypes' => $f['faulttypes']], '', '&', PHP_QUERY_RFC3986);
	}
}
