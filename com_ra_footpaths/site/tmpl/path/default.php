<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;

$path = $this->path;
$latest = $this->inspections[0] ?? null;
$showOpen = 8;
$date = static fn (?string $d): string => $d ? HTMLHelper::_('date', $d, 'j M Y') : 'No date';

$renderInspection = function (object $inspection) use ($date): void {
	?>
	<article class="rafp-inspection">
		<header>
			<?php echo FootpathsHelper::gradeChip($inspection->grade); ?>
			<h3><?php echo $date($inspection->inspection_date); ?></h3>
			<span class="rafp-by"><?php echo $this->escape((string) ($inspection->inspector ?: 'Unknown inspector')); ?></span>
		</header>
		<?php if (trim((string) $inspection->remarks) !== '') : ?>
			<p><?php echo nl2br($this->escape(trim((string) $inspection->remarks))); ?></p>
		<?php endif; ?>
		<?php if ($inspection->faults) : ?>
			<ul class="rafp-faults">
			<?php foreach ($inspection->faults as $fault) : ?>
				<li class="rafp-fault">
					<div class="rafp-fault-head">
						<?php echo FootpathsHelper::gradeChip($fault->grade ?: $inspection->grade); ?>
						<span><?php echo $this->escape(ucfirst((string) ($fault->type ?: 'Fault'))); ?></span>
						<?php if ($fault->gridref) : ?><span class="rafp-gridref"><?php echo $this->escape($fault->gridref); ?></span><?php endif; ?>
					</div>
					<?php if (trim((string) $fault->remarks) !== '') : ?>
						<p><?php echo nl2br($this->escape(trim((string) $fault->remarks))); ?></p>
					<?php endif; ?>
					<?php if ($fault->photos) : ?>
						<div class="rafp-photos">
						<?php foreach ($fault->photos as $photo) : ?>
							<?php // data-ecr-lightbox: the ECR template opens it in its photo popup, with arrows through every photo on the page. Other templates: a new tab. ?>
							<a href="<?php echo $this->photoUrl((int) $photo->id, false); ?>" target="_blank" rel="noopener" data-ecr-lightbox="rafp-photos" data-caption="<?php echo $this->escape(ucfirst((string) ($fault->type ?: 'Fault')) . ', ' . $date($inspection->inspection_date)); ?>">
								<img src="<?php echo $this->photoUrl((int) $photo->id, true); ?>" alt="Photo of the <?php echo $this->escape((string) $fault->type); ?> fault" loading="lazy">
							</a>
						<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ($fault->events) : ?>
						<ul class="rafp-events">
						<?php foreach ($fault->events as $event) : ?>
							<li><strong><?php echo $date($event->event_date); ?></strong>
								<?php echo $event->reporter ? '(' . $this->escape($event->reporter) . ')' : ''; ?>:
								<?php echo $this->escape((string) $event->notes); ?></li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</article>
	<?php
};
?>
<div class="rafp rafp-path">
	<p class="rafp-crumbs">
		<a href="<?php echo RouteHelper::url('view=parishes'); ?>">All parishes</a> &rsaquo;
		<a href="<?php echo $this->parishUrl(); ?>"><?php echo $this->escape((string) $path->parish); ?></a>
	</p>
	<h1><?php echo $this->escape($this->name()); ?>, <?php echo $this->escape((string) $path->parish); ?></h1>

	<dl class="rafp-facts">
		<dt>Latest grade</dt>
		<dd><?php echo FootpathsHelper::gradeChip($latest ? $latest->grade : 'N'); ?>
			<?php echo $latest ? $this->escape(FootpathsHelper::gradeLabel($latest->grade)) . ', ' . $date($latest->inspection_date) : 'Not inspected'; ?></dd>
		<?php if ($path->start_gridref || $path->end_gridref) : ?>
			<dt>From / to</dt><dd><?php echo $this->escape(trim($path->start_gridref . ' to ' . $path->end_gridref, ' to')); ?></dd>
		<?php endif; ?>
		<?php if ($path->length_m) : ?>
			<dt>Length</dt><dd><?php echo number_format((int) $path->length_m); ?> m (<?php echo number_format($path->length_m / 1609.344, 2); ?> miles)</dd>
		<?php endif; ?>
		<dt>Inspections</dt><dd><?php echo count($this->inspections); ?></dd>
	</dl>

	<?php echo $this->mapHtml; ?>
	<p class="small text-muted">This path is in its grade colour; the parish's other paths are grey. Dots are fault positions.</p>

	<h2>Inspection history</h2>
	<?php if (!$this->inspections) : ?>
		<p>No inspections recorded for this path.</p>
	<?php endif; ?>

	<?php foreach (array_slice($this->inspections, 0, $showOpen) as $inspection) {
		$renderInspection($inspection);
	} ?>

	<?php if (count($this->inspections) > $showOpen) : ?>
		<details class="rafp-older">
			<summary>Show <?php echo count($this->inspections) - $showOpen; ?> older inspections</summary>
			<?php foreach (array_slice($this->inspections, $showOpen) as $inspection) {
				$renderInspection($inspection);
			} ?>
		</details>
	<?php endif; ?>
</div>
