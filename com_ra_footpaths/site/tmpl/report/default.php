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
	<h1>Record an inspection: <?php echo $this->escape($this->name()); ?>, <?php echo $this->escape((string) $path->parish); ?></h1>

	<div class="rafp-demo-note" role="note">
		<strong>Trial version.</strong> Try everything on this form, including photos. Nothing is saved: when you press
		Save you'll see what would have been recorded. Please tell us what's missing or awkward.
	</div>

	<?php if ($this->last) : ?>
		<p class="rafp-last">Last inspected <?php echo $this->escape($this->date($this->last->inspection_date)); ?>
			<?php echo $this->last->inspector ? 'by ' . $this->escape((string) $this->last->inspector) : ''; ?>,
			grade <?php echo FootpathsHelper::gradeChip($this->last->grade); ?>,
			<?php echo count($this->last->faults); ?> <?php echo count($this->last->faults) === 1 ? 'fault' : 'faults'; ?>.</p>
	<?php endif; ?>

	<form class="rafp-form" data-rafp-report novalidate
		data-path="<?php echo $this->escape($this->name() . ', ' . $path->parish); ?>"
		data-path-url="<?php echo $this->escape($this->pathUrl()); ?>">

		<fieldset class="rafp-box">
			<legend>The inspection</legend>
			<div class="rafp-row">
				<label for="rafp-date">Date walked</label>
				<input type="date" id="rafp-date" name="date" class="form-control" value="<?php echo $today; ?>" max="<?php echo $today; ?>" required>
			</div>
			<div class="rafp-row">
				<label for="rafp-inspector">Inspector</label>
				<select id="rafp-inspector" name="inspector" class="form-select" required>
					<option value="">Choose&hellip;</option>
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
						<input type="radio" name="grade" value="<?php echo $g; ?>"<?php echo $g === 'A' ? ' checked' : ''; ?>>
						<?php echo FootpathsHelper::gradeChip($g); ?> <span><?php echo $this->escape($label); ?></span>
					</label>
				<?php endforeach; ?>
				</div>
				<p class="rafp-help" data-rafp-grade-note hidden></p>
			</div>
			<div class="rafp-row">
				<label for="rafp-remarks">Remarks <span class="rafp-optional">(optional)</span></label>
				<textarea id="rafp-remarks" name="remarks" class="form-control" rows="3" placeholder="Anything about the path as a whole"></textarea>
			</div>
		</fieldset>

		<fieldset class="rafp-box">
			<legend>Faults</legend>
			<p class="rafp-help">Add one entry for each problem. Mark where it is with your phone's location, or by tapping the map.</p>
			<div data-rafp-faults></div>
			<p class="rafp-none" data-rafp-no-faults>No faults added. Leave it like this if the path is fine.</p>
			<div class="rafp-actions">
				<button type="button" class="btn btn-outline-primary" data-rafp-add>+ Add a fault</button>
				<?php if ($this->last && $this->last->faults) : ?>
					<button type="button" class="btn btn-outline-secondary" data-rafp-copy>Copy the <?php echo count($this->last->faults); ?> faults from the last inspection</button>
				<?php endif; ?>
			</div>
		</fieldset>

		<div class="rafp-pick-map">
			<p class="rafp-pick-hint" data-rafp-pick-hint hidden>Tap the map where <strong data-rafp-pick-name></strong> is.
				<button type="button" class="btn btn-sm btn-link" data-rafp-pick-cancel>Cancel</button></p>
			<?php echo $this->mapHtml; ?>
			<p class="small text-muted">This path is in its grade colour; the parish's other paths are grey. Numbered dots are the faults on this form.</p>
		</div>

		<div class="rafp-actions rafp-submit">
			<button type="submit" class="btn btn-primary btn-lg">Save inspection</button>
			<a class="btn btn-link" href="<?php echo $this->pathUrl(); ?>">Cancel</a>
		</div>
	</form>

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
				<label data-for="photos">Photos <span class="rafp-optional">(optional)</span></label>
				<input type="file" class="form-control" data-name="photos" accept="image/*" multiple>
				<div class="rafp-photos" data-rafp-previews></div>
			</div>
		</fieldset>
	</template>

	<script type="application/json" data-rafp-last-faults><?php echo $this->lastFaultsJson(); ?></script>

	<dialog class="rafp-demo-dialog" data-rafp-dialog aria-labelledby="rafp-dialog-title">
		<h2 id="rafp-dialog-title">Not saved: this is a trial</h2>
		<p>Thank you for trying the form. <strong>Nothing has been saved</strong>, and the photos haven't left your device.
			When the form goes live, this is what would be recorded:</p>
		<div data-rafp-summary></div>
		<p class="rafp-help">Something missing, or awkward to fill in? Please tell Richard.</p>
		<div class="rafp-actions">
			<button type="button" class="btn btn-primary" data-rafp-close>Back to the form</button>
			<a class="btn btn-outline-secondary" href="<?php echo $this->pathUrl(); ?>">Back to the path</a>
		</div>
	</dialog>
</div>
