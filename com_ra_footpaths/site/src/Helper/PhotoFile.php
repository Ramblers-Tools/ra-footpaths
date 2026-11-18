<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/**
 * Finding and sending a fault photo, for the website (PhotoController) and the app (AppController).
 * Callers check who may see it first. Files sit outside the public folders, so this is the only way
 * to them.
 */
final class PhotoFile
{
	public const THUMB_WIDTH = 480;

	/** Refuse to decode anything larger than this (pixels and bytes), so one photo can't exhaust PHP's memory. */
	private const MAX_PIXELS = 40_000_000;

	private const MAX_BYTES = 25_000_000;

	/**
	 * The file to send for a photo, or null when there's no such photo, its path or parish is
	 * unpublished, the file is missing, or a thumbnail can't be made. Never falls back from a
	 * thumbnail to the original: it is larger and could carry metadata the thumbnail strips.
	 */
	public static function find(int $id, bool $thumb): ?string
	{
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select('ph.file')
			->from($db->quoteName('#__ra_footpaths_fault_photos', 'ph'))
			->join('INNER', $db->quoteName('#__ra_footpaths_faults', 'f'), 'f.id = ph.fault_id')
			->join('INNER', $db->quoteName('#__ra_footpaths_inspections', 'i'), 'i.id = f.inspection_id')
			->join('INNER', $db->quoteName('#__ra_footpaths_paths', 'p'), 'p.id = i.path_id AND p.state = 1')
			->join('INNER', $db->quoteName('#__ra_footpaths_parishes', 'pa'), 'pa.id = p.parish_id AND pa.state = 1')
			->where('ph.id = :id')
			->bind(':id', $id, ParameterType::INTEGER);
		$file = (string) $db->setQuery($query)->loadResult();

		$base = FootpathsHelper::photoPath();

		if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $file) || !is_file($base . '/' . $file)) {
			return null;
		}

		return $thumb ? self::thumbnail($base, $file) : $base . '/' . $file;
	}

	/** Sends the file as a JPEG, never cached, and ends the request. False if it can't be opened. */
	public static function send(CMSApplicationInterface $app, string $path): bool
	{
		$handle = @fopen($path, 'rb');
		$size = $handle ? fstat($handle)['size'] : false;

		if ($handle === false || $size === false) {
			return false;
		}

		$app->clearHeaders();
		$app->setHeader('Content-Type', 'image/jpeg', true);
		$app->setHeader('Content-Length', (string) $size, true);
		// Private records: nothing may keep a copy that outlives the login.
		$app->setHeader('Cache-Control', 'private, no-store', true);
		// An Expires of our own stops the site's .htaccess (mod_expires) adding a year's expiry to JPEGs.
		$app->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT', true);
		$app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$app->sendHeaders();
		fpassthru($handle);
		fclose($handle);
		$app->close();

		return true;
	}

	/** Makes (once) and returns a smaller copy, without the EXIF data. */
	private static function thumbnail(string $base, string $file): ?string
	{
		$dir = $base . '/thumbs';
		$target = $dir . '/' . $file;

		if (is_file($target)) {
			return $target;
		}

		if (!\function_exists('imagecreatefromjpeg') || (!is_dir($dir) && !@mkdir($dir, 0750, true))) {
			return null;
		}

		$source = $base . '/' . $file;
		$info = @getimagesize($source);

		if ($info === false || $info[2] !== IMAGETYPE_JPEG || $info[0] * $info[1] > self::MAX_PIXELS
			|| filesize($source) > self::MAX_BYTES) {
			return null;
		}

		$image = @imagecreatefromjpeg($source);

		if ($image === false) {
			return null;
		}

		$image = self::upright($image, $source);
		$small = imagescale($image, min(self::THUMB_WIDTH, imagesx($image)));

		// Write to a temporary name and rename, so a request at the same moment never sees a half-written file.
		$temp = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';

		if ($small === false || !imagejpeg($small, $temp, 80)) {
			@unlink($temp);

			return null;
		}

		@chmod($temp, 0640);

		if (!@rename($temp, $target)) {
			@unlink($temp);

			return null;
		}

		return $target;
	}

	/** Phone photos often rely on the EXIF orientation flag; turn the pixels instead. */
	private static function upright(\GdImage $image, string $path): \GdImage
	{
		if (!\function_exists('exif_read_data')) {
			return $image;
		}

		$exif = @exif_read_data($path);
		$angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;

		if ($angle === 0) {
			return $image;
		}

		$turned = imagerotate($image, $angle, 0);

		return $turned === false ? $image : $turned;
	}
}
