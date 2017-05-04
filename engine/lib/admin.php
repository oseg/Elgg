<?php
/**
 * Elgg admin functions.
 *
 * Admin menu items
 * Elgg has a convenience function for adding menu items to the sidebar of the
 * admin area. @see elgg_register_admin_menu_item()
 *
 * Admin pages
 * Plugins no not need to provide their own page handler to add a page to the
 * admin area. A view placed at admin/<section>/<subsection> can be access
 * at http://example.org/admin/<section>/<subsection>. The title of the page
 * will be elgg_echo('admin:<section>:<subsection>'). For an example of how to
 * add a page to the admin area, see the diagnostics plugin.
 *
 * Admin notices
 * System messages (success and error messages) are used in both the main site
 * and the admin area. There is a special presistent message for the admin area
 * called an admin notice. It should be used when a plugin requires an
 * administrator to take an action. @see elgg_add_admin_notice()
 *
 *
 * @package Elgg.Core
 * @subpackage Admin
 */

/**
 * Get the admin users
 *
 * @param array $options Options array, @see elgg_get_entities() for parameters
 *
 * @return mixed Array of admin users or false on failure. If a count, returns int.
 * @since 1.8.0
 */
function elgg_get_admins(array $options = []) {
	global $CONFIG;

	if (isset($options['joins'])) {
		if (!is_array($options['joins'])) {
			$options['joins'] = [$options['joins']];
		}
		$options['joins'][] = "join {$CONFIG->dbprefix}users_entity u on e.guid=u.guid";
	} else {
		$options['joins'] = ["join {$CONFIG->dbprefix}users_entity u on e.guid=u.guid"];
	}

	if (isset($options['wheres'])) {
		if (!is_array($options['wheres'])) {
			$options['wheres'] = [$options['wheres']];
		}
		$options['wheres'][] = "u.admin = 'yes'";
	} else {
		$options['wheres'][] = "u.admin = 'yes'";
	}

	return elgg_get_entities($options);
}

/**
 * Write a persistent message to the admin view.
 * Useful to alert the admin to take a certain action.
 * The id is a unique ID that can be cleared once the admin
 * completes the action.
 *
 * eg: add_admin_notice('twitter_services_no_api',
 * 	'Before your users can use Twitter services on this site, you must set up
 * 	the Twitter API key in the <a href="link">Twitter Services Settings</a>');
 *
 * @param string $id      A unique ID that your plugin can remember
 * @param string $message Body of the message
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_add_admin_notice($id, $message) {
	return _elgg_services()->adminNotices->add($id, $message);
}

/**
 * Remove an admin notice by ID.
 *
 * eg In actions/twitter_service/save_settings:
 * 	if (is_valid_twitter_api_key()) {
 * 		delete_admin_notice('twitter_services_no_api');
 * 	}
 *
 * @param string $id The unique ID assigned in add_admin_notice()
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_delete_admin_notice($id) {
	return _elgg_services()->adminNotices->delete($id);
}

/**
 * Get admin notices. An admin must be logged in since the notices are private.
 *
 * @param array $options Query options
 *
 * @return ElggObject[] Admin notices
 * @since 1.8.0
 */
function elgg_get_admin_notices(array $options = []) {
	return _elgg_services()->adminNotices->find($options);
}

/**
 * Check if an admin notice is currently active. (Ignores access)
 *
 * @param string $id The unique ID used to register the notice.
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_admin_notice_exists($id) {
	return _elgg_services()->adminNotices->exists($id);
}

/**
 * Add an admin area section or child section.
 * This is a wrapper for elgg_register_menu_item().
 *
 * Used in conjuction with http://elgg.org/admin/section_id/child_section style
 * page handler. See the documentation at the top of this file for more details
 * on that.
 *
 * The text of the menu item is obtained from elgg_echo(admin:$parent_id:$menu_id)
 *
 * This function handles registering the parent if it has not been registered.
 *
 * @param string $section   The menu section to add to
 * @param string $menu_id   The unique ID of section
 * @param string $parent_id If a child section, the parent section id
 * @param int    $priority  The menu item priority
 *
 * @return bool
 * @since 1.8.0
 */
