<?php
/**
 * Dev only: one-off set-up for the RA Footpaths demo. Run from the site root in the web container:
 *   php /var/www/html/tmp/dev-setup.php <parent menu item id> <user id>,<user id>,...
 * Creates (or reuses) the "Footpath inspectors" user group and access level, puts the users in the
 * group, chooses that group in the component options, and adds a menu item "Inspection Records"
 * under the given parent, visible only to that access level. Safe to run again.
 */

[$script, $parentId, $userList] = $argv + [null, '', ''];

if ((int) $parentId <= 0) {
	exit("Usage: php dev-setup.php <parent menu item id> <user ids, comma separated>\n");
}

$_SERVER['HTTP_HOST'] = 'localhost:8080';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
define('_JEXEC', 1);
define('JPATH_BASE', getcwd());
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Menu as MenuTable;
use Joomla\CMS\Table\Usergroup;
use Joomla\CMS\Table\ViewLevel;

$container = Factory::getContainer();
$container->alias('session', 'session.cli')->alias('JSession', 'session.cli')
	->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
	->alias(\Joomla\Session\Session::class, 'session.cli')
	->alias(\Joomla\Session\SessionInterface::class, 'session.cli');
$app = $container->get(\Joomla\CMS\Application\ConsoleApplication::class);
Factory::$application = $app;
$db = $container->get('DatabaseDriver');
$title = 'Footpath inspectors';

// User group, under Registered.
$groupId = (int) $db->setQuery($db->getQuery(true)->select('id')->from('#__usergroups')->where('title = ' . $db->quote($title)))->loadResult();

if (!$groupId) {
	$group = new Usergroup($db);
	$group->bind(['parent_id' => 2, 'title' => $title]);

	if (!$group->check() || !$group->store()) {
		exit("Group not saved: " . $group->getError() . "\n");
	}

	$group->rebuild();
	$groupId = (int) $group->id;
}

echo "Group: $groupId\n";

// Access level: the group plus Super Users.
$levelId = (int) $db->setQuery($db->getQuery(true)->select('id')->from('#__viewlevels')->where('title = ' . $db->quote($title)))->loadResult();
$level = new ViewLevel($db);

if ($levelId) {
	$level->load($levelId);
}

$level->bind(['title' => $title, 'rules' => json_encode([$groupId, 8])]);

if (!$level->store()) {
	exit("Access level not saved\n");
}

$levelId = (int) $level->id;
echo "Access level: $levelId\n";

foreach (array_filter(array_map('intval', explode(',', $userList))) as $uid) {
	// Straight to the map table: UserHelper would fire user plugins this bare bootstrap cannot load.
	$db->setQuery('INSERT IGNORE INTO ' . $db->quoteName('#__user_usergroup_map') . ' (user_id, group_id) VALUES (' . $uid . ', ' . $groupId . ')')->execute();
	echo "Added user $uid\n";
}

// Component option.
$query = $db->getQuery(true)->select(['extension_id', 'params'])->from('#__extensions')
	->where('element = ' . $db->quote('com_ra_footpaths'))->where('type = ' . $db->quote('component'));
$ext = $db->setQuery($query)->loadObject();
$params = json_decode((string) $ext->params, true) ?: [];
$params['inspector_group'] = (string) $groupId;
$db->setQuery($db->getQuery(true)->update('#__extensions')->set('params = ' . $db->quote(json_encode($params)))
	->where('extension_id = ' . (int) $ext->extension_id))->execute();
echo "Component option set\n";

// Menu item.
$parent = $db->setQuery($db->getQuery(true)->select(['id', 'menutype'])->from('#__menu')->where('id = ' . (int) $parentId))->loadObject();
$existing = (int) $db->setQuery($db->getQuery(true)->select('id')->from('#__menu')
	->where('link = ' . $db->quote('index.php?option=com_ra_footpaths&view=parishes'))->where('client_id = 0'))->loadResult();
$menu = new MenuTable($db);

if ($existing) {
	$menu->load($existing);
} else {
	$menu->setLocation((int) $parent->id, 'last-child');
}

$menu->bind([
	'menutype' => $parent->menutype,
	'title' => 'Inspection Records',
	'alias' => 'inspection-records',
	'link' => 'index.php?option=com_ra_footpaths&view=parishes',
	'type' => 'component',
	'published' => 1,
	'parent_id' => (int) $parent->id,
	'component_id' => (int) $ext->extension_id,
	'access' => $levelId,
	'language' => '*',
	'client_id' => 0,
	'params' => '{"menu_show":1}',
]);

if (!$menu->check() || !$menu->store()) {
	exit("Menu item not saved: " . $menu->getError() . "\n");
}

$menu->rebuildPath($menu->id);
echo "Menu item: {$menu->id} ({$menu->path})\n";
