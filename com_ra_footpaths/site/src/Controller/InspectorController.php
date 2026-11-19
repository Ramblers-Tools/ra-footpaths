<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Ramblers\Component\Ra_footpaths\Administrator\Controller\InspectorController as AdminController;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;

/** Save an inspector and link or unlink logins from the website: footpath admins only. */
class InspectorController extends AdminController
{
	protected function allow(string $action): void
	{
		if (!FootpathsHelper::canManage($this->app->getIdentity())) {
			throw new \RuntimeException(Text::_('JLIB_APPLICATION_ERROR_ACCESS_FORBIDDEN'), 403);
		}
	}

	protected function back(int $id, string $message, string $type = 'message'): void
	{
		$this->setRedirect(RouteHelper::url('view=inspector' . ($id > 0 ? '&id=' . $id : ''), false), $message, $type);
	}
}
