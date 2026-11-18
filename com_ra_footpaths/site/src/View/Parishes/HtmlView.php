<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Parishes;

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_footpaths\Site\Helper\MapHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;

class HtmlView extends BaseHtmlView
{
	/**
	 * Map shading: share of a parish's paths inspected in the last 12 months, best band first.
	 * [lowest percentage, colour, legend text]
	 */
	public const BANDS = [
		[75, '#2e7d32', '75% or more'],
		[50, '#f9a825', '50 to 74%'],
		[25, '#ef6c00', '25 to 49%'],
		[0, '#c62828', 'under 25%'],
	];

	/** @var array<int,object> */
	protected array $items = [];

	protected string $mapHtml = '';

	/** @var array<int,object> The signed-in inspector's own parishes (empty for everyone else). */
	protected array $mine = [];

	public function display($tpl = null): void
	{
		$model = $this->getModel();
		$this->items = $model->getItems();
		$myIds = $model->getMyParishIds((int) $this->getCurrentUser()->id);
		$this->mine = array_values(array_filter($this->items, static fn ($row) => \in_array((int) $row->id, $myIds, true)));

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site')->useScript('com_ra_footpaths.list');

		$this->mapHtml = MapHelper::render($this->mapData($model->getBoundaries()), '680px');

		$this->getDocument()->setTitle('Footpath inspections');
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}

	public static function percent(object $row): ?int
	{
		return (int) $row->paths ? (int) round(100 * (int) $row->recent / (int) $row->paths) : null;
	}

	public static function bandColour(?int $percent): string
	{
		if ($percent !== null) {
			foreach (self::BANDS as [$min, $colour]) {
				if ($percent >= $min) {
					return $colour;
				}
			}
		}

		return '#757575';
	}

	public function isMine(object $row): bool
	{
		foreach ($this->mine as $m) {
			if ((int) $m->id === (int) $row->id) {
				return true;
			}
		}

		return false;
	}

	/** @param array<int,string> $boundaries */
	private function mapData(array $boundaries): array
	{
		$parishes = [];

		foreach ($this->items as $row) {
			if (empty($boundaries[$row->id])) {
				continue;
			}

			$percent = self::percent($row);
			$parishes[] = [
				'name' => MapHelper::plain($row->name),
				'url' => RouteHelper::url('view=parish&id=' . (int) $row->id, false),
				'colour' => self::bandColour($percent),
				'paths' => (int) $row->paths,
				'recent' => (int) $row->recent,
				'percent' => $percent,
				'never' => (int) $row->never,
				'last' => $row->last_date ? HTMLHelper::_('date', $row->last_date, 'j M Y') : '',
				'boundary' => json_decode($boundaries[$row->id]),
				'mine' => $this->isMine($row),
			];
		}

		return ['parishes' => $parishes];
	}
}
