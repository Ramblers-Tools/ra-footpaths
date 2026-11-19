<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$item = $this->item;
$id = $item ? (int) $item->id : 0;
$action = $this->link();
$disabled = $this->canEdit ? '' : ' disabled';

// A small POST form with one button, for linking or unlinking a login.
$button = function (string $task, int $userId, string $label, string $class) use ($action, $id): string {
	if (!$this->canEdit) {
		return '';
	}

	return '<form action="' . $action . '" method="post" class="d-inline">'
		. '<input type="hidden" name="task" value="inspector.' . $task . '">'
		. '<input type="hidden" name="id" value="' . $id . '">'
		. '<input type="hidden" name="user_id" value="' . $userId . '">'
		. HTMLHelper::_('form.token')
		. '<button type="submit" class="btn btn-sm ' . $class . '">' . Text::_($label) . '</button></form>';
};

$userRow = function (object $u, string $buttons): string {
	return '<tr><td>' . $this->escape($u->name) . '</td><td>' . $this->escape($u->username) . '</td><td>' . $this->escape($u->email) . '</td>'
		. '<td>' . ((int) $u->block === 1 ? '<span class="badge bg-danger">' . Text::_('COM_RA_FOOTPATHS_BLOCKED') . '</span>' : '') . '</td>'
		. '<td class="text-end">' . $buttons . '</td></tr>';
};

$userHead = '<thead><tr><th scope="col">' . Text::_('COM_RA_FOOTPATHS_COL_NAME') . '</th><th scope="col">' . Text::_('JGLOBAL_USERNAME')
	. '</th><th scope="col">' . Text::_('JGLOBAL_EMAIL') . '</th><th scope="col"></th><th scope="col"></th></tr></thead>';
?>
<?php if ($this->frontend) : ?>
	<h1><?php echo $item ? Text::sprintf('COM_RA_FOOTPATHS_INSPECTOR_TITLE', $this->escape($item->name)) : Text::_('COM_RA_FOOTPATHS_INSPECTOR_NEW'); ?></h1>
	<p><a class="btn btn-secondary" href="<?php echo $this->link('view=inspectors'); ?>"><?php echo Text::_('COM_RA_FOOTPATHS_BACK_TO_INSPECTORS'); ?></a></p>
<?php endif; ?>
<?php if ($this->group <= 0) : ?>
	<div class="alert alert-warning"><?php echo Text::_('COM_RA_FOOTPATHS_NO_GROUP'); ?></div>
<?php endif; ?>

