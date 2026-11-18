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
			$send = $this->thumbnail($base, $file) ?? $full;
		}

		$this->app->clearHeaders();
		$this->app->setHeader('Content-Type', 'image/jpeg', true);
		$this->app->setHeader('Content-Length', (string) filesize($send), true);
		$this->app->setHeader('Cache-Control', 'private, max-age=86400', true);
		$this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
		$this->app->sendHeaders();
		readfile($send);
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

		if (!\function_exists('imagecreatefromjpeg') || (!is_dir($dir) && !@mkdir($dir, 0755, true))) {
			return null;
		}

		$image = @imagecreatefromjpeg($base . '/' . $file);

		if ($image === false) {
			return null;
		}

		$image = $this->upright($image, $base . '/' . $file);
		$small = imagescale($image, self::THUMB_WIDTH);

		if ($small === false || !imagejpeg($small, $target, 80)) {
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
