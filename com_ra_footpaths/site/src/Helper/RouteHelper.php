<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/** Links inside the component, kept under the footpaths menu item so the page keeps its menu and modules. */
final class RouteHelper
{
	private static ?int $itemId = null;

	public static function url(string $query, bool $xhtml = true): string
	{
		$itemId = self::itemId();

		return Route::_('index.php?option=com_ra_footpaths' . ($query !== '' ? '&' . $query : '') . ($itemId ? '&Itemid=' . $itemId : ''), $xhtml);
	}

	/** Hidden Itemid input for a GET form, so it stays under the footpaths menu item. */
	public static function itemIdField(): string
	{
		$itemId = self::itemId();

		return $itemId ? '<input type="hidden" name="Itemid" value="' . $itemId . '">' : '';
	}

	private static function itemId(): int
	{
		if (self::$itemId !== null) {
			return self::$itemId;
		}

		$menu = Factory::getApplication()->getMenu();
		$active = $menu->getActive();

		if ($active && ($active->query['option'] ?? '') === 'com_ra_footpaths') {
			return self::$itemId = (int) $active->id;
		}

		$item = $menu->getItems(['link'], ['index.php?option=com_ra_footpaths&view=parishes'], true);

		return self::$itemId = $item ? (int) $item->id : 0;
	}
}
