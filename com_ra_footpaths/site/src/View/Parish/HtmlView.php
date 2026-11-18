<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Parish;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\MapHelper;

class HtmlView extends BaseHtmlView
{
	protected ?object $parish = null;

	/** @var array<int,object> */
	protected array $paths = [];

	/** @var string[] */
	protected array $inspectors = [];

	protected string $mapHtml = '';

	public function display($tpl = null): void
	{
		$id = Factory::getApplication()->getInput()->getInt('id', 0);
		$model = $this->getModel();
		$this->parish = $model->getParish($id);

		if ($this->parish === null) {
			throw new \Exception(Text::_('COM_RA_FOOTPATHS_PARISH_NOT_FOUND'), 404);
		}

		$this->paths = $model->getPaths($id);
		$this->inspectors = $model->getInspectors($id);

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site')->useScript('com_ra_footpaths.list');

		$this->mapHtml = MapHelper::render($this->mapData(), '640px');

		$this->getDocument()->setTitle($this->parish->name . ' footpaths');
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}

	public function pathUrl(int $id): string
	{
		return RouteHelper::url('view=path&id=' . $id);
	}

	public function isDue(?string $date): bool
	{
		return $date === null || $date === '' || strtotime($date) < strtotime('-12 months');
	}

	private function mapData(): array
	{
		$paths = [];

		foreach ($this->paths as $p) {
			$grade = $p->inspection_id ? FootpathsHelper::normalise($p->grade) : 'N';
			$paths[] = [
				'id' => (int) $p->id,
				'label' => MapHelper::plain(FootpathsHelper::pathName($p->type, $p->number)),
				'grade' => $grade,
				'colour' => FootpathsHelper::gradeColour($grade),
				'date' => $p->inspection_date ? HTMLHelper::_('date', $p->inspection_date, 'j M Y') : '',
				'url' => RouteHelper::url('view=path&id=' . (int) $p->id, false),
				'route' => $p->route ? json_decode($p->route) : null,
				'current' => false,
			];
		}

		return [
			'boundary' => $this->parish->boundary ? json_decode($this->parish->boundary) : null,
			'paths' => $paths,
			'faults' => [],
		];
	}
}
