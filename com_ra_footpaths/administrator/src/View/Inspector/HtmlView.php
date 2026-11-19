<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\View\Inspector;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/** One inspector: name, active, parishes, and website logins (linked, same-name suggestions, search). */
class HtmlView extends BaseHtmlView
{
	protected ?object $item = null;

	protected array $logins = [];

	protected array $matches = [];

	protected array $found = [];

	protected string $search = '';

	protected array $parishes = [];

	protected int $group = 0;

	protected bool $canEdit = false;

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$id = $app->getInput()->getInt('id', 0);

		/** @var \Ramblers\Component\Ra_footpaths\Administrator\Model\InspectorModel $model */
		$model = $this->getModel();
		$this->item = $model->getItem($id);

		if ($id > 0 && !$this->item) {
			throw new \RuntimeException(Text::_('COM_RA_FOOTPATHS_INSPECTOR_NOT_FOUND'), 404);
		}

		$this->canEdit = $this->canDo($this->item ? 'core.edit' : 'core.create');
		$this->parishes = $model->getParishes($id);
		$this->group = FootpathsHelper::inspectorGroup();

		if ($this->item) {
			$this->logins = $model->getLogins($id);
			$this->matches = $model->getMatches($this->item, $this->logins);
			$this->search = trim($app->getInput()->getString('q', ''));
			$this->found = $model->searchUsers($this->search, $this->logins);
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	/** True on the website (footpath admins), false in the administrator. */
	public bool $frontend = false;

	protected function addToolbar(): void
	{
		ToolbarHelper::title(
			$this->item ? Text::sprintf('COM_RA_FOOTPATHS_INSPECTOR_TITLE', $this->item->name) : Text::_('COM_RA_FOOTPATHS_INSPECTOR_NEW'),
			'user'
		);
		ToolbarHelper::link('index.php?option=com_ra_footpaths&view=inspectors', 'COM_RA_FOOTPATHS_BACK_TO_INSPECTORS', 'arrow-left');
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
