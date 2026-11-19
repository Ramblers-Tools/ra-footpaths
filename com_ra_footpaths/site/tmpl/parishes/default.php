<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;

$totals = ['paths' => 0, 'recent' => 0, 'never' => 0, 'cd' => 0];

foreach ($this->items as $row) {
	$totals['paths'] += (int) $row->paths;
	$totals['recent'] += (int) $row->recent;
	$totals['never'] += (int) $row->never;
	$totals['cd'] += (int) $row->grade_c + (int) $row->grade_d;
}
?>
<div class="rafp rafp-parishes">
	<h1>Footpath inspections</h1>
	<p class="rafp-manage-link">
		<a class="btn btn-sm btn-secondary" href="<?php echo RouteHelper::url('view=reports'); ?>">Reports</a>
		<?php if ($this->canManage) : ?>
			<a class="btn btn-sm btn-secondary" href="<?php echo RouteHelper::url('view=inspectors'); ?>">Manage inspectors</a>
		<?php endif; ?>
	</p>
	<p class="rafp-demo-note"><strong>Demo, read only.</strong> Records copied from the old Footpath Inspections Database. Nothing here can be changed yet, and changes made in the old system after the copy are not shown.</p>

	<?php if ($this->mine) : ?>
	<section class="rafp-mine" aria-labelledby="rafp-mine-title">
		<h2 id="rafp-mine-title"><?php echo count($this->mine) === 1 ? 'Your parish' : 'Your parishes'; ?></h2>
		<ul class="rafp-mine-list">
		<?php foreach ($this->mine as $row) :
			$percent = $this::percent($row);
			$cd = (int) $row->grade_c + (int) $row->grade_d;
			$due = (int) $row->paths - (int) $row->recent;
			?>
			<li>
				<a class="rafp-mine-name" href="<?php echo RouteHelper::url('view=parish&id=' . (int) $row->id); ?>"><?php echo $this->escape($row->name); ?></a>
				<span><?php echo (int) $row->paths; ?> paths</span>
				<span><?php echo (int) $row->recent; ?> inspected in the last 12 months<?php echo $percent === null ? '' : ' (' . $percent . '%)'; ?></span>
				<?php if ($due > 0) : ?><span class="rafp-due"><?php echo $due; ?> due (not inspected for 12 months)</span><?php endif; ?>
				<?php if ($cd > 0) : ?><span><?php echo $cd; ?> graded C or D last time</span><?php endif; ?>
				<span>Last inspection <?php echo $row->last_date ? HTMLHelper::_('date', $row->last_date, 'j M Y') : 'none'; ?></span>
			</li>
		<?php endforeach; ?>
		</ul>
	</section>
	<h2 class="rafp-all-title">All parishes</h2>
	<?php endif; ?>

	<ul class="rafp-stats">
		<li><strong><?php echo count($this->items); ?></strong><span>parishes</span></li>
		<li><strong><?php echo number_format($totals['paths']); ?></strong><span>paths</span></li>
		<li><strong><?php echo number_format($totals['recent']); ?></strong><span>inspected in the last 12 months</span></li>
		<li><strong><?php echo number_format($totals['cd']); ?></strong><span>graded C or D last time</span></li>
	</ul>

	<?php if ($this->mapHtml !== '') : ?>
	<div class="rafp-switch" role="group" aria-label="Show parishes as">
		<button type="button" class="btn btn-sm" data-rafp-show="map" aria-pressed="true">Map</button>
		<button type="button" class="btn btn-sm" data-rafp-show="list" aria-pressed="false">List</button>
	</div>

	<section data-rafp-panel="map">
		<p class="rafp-hint">Click a parish to see its figures and open it. Shaded by the share of its paths inspected in the last 12 months:</p>
		<ul class="rafp-legend">
			<?php foreach ($this::BANDS as [, $colour, $label]) : ?>
				<li><span class="rafp-swatch" style="background:<?php echo $colour; ?>"></span><?php echo $label; ?></li>
			<?php endforeach; ?>
		</ul>
		<?php echo $this->mapHtml; ?>
	</section>
	<?php endif; ?>

	<section data-rafp-panel="list">
	<div class="rafp-filters">
		<label>Find a parish <input type="search" name="search" class="form-control" data-rafp-for="rafp-parish-table" autocomplete="off"></label>
		<span class="rafp-count" data-rafp-count="rafp-parish-table" aria-live="polite"></span>
	</div>

	<table class="rafp-table" id="rafp-parish-table" data-rafp-filter>
		<thead>
			<tr>
				<th scope="col">Parish</th>
				<th scope="col" class="num">Paths</th>
				<th scope="col" class="num">Inspected in last 12 months</th>
				<th scope="col" class="num rafp-hide-sm">Never inspected</th>
				<th scope="col" class="rafp-hide-sm">Latest grades</th>
				<th scope="col" class="num rafp-hide-sm">Last inspection</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($this->items as $row) :
			$url = RouteHelper::url('view=parish&id=' . (int) $row->id);
			$paths = (int) $row->paths;
			$recent = (int) $row->recent;
			?>
			<tr data-search="<?php echo $this->escape(strtolower($row->name)); ?>">
				<th scope="row"><a href="<?php echo $url; ?>"><?php echo $this->escape($row->name); ?></a><?php echo $this->isMine($row) ? ' <span class="rafp-yours">yours</span>' : ''; ?></th>
				<td class="num"><?php echo $paths; ?></td>
				<td class="num"><?php echo $recent; ?><?php echo $paths ? ' <span class="text-muted">(' . round(100 * $recent / $paths) . '%)</span>' : ''; ?></td>
				<td class="num rafp-hide-sm"><?php echo (int) $row->never; ?></td>
				<td class="rafp-hide-sm">
					<?php foreach (['A' => $row->grade_a, 'B' => $row->grade_b, 'C' => $row->grade_c, 'D' => $row->grade_d] as $g => $n) :
						if ((int) $n === 0) {
							continue;
						} ?>
						<span class="rafp-grade rafp-grade-<?php echo strtolower($g); ?>" title="<?php echo (int) $n . ' paths graded ' . $g; ?>"><?php echo $g . ' ' . (int) $n; ?></span>
					<?php endforeach; ?>
				</td>
				<td class="num rafp-hide-sm"><?php echo $row->last_date ? HTMLHelper::_('date', $row->last_date, 'j M Y') : '&ndash;'; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	</section>
</div>