<div class="row">
	<div class="col-lg-7">
		<form action="<?php echo $action; ?>" method="post" class="card mb-3">
			<div class="card-body">
				<h2 class="h4"><?php echo Text::_('COM_RA_FOOTPATHS_INSPECTOR_DETAILS'); ?></h2>
				<?php if ($item) : ?>
					<p class="text-muted">
						<?php echo Text::sprintf('COM_RA_FOOTPATHS_INSPECTOR_STATS', (int) $item->inspections, (int) $item->recent,
							$item->last_inspection ? HTMLHelper::_('date', $item->last_inspection, 'j M Y') : '–'); ?>
					</p>
				<?php endif; ?>
				<div class="mb-3">
					<label class="form-label" for="name"><?php echo Text::_('COM_RA_FOOTPATHS_COL_NAME'); ?></label>
					<input type="text" class="form-control" id="name" name="name" maxlength="100" required value="<?php echo $this->escape($item->name ?? ''); ?>"<?php echo $disabled; ?>>
				</div>
				<div class="form-check mb-3">
					<input class="form-check-input" type="checkbox" id="active" name="active" value="1"<?php echo !$item || (int) $item->active === 1 ? ' checked' : ''; ?><?php echo $disabled; ?>>
					<label class="form-check-label" for="active"><?php echo Text::_('COM_RA_FOOTPATHS_INSPECTOR_ACTIVE'); ?></label>
				</div>

				<fieldset class="mb-3">
					<legend class="h5"><?php echo Text::_('COM_RA_FOOTPATHS_COL_PARISHES'); ?></legend>
					<div class="row">
					<?php foreach ($this->parishes as $p) : ?>
						<div class="col-sm-6">
							<div class="form-check">
								<input class="form-check-input" type="checkbox" name="parishes[]" id="parish<?php echo (int) $p->id; ?>" value="<?php echo (int) $p->id; ?>"<?php echo $p->mine ? ' checked' : ''; ?><?php echo $disabled; ?>>
								<label class="form-check-label" for="parish<?php echo (int) $p->id; ?>">
									<?php echo $this->escape($p->name); ?>
									<?php if ($p->others) : ?>
										<span class="text-muted small">(<?php echo $this->escape(implode(', ', $p->others)); ?>)</span>
									<?php endif; ?>
								</label>
							</div>
						</div>
					<?php endforeach; ?>
					</div>
					<p class="form-text"><?php echo Text::_('COM_RA_FOOTPATHS_PARISHES_HELP'); ?></p>
				</fieldset>

				<?php if ($item && $item->legacy_id !== null) : ?>
					<div class="alert alert-info"><?php echo Text::_((int) $item->site_managed === 1 ? 'COM_RA_FOOTPATHS_MANAGED_HERE_DESC' : 'COM_RA_FOOTPATHS_FROM_OLD_SYSTEM'); ?></div>
				<?php endif; ?>

				<?php if ($this->canEdit) : ?>
					<input type="hidden" name="task" value="inspector.save">
					<input type="hidden" name="id" value="<?php echo $id; ?>">
					<?php echo HTMLHelper::_('form.token'); ?>
					<button type="submit" class="btn btn-success"><?php echo Text::_('JAPPLY'); ?></button>
				<?php endif; ?>
			</div>
		</form>
	</div>

	<div class="col-lg-5">
		<div class="card mb-3">
			<div class="card-body">
				<h2 class="h4"><?php echo Text::_('COM_RA_FOOTPATHS_COL_LOGINS'); ?></h2>
				<?php if (!$item) : ?>
					<p><?php echo Text::_('COM_RA_FOOTPATHS_SAVE_FIRST'); ?></p>
				<?php else : ?>
					<p class="small text-muted"><?php echo Text::_('COM_RA_FOOTPATHS_LOGINS_HELP'); ?></p>
					<?php if ($this->logins) : ?>
						<div class="table-responsive"><table class="table table-sm small"><?php echo $userHead; ?><tbody>
						<?php foreach ($this->logins as $u) : ?>
							<?php
							$buttons = $button('unlink', (int) $u->id, 'COM_RA_FOOTPATHS_UNLINK', 'btn-outline-danger');
							if ($this->group > 0 && !(int) $u->in_group) {
								$buttons = '<span class="badge bg-warning text-dark">' . Text::_('COM_RA_FOOTPATHS_NOT_IN_GROUP') . '</span> '
									. $button('link', (int) $u->id, 'COM_RA_FOOTPATHS_ADD_TO_GROUP', 'btn-outline-primary') . ' ' . $buttons;
							}
							echo $userRow($u, $buttons);
							?>
						<?php endforeach; ?>
						</tbody></table></div>
					<?php else : ?>
						<p><span class="badge bg-warning text-dark"><?php echo Text::_('COM_RA_FOOTPATHS_NO_LOGIN'); ?></span></p>
					<?php endif; ?>

					<?php if ($this->matches) : ?>
						<h3 class="h5"><?php echo Text::_('COM_RA_FOOTPATHS_SAME_NAME'); ?></h3>
						<div class="table-responsive"><table class="table table-sm small"><?php echo $userHead; ?><tbody>
						<?php foreach ($this->matches as $u) : ?>
							<?php echo $userRow($u, $button('link', (int) $u->id, 'COM_RA_FOOTPATHS_LINK', 'btn-primary')); ?>
						<?php endforeach; ?>
						</tbody></table></div>
					<?php endif; ?>

					<?php if ($this->canEdit) : ?>
						<h3 class="h5"><?php echo Text::_('COM_RA_FOOTPATHS_FIND_USER'); ?></h3>
						<form action="<?php echo $this->link('view=inspector&id=' . $id); ?>" method="get" class="d-flex gap-2 mb-2">
							<?php echo $this->hiddenFields('inspector'); ?>
							<input type="hidden" name="id" value="<?php echo $id; ?>">
							<label class="visually-hidden" for="q"><?php echo Text::_('COM_RA_FOOTPATHS_FIND_USER'); ?></label>
							<input type="search" class="form-control" id="q" name="q" minlength="2" value="<?php echo $this->escape($this->search); ?>" placeholder="<?php echo $this->escape(Text::_('COM_RA_FOOTPATHS_FIND_USER_HINT')); ?>">
							<button type="submit" class="btn btn-secondary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
						</form>
						<?php if ($this->search !== '') : ?>
							<?php if ($this->found) : ?>
								<div class="table-responsive"><table class="table table-sm small"><?php echo $userHead; ?><tbody>
								<?php foreach ($this->found as $u) : ?>
									<?php echo $userRow($u, $button('link', (int) $u->id, 'COM_RA_FOOTPATHS_LINK', 'btn-primary')); ?>
								<?php endforeach; ?>
								</tbody></table></div>
							<?php else : ?>
								<p><?php echo Text::_('COM_RA_FOOTPATHS_NONE_FOUND'); ?></p>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>