function elgg_register_admin_menu_item($section, $menu_id, $parent_id = null, $priority = 100) {

	// make sure parent is registered
	if ($parent_id && !elgg_is_menu_item_registered('page', $parent_id)) {
		elgg_register_admin_menu_item($section, $parent_id);
	}

	// in the admin section parents never have links
	if ($parent_id) {
		$href = "admin/$parent_id/$menu_id";
	} else {
		$href = null;
	}

	$name = $menu_id;
	if ($parent_id) {
		$name = "$parent_id:$name";
	}

	return elgg_register_menu_item('page', [
		'name' => $name,
		'href' => $href,
		'text' => elgg_echo("admin:$name"),
		'context' => 'admin',
		'parent_name' => $parent_id,
		'priority' => $priority,
		'section' => $section
	]);
}

/**
 * Add an admin notice when a new \ElggUpgrade object is created.
 *
 * @param string     $event
 * @param string     $type
 * @param \ElggObject $object
 * @access private
 */
function _elgg_create_notice_of_pending_upgrade($event, $type, $object) {
	if ($object instanceof \ElggUpgrade) {
		// Link to the Upgrades section
		$link = elgg_view('output/url', [
			'href' => 'admin/upgrades',
			'text' => elgg_echo('admin:view_upgrades'),
		]);

		$message = elgg_echo('admin:pending_upgrades');

		elgg_add_admin_notice('pending_upgrades', "$message $link");
	}
}

/**
 * Initialize the admin backend.
 * @return void
 * @access private
 */
