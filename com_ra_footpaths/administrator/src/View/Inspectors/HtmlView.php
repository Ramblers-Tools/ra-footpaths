<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\View\Inspectors;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/** Inspectors, with filters, so each can be linked to a website login and given parishes. */
class HtmlView extends BaseHtmlView
{
	protected array $items = [];

	protected array $filters = [];

	/** @var array<int,string> */
	protected array $parishes = [];

	protected array $unlinked = [];

	protected int $group = 0;

	public function display($tpl = null): void
	{
		/** @var \Ramblers\Component\Ra_footpaths\Administrator\Model\InspectorsModel $model */
		$model = $this->getModel();
		$this->filters = $model->getFilters();
		$this->items = $model->getItems($this->filters);
		$this->parishes = $model->getParishes();
		$this->unlinked = $model->getUnlinkedCounts();
		$this->group = FootpathsHelper::inspectorGroup();

		ToolbarHelper::title(Text::_('COM_RA_FOOTPATHS_INSPECTORS'), 'users');
		$user = Factory::getApplication()->getIdentity();

		if ($user->authorise('core.create', 'com_ra_footpaths')) {
			ToolbarHelper::link('index.php?option=com_ra_footpaths&view=inspector', 'COM_RA_FOOTPATHS_INSPECTOR_NEW', 'new');
		}

		if ($user->authorise('core.admin', 'com_ra_footpaths')) {
			ToolbarHelper::preferences('com_ra_footpaths');
		}

		parent::display($tpl);
	}
}
