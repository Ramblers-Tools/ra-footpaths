<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$f = $this->filters;
$edit = fn (int $id) => $this->link('view=inspector&id=' . $id);
$options = static function (array $list, $current): string {
	$html = '';
	foreach ($list as $value => $label) {
		$html .= '<option value="' . htmlspecialchars((string) $value) . '"' . ((string) $value === (string) $current ? ' selected' : '') . '>' . htmlspecialchars($label) . '</option>';
	}
	return $html;
};
?>
<?php if ($this->frontend) : ?>
	<h1><?php echo Text::_('COM_RA_FOOTPATHS_INSPECTORS'); ?></h1>
	<p class="d-flex flex-wrap gap-2">
		<a class="btn btn-secondary" href="<?php echo $this->link('view=parishes'); ?>"><?php echo Text::_('COM_RA_FOOTPATHS_BACK_TO_PARISHES'); ?></a>
		<?php if ($this->canDo('core.create')) : ?>
			<a class="btn btn-primary" href="<?php echo $this->link('view=inspector'); ?>"><?php echo Text::_('COM_RA_FOOTPATHS_INSPECTOR_NEW'); ?></a>
		<?php endif; ?>
	</p>
<?php endif; ?>
<?php if ($this->group <= 0) : ?>
	<div class="alert alert-warning"><?php echo Text::_('COM_RA_FOOTPATHS_NO_GROUP'); ?></div>
<?php endif; ?>

<p>
	<?php echo Text::sprintf('COM_RA_FOOTPATHS_INSPECTORS_UNLINKED', $this->unlinked['active'], $this->unlinked['recent']); ?>
	<?php echo Text::_('COM_RA_FOOTPATHS_INSPECTORS_INTRO'); ?>
</p>

<form action="<?php echo $this->link('view=inspectors'); ?>" method="get" class="row g-2 align-items-end mb-3">
	<?php echo $this->hiddenFields('inspectors'); ?>
	<div class="col-auto">
		<label class="form-label" for="filter_search"><?php echo Text::_('COM_RA_FOOTPATHS_FILTER_NAME'); ?></label>
		<input type="search" class="form-control" id="filter_search" name="filter_search" value="<?php echo $this->escape($f['search']); ?>">
	</div>
	<div class="col-auto">
		<label class="form-label" for="filter_status"><?php echo Text::_('COM_RA_FOOTPATHS_FILTER_STATUS'); ?></label>
		<select class="form-select" id="filter_status" name="filter_status" onchange="this.form.submit()">
			<?php echo $options([
				'active' => Text::_('COM_RA_FOOTPATHS_FILTER_ACTIVE'),
				'recent' => Text::_('COM_RA_FOOTPATHS_FILTER_RECENT'),
				'inactive' => Text::_('COM_RA_FOOTPATHS_FILTER_INACTIVE'),
				'all' => Text::_('COM_RA_FOOTPATHS_FILTER_ALL'),
			], $f['status']); ?>
		</select>
	</div>
	<div class="col-auto">
		<label class="form-label" for="filter_login"><?php echo Text::_('COM_RA_FOOTPATHS_FILTER_LOGIN'); ?></label>
		<select class="form-select" id="filter_login" name="filter_login" onchange="this.form.submit()">
			<?php echo $options([
				'' => Text::_('COM_RA_FOOTPATHS_FILTER_ANY'),
				'unlinked' => Text::_('COM_RA_FOOTPATHS_FILTER_UNLINKED'),
				'linked' => Text::_('COM_RA_FOOTPATHS_FILTER_LINKED'),
			], $f['login']); ?>
		</select>
	</div>
	<div class="col-auto">
		<label class="form-label" for="filter_parish"><?php echo Text::_('COM_RA_FOOTPATHS_FILTER_PARISH'); ?></label>
		<select class="form-select" id="filter_parish" name="filter_parish" onchange="this.form.submit()">
			<?php echo $options([0 => Text::_('COM_RA_FOOTPATHS_FILTER_ANY')] + $this->parishes, $f['parish']); ?>
		</select>
	</div>
	<div class="col-auto">
		<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
	</div>
	<?php if ($this->canDo('core.manage')) : ?>
		<div class="col-auto">
			<a class="btn btn-outline-secondary" href="<?php echo $this->link('task=inspector.export&' . Session::getFormToken() . '=1'); ?>"
				title="<?php echo $this->escape(Text::_('COM_RA_FOOTPATHS_EXPORT_DESC')); ?>"><?php echo Text::_('COM_RA_FOOTPATHS_EXPORT'); ?></a>
		</div>
	<?php endif; ?>
