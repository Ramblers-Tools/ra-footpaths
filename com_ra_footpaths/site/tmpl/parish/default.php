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

$due = 0;

foreach ($this->paths as $p) {
	$due += $this->isDue($p->inspection_date) ? 1 : 0;
}
?>
<div class="rafp rafp-parish">
	<p class="rafp-crumbs"><a href="<?php echo RouteHelper::url('view=parishes'); ?>">All parishes</a></p>
	<h1><?php echo $this->escape($this->parish->name); ?> footpaths</h1>
	<p>
		<?php echo count($this->paths); ?> paths, <?php echo $due; ?> not inspected for 12 months or more.
		<?php if ($this->inspectors) : ?>
			Inspectors: <?php echo $this->escape(implode(', ', $this->inspectors)); ?>.
		<?php endif; ?>
	</p>

	<?php echo $this->mapHtml; ?>
	<ul class="rafp-legend" aria-label="Grade colours">
		<?php foreach (FootpathsHelper::GRADES as $g => [$label]) : ?>
			<li><?php echo FootpathsHelper::gradeChip($g); ?> <?php echo $this->escape($label); ?></li>
		<?php endforeach; ?>
	</ul>

	<div class="rafp-filters">
		<label>Find a path <input type="search" name="search" class="form-control" data-rafp-for="rafp-path-table" autocomplete="off" placeholder="e.g. FP 12"></label>
		<label>Grade
			<select name="grade" class="form-select" data-rafp-for="rafp-path-table">
				<option value="">Any</option>
				<?php foreach (FootpathsHelper::GRADES as $g => [$label]) : ?>
					<option value="<?php echo $g; ?>"><?php echo $g === 'N' ? 'Not inspected' : $g . ': ' . $this->escape($label); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label><input type="checkbox" name="due" data-rafp-for="rafp-path-table"> Not inspected for 12 months</label>
		<label><input type="checkbox" name="photos" data-rafp-for="rafp-path-table"> With photos</label>
		<span class="rafp-count" data-rafp-count="rafp-path-table" aria-live="polite"></span>
	</div>

	<table class="rafp-table" id="rafp-path-table" data-rafp-filter>
		<thead>
			<tr>
				<th scope="col">Path</th>
				<th scope="col">Grade</th>
				<th scope="col" class="num">Last inspected</th>
				<th scope="col" class="rafp-hide-sm">By</th>
				<th scope="col" class="num rafp-hide-sm">Faults</th>
				<th scope="col" class="rafp-hide-sm">Remarks</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($this->paths as $p) :
			$name = FootpathsHelper::pathName($p->type, $p->number);
			$grade = $p->inspection_id ? FootpathsHelper::normalise($p->grade) : 'N';
			$isDue = $this->isDue($p->inspection_date);
			$remarks = trim((string) $p->remarks);
			?>
			<tr data-search="<?php echo $this->escape(strtolower($name . ' ' . str_replace(' ', '', $name))); ?>" data-grade="<?php echo $grade; ?>" data-due="<?php echo $isDue ? '1' : '0'; ?>" data-photos="<?php echo (int) $p->photos ? '1' : '0'; ?>">
				<th scope="row"><a href="<?php echo $this->pathUrl((int) $p->id); ?>"><?php echo $this->escape($name); ?></a><?php
					if ((int) $p->photos) :
						$label = (int) $p->photos === 1 ? '1 photo' : (int) $p->photos . ' photos'; ?>
					<span class="rafp-photo-count" title="<?php echo $label; ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M4 8h3l2-3h6l2 3h3v11H4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg><span class="visually-hidden"><?php echo $label; ?></span><span aria-hidden="true"><?php echo (int) $p->photos; ?></span></span>
					<?php endif; ?></th>
				<td><?php echo FootpathsHelper::gradeChip($grade); ?></td>
				<td class="num<?php echo $isDue ? ' rafp-due' : ''; ?>"><?php echo $p->inspection_date ? HTMLHelper::_('date', $p->inspection_date, 'j M Y') : 'Never'; ?></td>
				<td class="rafp-hide-sm"><?php echo $this->escape((string) $p->inspector); ?></td>
				<td class="num rafp-hide-sm"><?php echo (int) $p->faults ?: ''; ?></td>
				<td class="rafp-remarks rafp-hide-sm"><?php
					// The latest inspection's remarks, then each fault's comment, in full.
					if ($remarks !== '') : ?><p><?php echo nl2br($this->escape($remarks)); ?></p><?php endif;
					foreach ($p->fault_remarks as $f) : ?>
						<p class="rafp-fault-remark"><strong><?php echo $this->escape(ucfirst((string) ($f->type ?: 'Fault'))); ?>:</strong> <?php echo nl2br($this->escape(trim((string) $f->remarks))); ?></p>
					<?php endforeach; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
