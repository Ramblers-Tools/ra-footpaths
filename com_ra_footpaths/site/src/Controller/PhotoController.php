<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Ramblers\Component\Ra_footpaths\Site\Helper\PhotoFile;

/**
 * Serves a fault photo on the website (task=photo.show&id=N[&size=thumb]). The dispatcher has already
 * checked that the user is an inspector. The app has its own route (AppController::photo).
 */
class PhotoController extends BaseController
{
	public function show(): void
	{
		$path = PhotoFile::find($this->input->getInt('id', 0), $this->input->getCmd('size', '') === 'thumb');

		if ($path === null || !PhotoFile::send($this->app, $path)) {
			$this->app->setHeader('Status', '404', true);
			$this->app->sendHeaders();
			echo 'Photo not found';
			$this->app->close();
		}
	}
}