function _elgg_admin_init() {

	$url = elgg_get_simplecache_url('admin.css');
	elgg_register_css('elgg.admin', $url);
		
	elgg_register_plugin_hook_handler('register', 'menu:admin_header', '_elgg_admin_header_menu');
	elgg_register_plugin_hook_handler('register', 'menu:admin_footer', '_elgg_admin_footer_menu');

	// maintenance mode
	if (elgg_get_config('elgg_maintenance_mode', null)) {
		elgg_register_plugin_hook_handler('route', 'all', '_elgg_admin_maintenance_handler', 600);
		elgg_register_plugin_hook_handler('action', 'all', '_elgg_admin_maintenance_action_check', 600);
		elgg_register_css('maintenance', elgg_get_simplecache_url('maintenance.css'));

		elgg_register_menu_item('topbar', [
			'name' => 'maintenance_mode',
			'href' => 'admin/administer_utilities/maintenance',
			'text' => elgg_echo('admin:maintenance_mode:indicator_menu_item'),
			'priority' => 900,
		]);
	}

	elgg_register_action('admin/user/ban', '', 'admin');
	elgg_register_action('admin/user/unban', '', 'admin');
	elgg_register_action('admin/user/delete', '', 'admin');
	elgg_register_action('admin/user/resetpassword', '', 'admin');
	elgg_register_action('admin/user/makeadmin', '', 'admin');
	elgg_register_action('admin/user/removeadmin', '', 'admin');

	elgg_register_action('admin/site/update_basic', '', 'admin');
	elgg_register_action('admin/site/update_advanced', '', 'admin');
	elgg_register_action('admin/site/flush_cache', '', 'admin');
	elgg_register_action('admin/site/unlock_upgrade', '', 'admin');
	elgg_register_action('admin/site/set_robots', '', 'admin');
	elgg_register_action('admin/site/set_maintenance_mode', '', 'admin');

	elgg_register_action('admin/upgrades/upgrade_database_guid_columns', '', 'admin');
	elgg_register_action('admin/site/regenerate_secret', '', 'admin');
	elgg_register_action('admin/upgrade', '', 'admin');

	elgg_register_action('admin/menu/save', '', 'admin');

	elgg_register_action('admin/delete_admin_notice', '', 'admin');
	
	elgg_register_action('admin/security/settings', '', 'admin');

	elgg_register_action('profile/fields/reset', '', 'admin');
	elgg_register_action('profile/fields/add', '', 'admin');
	elgg_register_action('profile/fields/edit', '', 'admin');
	elgg_register_action('profile/fields/delete', '', 'admin');
	elgg_register_action('profile/fields/reorder', '', 'admin');

	elgg_register_simplecache_view('admin.css');

	elgg_register_js('jquery.jeditable', elgg_get_simplecache_url('jquery.jeditable.js'));

	// administer
	// dashboard
	elgg_register_menu_item('page', [
		'name' => 'dashboard',
		'href' => 'admin/dashboard',
		'text' => elgg_echo('admin:dashboard'),
		'context' => 'admin',
		'priority' => 10,
		'section' => 'administer'
	]);
	// statistics
	elgg_register_admin_menu_item('administer', 'statistics', null, 20);
	elgg_register_admin_menu_item('administer', 'overview', 'statistics');
	elgg_register_admin_menu_item('administer', 'server', 'statistics');
	//utilities
	elgg_register_admin_menu_item('administer', 'maintenance', 'administer_utilities');
	// security
	elgg_register_admin_menu_item('administer', 'settings', 'administer_security');

	// users
	elgg_register_admin_menu_item('administer', 'users', null, 20);
	elgg_register_admin_menu_item('administer', 'online', 'users', 10);
	elgg_register_admin_menu_item('administer', 'admins', 'users', 20);
	elgg_register_admin_menu_item('administer', 'newest', 'users', 30);
	elgg_register_admin_menu_item('administer', 'add', 'users', 40);

	// configure
	// upgrades
	elgg_register_menu_item('page', [
		'name' => 'upgrades',
		'href' => 'admin/upgrades',
		'text' => elgg_echo('admin:upgrades'),
		'context' => 'admin',
		'priority' => 10,
		'section' => 'configure'
	]);

	// plugins
	elgg_register_menu_item('page', [
		'name' => 'plugins',
		'href' => 'admin/plugins',
		'text' => elgg_echo('admin:plugins'),
		'context' => 'admin',
		'priority' => 75,
		'section' => 'configure'
	]);

	// settings
	elgg_register_admin_menu_item('configure', 'appearance', null, 50);
	elgg_register_admin_menu_item('configure', 'settings', null, 100);
	elgg_register_admin_menu_item('configure', 'basic', 'settings', 10);
	elgg_register_admin_menu_item('configure', 'advanced', 'settings', 20);
	// plugin settings are added in _elgg_admin_add_plugin_settings_menu() via the admin page handler
	// for performance reasons.

	// appearance
	elgg_register_admin_menu_item('configure', 'menu_items', 'appearance', 30);
	elgg_register_admin_menu_item('configure', 'profile_fields', 'appearance', 40);
	// default widgets is added via an event handler elgg_default_widgets_init() in widgets.php
	// because it requires additional setup.

	// configure utilities
	elgg_register_admin_menu_item('configure', 'robots', 'configure_utilities');

	// we want plugin settings menu items to be sorted alphabetical
	if (elgg_in_context('admin') && elgg_is_admin_logged_in()) {
		elgg_register_plugin_hook_handler('prepare', 'menu:page', '_elgg_admin_sort_page_menu');
	}

	// widgets
	$widgets = ['online_users', 'new_users', 'content_stats', 'banned_users', 'admin_welcome', 'control_panel', 'cron_status'];
	foreach ($widgets as $widget) {
		elgg_register_widget_type(
				$widget,
				elgg_echo("admin:widget:$widget"),
				elgg_echo("admin:widget:$widget:help"),
				['admin']
		);
	}

	// automatic adding of widgets for admin
	elgg_register_event_handler('make_admin', 'user', '_elgg_add_admin_widgets');
	
	elgg_register_notification_event('user', '', ['make_admin', 'remove_admin']);
	elgg_register_plugin_hook_handler('get', 'subscriptions', '_elgg_admin_get_admin_subscribers_admin_action');
	elgg_register_plugin_hook_handler('get', 'subscriptions', '_elgg_admin_get_user_subscriber_admin_action');
	elgg_register_plugin_hook_handler('prepare', 'notification:make_admin:user:', '_elgg_admin_prepare_admin_notification_make_admin');
	elgg_register_plugin_hook_handler('prepare', 'notification:make_admin:user:', '_elgg_admin_prepare_user_notification_make_admin');
	elgg_register_plugin_hook_handler('prepare', 'notification:remove_admin:user:', '_elgg_admin_prepare_admin_notification_remove_admin');
	elgg_register_plugin_hook_handler('prepare', 'notification:remove_admin:user:', '_elgg_admin_prepare_user_notification_remove_admin');
	
	// Add notice about pending upgrades
	elgg_register_event_handler('create', 'object', '_elgg_create_notice_of_pending_upgrade');

	elgg_register_page_handler('admin', '_elgg_admin_page_handler');
	elgg_register_page_handler('admin_plugin_text_file', '_elgg_admin_markdown_page_handler');
	elgg_register_page_handler('robots.txt', '_elgg_robots_page_handler');
	elgg_register_page_handler('admin_plugins_refresh', '_elgg_ajax_plugins_update');
}

