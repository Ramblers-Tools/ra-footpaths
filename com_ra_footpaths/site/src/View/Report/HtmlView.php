<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Report;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\MapHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Site\Model\ParishModel;

/**
 * "Record an inspection" for one path. DEMO: the form works in the browser (faults, map position, photos)
 * but nothing is sent to the server; submitting shows what would have been saved.
 */
class HtmlView extends BaseHtmlView
{
	protected ?object $path = null;

	protected ?object $last = null;

	/** @var array<int,object> */
	protected array $faultTypes = [];

	/** @var array<int,object> */
	protected array $inspectors = [];

	protected int $myInspector = 0;

	protected string $mapHtml = '';

	public function display($tpl = null): void
	{
		$id = Factory::getApplication()->getInput()->getInt('id', 0);
		$model = $this->getModel();
		$this->path = $model->getPath($id);

		if ($this->path === null) {
			throw new \Exception(Text::_('COM_RA_FOOTPATHS_PATH_NOT_FOUND'), 404);
		}

		$this->last = $model->getInspections($id)[0] ?? null;
		$this->faultTypes = $model->getFaultTypes();
		$this->inspectors = $model->getInspectors();

		$mine = $model->getMyInspectorIds((int) Factory::getApplication()->getIdentity()->id);
		$this->myInspector = \count($mine) === 1 ? $mine[0] : 0;

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site')->useScript('com_ra_footpaths.report');

		$this->mapHtml = MapHelper::render($this->mapData(), '380px');

		$this->getDocument()->setTitle('Record an inspection: ' . $this->name() . ', ' . $this->path->parish);
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}

	public function name(): string
	{
		return FootpathsHelper::pathName($this->path->type, $this->path->number);
	}

	public function pathUrl(): string
	{
		return RouteHelper::url('view=path&id=' . (int) $this->path->id);
	}

	public function parishUrl(): string
	{
		return RouteHelper::url('view=parish&id=' . (int) $this->path->parish_id);
	}

	public function date(?string $d): string
	{
		return $d ? HTMLHelper::_('date', $d, 'j M Y') : 'no date';
	}

	/**
	 * The last inspection's faults, for "copy from the last inspection". Goes into a JSON script block
	 * (json_encode with the HEX flags, so the free text can't close the tag).
	 */
	public function lastFaultsJson(): string
	{
		$types = [];

		foreach ($this->faultTypes as $t) {
			$types[strtolower($t->name)] = (int) $t->id;
		}

		$faults = [];

		foreach ($this->last->faults ?? [] as $f) {
			$faults[] = [
				'type' => $types[strtolower((string) $f->type)] ?? 0,
				'grade' => \in_array($f->grade, ['B', 'C', 'D'], true) ? $f->grade : '',
				'remarks' => trim((string) $f->remarks),
				'lat' => $f->lat !== null && (float) $f->lat !== 0.0 ? (float) $f->lat : null,
				'lng' => $f->lng !== null && (float) $f->lng !== 0.0 ? (float) $f->lng : null,
				'gridref' => (string) $f->gridref,
			];
		}

		return json_encode($faults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}

	/** This path highlighted, the parish's others in grey, and "pick" so a tap on the map places a fault. */
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
				'url' => '',
				'route' => $p->route ? json_decode($p->route) : null,
				'current' => $current,
			];
		}

		usort($paths, static fn ($a, $b) => (int) $a['current'] <=> (int) $b['current']);

		return ['boundary' => null, 'paths' => $paths, 'faults' => [], 'pick' => true];
	}
}
