<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;

$path = $this->path;
$editing = $this->editing;
$chosenGrade = $editing ? FootpathsHelper::normalise($editing->grade_recorded ?: $editing->grade) : 'A';
$today = HTMLHelper::_('date', 'now', 'Y-m-d');
$grades = [
	'A' => 'Good: no problems',
	'B' => 'Minor problems',
	'C' => 'Significant problems',
	'D' => 'Serious problem: path unusable or dangerous',
];
?>
<div class="rafp rafp-report">
	<p class="rafp-crumbs">
		<a href="<?php echo RouteHelper::url('view=parishes'); ?>">All parishes</a> &rsaquo;
		<a href="<?php echo $this->parishUrl(); ?>"><?php echo $this->escape((string) $path->parish); ?></a> &rsaquo;
		<a href="<?php echo $this->pathUrl(); ?>"><?php echo $this->escape($this->name()); ?></a>
	</p>
	<h1><?php echo $editing ? 'Change an inspection' : 'Record an inspection'; ?>: <?php echo $this->escape($this->name()); ?>, <?php echo $this->escape((string) $path->parish); ?></h1>

	<?php if ($editing) : ?>
		<p class="rafp-last">You are changing the inspection of <?php echo $this->escape($this->date($editing->inspection_date)); ?>.
			<?php if ($editing->legacy_id !== null) : ?>It came from the old system.<?php endif; ?></p>
	<?php elseif ($this->last) : ?>
		<p class="rafp-last">Last inspected <?php echo $this->escape($this->date($this->last->inspection_date)); ?>
			<?php echo $this->last->inspector ? 'by ' . $this->escape((string) $this->last->inspector) : ''; ?>,
			grade <?php echo FootpathsHelper::gradeChip($this->last->grade); ?>,
			<?php echo count($this->last->faults); ?> <?php echo count($this->last->faults) === 1 ? 'fault' : 'faults'; ?>.</p>
	<?php endif; ?>

	<form class="rafp-form" data-rafp-report novalidate
		data-save-url="<?php echo $this->escape($this->saveUrl()); ?>"
		data-token="<?php echo $this->escape($this->token()); ?>"
		data-path-id="<?php echo (int) $path->id; ?>"
		data-inspection-id="<?php echo $editing ? (int) $editing->id : 0; ?>"
		data-max-uploads="<?php echo $this->maxUploads(); ?>"
		data-max-post="<?php echo $this->maxPost(); ?>"
		data-max-per-fault="<?php echo \Ramblers\Component\Ra_footpaths\Site\Helper\PhotoStore::MAX_PER_FAULT; ?>">

		<fieldset class="rafp-box">
			<legend>The inspection</legend>
			<div class="rafp-row">
				<label for="rafp-date">Date walked</label>
				<input type="date" id="rafp-date" name="date" class="form-control" value="<?php echo $editing ? $this->escape((string) $editing->inspection_date) : $today; ?>" max="<?php echo $today; ?>" required>
			</div>
			<div class="rafp-row">
				<label for="rafp-inspector">Inspector</label>
				<select id="rafp-inspector" name="inspector" class="form-select" required>
					<?php if (count($this->inspectors) !== 1) : ?><option value="">Choose&hellip;</option><?php endif; ?>
					<?php foreach ($this->inspectors as $i) : ?>
						<option value="<?php echo (int) $i->id; ?>"<?php echo (int) $i->id === $this->myInspector ? ' selected' : ''; ?>><?php echo $this->escape((string) $i->name); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="rafp-row" role="radiogroup" aria-labelledby="rafp-grade-label">
				<span id="rafp-grade-label" class="rafp-label">Overall condition</span>
				<div class="rafp-grades">
				<?php foreach ($grades as $g => $label) : ?>
					<label class="rafp-grade-pick">
						<input type="radio" name="grade" value="<?php echo $g; ?>"<?php echo $g === $chosenGrade ? ' checked' : ''; ?>>
						<?php echo FootpathsHelper::gradeChip($g); ?> <span><?php echo $this->escape($label); ?></span>
					</label>
				<?php endforeach; ?>
				</div>
				<p class="rafp-help" data-rafp-grade-note hidden></p>
			</div>
			<div class="rafp-row">
				<label for="rafp-remarks">Remarks <span class="rafp-optional">(optional)</span></label>
				<textarea id="rafp-remarks" name="remarks" class="form-control" rows="3" placeholder="Anything about the path as a whole"><?php echo $editing ? $this->escape((string) $editing->remarks) : ''; ?></textarea>
			</div>
		</fieldset>

		<fieldset class="rafp-box">
			<legend>Faults</legend>
			<p class="rafp-help">Add one entry for each problem. Mark where it is with your phone's location, or by tapping the map.</p>
			<div data-rafp-faults></div>
			<p class="rafp-none" data-rafp-no-faults>No faults added. Leave it like this if the path is fine.</p>
			<div class="rafp-actions">
				<button type="button" class="btn btn-outline-primary" data-rafp-add>+ Add a fault</button>
				<?php if (!$editing && $this->last && $this->last->faults) : ?>
					<button type="button" class="btn btn-outline-secondary" data-rafp-copy><?php echo count($this->last->faults) === 1 ? 'Copy the fault' : 'Copy the ' . count($this->last->faults) . ' faults'; ?> from the last inspection</button>
				<?php endif; ?>
			</div>
		</fieldset>

		<div class="rafp-pick-map">
			<p class="rafp-pick-hint" data-rafp-pick-hint hidden>Tap the map where <strong data-rafp-pick-name></strong> is.
				<button type="button" class="btn btn-sm btn-link" data-rafp-pick-cancel>Cancel</button></p>
			<?php echo $this->mapHtml; ?>
			<p class="small text-muted">This path is in its grade colour; the parish's other paths are grey. Numbered dots are the faults on this form.</p>
		</div>

		<div class="rafp-status" data-rafp-status role="alert" hidden></div>
		<div class="rafp-actions rafp-submit">
			<button type="submit" class="btn btn-primary btn-lg" data-rafp-save><?php echo $editing ? 'Save changes' : 'Save inspection'; ?></button>
			<a class="btn btn-link" href="<?php echo $this->pathUrl(); ?>">Cancel</a>
		</div>
	</form>

	<?php if ($editing) : ?>
		<form class="rafp-delete" method="post" action="<?php echo RouteHelper::url('task=inspection.delete'); ?>"
			onsubmit="return window.confirm('Delete this inspection, with its faults, photos and notes? This can\'t be undone.');">
			<input type="hidden" name="id" value="<?php echo (int) $editing->id; ?>">
			<input type="hidden" name="path_id" value="<?php echo (int) $path->id; ?>">
			<?php echo HTMLHelper::_('form.token'); ?>
			<button type="submit" class="btn btn-outline-danger">Delete this inspection</button>
		</form>
	<?php endif; ?>

	<template data-rafp-fault-template>
		<fieldset class="rafp-fault-entry">
			<legend><span data-rafp-fault-title>Fault</span>
				<button type="button" class="btn btn-sm btn-link rafp-remove" data-rafp-remove>Remove</button></legend>
			<div class="rafp-row">
				<label data-for="type">Type of fault</label>
				<select class="form-select" data-name="type" required>
					<option value="">Choose&hellip;</option>
					<?php foreach ($this->faultTypes as $t) : ?>
						<option value="<?php echo (int) $t->id; ?>"><?php echo $this->escape(ucfirst((string) $t->name)); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="rafp-row">
				<span class="rafp-label">How bad</span>
				<div class="rafp-grades rafp-grades-inline">
				<?php foreach (['B', 'C', 'D'] as $g) : ?>
					<label class="rafp-grade-pick">
						<input type="radio" data-name="fgrade" value="<?php echo $g; ?>"<?php echo $g === 'B' ? ' checked' : ''; ?>>
						<?php echo FootpathsHelper::gradeChip($g); ?> <span><?php echo $this->escape(explode(':', $grades[$g])[0]); ?></span>
					</label>
				<?php endforeach; ?>
				</div>
			</div>
			<div class="rafp-row">
				<label data-for="remarks">What's wrong</label>
				<textarea class="form-control" data-name="remarks" rows="2" placeholder="For example: stile broken, top rail missing"></textarea>
			</div>
			<div class="rafp-row">
				<span class="rafp-label">Where</span>
				<div class="rafp-where">
					<button type="button" class="btn btn-sm btn-outline-secondary" data-rafp-gps>Use my location</button>
					<button type="button" class="btn btn-sm btn-outline-secondary" data-rafp-pick>Tap on the map</button>
					<input type="text" class="form-control rafp-gridref" data-name="gridref" placeholder="or grid ref, e.g. SJ 912 765" aria-label="Grid reference">
				</div>
				<p class="rafp-help" data-rafp-where-note></p>
			</div>
			<div class="rafp-row">
				<label data-for="photos">Photos <span class="rafp-optional">(optional, up to <?php echo \Ramblers\Component\Ra_footpaths\Site\Helper\PhotoStore::MAX_PER_FAULT; ?>)</span></label>
				<div class="rafp-photos rafp-photos-kept" data-rafp-kept hidden></div>
				<input type="file" class="form-control" data-name="photos" accept="image/*" multiple>
				<div class="rafp-photos" data-rafp-previews></div>
				<p class="rafp-help" data-rafp-photo-note></p>
			</div>
		</fieldset>
	</template>

	<script type="application/json" data-rafp-last-faults><?php echo $this->lastFaultsJson(); ?></script>

	<script type="application/json" data-rafp-editing><?php echo $this->editingJson(); ?></script>
</div>
