<?php
/**
 * @package     com_ra_footpaths
 * @copyright   2026 East Cheshire Ramblers
 * @license     GNU General Public License version 2 or later
 */

namespace Ramblers\Component\Ra_footpaths\Site\View\Report;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_footpaths\Administrator\Helper\FootpathsHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\MapHelper;
use Ramblers\Component\Ra_footpaths\Site\Helper\RouteHelper;
use Ramblers\Component\Ra_footpaths\Site\Model\InspectionModel;
use Ramblers\Component\Ra_footpaths\Site\Model\ParishModel;

/**
 * "Record an inspection" for one path (view=report&id=<path>), or change one (&inspection=<id>). Inspectors record in
 * their own name; footpath admins in anyone's. The form sends itself with footpaths-report.js (task=inspection.save).
 */
class HtmlView extends BaseHtmlView
{
	protected ?object $path = null;

	protected ?object $last = null;

	/** @var array<int,object> */
	protected array $faultTypes = [];

	/** @var array<int,object> */
	protected array $inspectors = [];

	protected int $myInspector = 0;

	/** The inspection being changed, with its faults (InspectionModel::getFull), or null for a new one. */
	protected ?object $editing = null;

	protected bool $isAdmin = false;

	protected string $mapHtml = '';

	public function display($tpl = null): void
	{
		$id = Factory::getApplication()->getInput()->getInt('id', 0);
		$model = $this->getModel();
		$this->path = $model->getPath($id);

		if ($this->path === null) {
			throw new \Exception(Text::_('COM_RA_FOOTPATHS_PATH_NOT_FOUND'), 404);
		}

		$user = Factory::getApplication()->getIdentity();

		if (!FootpathsHelper::recordingOn()) {
			throw new \Exception(FootpathsHelper::recordingOffMessage(), 403);
		}

		if (!FootpathsHelper::canRecord($user)) {
			throw new \Exception('Your website login is not linked to an inspector yet, so you can\'t record inspections. Please ask the footpaths secretary to link it.', 403);
		}

		$this->isAdmin = FootpathsHelper::canManage($user);
		$inspectionId = Factory::getApplication()->getInput()->getInt('inspection', 0);

		if ($inspectionId > 0) {
			$this->editing = (new InspectionModel(['dbo' => Factory::getContainer()->get('DatabaseDriver')]))->getFull($inspectionId);

			if ($this->editing === null || (int) $this->editing->path_id !== $id) {
				throw new \Exception('That inspection no longer exists.', 404);
			}

			if (!FootpathsHelper::canActFor($user, $this->editing->inspector_id === null ? null : (int) $this->editing->inspector_id)) {
				throw new \Exception('You can only change your own inspections.', 403);
			}
		}

		$this->last = $model->getInspections($id)[0] ?? null;
		$this->faultTypes = $model->getFaultTypes($this->editing);
		$current = $this->editing && $this->editing->inspector_id !== null ? (int) $this->editing->inspector_id : 0;
		$this->inspectors = $model->getInspectors($this->isAdmin ? null : FootpathsHelper::myInspectorIds($user), $current);

		if ($current > 0) {
			$this->myInspector = $current;
		} elseif (\count($this->inspectors) === 1) {
			$this->myInspector = (int) $this->inspectors[0]->id;
		} else {
			$mine = FootpathsHelper::myInspectorIds($user);
			$this->myInspector = \count($mine) === 1 ? $mine[0] : 0;
		}

		$wa = $this->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_ra_footpaths');
		$wa->useStyle('com_ra_footpaths.site')->useScript('com_ra_footpaths.report');

		$this->mapHtml = MapHelper::render($this->mapData(), '380px');

		$this->getDocument()->setTitle(($this->editing ? 'Change an inspection: ' : 'Record an inspection: ') . $this->name() . ', ' . $this->path->parish);
		$this->getDocument()->setMetaData('robots', 'noindex, nofollow');

		parent::display($tpl);
	}

	public function name(): string
	{
		return FootpathsHelper::pathName($this->path->type, $this->path->number);
	}

	public function pathUrl(): string
	{
		return RouteHelper::url('view=path&id=' . (int) $this->path->id);
	}

	public function parishUrl(): string
	{
		return RouteHelper::url('view=parish&id=' . (int) $this->path->parish_id);
	}

	public function photoUrl(int $id, bool $thumb): string
	{
		return RouteHelper::url('task=photo.show&id=' . $id . ($thumb ? '&size=thumb' : ''), false);
	}

