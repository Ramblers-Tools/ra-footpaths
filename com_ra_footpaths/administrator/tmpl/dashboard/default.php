<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

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
		<p class="text-muted small"><?php echo Text::sprintf('COM_RA_FOOTPATHS_DASHBOARD_PHOTOS', $this->escape($this->photoPath)); ?></p>
	</div>
</div>
