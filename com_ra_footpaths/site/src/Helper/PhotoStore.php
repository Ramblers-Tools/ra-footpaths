<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Helper;

\defined('_JEXEC') or die;

use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

/**
 * Storing an uploaded fault photo. The browser already shrinks photos (footpaths-report.js); here every upload is
 * decoded and saved again as a new JPEG, at most LONG_EDGE pixels on its longest side. Nothing of the original file
 * is kept, so no EXIF data (camera, GPS position, time) survives. Files go in the private photo folder.
 */
final class PhotoStore
{
	public const LONG_EDGE = 2000;

	public const MAX_BYTES = 25_000_000;

	public const MAX_PER_FAULT = 6;

	private const MAX_PIXELS = 40_000_000;

	/**
	 * @param  array{name?:string,tmp_name?:string,error?:int,size?:int}  $upload  one entry from the uploaded files
	 *
	 * @return array{file:string,width:int,height:int,bytes:int}
	 *
	 * @throws \RuntimeException with wording for the inspector
	 */
	public static function save(array $upload, int $faultId): array
	{
		$name = (string) ($upload['name'] ?? 'photo');
		$error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);

		if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
			throw new \RuntimeException('The photo "' . $name . '" is too large.');
		}

		$tmp = (string) ($upload['tmp_name'] ?? '');

		if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
			throw new \RuntimeException('The photo "' . $name . '" did not arrive. Please try again.');
		}

		if (filesize($tmp) > self::MAX_BYTES) {
			throw new \RuntimeException('The photo "' . $name . '" is too large (the limit is 25 MB).');
		}

		$info = @getimagesize($tmp);

		if ($info === false || !\in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
			throw new \RuntimeException('"' . $name . '" is not a photo this site can read. Please use a JPEG.');
		}

		if ($info[0] * $info[1] > self::MAX_PIXELS) {
			throw new \RuntimeException('The photo "' . $name . '" is too large (more than 40 megapixels).');
		}

		$image = @imagecreatefromstring((string) file_get_contents($tmp));

		if ($image === false) {
			throw new \RuntimeException('The photo "' . $name . '" could not be read.');
		}

		if ($info[2] === IMAGETYPE_JPEG) {
			$image = self::upright($image, $tmp);
		}

		$w = imagesx($image);
		$h = imagesy($image);
		$scale = min(1, self::LONG_EDGE / max($w, $h));

		if ($scale < 1) {
			$small = imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));

			if ($small !== false) {
				$image = $small;
			}
		}

		// PNG or WebP with transparency: put it on white, since JPEG has none.
		$flat = imagecreatetruecolor(imagesx($image), imagesy($image));
		imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
		imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

		$dir = FootpathsHelper::photoPath();

		if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
			throw new \RuntimeException('The photo folder is missing. Please tell the webmaster.');
		}

		$file = 'fault-' . $faultId . '-' . bin2hex(random_bytes(6)) . '.jpg';
		$temp = $dir . '/' . $file . '.tmp';

		if (!imagejpeg($flat, $temp, 85) || !@rename($temp, $dir . '/' . $file)) {
			@unlink($temp);

			throw new \RuntimeException('The photo "' . $name . '" could not be saved. Please tell the webmaster.');
		}

		@chmod($dir . '/' . $file, 0640);

		return ['file' => $file, 'width' => imagesx($flat), 'height' => imagesy($flat), 'bytes' => (int) filesize($dir . '/' . $file)];
	}

	/** Deletes a stored photo and its thumbnail. */
	public static function remove(string $file): void
	{
		if ($file === '' || !preg_match('/^[A-Za-z0-9_.-]+$/', $file)) {
			return;
		}

		$dir = FootpathsHelper::photoPath();
		@unlink($dir . '/' . $file);
		@unlink($dir . '/thumbs/' . $file);
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
