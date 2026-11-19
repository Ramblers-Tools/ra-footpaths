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

		$user = $app->getIdentity();
		$this->canEdit = $user->authorise($this->item ? 'core.edit' : 'core.create', 'com_ra_footpaths');
		$this->parishes = $model->getParishes($id);
		$this->group = FootpathsHelper::inspectorGroup();

		if ($this->item) {
			$this->logins = $model->getLogins($id);
			$this->matches = $model->getMatches($this->item, $this->logins);
			$this->search = trim($app->getInput()->getString('q', ''));
			$this->found = $model->searchUsers($this->search, $this->logins);
		}

		ToolbarHelper::title(
			$this->item ? Text::sprintf('COM_RA_FOOTPATHS_INSPECTOR_TITLE', $this->item->name) : Text::_('COM_RA_FOOTPATHS_INSPECTOR_NEW'),
			'user'
		);
		ToolbarHelper::link('index.php?option=com_ra_footpaths&view=inspectors', 'COM_RA_FOOTPATHS_BACK_TO_INSPECTORS', 'arrow-left');

		parent::display($tpl);
	}
}