/**
 * Returns plugin listing and admin menu to the client (used after plugin (de)activation)
 *
 * @access private
 * @return Elgg\Http\OkResponse
 */
function _elgg_ajax_plugins_update() {
	elgg_admin_gatekeeper();
	_elgg_admin_add_plugin_settings_menu();
	elgg_set_context('admin');

	return elgg_ok_response([
		'list' => elgg_view('admin/plugins', ['list_only' => true]),
		'sidebar' => elgg_view('admin/sidebar'),
	]);
}

/**
 * Register menu items for the admin_header menu
 *
 * @param string $hook
 * @param string $type
 * @param array  $return
 * @param array  $params
 * @return array
 *
 * @access private
 *
 * @since 3.0
 */
function _elgg_admin_header_menu($hook, $type, $return, $params) {
	if (!elgg_in_context('admin') || !elgg_is_admin_logged_in()) {
		return;
	}

	$admin = elgg_get_logged_in_user_entity();

	$return[] = \ElggMenuItem::factory([
		'name' => 'admin_logout',
		'href' => 'action/logout',
		'text' => elgg_echo('logout'),
		'is_trusted' => true,
		'priority' => 1000,
	]);

	$return[] = \ElggMenuItem::factory([
		'name' => 'view_site',
		'href' => elgg_get_site_url(),
		'text' => elgg_echo('admin:view_site'),
		'is_trusted' => true,
		'priority' => 900,
	]);

	$return[] = \ElggMenuItem::factory([
		'name' => 'admin_profile',
		'href' => false,
		'text' => elgg_echo('admin:loggedin', [$admin->name]),
		'priority' => 800,
	]);

	if (elgg_get_config('elgg_maintenance_mode', null)) {
		$return[] = \ElggMenuItem::factory([
			'name' => 'maintenance',
			'href' => 'admin/administer_utilities/maintenance',
			'text' => elgg_echo('admin:administer_utilities:maintenance'),
			'link_class' => 'elgg-maintenance-mode-warning',
			'priority' => 700,
		]);
	}
	
	return $return;
}

/**
 * Register menu items for the admin_footer menu
 *
 * @param string $hook
 * @param string $type
 * @param array  $return
 * @param array  $params
 * @return array
 *
 * @access private
 *
 * @since 3.0
 */
function _elgg_admin_footer_menu($hook, $type, $return, $params) {
	if (!elgg_in_context('admin') || !elgg_is_admin_logged_in()) {
		return;
	}

	$return[] = \ElggMenuItem::factory([
		'name' => 'faq',
		'text' => elgg_echo('admin:footer:faq'),
		'href' => 'http://learn.elgg.org/en/stable/appendix/faqs.html',
	]);

	$return[] = \ElggMenuItem::factory([
		'name' => 'manual',
		'text' => elgg_echo('admin:footer:manual'),
		'href' => 'http://learn.elgg.org/en/stable/admin/index.html',
	]);

	$return[] = \ElggMenuItem::factory([
		'name' => 'community_forums',
		'text' => elgg_echo('admin:footer:community_forums'),
		'href' => 'http://elgg.org/groups/all/',
	]);

	$return[] = \ElggMenuItem::factory([
		'name' => 'blog',
		'text' => elgg_echo('admin:footer:blog'),
		'href' => 'https://elgg.org/blog/all',
	]);
	
	return $return;
}

