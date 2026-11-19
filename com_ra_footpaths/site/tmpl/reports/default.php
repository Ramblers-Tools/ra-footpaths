<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Site\Model\ReportsModel;

$f = $this->filters;
$type = $f['type'];
$e = fn ($v): string => $this->escape((string) $v);
$checked = static fn (bool $on): string => $on ? ' checked' : '';
$changes = \in_array($type, ['improved', 'worse'], true);
$uninspected = $type === 'uninspected';

// Notes for one inspection: its remarks, then each fault with its follow-up notes (and photos if asked for).
$notes = function (object $inspection, array $faults) use ($e, $f): void {
	if (trim((string) $inspection->remarks) !== '') {
		echo '<p>' . nl2br($e(trim((string) $inspection->remarks))) . '</p>';
	}

	if (!$faults) {
		return;
	}

	echo '<ul class="rafp-rep-faults">';

	foreach ($faults as $fault) {
		echo '<li>' . FootpathsHelper::gradeChip($fault->grade) . ' <strong>' . $e(ucfirst((string) ($fault->type ?: 'Fault'))) . '</strong>'
			. ($fault->gridref ? ' <span class="rafp-gridref">' . $e($fault->gridref) . '</span>' : '')
			. (trim((string) $fault->remarks) !== '' ? ' ' . $e(trim((string) $fault->remarks)) : '');

		foreach ($fault->events as $ev) {
			echo '<div class="rafp-rep-event">' . $e($this::date($ev->event_date)) . ' ' . $e($ev->reporter) . ': ' . $e(trim((string) $ev->notes)) . '</div>';
		}

		if ($f['photos'] && $fault->photos) {
			echo '<div class="rafp-photos">';

			foreach ($fault->photos as $photoId) {
				echo '<a href="' . $this->photoUrl($photoId, false) . '" target="_blank" rel="noopener"><img src="' . $this->photoUrl($photoId, true)
					. '" alt="Photo of the ' . $e($fault->type) . ' fault" loading="lazy"></a>';
			}

			echo '</div>';
		}

		echo '</li>';
	}

	echo '</ul>';
};
?>
<div class="rafp rafp-reports">
	<p class="rafp-crumbs rafp-noprint"><a href="<?php echo RouteHelper::url(''); ?>">Footpath inspections</a> &rsaquo; Reports</p>
	<h1 class="rafp-noprint">Footpath reports</h1>

	<details class="rafp-report-choose rafp-noprint"<?php echo $type === '' ? ' open' : ''; ?>>
	<summary><?php echo $type === '' ? 'Choose a report' : 'Change the report'; ?></summary>
	<form class="rafp-report-form" method="get" action="<?php echo Uri::root(true); ?>/index.php">
		<input type="hidden" name="option" value="com_ra_footpaths">
		<input type="hidden" name="view" value="reports">
		<?php echo RouteHelper::itemIdField(); ?>

		<fieldset>
			<legend>Report</legend>
			<div class="rafp-report-types">
			<?php foreach (ReportsModel::TYPES as $key => [$label, $help]) : ?>
				<label><input type="radio" name="type" value="<?php echo $key; ?>" required<?php echo $checked($type === $key); ?>>
					<span><strong><?php echo $e($label); ?></strong><small><?php echo $e($help); ?></small></span></label>
			<?php endforeach; ?>
			</div>
		</fieldset>

		<div class="rafp-report-dates">
			<label>From <input type="date" name="from" class="form-control" required value="<?php echo $e($f['from']); ?>"></label>
			<label>To <input type="date" name="to" class="form-control" required value="<?php echo $e($f['to']); ?>"></label>
		</div>

		<details class="rafp-report-pick"<?php echo $f['parishes'] ? ' open' : ''; ?>>
			<summary>Parishes: <span data-rafp-picked="parishes"><?php echo $f['parishes'] ? \count($f['parishes']) . ' chosen' : 'all'; ?></span></summary>
			<p class="rafp-hint">Tick none for all parishes.</p>
			<div class="rafp-report-checks">
			<?php foreach ($this->parishes as $p) : ?>
				<label><input type="checkbox" name="parishes[]" value="<?php echo (int) $p->id; ?>"<?php echo $checked(\in_array((int) $p->id, $f['parishes'], true)); ?>> <?php echo $e($p->name); ?></label>
			<?php endforeach; ?>
			</div>
		</details>

		<details class="rafp-report-pick"<?php echo $f['faulttypes'] ? ' open' : ''; ?>>
			<summary>Fault types: <span data-rafp-picked="faulttypes"><?php echo $f['faulttypes'] ? \count($f['faulttypes']) . ' chosen' : 'all'; ?></span></summary>
			<p class="rafp-hint">Tick none for all. With some ticked, path reports show only paths with those faults. Not used by the uninspected paths or the classification summary.</p>
			<div class="rafp-report-checks">
			<?php foreach ($this->faultTypes as $t) : ?>
				<label><input type="checkbox" name="faulttypes[]" value="<?php echo (int) $t->id; ?>"<?php echo $checked(\in_array((int) $t->id, $f['faulttypes'], true)); ?>> <?php echo $e(ucfirst((string) $t->name)); ?></label>
			<?php endforeach; ?>
			</div>
		</details>

		<label class="rafp-report-photos"><input type="checkbox" name="photos" value="1"<?php echo $checked($f['photos']); ?>> Include photos (path reports)</label>

		<div><button type="submit" class="btn btn-primary">Show report</button></div>
	</form>
	</details>

	<?php if ($type !== '') : ?>
	<section class="rafp-report" aria-labelledby="rafp-report-title">
		<header class="rafp-report-head">
			<?php $brand = $this->brand(); if ($brand['logo'] !== '' || $brand['name'] !== '') : ?>
			<div class="rafp-report-brand">
				<?php if ($brand['logo'] !== '') : ?><img src="<?php echo $e($brand['logo']); ?>" alt="<?php echo $e($brand['name']); ?>"><?php endif; ?>
				<?php if ($brand['name'] !== '') : ?><span><?php echo $e($brand['name']); ?><br><small>Footpath inspections</small></span><?php endif; ?>
			</div>
			<?php endif; ?>
			<h2 id="rafp-report-title"><?php echo $e($this->title()); ?></h2>
			<p><?php echo $e($this->period()); ?>. <?php echo $e($this->scope()); ?>
				<?php if (\in_array($type, ReportsModel::PATH_TYPES, true)) : ?>
					<strong><?php echo $this->count() . ' ' . ($this->count() === 1 ? 'path' : 'paths'); ?>.</strong>
				<?php endif; ?></p>
			<p class="rafp-report-actions rafp-noprint">
				<button type="button" class="btn btn-secondary btn-sm" data-rafp-print>Print or save as PDF</button>
				<a class="btn btn-secondary btn-sm" href="<?php echo $this->csvUrl(); ?>">Download for a spreadsheet (CSV)</a>
			</p>
		</header>

		<?php if ($type === 'summary') : ?>
			<?php if (!$this->rows) : ?><p>No paths.</p><?php else : ?>
			<div class="rafp-table-wrap">
			<table class="rafp-table rafp-report-table">
				<thead><tr><th scope="col">Parish</th><th scope="col" class="num">Paths</th><th scope="col" class="num">Inspected</th>
					<?php foreach (['A', 'B', 'C', 'D'] as $g) : ?><th scope="col" class="num"><?php echo FootpathsHelper::gradeChip($g); ?></th><?php endforeach; ?>
					<th scope="col" class="num">Not inspected</th></tr></thead>
				<tbody>
				<?php foreach ($this->rows as $r) : ?>
					<tr<?php echo !empty($r->total) ? ' class="rafp-total"' : ''; ?>>
						<th scope="row"><?php echo $e($r->parish); ?></th>
						<td class="num"><?php echo $r->paths; ?></td>
						<?php foreach (['inspected', 'a', 'b', 'c', 'd'] as $k) : ?>
							<td class="num"><?php echo $r->$k; ?> <small><?php echo $this::percent($r->$k, $r->paths); ?></small></td>
						<?php endforeach; ?>
						<td class="num"><?php echo $r->paths - $r->inspected; ?> <small><?php echo $this::percent($r->paths - $r->inspected, $r->paths); ?></small></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p class="rafp-hint">Each path counts once, with the grade of its latest inspection in the period. Percentages are of all the parish's paths.</p>
			<?php endif; ?>

		<?php elseif ($type === 'faults') : ?>
			<?php if (!$this->rows) : ?><p>No faults graded B, C or D in this period.</p><?php else :
				$max = max(array_column($this->rows, 'total')); ?>
			<div class="rafp-table-wrap">
			<table class="rafp-table rafp-report-table">
				<thead><tr><th scope="col">Fault type</th>
					<?php foreach (['B', 'C', 'D'] as $g) : ?><th scope="col" class="num"><?php echo FootpathsHelper::gradeChip($g); ?></th><?php endforeach; ?>
					<th scope="col" class="num">Total</th><th scope="col" class="rafp-bar-col"><span class="visually-hidden">Chart</span></th></tr></thead>
				<tbody>
				<?php foreach ($this->rows as $r) : ?>
					<tr>
						<th scope="row"><?php echo $e(ucfirst($r->type)); ?></th>
						<td class="num"><?php echo $r->b; ?></td><td class="num"><?php echo $r->c; ?></td><td class="num"><?php echo $r->d; ?></td>
						<td class="num"><strong><?php echo $r->total; ?></strong></td>
						<td class="rafp-bar-col" aria-hidden="true"><span class="rafp-bar">
							<?php foreach (['b', 'c', 'd'] as $g) : if ($r->$g) : ?><span class="rafp-bar-<?php echo $g; ?>" style="width:<?php echo round(100 * $r->$g / $max, 2); ?>%"></span><?php endif; endforeach; ?>
						</span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<p class="rafp-hint">Every inspection in the period counts, not only each path's latest, so a fault seen twice counts twice.</p>
			<?php endif; ?>

		<?php elseif (!$this->paths) : ?>
			<p>No paths match.</p>

		<?php else : ?>
			<?php foreach ($this->paths as $parish => $paths) : ?>
			<h3 class="rafp-report-parish"><?php echo $e($parish); ?> <small><?php echo \count($paths); ?> <?php echo \count($paths) === 1 ? 'path' : 'paths'; ?></small></h3>
			<div class="rafp-table-wrap">
			<table class="rafp-table rafp-report-table rafp-report-paths">
				<thead><tr>
					<th scope="col">Path</th>
					<?php if ($uninspected) : ?>
						<th scope="col">Last inspected</th><th scope="col">Grade</th><th scope="col">Inspector</th>
					<?php else : ?>
						<th scope="col">Previous</th><?php if ($changes) : ?><th scope="col">Previous date</th><?php endif; ?>
						<th scope="col">Grade</th><th scope="col">Date</th><th scope="col">Inspector</th><th scope="col">Notes</th>
					<?php endif; ?>
				</tr></thead>
				<tbody>
				<?php foreach ($paths as $p) : $c = $p->current; ?>
					<tr>
						<th scope="row"><a href="<?php echo $this->pathUrl((int) $p->id); ?>"><?php echo $e($p->name); ?></a></th>
						<?php if ($uninspected) : ?>
							<td data-label="Last inspected"><?php echo $c ? $e($this::date($c->inspection_date)) : 'Never'; ?></td>
							<td><?php echo $c ? FootpathsHelper::gradeChip($c->grade) : ''; ?></td>
							<td><?php echo $c ? $e($c->inspector) : ''; ?></td>
						<?php else : ?>
							<td data-label="Previous"><?php echo $p->previous ? FootpathsHelper::gradeChip($p->previous->grade) : '<span class="rafp-none">none</span>'; ?></td>
							<?php if ($changes) : ?><td class="rafp-nowrap"><?php echo $p->previous ? $e($this::date($p->previous->inspection_date)) : ''; ?></td><?php endif; ?>
							<td data-label="Now"><?php echo FootpathsHelper::gradeChip($c->grade); ?></td>
							<td class="rafp-nowrap"><?php echo $e($this::date($c->inspection_date)); ?></td>
							<td><?php echo $e($c->inspector); ?></td>
							<td class="rafp-remarks"><?php $notes($c, $p->faults); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
	<?php endif; ?>
</div>
