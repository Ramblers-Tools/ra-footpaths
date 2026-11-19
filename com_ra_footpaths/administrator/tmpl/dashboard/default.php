<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$labels = [
	'parishes' => 'Parishes',
	'paths' => 'Paths',
	'inspectors' => 'Inspectors',
	'inspections' => 'Inspections',
	'faults' => 'Faults',
	'photos' => 'Photos',
	'events' => 'Fault follow-up notes',
];
?>
<div class="card">
	<div class="card-body">
		<p><?php echo Text::_('COM_RA_FOOTPATHS_DASHBOARD_INTRO'); ?></p>
		<table class="table w-auto">
			<tbody>
			<?php foreach ($labels as $key => $label) : ?>
				<tr><th scope="row"><?php echo $this->escape($label); ?></th><td class="text-end"><?php echo number_format($this->counts[$key] ?? 0); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><?php echo Text::sprintf('COM_RA_FOOTPATHS_DASHBOARD_UNLINKED', $this->unlinked['active'], $this->unlinked['recent'],
			Route::_('index.php?option=com_ra_footpaths&view=inspectors&filter_status=recent&filter_login=unlinked')); ?></p>
		<p class="text-muted small"><?php echo Text::sprintf('COM_RA_FOOTPATHS_DASHBOARD_PHOTOS', $this->escape($this->photoPath)); ?></p>
		<?php if ($this->photosInSite) : ?>
			<div class="alert alert-warning"><?php echo Text::_('COM_RA_FOOTPATHS_DASHBOARD_PHOTOS_IN_SITE'); ?></div>
		<?php endif; ?>
	</div>
</div>