/**
 * Create the plugin settings page menu.
 *
 * This is done in a separate function called from the admin
 * page handler because of performance concerns.
 *
 * @return void
 * @access private
 * @since 1.8.0
 */
function _elgg_admin_add_plugin_settings_menu() {

	$active_plugins = elgg_get_plugins('active');
	if (!$active_plugins) {
		// nothing added because no items
		return;
	}

	foreach ($active_plugins as $plugin) {
		$plugin_id = $plugin->getID();
		$settings_view_old = 'settings/' . $plugin_id . '/edit';
		$settings_view_new = 'plugins/' . $plugin_id . '/settings';
		if (elgg_view_exists($settings_view_new) || elgg_view_exists($settings_view_old)) {
			elgg_register_menu_item('page', [
				'name' => $plugin_id,
				'href' => "admin/plugin_settings/$plugin_id",
				'text' => $plugin->getManifest()->getName(),
				'parent_name' => 'settings',
				'context' => 'admin',
				'section' => 'configure',
			]);
		}
	}
}

/**
 * Sort the plugin settings menu items
 *
 * @param string $hook
 * @param string $type
 * @param array  $return
 * @param array  $params
 *
 * @return void
 * @since 1.8.0
 * @access private
 */
function _elgg_admin_sort_page_menu($hook, $type, $return, $params) {
	$configure_items = $return['configure'];
	if (is_array($configure_items)) {
		/* @var \ElggMenuItem[] $configure_items */
		foreach ($configure_items as $menu_item) {
			if ($menu_item->getName() == 'settings') {
				$settings = $menu_item;
			}
		}

		if (!empty($settings) && $settings instanceof \ElggMenuItem) {
			// keep the basic and advanced settings at the top
			/* @var \ElggMenuItem $settings */
			$children = $settings->getChildren();
			$site_settings = array_splice($children, 0, 2);
			usort($children, [\ElggMenuBuilder::class, 'compareByText']);
			array_splice($children, 0, 0, $site_settings);
			$settings->setChildren($children);
		}
	}
}

/**
 * Handle admin pages.  Expects corresponding views as admin/section/subsection
 *
 * @param array $page Array of pages
 *
 * @return bool
 * @access private
 */
function _elgg_admin_page_handler($page) {
	elgg_admin_gatekeeper();
	_elgg_admin_add_plugin_settings_menu();
	elgg_set_context('admin');

	elgg_unregister_css('elgg');
	elgg_require_js('elgg/admin');

	elgg_load_js('jquery.jeditable');

	// default to dashboard
	if (!isset($page[0]) || empty($page[0])) {
		$page = ['dashboard'];
	}

	// was going to fix this in the page_handler() function but
	// it's commented to explicitly return a string if there's a trailing /
	if (empty($page[count($page) - 1])) {
		array_pop($page);
	}

	$vars = ['page' => $page];

	// special page for plugin settings since we create the form for them
	if ($page[0] == 'plugin_settings') {
		if (isset($page[1]) && (elgg_view_exists("settings/{$page[1]}/edit") ||
				elgg_view_exists("plugins/{$page[1]}/settings"))) {
			$view = 'admin/plugin_settings';
			$plugin = elgg_get_plugin_from_id($page[1]);
			$vars['plugin'] = $plugin;

			$title = elgg_echo("admin:{$page[0]}");
		} else {
			forward('', '404');
		}
	} else {
		$view = 'admin/' . implode('/', $page);
		$title = elgg_echo("admin:{$page[0]}");
		if (count($page) > 1) {
			$title .= ' : ' . elgg_echo('admin:' .  implode(':', $page));
		}
	}

	// gets content and prevents direct access to 'components' views
	if ($page[0] == 'components' || !($content = elgg_view($view, $vars))) {
		$title = elgg_echo('admin:unknown_section');
		$content = elgg_echo('admin:unknown_section');
	}

	$body = elgg_view_layout('admin', ['content' => $content, 'title' => $title]);
	echo elgg_view_page($title, $body, 'admin');
	return true;
}

