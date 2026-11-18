<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

class DisplayController extends BaseController
{
	protected $default_view = 'parishes';

	public function display($cachable = false, $urlparams = [])
	{
		// Records are for inspectors only, so never cache the pages.
		return parent::display(false, $urlparams);
	}
}
