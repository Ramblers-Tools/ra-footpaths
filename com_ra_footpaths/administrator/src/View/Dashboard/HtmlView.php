<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Administrator\View\Dashboard;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/** Counts of what is in the tables, so an import can be checked at a glance. */
class HtmlView extends BaseHtmlView
{
	/** @var array<string,int> */
	protected array $counts = [];

	protected string $photoPath = '';

	/** True when the photo folder is inside the website, where only .htaccess (Apache) keeps it private. */
	protected bool $photosInSite = false;

	public function display($tpl = null): void
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$tables = [
			'parishes' => '#__ra_footpaths_parishes',
			'paths' => '#__ra_footpaths_paths',
			'inspectors' => '#__ra_footpaths_inspectors',
			'inspections' => '#__ra_footpaths_inspections',
			'faults' => '#__ra_footpaths_faults',
			'photos' => '#__ra_footpaths_fault_photos',
			'events' => '#__ra_footpaths_fault_events',
		];

		foreach ($tables as $key => $table) {
			$this->counts[$key] = (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName($table)))->loadResult();
		}

		$this->photoPath = FootpathsHelper::photoPath();
		$root = (realpath(JPATH_ROOT) ?: JPATH_ROOT) . '/';
		$this->photosInSite = str_starts_with((realpath($this->photoPath) ?: $this->photoPath) . '/', $root);

		ToolbarHelper::title(Text::_('COM_RA_FOOTPATHS'), 'map');

		if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_ra_footpaths')) {
			ToolbarHelper::preferences('com_ra_footpaths');
		}

		parent::display($tpl);
	}
}