/**
 * Formats and serves out markdown files from plugins.
 *
 * URLs in format like admin_plugin_text_file/<plugin_id>/filename.ext
 *
 * The only valid files are:
 *	* README.txt
 *	* CHANGES.txt
 *	* INSTALL.txt
 *	* COPYRIGHT.txt
 *	* LICENSE.txt
 *
 * @param array $pages
 * @return bool
 * @access private
 */
function _elgg_admin_markdown_page_handler($pages) {
	elgg_set_context('admin');

	echo elgg_view_resource('admin/plugin_text_file', [
		'plugin_id' => elgg_extract(0, $pages),
		'filename' => elgg_extract(1, $pages),
	]);
	return true;
}

/**
 * Handle request for robots.txt
 *
 * @access private
 */
function _elgg_robots_page_handler() {
	echo elgg_view_resource('robots.txt');
	return true;
}

/**
 * When in maintenance mode, should the given URL be handled normally?
 *
 * @param string $current_url Current page URL
 * @return bool
 *
 * @access private
 */
function _elgg_admin_maintenance_allow_url($current_url) {
	$site_path = preg_replace('~^https?~', '', elgg_get_site_url());
	$current_path = preg_replace('~^https?~', '', $current_url);
	if (0 === strpos($current_path, $site_path)) {
		$current_path = ($current_path === $site_path) ? '' : substr($current_path, strlen($site_path));
	} else {
		$current_path = false;
	}

	// allow plugins to control access for specific URLs/paths
	$params = [
		'current_path' => $current_path,
		'current_url' => $current_url,
	];
	return (bool) elgg_trigger_plugin_hook('maintenance:allow', 'url', $params, false);
}

/**
 * Handle requests when in maintenance mode
 *
 * @access private
 */
function _elgg_admin_maintenance_handler($hook, $type, $info) {
	if (elgg_is_admin_logged_in()) {
		return;
	}

	if ($info['identifier'] == 'action' && $info['segments'][0] == 'login') {
		return;
	}

	if (_elgg_admin_maintenance_allow_url(current_page_url())) {
		return;
	}

	elgg_unregister_plugin_hook_handler('register', 'menu:login', '_elgg_login_menu_setup');

	echo elgg_view_resource('maintenance');

	return false;
}

/**
 * Prevent non-admins from using actions
 *
 * @access private
 *
 * @param string $hook Hook name
 * @param string $type Action name
 * @return bool
 */
function _elgg_admin_maintenance_action_check($hook, $type) {
	if (elgg_is_admin_logged_in()) {
		return true;
	}

	if ($type == 'login') {
		$username = get_input('username');

		$user = get_user_by_username($username);

		if (!$user) {
			$users = get_user_by_email($username);
			if ($users) {
				$user = $users[0];
			}
		}

		if ($user && $user->isAdmin()) {
			return true;
		}
	}

	if (_elgg_admin_maintenance_allow_url(current_page_url())) {
		return true;
	}

	register_error(elgg_echo('actionunauthorized'));

	return false;
}

/**
 * Adds default admin widgets to the admin dashboard.
 *
 * @param string $event
 * @param string $type
 * @param \ElggUser $user
 *
 * @return null|true
 * @access private
 */
function _elgg_add_admin_widgets($event, $type, $user) {
	elgg_set_ignore_access(true);

	// check if the user already has widgets
	if (elgg_get_widgets($user->getGUID(), 'admin')) {
		return true;
	}

	// In the form column => array of handlers in order, top to bottom
	$adminWidgets = [
		1 => ['control_panel', 'admin_welcome'],
		2 => ['online_users', 'new_users', 'content_stats'],
	];

	foreach ($adminWidgets as $column => $handlers) {
		foreach ($handlers as $position => $handler) {
			$guid = elgg_create_widget($user->getGUID(), $handler, 'admin');
			if ($guid) {
				$widget = get_entity($guid);
				/* @var \ElggWidget $widget */
				$widget->move($column, $position);
			}
		}
	}
	elgg_set_ignore_access(false);
}

