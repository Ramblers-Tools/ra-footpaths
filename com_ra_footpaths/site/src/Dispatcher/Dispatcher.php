<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Dispatcher;

\defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/**
 * Every page and task of the component is for footpath inspectors only. Hiding the menu item is not
 * enough, so the check is made here for every request, whatever the URL.
 */
class Dispatcher extends ComponentDispatcher
{
	protected function checkAccess(): void
	{
		parent::checkAccess();

		$user = $this->app->getIdentity();

		if (FootpathsHelper::canView($user)) {
			return;
		}

		if ($user === null || $user->guest) {
			$return = base64_encode(Uri::getInstance()->toString());
			$this->app->enqueueMessage(Text::_('COM_RA_FOOTPATHS_LOGIN_FIRST'), 'notice');
			$this->app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
		}

		throw new \Exception(Text::_('COM_RA_FOOTPATHS_NOT_ALLOWED'), 403);
	}
}
