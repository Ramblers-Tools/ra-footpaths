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
use Joomla\CMS\Router\Route;
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

		$this->addToolbar();

		parent::display($tpl);
	}

	/** True on the website (footpath admins), false in the administrator. */
	public bool $frontend = false;

	protected function addToolbar(): void
	{
		ToolbarHelper::title(Text::_('COM_RA_FOOTPATHS_INSPECTORS'), 'users');
		$user = Factory::getApplication()->getIdentity();

		if ($this->canDo('core.create')) {
			ToolbarHelper::link('index.php?option=com_ra_footpaths&view=inspector', 'COM_RA_FOOTPATHS_INSPECTOR_NEW', 'new');
		}

		if ($user->authorise('core.admin', 'com_ra_footpaths')) {
			ToolbarHelper::preferences('com_ra_footpaths');
		}
	}

	/** May the current user do this (core.create, core.edit)? */
	public function canDo(string $action): bool
	{
		return Factory::getApplication()->getIdentity()->authorise($action, 'com_ra_footpaths');
	}

	/** A link inside the component; $query without option, e.g. "view=inspector&id=3". */
	public function link(string $query = ''): string
	{
		return Route::_('index.php?option=com_ra_footpaths' . ($query !== '' ? '&' . $query : ''));
	}

	/** Hidden inputs a GET form needs to come back to $view. */
	public function hiddenFields(string $view): string
	{
		return '<input type="hidden" name="option" value="com_ra_footpaths"><input type="hidden" name="view" value="' . htmlspecialchars($view) . '">';
	}
}