/**
 * Add the current site admins to the subscribers when making/removing an admin user
 *
 * @param string $hook         'get'
 * @param string $type         'subscribers'
 * @param array  $return_value current subscribers
 * @param arary  $params       supplied params
 *
 * @return void|array
 */
function _elgg_admin_get_admin_subscribers_admin_action($hook, $type, $return_value, $params) {
	
	if (!_elgg_config()->security_notify_admins) {
		return;
	}
	
	$event = elgg_extract('event', $params);
	if (!($event instanceof \Elgg\Notifications\Event)) {
		return;
	}
	
	if (!in_array($event->getAction(), ['make_admin', 'remove_admin'])) {
		return;
	}
	
	$user = $event->getObject();
	if (!($user instanceof \ElggUser)) {
		return;
	}
	
	/* @var $admin_batch \Elgg\BatchResult */
	$admin_batch = elgg_get_admins([
		'limit' => false,
		'wheres' => [
			"e.guid <> {$user->getGUID()}",
		],
		'batch' => true,
	]);
	
	/* @var $admin \ElggUser */
	foreach ($admin_batch as $admin) {
		$return_value[$admin->getGUID()] = ['email'];
	}
	
	return $return_value;
}

/**
 * Prepare the notification content for site admins about making a site admin
 *
 * @param string                           $hook         'prepare'
 * @param string                           $type         'notification:make_admin:user:'
 * @param \Elgg\Notifications\Notification $return_value current notification content
 * @param array                            $params       supplied params
 *
 * @return void|\Elgg\Notifications\Notification
 */
function _elgg_admin_prepare_admin_notification_make_admin($hook, $type, $return_value, $params) {
	
	if (!($return_value instanceof \Elgg\Notifications\Notification)) {
		return;
	}
	
	$recipient = elgg_extract('recipient', $params);
	$object = elgg_extract('object', $params);
	$actor = elgg_extract('sender', $params);
	$language = elgg_extract('language', $params);
	
	if (!($recipient instanceof ElggUser) || !($object instanceof ElggUser) || !($actor instanceof ElggUser)) {
		return;
	}
	
	if ($recipient->getGUID() === $object->getGUID()) {
		// recipient is the user being acted on, this is handled elsewhere
		return;
	}
	
	$site = elgg_get_site_entity();
	
	$return_value->subject = elgg_echo('admin:notification:make_admin:admin:subject', [$site->name], $language);
	$return_value->body = elgg_echo('admin:notification:make_admin:admin:body', [
		$recipient->name,
		$actor->name,
		$object->name,
		$site->name,
		$object->getURL(),
		$site->getURL(),
	], $language);

	$return_value->url = elgg_normalize_url('admin/users/admins');
	
	return $return_value;
}

/**
 * Prepare the notification content for site admins about removing a site admin
 *
 * @param string                           $hook         'prepare'
 * @param string                           $type         'notification:remove_admin:user:'
 * @param \Elgg\Notifications\Notification $return_value current notification content
 * @param array                            $params       supplied params
 *
 * @return void|\Elgg\Notifications\Notification
 */
function _elgg_admin_prepare_admin_notification_remove_admin($hook, $type, $return_value, $params) {
	
	if (!($return_value instanceof \Elgg\Notifications\Notification)) {
		return;
	}
	
	$recipient = elgg_extract('recipient', $params);
	$object = elgg_extract('object', $params);
	$actor = elgg_extract('sender', $params);
	$language = elgg_extract('language', $params);
	
	if (!($recipient instanceof ElggUser) || !($object instanceof ElggUser) || !($actor instanceof ElggUser)) {
		return;
	}
	
	if ($recipient->getGUID() === $object->getGUID()) {
		// recipient is the user being acted on, this is handled elsewhere
		return;
	}
	
	$site = elgg_get_site_entity();
	
	$return_value->subject = elgg_echo('admin:notification:remove_admin:admin:subject', [$site->name], $language);
	$return_value->body = elgg_echo('admin:notification:remove_admin:admin:body', [
		$recipient->name,
		$actor->name,
		$object->name,
		$site->name,
		$object->getURL(),
		$site->getURL(),
	], $language);

	$return_value->url = elgg_normalize_url('admin/users/admins');
	
	return $return_value;
}

