<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/**
 * Serves a fault photo (task=photo.show&id=N[&size=thumb]). The dispatcher has already checked that
 * the user is an inspector. Files sit outside the public folders, so this is the only way to them.
 */
class PhotoController extends BaseController
{
	private const THUMB_WIDTH = 480;

	/** Refuse to decode anything larger than this (pixels and bytes), so one photo can't exhaust PHP's memory. */
	private const MAX_PIXELS = 40_000_000;

	private const MAX_BYTES = 25_000_000;

	public function show(): void
	{
		$id = $this->input->getInt('id', 0);
		$thumb = $this->input->getCmd('size', '') === 'thumb';
		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true)
			->select($db->quoteName('file'))
			->from($db->quoteName('#__ra_footpaths_fault_photos'))
			->where($db->quoteName('id') . ' = :id')
			->bind(':id', $id, \Joomla\Database\ParameterType::INTEGER);
		$file = (string) $db->setQuery($query)->loadResult();

		$base = FootpathsHelper::photoPath();
		$full = $base . '/' . $file;

		if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $file) || !is_file($full)) {
			$this->notFound();

			return;
		}

		$send = $full;

		if ($thumb) {
			// Never fall back to the original: it is larger and could carry metadata the thumbnail strips.
			$send = $this->thumbnail($base, $file);

			if ($send === null) {
				$this->notFound();

				return;
			}
		}

		$handle = @fopen($send, 'rb');
		$size = $handle ? fstat($handle)['size'] : false;

		if ($handle === false || $size === false) {
			$this->notFound();

			return;
		}

		$this->app->clearHeaders();
		$this->app->setHeader('Content-Type', 'image/jpeg', true);
		$this->app->setHeader('Content-Length', (string) $size, true);
		// Private records: browsers must not keep a copy that outlives the login.
		$this->app->setHeader('Cache-Control', 'private, no-store', true);
		$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$this->app->sendHeaders();
		fpassthru($handle);
		fclose($handle);
		$this->app->close();
	}

	/** Makes (once) and returns a smaller copy, without the EXIF data. */
	private function thumbnail(string $base, string $file): ?string
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

		$image = $this->upright($image, $source);
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
	private function upright(\GdImage $image, string $path): \GdImage
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

	private function notFound(): void
	{
		$this->app->setHeader('Status', '404', true);
		$this->app->sendHeaders();
		echo 'Photo not found';
		$this->app->close();
	}
}
