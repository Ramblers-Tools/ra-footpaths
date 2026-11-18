<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Draws a map with the Ramblers library (so it gets the same layers, including the Ordnance Survey ones
 * and the club key that RA Tweaks swaps in), and our own script to add paths, the parish boundary and
 * fault positions.
 *
 * Data shape: ['boundary' => GeoJSON|null, 'paths' => [[id, label, grade, colour, date, url, route, current]],
 *              'faults' => [[lat, lng, label, colour]]]. Only short, plain values go in: no free text
 * from the records, since the library writes the data into an inline script.
 */
final class MapHelper
{
	public static function available(): bool
	{
		return class_exists('RLeafletMap');
	}

	public static function render(array $data, string $height = '480px'): string
	{
		if (!self::available()) {
			return '';
		}

		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useScript('com_ra_footpaths.map')->useStyle('com_ra_footpaths.site');

		$map = new \RLeafletMap();
		$map->options->mapHeight = $height;
		$map->options->mouseposition = false;
		$map->options->print = false;
		$map->setCommand('ra.display.footpaths');
		$map->setDataObject($data);

		ob_start();
		$map->display();

		return '<div class="rafp-map">' . ob_get_clean() . '</div>';
	}

	/** Strip anything but plain label characters, for values that go into the map data. */
	public static function plain(string $text): string
	{
		return trim(preg_replace('/[^A-Za-z0-9 .,()\/\'-]/', '', $text));
	}
}
