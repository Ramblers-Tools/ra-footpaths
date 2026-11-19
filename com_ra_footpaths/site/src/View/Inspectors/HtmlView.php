<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Inspectors;

\defined('_JEXEC') or die;

use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Administrator\View\Inspectors\HtmlView as AdminView;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;

/** The administrator page, for footpath admins on the website (the Dispatcher checks they are one). */
class HtmlView extends AdminView
{
	public bool $frontend = true;

	protected function addToolbar(): void
	{
		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site');
		$this->getDocument()->setTitle('Footpath inspectors');
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');
	}

	public function canDo(string $action): bool
	{
		return FootpathsHelper::canManage($this->getCurrentUser());
	}

	public function link(string $query = ''): string
	{
		return RouteHelper::url($query);
	}

	public function hiddenFields(string $view): string
	{
		return parent::hiddenFields($view) . RouteHelper::itemIdField();
	}
}
