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
$today = HTMLHelper::_('date', 'now', 'Y-m-d');

// Add a follow-up note to a fault, or change one ($event).
$noteForm = function (object $fault, ?object $event) use ($today): string {
	$id = $event ? (int) $event->id : 0;

	return '<form method="post" action="' . $this->taskUrl('note') . '">'
		. '<input type="hidden" name="fault_id" value="' . (int) $fault->id . '">'
		. '<input type="hidden" name="note_id" value="' . $id . '">'
		. '<input type="hidden" name="path_id" value="' . (int) $this->path->id . '">'
		. HTMLHelper::_('form.token')
		. '<label class="visually-hidden" for="rafp-nd-' . (int) $fault->id . '-' . $id . '">Date</label>'
		. '<input type="date" class="form-control form-control-sm rafp-note-date" id="rafp-nd-' . (int) $fault->id . '-' . $id . '" name="date" required max="' . $today . '" value="' . $this->escape($event ? (string) $event->event_date : $today) . '">'
		. '<label class="visually-hidden" for="rafp-nn-' . (int) $fault->id . '-' . $id . '">Note</label>'
		. '<textarea class="form-control form-control-sm" id="rafp-nn-' . (int) $fault->id . '-' . $id . '" name="notes" rows="2" required maxlength="2000" placeholder="For example: reported to Cheshire East">'
		. ($event ? $this->escape((string) $event->notes) : '') . '</textarea>'
		. '<div><button type="submit" class="btn btn-sm btn-primary">Save note</button></div></form>';
};

$renderInspection = function (object $inspection) use ($date, $noteForm): void {
	?>
	<article class="rafp-inspection" id="rafp-inspection-<?php echo (int) $inspection->id; ?>" tabindex="-1">
		<header>
			<?php echo FootpathsHelper::gradeChip($inspection->grade); ?>
			<h3><?php echo $date($inspection->inspection_date); ?></h3>
			<span class="rafp-by"><?php echo $this->escape((string) ($inspection->inspector ?: 'Unknown inspector')); ?></span>
			<?php if ($this->canEdit($inspection)) : ?>
				<a class="rafp-edit" href="<?php echo $this->editUrl($inspection); ?>">Change or delete</a>
			<?php endif; ?>
		</header>
		<?php if (trim((string) $inspection->remarks) !== '') : ?>
			<p><?php echo nl2br($this->escape(trim((string) $inspection->remarks))); ?></p>
		<?php endif; ?>
		<?php if ($inspection->faults) : ?>
			<ul class="rafp-faults">
			<?php foreach ($inspection->faults as $fault) : ?>
				<li class="rafp-fault" id="rafp-fault-<?php echo (int) $fault->id; ?>">
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
								<?php echo nl2br($this->escape((string) $event->notes)); ?>
								<?php if ($this->canEditNote($event)) : ?>
									<details class="rafp-note-form"><summary>Change</summary><?php echo $noteForm($fault, $event); ?></details>
									<form method="post" action="<?php echo $this->taskUrl('deletenote'); ?>" onsubmit="return window.confirm('Delete this note?');">
										<input type="hidden" name="note_id" value="<?php echo (int) $event->id; ?>">
										<input type="hidden" name="fault_id" value="<?php echo (int) $fault->id; ?>">
										<input type="hidden" name="path_id" value="<?php echo (int) $this->path->id; ?>">
										<?php echo HTMLHelper::_('form.token'); ?>
										<button type="submit" class="btn btn-link text-danger">Delete</button>
									</form>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<details class="rafp-note-form"><summary>Add a follow-up note</summary><?php echo $noteForm($fault, null); ?></details>
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

	<?php if ($this->canRecord) : ?>
		<p class="rafp-record"><a class="btn btn-primary" href="<?php echo RouteHelper::url('view=report&id=' . (int) $path->id); ?>">Record an inspection</a></p>
	<?php else : ?>
		<p class="rafp-record small text-muted">To record inspections, your website login needs linking to your inspector record: ask the footpaths secretary.</p>
	<?php endif; ?>

	<?php echo $this->mapHtml; ?>
	<p class="small text-muted">This path is in its grade colour; the parish's other paths are grey. Dots are fault positions: click one to open its report.</p>

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
