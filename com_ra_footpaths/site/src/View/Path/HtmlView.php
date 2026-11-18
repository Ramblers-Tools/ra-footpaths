<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Path;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\MapHelper;
use Ramblers\Component\Ra_footpaths\Site\Model\ParishModel;

class HtmlView extends BaseHtmlView
{
	protected ?object $path = null;

	/** @var array<int,object> */
	protected array $inspections = [];

	protected string $mapHtml = '';

	public function display($tpl = null): void
	{
		$id = Factory::getApplication()->getInput()->getInt('id', 0);
		$this->path = $this->getModel()->getPath($id);

		if ($this->path === null) {
			throw new \Exception(Text::_('COM_RA_FOOTPATHS_PATH_NOT_FOUND'), 404);
		}

		$this->inspections = $this->getModel()->getInspections($id);

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site');

		$this->mapHtml = MapHelper::render($this->mapData(), '420px');

		$this->getDocument()->setTitle($this->name() . ', ' . $this->path->parish);
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}

	public function name(): string
	{
		return FootpathsHelper::pathName($this->path->type, $this->path->number);
	}

	public function parishUrl(): string
	{
		return RouteHelper::url('view=parish&id=' . (int) $this->path->parish_id);
	}

	public function photoUrl(int $id, bool $thumb): string
	{
		return RouteHelper::url('task=photo.show&id=' . $id . ($thumb ? '&size=thumb' : ''));
	}

	/** The parish's other paths (grey) for context, this path highlighted, and the latest faults' positions. */
	private function mapData(): array
	{
		$parish = new ParishModel(['dbo' => Factory::getContainer()->get('DatabaseDriver')]);
		$paths = [];

		foreach ($parish->getPaths((int) $this->path->parish_id) as $p) {
			$current = (int) $p->id === (int) $this->path->id;
			$grade = $p->inspection_id ? FootpathsHelper::normalise($p->grade) : 'N';
			$paths[] = [
				'id' => (int) $p->id,
				'label' => MapHelper::plain(FootpathsHelper::pathName($p->type, $p->number)),
				'grade' => $grade,
				'colour' => $current ? FootpathsHelper::gradeColour($grade) : '#9e9e9e',
				'date' => $p->inspection_date ? HTMLHelper::_('date', $p->inspection_date, 'j M Y') : '',
				'url' => RouteHelper::url('view=path&id=' . (int) $p->id, false),
				'route' => $p->route ? json_decode($p->route) : null,
				'current' => $current,
			];
		}

		// Current path drawn last so it sits on top.
		usort($paths, static fn ($a, $b) => (int) $a['current'] <=> (int) $b['current']);

		$faults = [];

		foreach ($this->inspections as $inspection) {
			foreach ($inspection->faults as $fault) {
				if ($fault->lat === null || $fault->lng === null || (float) $fault->lat === 0.0) {
					continue;
				}

				$date = $inspection->inspection_date ? HTMLHelper::_('date', $inspection->inspection_date, 'j M Y') : '';
				$faults[] = [
					'lat' => (float) $fault->lat,
					'lng' => (float) $fault->lng,
					'label' => MapHelper::plain(ucfirst((string) $fault->type) . ', ' . $date),
					'colour' => FootpathsHelper::gradeColour($fault->grade ?: $inspection->grade),
					// The inspection's report further down this page.
					'report' => 'rafp-inspection-' . (int) $inspection->id,
				];
			}

			if (\count($faults) >= 40) {
				break;
			}
		}

		return ['boundary' => null, 'paths' => $paths, 'faults' => $faults];
	}
}