/**
 * Add the user to the subscribers when making/removing the admin role
 *
 * @param string $hook         'get'
 * @param string $type         'subscribers'
 * @param array  $return_value current subscribers
 * @param arary  $params       supplied params
 *
 * @return void|array
 */
function _elgg_admin_get_user_subscriber_admin_action($hook, $type, $return_value, $params) {
	
	if (!_elgg_config()->security_notify_user_admin) {
		return;
	}
	
	$event = elgg_extract('event', $params);
	if (!($event instanceof \Elgg\Notifications\Event)) {
		return;
	}
	
	if (!in_array($event->getAction(), ['make_admin', 'remove_admin'])) {
		return;
	}
	
	$user = $event->getObject();
	if (!($user instanceof \ElggUser)) {
		return;
	}
	
	$return_value[$user->getGUID()] = ['email'];
	
	return $return_value;
}

/**
 * Prepare the notification content for the user being made as a site admins
 *
 * @param string                           $hook         'prepare'
 * @param string                           $type         'notification:make_admin:user:'
 * @param \Elgg\Notifications\Notification $return_value current notification content
 * @param array                            $params       supplied params
 *
 * @return void|\Elgg\Notifications\Notification
 */
function _elgg_admin_prepare_user_notification_make_admin($hook, $type, $return_value, $params) {
	
	if (!($return_value instanceof \Elgg\Notifications\Notification)) {
		return;
	}
	
	$recipient = elgg_extract('recipient', $params);
	$object = elgg_extract('object', $params);
	$actor = elgg_extract('sender', $params);
	$language = elgg_extract('language', $params);
	
	if (!($recipient instanceof ElggUser) || !($object instanceof ElggUser) || !($actor instanceof ElggUser)) {
		return;
	}
	
	if ($recipient->getGUID() !== $object->getGUID()) {
		// recipient is some other user, this is handled elsewhere
		return;
	}
	
	$site = elgg_get_site_entity();
	
	$return_value->subject = elgg_echo('admin:notification:make_admin:user:subject', [$site->name], $language);
	$return_value->body = elgg_echo('admin:notification:make_admin:user:body', [
		$recipient->name,
		$actor->name,
		$site->name,
		$site->getURL(),
	], $language);

	$return_value->url = elgg_normalize_url('admin');
	
	return $return_value;
}

/**
 * Prepare the notification content for the user being removed as a site admins
 *
 * @param string                           $hook         'prepare'
 * @param string                           $type         'notification:remove_admin:user:'
 * @param \Elgg\Notifications\Notification $return_value current notification content
 * @param array                            $params       supplied params
 *
 * @return void|\Elgg\Notifications\Notification
 */
function _elgg_admin_prepare_user_notification_remove_admin($hook, $type, $return_value, $params) {
	
	if (!($return_value instanceof \Elgg\Notifications\Notification)) {
		return;
	}
	
	$recipient = elgg_extract('recipient', $params);
	$object = elgg_extract('object', $params);
	$actor = elgg_extract('sender', $params);
	$language = elgg_extract('language', $params);
	
	if (!($recipient instanceof ElggUser) || !($object instanceof ElggUser) || !($actor instanceof ElggUser)) {
		return;
	}
	
	if ($recipient->getGUID() !== $object->getGUID()) {
		// recipient is some other user, this is handled elsewhere
		return;
	}
	
	$site = elgg_get_site_entity();
	
	$return_value->subject = elgg_echo('admin:notification:remove_admin:user:subject', [$site->name], $language);
	$return_value->body = elgg_echo('admin:notification:remove_admin:user:body', [
		$recipient->name,
		$actor->name,
		$site->name,
		$site->getURL(),
	], $language);

	$return_value->url = false;
	
	return $return_value;
}

return function(\Elgg\EventsService $events, \Elgg\HooksRegistrationService $hooks) {
	$events->registerHandler('init', 'system', '_elgg_admin_init');
};