	/** Not a SEF link: the router would make it "….json", which the site's .htaccess doesn't pass to Joomla. */
	public function saveUrl(): string
	{
		return Uri::root(true) . '/index.php?option=com_ra_footpaths&task=inspection.save&format=json';
	}

	public function token(): string
	{
		return Session::getFormToken();
	}

	/** The most photos one save can send: PHP's max_file_uploads. */
	public function maxUploads(): int
	{
		return max(1, (int) ini_get('max_file_uploads'));
	}

	/** The largest request PHP accepts (post_max_size), in bytes, less a margin for the other fields. */
	public function maxPost(): int
	{
		$v = trim((string) ini_get('post_max_size'));
		$n = (int) $v;
		$n *= ['k' => 1024, 'm' => 1048576, 'g' => 1073741824][strtolower(substr($v, -1))] ?? 1;

		return $n > 0 ? max(1, $n - 200000) : 0;
	}

	/** The inspection being changed, for the form to fill itself in. Same JSON flags as lastFaultsJson. */
	public function editingJson(): string
	{
		$e = $this->editing;

		if ($e === null) {
			return 'null';
		}

		$faults = [];

		foreach ($e->faults as $f) {
			$faults[] = [
				'id' => (int) $f->id,
				'type' => (int) $f->fault_type_id,
				'grade' => (string) $f->grade,
				'remarks' => trim((string) $f->remarks),
				'lat' => $f->lat !== null && (float) $f->lat !== 0.0 ? (float) $f->lat : null,
				'lng' => $f->lng !== null && (float) $f->lng !== 0.0 ? (float) $f->lng : null,
				'gridref' => (string) $f->gridref,
				'notes' => (int) $f->notes,
				'photos' => array_map(fn ($id) => ['id' => $id, 'thumb' => $this->photoUrl($id, true)], $f->photos),
			];
		}

		return json_encode([
			'id' => (int) $e->id,
			'date' => $e->inspection_date,
			'grade' => FootpathsHelper::normalise($e->grade_recorded ?: $e->grade),
			'remarks' => (string) $e->remarks,
			'faults' => $faults,
		], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}

	public function date(?string $d): string
	{
		return $d ? HTMLHelper::_('date', $d, 'j M Y') : 'no date';
	}

	/**
	 * The last inspection's faults, for "copy from the last inspection". Goes into a JSON script block
	 * (json_encode with the HEX flags, so the free text can't close the tag).
	 */
	public function lastFaultsJson(): string
	{
		$types = [];

		foreach ($this->faultTypes as $t) {
			$types[strtolower($t->name)] = (int) $t->id;
		}

		$faults = [];

		foreach ($this->last->faults ?? [] as $f) {
			$faults[] = [
				'type' => $types[strtolower((string) $f->type)] ?? 0,
				'grade' => \in_array($f->grade, ['B', 'C', 'D'], true) ? $f->grade : '',
				'remarks' => trim((string) $f->remarks),
				'lat' => $f->lat !== null && (float) $f->lat !== 0.0 ? (float) $f->lat : null,
				'lng' => $f->lng !== null && (float) $f->lng !== 0.0 ? (float) $f->lng : null,
				'gridref' => (string) $f->gridref,
			];
		}

		return json_encode($faults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}

	/** This path highlighted, the parish's others in grey, and "pick" so a tap on the map places a fault. */
	private function mapData(): array
	{
		$parish = new ParishModel(['dbo' => Factory::getContainer()->get('DatabaseDriver')]);
		$paths = [];

		foreach ($parish->getPaths((int) $this->path->parish_id) as $p) {
			$current = (int) $p->id === (int) $this->path->id;
			$grade = $p->inspection_id ? FootpathsHelper::normalise($p->grade) : 'N';
			$paths[] = [
				'id' => (int) $p->id,
				'label' => MapHelper::plain(FootpathsHelper::pathName($p->type, $p->number)),
				'grade' => $grade,
				'colour' => $current ? FootpathsHelper::gradeColour($grade) : '#9e9e9e',
				'date' => $p->inspection_date ? HTMLHelper::_('date', $p->inspection_date, 'j M Y') : '',
				'url' => '',
				'route' => $p->route ? json_decode($p->route) : null,
				'current' => $current,
			];
		}

		usort($paths, static fn ($a, $b) => (int) $a['current'] <=> (int) $b['current']);

		return ['boundary' => null, 'paths' => $paths, 'faults' => [], 'pick' => true];
	}
}