</form>

<table class="table table-striped">
	<caption class="visually-hidden"><?php echo Text::_('COM_RA_FOOTPATHS_INSPECTORS'); ?></caption>
	<thead>
		<tr>
			<th scope="col"><?php echo Text::_('COM_RA_FOOTPATHS_COL_NAME'); ?></th>
			<th scope="col"><?php echo Text::_('COM_RA_FOOTPATHS_COL_CONTACT'); ?></th>
			<th scope="col"><?php echo Text::_('COM_RA_FOOTPATHS_COL_ACTIVE'); ?></th>
			<th scope="col"><?php echo Text::_('COM_RA_FOOTPATHS_COL_LAST'); ?></th>
			<th scope="col" class="text-end"><?php echo Text::_('COM_RA_FOOTPATHS_COL_RECENT'); ?></th>
			<th scope="col"><?php echo Text::_('COM_RA_FOOTPATHS_COL_PARISHES'); ?></th>
			<th scope="col"><?php echo Text::_('COM_RA_FOOTPATHS_COL_LOGINS'); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ($this->items as $item) : ?>
		<tr>
			<th scope="row">
				<a href="<?php echo $edit((int) $item->id); ?>"><?php echo $this->escape($item->name); ?></a>
				<?php if ($item->legacy_id === null) : ?>
					<span class="badge bg-info"><?php echo Text::_('COM_RA_FOOTPATHS_ADDED_HERE'); ?></span>
				<?php elseif ((int) $item->site_managed === 1) : ?>
					<span class="badge bg-secondary" title="<?php echo $this->escape(Text::_('COM_RA_FOOTPATHS_MANAGED_HERE_DESC')); ?>"><?php echo Text::_('COM_RA_FOOTPATHS_MANAGED_HERE'); ?></span>
				<?php endif; ?>
			</th>
			<td class="small">
				<?php if ($item->email) : ?><div><a href="mailto:<?php echo $this->escape($item->email); ?>"><?php echo $this->escape($item->email); ?></a></div><?php endif; ?>
				<?php if ($item->phone) : ?><div><?php echo $this->escape($item->phone); ?></div><?php endif; ?>
				<?php if (!$item->email && !$item->phone) : ?><span class="text-muted">–</span><?php endif; ?>
			</td>
			<td><?php echo Text::_((int) $item->active === 1 ? 'JYES' : 'JNO'); ?></td>
			<td><?php echo $item->last_inspection ? HTMLHelper::_('date', $item->last_inspection, 'j M Y') : '–'; ?></td>
			<td class="text-end"><?php echo (int) $item->recent; ?></td>
			<td><?php echo $item->parishes ? $this->escape(implode(', ', $item->parishes)) : '<span class="text-muted">–</span>'; ?></td>
			<td>
				<?php foreach ($item->logins as $login) : ?>
					<div>
						<?php echo $this->escape($login->name); ?> <span class="text-muted">(<?php echo $this->escape($login->username); ?>)</span>
						<?php if ((int) $login->block === 1) : ?><span class="badge bg-danger"><?php echo Text::_('COM_RA_FOOTPATHS_BLOCKED'); ?></span><?php endif; ?>
						<?php if ($this->group > 0 && !(int) $login->in_group) : ?><span class="badge bg-warning text-dark"><?php echo Text::_('COM_RA_FOOTPATHS_NOT_IN_GROUP'); ?></span><?php endif; ?>
					</div>
				<?php endforeach; ?>
				<?php if (!$item->logins) : ?>
					<span class="badge bg-warning text-dark"><?php echo Text::_('COM_RA_FOOTPATHS_NO_LOGIN'); ?></span>
					<?php if ($item->matches) : ?>
						<a href="<?php echo $edit((int) $item->id); ?>" class="small"><?php echo Text::plural('COM_RA_FOOTPATHS_N_MATCHES', count($item->matches)); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	<?php if (!$this->items) : ?>
		<tr><td colspan="7"><?php echo Text::_('COM_RA_FOOTPATHS_NONE_FOUND'); ?></td></tr>
	<?php endif; ?>
	</tbody>
</table>
<p class="text-muted small"><?php echo Text::sprintf('COM_RA_FOOTPATHS_N_SHOWN', count($this->items)); ?></p>
