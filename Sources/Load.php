<?php

/**
 * This file has the hefty job of loading information for the forum.
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines http://www.simplemachines.org
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 1.0 Alpha 1
 */

if (!defined('SMF'))
	die('No direct access...'); 

/**
 * Load the $modSettings array.
 *
 * @todo okay question of the day: why a function for loading settings is called reloadSettings()
 *
 * @global array $modSettings is a giant array of all of the forum-wide settings and statistics.
 */
function reloadSettings()
{
	global $modSettings, $boarddir, $smcFunc, $txt, $db_character_set, $context, $sourcedir;

	// Most database systems have not set UTF-8 as their default input charset.
	if (!empty($db_character_set))
		$smcFunc['db_query']('set_character_set', '
			SET NAMES ' . $db_character_set,
			array(
			)
		);

	// Try to load it from the cache first; it'll never get cached if the setting is off.
	if (($modSettings = cache_get_data('modSettings', 90)) == null)
	{
		$request = $smcFunc['db_query']('', '
			SELECT variable, value
			FROM {db_prefix}settings',
			array(
			)
		);
		$modSettings = array();
		if (!$request)
			display_db_error();
		while ($row = $smcFunc['db_fetch_row']($request))
			$modSettings[$row[0]] = $row[1];
		$smcFunc['db_free_result']($request);

		// Do a few things to protect against missing settings or settings with invalid values...
		if (empty($modSettings['defaultMaxTopics']) || $modSettings['defaultMaxTopics'] <= 0 || $modSettings['defaultMaxTopics'] > 999)
			$modSettings['defaultMaxTopics'] = 20;
		if (empty($modSettings['defaultMaxMessages']) || $modSettings['defaultMaxMessages'] <= 0 || $modSettings['defaultMaxMessages'] > 999)
			$modSettings['defaultMaxMessages'] = 15;
		if (empty($modSettings['defaultMaxMembers']) || $modSettings['defaultMaxMembers'] <= 0 || $modSettings['defaultMaxMembers'] > 999)
			$modSettings['defaultMaxMembers'] = 30;

		if (!empty($modSettings['cache_enable']))
			cache_put_data('modSettings', $modSettings, 90);
	}

	// UTF-8 ?
	$utf8 = (empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8';

	// Set a list of common functions.
	$ent_list = empty($modSettings['disableEntityCheck']) ? '&(#\d{1,7}|quot|amp|lt|gt|nbsp);' : '&(#021|quot|amp|lt|gt|nbsp);';
	$ent_check = empty($modSettings['disableEntityCheck']) ? array('preg_replace_callback(\'~(&#(\d{1,7}|x[0-9a-fA-F]{1,6});)~\', \'entity_fix__callback\', ', ')') : array('', '');

	// Preg_replace space characters depend on the character set in use
	$space_chars = $utf8 ? '\x{A0}\x{AD}\x{2000}-\x{200F}\x{201F}\x{202F}\x{3000}\x{FEFF}' : '\x00-\x08\x0B\x0C\x0E-\x19\xA0';

	// global array of anonymous helper functions, used mosly to properly handle multi byte strings
	$smcFunc += array(
		'entity_fix' => create_function('$string', '
			$num = $string[0] === \'x\' ? hexdec(substr($string, 1)) : (int) $string;
			return $num < 0x20 || $num > 0x10FFFF || ($num >= 0xD800 && $num <= 0xDFFF) || $num === 0x202E || $num === 0x202D ? \'\' : \'&#\' . $num . \';\';'),
		'htmlspecialchars' => create_function('$string, $quote_style = ENT_COMPAT, $charset = \'ISO-8859-1\'', '
			global $smcFunc;
			return ' . strtr($ent_check[0], array('&' => '&amp;')) . 'htmlspecialchars($string, $quote_style, ' . ($utf8 ? '\'UTF-8\'' : '$charset') . ')' . $ent_check[1] . ';'),
		'htmltrim' => create_function('$string', '
			global $smcFunc;
			return preg_replace(\'~^(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+|(?:[ \t\n\r\x0B\x00' . $space_chars . ']|&nbsp;)+$~' . ($utf8 ? 'u' : '') . '\', \'\', ' . implode('$string', $ent_check) . ');'),
		'strlen' => create_function('$string', '
			global $smcFunc;
			return strlen(preg_replace(\'~' . $ent_list . ($utf8 ? '|.~u' : '~') . '\', \'_\', ' . implode('$string', $ent_check) . '));'),
		'strpos' => create_function('$haystack, $needle, $offset = 0', '
			global $smcFunc;
			$haystack_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$haystack', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$haystack_size = count($haystack_arr);
			if (strlen($needle) === 1)
			{
				$result = array_search($needle, array_slice($haystack_arr, $offset));
				return is_int($result) ? $result + $offset : false;
			}
			else
			{
				$needle_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\',  ' . implode('$needle', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
				$needle_size = count($needle_arr);

				$result = array_search($needle_arr[0], array_slice($haystack_arr, $offset));
				while ((int) $result === $result)
				{
					$offset += $result;
					if (array_slice($haystack_arr, $offset, $needle_size) === $needle_arr)
						return $offset;
					$result = array_search($needle_arr[0], array_slice($haystack_arr, ++$offset));
				}
				return false;
			}'),
		'substr' => create_function('$string, $start, $length = null', '
			global $smcFunc;
			$ent_arr = preg_split(\'~(&#' . (empty($modSettings['disableEntityCheck']) ? '\d{1,7}' : '021') . ';|&quot;|&amp;|&lt;|&gt;|&nbsp;|.)~' . ($utf8 ? 'u' : '') . '\', ' . implode('$string', $ent_check) . ', -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			return $length === null ? implode(\'\', array_slice($ent_arr, $start)) : implode(\'\', array_slice($ent_arr, $start, $length));'),
		'strtolower' => $utf8 ? (function_exists('mb_strtolower') ? create_function('$string', '
			return mb_strtolower($string, \'UTF-8\');') : create_function('$string', '
			global $sourcedir;
			require_once($sourcedir . \'/Subs-Charset.php\');
			return utf8_strtolower($string);')) : 'strtolower',
		'strtoupper' => $utf8 ? (function_exists('mb_strtoupper') ? create_function('$string', '
			return mb_strtoupper($string, \'UTF-8\');') : create_function('$string', '
			global $sourcedir;
			require_once($sourcedir . \'/Subs-Charset.php\');
			return utf8_strtoupper($string);')) : 'strtoupper',
		'truncate' => create_function('$string, $length', (empty($modSettings['disableEntityCheck']) ? '
			global $smcFunc;
			$string = ' . implode('$string', $ent_check) . ';' : '') . '
			preg_match(\'~^(' . $ent_list . '|.){\' . $smcFunc[\'strlen\'](substr($string, 0, $length)) . \'}~'.  ($utf8 ? 'u' : '') . '\', $string, $matches);
			$string = $matches[0];
			while (strlen($string) > $length)
				$string = preg_replace(\'~(?:' . $ent_list . '|.)$~'.  ($utf8 ? 'u' : '') . '\', \'\', $string);
			return $string;'),
		'ucfirst' => $utf8 ? create_function('$string', '
			global $smcFunc;
			return $smcFunc[\'strtoupper\']($smcFunc[\'substr\']($string, 0, 1)) . $smcFunc[\'substr\']($string, 1);') : 'ucfirst',
		'ucwords' => $utf8 ? create_function('$string', '
			global $smcFunc;
			$words = preg_split(\'~([\s\r\n\t]+)~\', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
			for ($i = 0, $n = count($words); $i < $n; $i += 2)
				$words[$i] = $smcFunc[\'ucfirst\']($words[$i]);
			return implode(\'\', $words);') : 'ucwords',
	);

	// Setting the timezone is a requirement for some functions in PHP >= 5.1.
	if (isset($modSettings['default_timezone']) && function_exists('date_default_timezone_set'))
		date_default_timezone_set($modSettings['default_timezone']);

	// Check the load averages?
	if (!empty($modSettings['loadavg_enable']))
	{
		if (($modSettings['load_average'] = cache_get_data('loadavg', 90)) == null)
		{
			$modSettings['load_average'] = @file_get_contents('/proc/loadavg');
			if (!empty($modSettings['load_average']) && preg_match('~^([^ ]+?) ([^ ]+?) ([^ ]+)~', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			elseif (($modSettings['load_average'] = @`uptime`) != null && preg_match('~load average[s]?: (\d+\.\d+), (\d+\.\d+), (\d+\.\d+)~i', $modSettings['load_average'], $matches) != 0)
				$modSettings['load_average'] = (float) $matches[1];
			else
				unset($modSettings['load_average']);

			if (!empty($modSettings['load_average']))
				cache_put_data('loadavg', $modSettings['load_average'], 90);
		}

		if (!empty($modSettings['load_average']))
			call_integration_hook('integrate_load_average', array($modSettings['load_average']));

		if (!empty($modSettings['loadavg_forum']) && !empty($modSettings['load_average']) && $modSettings['load_average'] >= $modSettings['loadavg_forum'])
			display_loadavg_error();
	}

	// Is post moderation alive and well?
	$modSettings['postmod_active'] = isset($modSettings['admin_features']) ? in_array('pm', explode(',', $modSettings['admin_features'])) : true;

	// Integration is cool.
	if (defined('SMF_INTEGRATION_SETTINGS'))
	{
		$integration_settings = unserialize(SMF_INTEGRATION_SETTINGS);
		foreach ($integration_settings as $hook => $function)
			add_integration_function($hook, $function, false);
	}

	// Any files to pre include?
	if (!empty($modSettings['integrate_pre_include']))
	{
		$pre_includes = explode(',', $modSettings['integrate_pre_include']);
		foreach ($pre_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call pre load integration functions.
	call_integration_hook('integrate_pre_load');
}

/**
 * Load all the important user information.
 * What it does:
 * 	- sets up the $user_info array
 * 	- first checks for cookie or integration validation.
 * 	- uses the current session if no integration function or cookie is found.
 * 	- checks password length, if member is activated and the login span isn't over.
 * 		- if validation fails for the user, $id_member is set to 0.
 * 		- updates the last visit time when needed.
 */
function loadUserSettings()
{
	global $modSettings, $user_settings, $sourcedir, $smcFunc;
	global $cookiename, $user_info, $language, $context;

	// Check first the integration, then the cookie, and last the session.
	if (count($integration_ids = call_integration_hook('integrate_verify_user')) > 0)
	{
		$id_member = 0;
		foreach ($integration_ids as $integration_id)
		{
			$integration_id = (int) $integration_id;
			if ($integration_id > 0)
			{
				$id_member = $integration_id;
				$already_verified = true;
				break;
			}
		}
	}
	else
		$id_member = 0;

	if (empty($id_member) && isset($_COOKIE[$cookiename]))
	{
		// Fix a security hole in PHP 4.3.9 and below...
		if (preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~i', $_COOKIE[$cookiename]) == 1)
		{
			list ($id_member, $password) = @unserialize($_COOKIE[$cookiename]);
			$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
		}
		else
			$id_member = 0;
	}
	elseif (empty($id_member) && isset($_SESSION['login_' . $cookiename]) && ($_SESSION['USER_AGENT'] == $_SERVER['HTTP_USER_AGENT'] || !empty($modSettings['disableCheckUA'])))
	{
		// @todo Perhaps we can do some more checking on this, such as on the first octet of the IP?
		list ($id_member, $password, $login_span) = @unserialize($_SESSION['login_' . $cookiename]);
		$id_member = !empty($id_member) && strlen($password) == 40 && $login_span > time() ? (int) $id_member : 0;
	}

	// Only load this stuff if the user isn't a guest.
	if ($id_member != 0)
	{
		// Is the member data cached?
		if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < 2 || ($user_settings = cache_get_data('user_settings-' . $id_member, 60)) == null)
		{
			$request = $smcFunc['db_query']('', '
				SELECT mem.*
				FROM {db_prefix}members AS mem
				WHERE mem.id_member = {int:id_member}
				LIMIT 1',
				array(
					'id_member' => $id_member,
				)
			);
			$user_settings = $smcFunc['db_fetch_assoc']($request);
			$smcFunc['db_free_result']($request);

			if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
				cache_put_data('user_settings-' . $id_member, $user_settings, 60);
		}

		// Did we find 'im?  If not, junk it.
		if (!empty($user_settings))
		{
			// As much as the password should be right, we can assume the integration set things up.
			if (!empty($already_verified) && $already_verified === true)
				$check = true;
			// SHA-1 passwords should be 40 characters long.
			elseif (strlen($password) == 40)
				$check = sha1($user_settings['passwd'] . $user_settings['password_salt']) == $password;
			else
				$check = false;

			// Wrong password or not activated - either way, you're going nowhere.
			$id_member = $check && ($user_settings['is_activated'] == 1 || $user_settings['is_activated'] == 11) ? $user_settings['id_member'] : 0;
		}
		else
			$id_member = 0;

		// If we no longer have the member maybe they're being all hackey, stop brute force!
		if (!$id_member)
		{
			require_once($sourcedir . '/LogInOut.php');
			validatePasswordFlood(!empty($user_settings['id_member']) ? $user_settings['id_member'] : $id_member, !empty($user_settings['passwd_flood']) ? $user_settings['passwd_flood'] : false, $id_member != 0);
		}
	}

	// Found 'im, let's set up the variables.
	if ($id_member != 0)
	{
		// Let's not update the last visit time in these cases...
		// 1. SSI doesn't count as visiting the forum.
		// 2. RSS feeds and XMLHTTP requests don't count either.
		// 3. If it was set within this session, no need to set it again.
		// 4. New session, yet updated < five hours ago? Maybe cache can help.
		if (SMF != 'SSI' && !isset($_REQUEST['xml']) && (!isset($_REQUEST['action']) || $_REQUEST['action'] != '.xml') && empty($_SESSION['id_msg_last_visit']) && (empty($modSettings['cache_enable']) || ($_SESSION['id_msg_last_visit'] = cache_get_data('user_last_visit-' . $id_member, 5 * 3600)) === null))
		{
			// @todo can this be cached?
			// Do a quick query to make sure this isn't a mistake.
			$result = $smcFunc['db_query']('', '
				SELECT poster_time
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $user_settings['id_msg_last_visit'],
				)
			);
			list ($visitTime) = $smcFunc['db_fetch_row']($result);
			$smcFunc['db_free_result']($result);

			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

			// If it was *at least* five hours ago...
			if ($visitTime < time() - 5 * 3600)
			{
				updateMemberData($id_member, array('id_msg_last_visit' => (int) $modSettings['maxMsgID'], 'last_login' => time(), 'member_ip' => $_SERVER['REMOTE_ADDR'], 'member_ip2' => $_SERVER['BAN_CHECK_IP']));
				$user_settings['last_login'] = time();

				if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
					cache_put_data('user_settings-' . $id_member, $user_settings, 60);

				if (!empty($modSettings['cache_enable']))
					cache_put_data('user_last_visit-' . $id_member, $_SESSION['id_msg_last_visit'], 5 * 3600);
			}
		}
		elseif (empty($_SESSION['id_msg_last_visit']))
			$_SESSION['id_msg_last_visit'] = $user_settings['id_msg_last_visit'];

		$username = $user_settings['member_name'];

		if (empty($user_settings['additional_groups']))
			$user_info = array(
				'groups' => array($user_settings['id_group'], $user_settings['id_post_group'])
			);
		else
			$user_info = array(
				'groups' => array_merge(
					array($user_settings['id_group'], $user_settings['id_post_group']),
					explode(',', $user_settings['additional_groups'])
				)
			);

		// Because history has proven that it is possible for groups to go bad - clean up in case.
		foreach ($user_info['groups'] as $k => $v)
			$user_info['groups'][$k] = (int) $v;

		// This is a logged in user, so definitely not a spider.
		$user_info['possibly_robot'] = false;
	}
	// If the user is a guest, initialize all the critical user settings.
	else
	{
		// This is what a guest's variables should be.
		$username = '';
		$user_info = array('groups' => array(-1));
		$user_settings = array();

		if (isset($_COOKIE[$cookiename]))
			$_COOKIE[$cookiename] = '';

		// Create a login token if it doesn't exist yet.
		if (!isset($_SESSION['token']['post-login']))
			createToken('login');
		else
			list ($context['login_token_var'],,, $context['login_token']) = $_SESSION['token']['post-login'];

		// Do we perhaps think this is a search robot? Check every five minutes just in case...
		if ((!empty($modSettings['spider_mode']) || !empty($modSettings['spider_group'])) && (!isset($_SESSION['robot_check']) || $_SESSION['robot_check'] < time() - 300))
		{
			require_once($sourcedir . '/ManageSearchEngines.php');
			$user_info['possibly_robot'] = SpiderCheck();
		}
		elseif (!empty($modSettings['spider_mode']))
			$user_info['possibly_robot'] = isset($_SESSION['id_robot']) ? $_SESSION['id_robot'] : 0;
		// If we haven't turned on proper spider hunts then have a guess!
		else
		{
			$ci_user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			$user_info['possibly_robot'] = (strpos($_SERVER['HTTP_USER_AGENT'], 'Mozilla') === false && strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') === false) || strpos($ci_user_agent, 'googlebot') !== false || strpos($ci_user_agent, 'slurp') !== false || strpos($ci_user_agent, 'crawl') !== false || strpos($ci_user_agent, 'msnbot') !== false;
		}
	}

	// Set up the $user_info array.
	$user_info += array(
		'id' => $id_member,
		'username' => $username,
		'name' => isset($user_settings['real_name']) ? $user_settings['real_name'] : '',
		'email' => isset($user_settings['email_address']) ? $user_settings['email_address'] : '',
		'passwd' => isset($user_settings['passwd']) ? $user_settings['passwd'] : '',
		'language' => empty($user_settings['lngfile']) || empty($modSettings['userLanguage']) ? $language : $user_settings['lngfile'],
		'is_guest' => $id_member == 0,
		'is_admin' => in_array(1, $user_info['groups']),
		'theme' => empty($user_settings['id_theme']) ? 0 : $user_settings['id_theme'],
		'last_login' => empty($user_settings['last_login']) ? 0 : $user_settings['last_login'],
		'ip' => $_SERVER['REMOTE_ADDR'],
		'ip2' => $_SERVER['BAN_CHECK_IP'],
		'posts' => empty($user_settings['posts']) ? 0 : $user_settings['posts'],
		'time_format' => empty($user_settings['time_format']) ? $modSettings['time_format'] : $user_settings['time_format'],
		'time_offset' => empty($user_settings['time_offset']) ? 0 : $user_settings['time_offset'],
		'smiley_set' => isset($user_settings['smiley_set']) ? $user_settings['smiley_set'] : '',
		'messages' => empty($user_settings['instant_messages']) ? 0 : $user_settings['instant_messages'],
		'unread_messages' => empty($user_settings['unread_messages']) ? 0 : $user_settings['unread_messages'],
		'total_time_logged_in' => empty($user_settings['total_time_logged_in']) ? 0 : $user_settings['total_time_logged_in'],
		'buddies' => !empty($modSettings['enable_buddylist']) && !empty($user_settings['buddy_list']) ? explode(',', $user_settings['buddy_list']) : array(),
		'ignoreusers' => !empty($user_settings['pm_ignore_list']) ? explode(',', $user_settings['pm_ignore_list']) : array(),
		'warning' => isset($user_settings['warning']) ? $user_settings['warning'] : 0,
		'permissions' => array(),
	);
	$user_info['groups'] = array_unique($user_info['groups']);

	// Do we have any languages to validate this?
	if (!empty($modSettings['userLanguage']) && (!empty($_GET['language']) || !empty($_SESSION['language'])))
		$languages = getLanguages();

	// Allow the user to change their language if its valid.
	if (!empty($modSettings['userLanguage']) && !empty($_GET['language']) && isset($languages[strtr($_GET['language'], './\\:', '____')]))
	{
		$user_info['language'] = strtr($_GET['language'], './\\:', '____');
		$_SESSION['language'] = $user_info['language'];
	}
	elseif (!empty($modSettings['userLanguage']) && !empty($_SESSION['language']) && isset($languages[strtr($_SESSION['language'], './\\:', '____')]))
		$user_info['language'] = strtr($_SESSION['language'], './\\:', '____');

	call_integration_hook('integrate_user_info');
}


/**
 * What it does:
 * - redirects to appropriate post if only message id is requested.
 * - is only used when inside a topic.
 */
function loadBoard()
{
	global $txt, $scripturl, $context, $modSettings;
	global $topic, $user_info, $smcFunc;

	// Have they by chance specified a message id but nothing else?
	if (empty($_REQUEST['action']) && empty($topic) && !empty($_REQUEST['msg']))
	{
		// Make sure the message id is really an int.
		$_REQUEST['msg'] = (int) $_REQUEST['msg'];

		// Looking through the message table can be slow, so try using the cache first.
		if (($topic = cache_get_data('msg_topic-' . $_REQUEST['msg'], 120)) === NULL)
		{
			$request = $smcFunc['db_query']('', '
				SELECT id_topic
				FROM {db_prefix}messages
				WHERE id_msg = {int:id_msg}
				LIMIT 1',
				array(
					'id_msg' => $_REQUEST['msg'],
				)
			);

			// So did it find anything?
			if ($smcFunc['db_num_rows']($request))
			{
				list ($topic) = $smcFunc['db_fetch_row']($request);
				$smcFunc['db_free_result']($request);
				// Save save save.
				cache_put_data('msg_topic-' . $_REQUEST['msg'], $topic, 120);
			}
		}

		// Remember redirection is the key to avoiding fallout from your bosses.
		if (!empty($topic))
			redirectexit('topic=' . $topic . '.msg' . $_REQUEST['msg'] . '#msg' . $_REQUEST['msg']);
		else
		{
			loadPermissions();
			loadTheme();
			fatal_lang_error('topic_gone', false);
		}
	}

	// Set the template contextual information.
	$context['current_topic'] = $topic;
}


/**
 * Load this user's permissions.
 *
 */
function loadPermissions()
{
	global $user_info, $modSettings, $smcFunc, $sourcedir;

	if ($user_info['is_admin'])
	{
		banPermissions();
		return;
	}

	if (!empty($modSettings['cache_enable']))
	{
		$cache_groups = $user_info['groups'];
		asort($cache_groups);
		$cache_groups = implode(',', $cache_groups);
		// If it's a spider then cache it different.
		if ($user_info['possibly_robot'])
			$cache_groups .= '-spider';

		if (($temp = cache_get_data('permissions:' . $cache_groups, 240)) != null && time() - 240 > $modSettings['settings_updated'])
			list ($user_info['permissions'], $removals) = $temp;
	}

	// If it is detected as a robot, and we are restricting permissions as a special group - then implement this.
	$spider_restrict = $user_info['possibly_robot'] && !empty($modSettings['spider_group']) ? ' OR (id_group = {int:spider_group} AND add_deny = 0)' : '';

	if (empty($user_info['permissions']))
	{
		// Get the general permissions.
		$request = $smcFunc['db_query']('', '
			SELECT permission, add_deny
			FROM {db_prefix}permissions
			WHERE id_group IN ({array_int:member_groups})
				' . $spider_restrict,
			array(
				'member_groups' => $user_info['groups'],
				'spider_group' => !empty($modSettings['spider_group']) ? $modSettings['spider_group'] : 0,
			)
		);
		$removals = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			if (empty($row['add_deny']))
				$removals[] = $row['permission'];
			else
				$user_info['permissions'][] = $row['permission'];
		}
		$smcFunc['db_free_result']($request);

		if (isset($cache_groups))
			cache_put_data('permissions:' . $cache_groups, array($user_info['permissions'], $removals), 240);
	}

	// Remove all the permissions they shouldn't have ;).
	if (!empty($modSettings['permission_enable_deny']))
		$user_info['permissions'] = array_diff($user_info['permissions'], $removals);

	// Banned?  Watch, don't touch..
	banPermissions();

	// Load the mod cache so we can know what additional boards they should see, but no sense in doing it for guests
	if (!$user_info['is_guest'])
	{
		if (!isset($_SESSION['mc']) || $_SESSION['mc']['time'] <= $modSettings['settings_updated'])
		{
			require_once($sourcedir . '/Subs-Auth.php');
			rebuildModCache();
		}
		else
			$user_info['mod_cache'] = $_SESSION['mc'];
	}
}

/**
 * Loads an array of users' data by ID or member_name.
 *
 * @param mixed $users An array of users by id or name
 * @param bool $is_name = false $users is by name or by id
 * @param string $set = 'normal' What kind of data to load (normal, profile, minimal)
 * @return array|bool The ids of the members loaded or false
 */
function loadMemberData($users, $is_name = false, $set = 'normal')
{
	global $user_profile, $modSettings, $smcFunc, $context;

	// Can't just look for no users :P.
	if (empty($users))
		return false;

	/* Pass the set value */
	$context['loadMemberContext_set'] = $set;

	// Make sure it's an array.
	$users = !is_array($users) ? array($users) : array_unique($users);
	$loaded_ids = array();

	if (!$is_name && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		$users = array_values($users);
		for ($i = 0, $n = count($users); $i < $n; $i++)
		{
			$data = cache_get_data('member_data-' . $set . '-' . $users[$i], 240);
			if ($data == null)
				continue;

			$loaded_ids[] = $data['id_member'];
			$user_profile[$data['id_member']] = $data;
			unset($users[$i]);
		}
	}

	// Used by default
	$select_columns = '
			IFNULL(lo.log_time, 0) AS is_online,
			mem.signature, mem.personal_text, mem.location, mem.gender, mem.id_member, mem.member_name,
			mem.real_name, mem.email_address, mem.hide_email, mem.date_registered, mem.website_title, mem.website_url,
			mem.birthdate, mem.member_ip, mem.member_ip2, mem.posts, mem.last_login,
			mem.id_post_group, mem.lngfile, mem.id_group, mem.time_offset, mem.show_online,
			mg.online_color AS member_group_color, IFNULL(mg.group_name, {string:blank_string}) AS member_group,
			pg.online_color AS post_group_color, IFNULL(pg.group_name, {string:blank_string}) AS post_group,
			mem.is_activated, mem.warning' . (!empty($modSettings['titlesEnable']) ? ', mem.usertitle, ' : '') . '
			CASE WHEN mem.id_group = 0 OR mg.icons = {string:blank_string} THEN pg.icons ELSE mg.icons END AS icons';
	$select_tables = '
			LEFT JOIN {db_prefix}log_online AS lo ON (lo.id_member = mem.id_member)
			LEFT JOIN {db_prefix}membergroups AS pg ON (pg.id_group = mem.id_post_group)
			LEFT JOIN {db_prefix}membergroups AS mg ON (mg.id_group = mem.id_group)';

	// We add or replace according the the set
	switch ($set)
	{
		case 'normal':
			$select_columns .= ', mem.buddy_list';
			break;
		case 'profile':
			$select_columns .= ', mem.id_theme, mem.pm_ignore_list, mem.pm_email_notify, mem.pm_receive_from,
			mem.time_format, mem.secret_question,  mem.additional_groups, mem.smiley_set,
			mem.total_time_logged_in, mem.notify_announcements, mem.notify_regularity, mem.notify_send_body,
			mem.notify_types, lo.url, mem.password_salt, mem.pm_prefs';
			break;
		case 'minimal':
			$select_columns = '
			mem.id_member, mem.member_name, mem.real_name, mem.email_address, mem.hide_email, mem.date_registered,
			mem.posts, mem.last_login, mem.member_ip, mem.member_ip2, mem.lngfile, mem.id_group';
			$select_tables = '';
			break;
		default:
			trigger_error('loadMemberData(): Invalid member data set \'' . $set . '\'', E_USER_WARNING);
	}

	// Allow mods to easily add to the selected member data
	call_integration_hook('integrate_load_member_data', array($select_columns, $select_tables, $set));

	if (!empty($users))
	{
		// Load the member's data.
		$request = $smcFunc['db_query']('', '
			SELECT' . $select_columns . '
			FROM {db_prefix}members AS mem' . $select_tables . '
			WHERE mem.' . ($is_name ? 'member_name' : 'id_member') . (count($users) == 1 ? ' = {' . ($is_name ? 'string' : 'int') . ':users}' : ' IN ({' . ($is_name ? 'array_string' : 'array_int') . ':users})'),
			array(
				'blank_string' => '',
				'users' => count($users) == 1 ? current($users) : $users,
			)
		);
		$new_loaded_ids = array();
		while ($row = $smcFunc['db_fetch_assoc']($request))
		{
			$new_loaded_ids[] = $row['id_member'];
			$loaded_ids[] = $row['id_member'];
			$row['options'] = array();
			$user_profile[$row['id_member']] = $row;
		}
		$smcFunc['db_free_result']($request);
	}

	if (!empty($new_loaded_ids) && $set !== 'minimal')
	{
		$request = $smcFunc['db_query']('', '
			SELECT *
			FROM {db_prefix}themes
			WHERE id_member' . (count($new_loaded_ids) == 1 ? ' = {int:loaded_ids}' : ' IN ({array_int:loaded_ids})'),
			array(
				'loaded_ids' => count($new_loaded_ids) == 1 ? $new_loaded_ids[0] : $new_loaded_ids,
			)
		);
		while ($row = $smcFunc['db_fetch_assoc']($request))
			$user_profile[$row['id_member']]['options'][$row['variable']] = $row['value'];
		$smcFunc['db_free_result']($request);
	}

	if (!empty($new_loaded_ids) && !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 3)
	{
		for ($i = 0, $n = count($new_loaded_ids); $i < $n; $i++)
			cache_put_data('member_data-' . $set . '-' . $new_loaded_ids[$i], $user_profile[$new_loaded_ids[$i]], 240);
	}

	return empty($loaded_ids) ? false : $loaded_ids;
}

/**
 * Loads the user's basic values... meant for template/theme usage.
 *
 * @param int $user
 * @param bool $display_custom_fields = false
 * @return boolean
 */
function loadMemberContext($user, $display_custom_fields = false)
{
	global $memberContext, $user_profile, $txt, $scripturl, $user_info;
	global $context, $modSettings, $settings;
	global $smcFunc;
	static $dataLoaded = array();

	// If this person's data is already loaded, skip it.
	if (isset($dataLoaded[$user]))
		return true;

	// We can't load guests or members not loaded by loadMemberData()!
	if ($user == 0)
		return false;
	if (!isset($user_profile[$user]))
	{
		trigger_error('loadMemberContext(): member id ' . $user . ' not previously loaded by loadMemberData()', E_USER_WARNING);
		return false;
	}

	// Well, it's loaded now anyhow.
	$dataLoaded[$user] = true;
	$profile = $user_profile[$user];

	// Censor everything.
	censorText($profile['signature']);
	censorText($profile['personal_text']);
	censorText($profile['location']);

	// Set things up to be used before hand.
	$gendertxt = $profile['gender'] == 2 ? $txt['female'] : ($profile['gender'] == 1 ? $txt['male'] : '');
	$profile['signature'] = str_replace(array("\n", "\r"), array('<br />', ''), $profile['signature']);
	$profile['signature'] = parse_bbc($profile['signature'], true, 'sig' . $profile['id_member']);

	$profile['is_online'] = (!empty($profile['show_online']) || allowedTo('moderate_forum')) && $profile['is_online'] > 0;
	$profile['icons'] = empty($profile['icons']) ? array('', '') : explode('#', $profile['icons']);
	// Setup the buddy status here (One whole in_array call saved :P)
	$profile['buddy'] = in_array($profile['id_member'], $user_info['buddies']);
	$buddy_list = !empty($profile['buddy_list']) ? explode(',', $profile['buddy_list']) : array();

	// These minimal values are always loaded
	$memberContext[$user] = array(
		'username' => $profile['member_name'],
		'name' => $profile['real_name'],
		'id' => $profile['id_member'],
		'href' => $scripturl . '?action=profile;u=' . $profile['id_member'],
		'link' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '">' . $profile['real_name'] . '</a>',
		'email' => $profile['email_address'],
		'show_email' => showEmailAddress(!empty($profile['hide_email']), $profile['id_member']),
		'registered' => empty($profile['date_registered']) ? $txt['not_applicable'] : timeformat($profile['date_registered']),
		'registered_timestamp' => empty($profile['date_registered']) ? 0 : forum_time(true, $profile['date_registered']),
	);

	// If the set isn't minimal then load the monstrous array.
	if ($context['loadMemberContext_set'] != 'minimal')
		$memberContext[$user] += array(
			'username_color' => '<span '. (!empty($profile['member_group_color']) ? 'style="color:'. $profile['member_group_color'] .';"' : '') .'>'. $profile['member_name'] .'</span>',
			'name_color' => '<span '. (!empty($profile['member_group_color']) ? 'style="color:'. $profile['member_group_color'] .';"' : '') .'>'. $profile['real_name'] .'</span>',
			'link_color' => '<a href="' . $scripturl . '?action=profile;u=' . $profile['id_member'] . '" title="' . $txt['profile_of'] . ' ' . $profile['real_name'] . '" '. (!empty($profile['member_group_color']) ? 'style="color:'. $profile['member_group_color'] .';"' : '') .'>' . $profile['real_name'] . '</a>',
			'is_buddy' => $profile['buddy'],
			'is_reverse_buddy' => in_array($user_info['id'], $buddy_list),
			'buddies' => $buddy_list,
			'title' => !empty($modSettings['titlesEnable']) ? $profile['usertitle'] : '',
			'blurb' => $profile['personal_text'],
			'gender' => array(
				'name' => $gendertxt,
				'image' => !empty($profile['gender']) ? '<img class="gender" src="' . $settings['images_url'] . '/' . ($profile['gender'] == 1 ? 'Male' : 'Female') . '.png" alt="' . $gendertxt . '" />' : ''
			),
			'website' => array(
				'title' => $profile['website_title'],
				'url' => $profile['website_url'],
			),
			'birth_date' => empty($profile['birthdate']) || $profile['birthdate'] === '0001-01-01' ? '0000-00-00' : (substr($profile['birthdate'], 0, 4) === '0004' ? '0000' . substr($profile['birthdate'], 4) : $profile['birthdate']),
			'signature' => $profile['signature'],
			'location' => $profile['location'],
			'real_posts' => $profile['posts'],
			'posts' => $profile['posts'] > 500000 ? $txt['geek'] : comma_format($profile['posts']),
			'last_login' => empty($profile['last_login']) ? $txt['never'] : timeformat($profile['last_login']),
			'last_login_timestamp' => empty($profile['last_login']) ? 0 : forum_time(0, $profile['last_login']),
			'ip' => htmlspecialchars($profile['member_ip']),
			'ip2' => htmlspecialchars($profile['member_ip2']),
			'online' => array(
				'is_online' => $profile['is_online'],
				'text' => $smcFunc['htmlspecialchars']($txt[$profile['is_online'] ? 'online' : 'offline']),
				'member_online_text' => sprintf($txt[$profile['is_online'] ? 'member_is_online' : 'member_is_offline'], $smcFunc['htmlspecialchars']($profile['real_name'])),
				'href' => $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'],
				'link' => '<a href="' . $scripturl . '?action=pm;sa=send;u=' . $profile['id_member'] . '">' . $txt[$profile['is_online'] ? 'online' : 'offline'] . '</a>',
				'image_href' => $settings['images_url'] . '/' . ($profile['buddy'] ? 'buddy_' : '') . ($profile['is_online'] ? 'useron' : 'useroff') . '.png',
				'label' => $txt[$profile['is_online'] ? 'online' : 'offline']
			),
			'language' => $smcFunc['ucwords'](strtr($profile['lngfile'], array('_' => ' ', '-utf8' => ''))),
			'is_activated' => isset($profile['is_activated']) ? $profile['is_activated'] : 1,
			'is_banned' => isset($profile['is_activated']) ? $profile['is_activated'] >= 10 : 0,
			'options' => $profile['options'],
			'is_guest' => false,
			'group' => $profile['member_group'],
			'group_color' => $profile['member_group_color'],
			'group_id' => $profile['id_group'],
			'post_group' => $profile['post_group'],
			'post_group_color' => $profile['post_group_color'],
			'group_icons' => str_repeat('<img src="' . str_replace('$language', $context['user']['language'], isset($profile['icons'][1]) ? $settings['images_url'] . '/' . $profile['icons'][1] : '') . '" alt="*" />', empty($profile['icons'][0]) || empty($profile['icons'][1]) ? 0 : $profile['icons'][0]),
			'warning' => $profile['warning'],
			'warning_status' => !empty($modSettings['warning_mute']) && $modSettings['warning_mute'] <= $profile['warning'] ? 'mute' : (!empty($modSettings['warning_moderate']) && $modSettings['warning_moderate'] <= $profile['warning'] ? 'moderate' : (!empty($modSettings['warning_watch']) && $modSettings['warning_watch'] <= $profile['warning'] ? 'watch' : (''))),
			'local_time' => timeformat(time() + ($profile['time_offset'] - $user_info['time_offset']) * 3600, false),
		);

	// Are we also loading the members custom fields into context?
	if ($display_custom_fields && !empty($modSettings['displayFields']))
	{
		$memberContext[$user]['custom_fields'] = array();
		if (!isset($context['display_fields']))
			$context['display_fields'] = unserialize($modSettings['displayFields']);

		foreach ($context['display_fields'] as $custom)
		{
			if (!isset($custom['title']) || trim($custom['title']) == '' || empty($profile['options'][$custom['colname']]))
				continue;

			$value = $profile['options'][$custom['colname']];

			// BBC?
			if ($custom['bbc'])
				$value = parse_bbc($value);
			// ... or checkbox?
			elseif (isset($custom['type']) && $custom['type'] == 'check')
				$value = $value ? $txt['yes'] : $txt['no'];

			// Enclosing the user input within some other text?
			if (!empty($custom['enclose']))
				$value = strtr($custom['enclose'], array(
					'{SCRIPTURL}' => $scripturl,
					'{IMAGES_URL}' => $settings['images_url'],
					'{DEFAULT_IMAGES_URL}' => $settings['default_images_url'],
					'{INPUT}' => $value,
				));

			$memberContext[$user]['custom_fields'][] = array(
				'title' => $custom['title'],
				'colname' => $custom['colname'],
				'value' => $value,
				'placement' => !empty($custom['placement']) ? $custom['placement'] : 0,
			);
		}
	}

	call_integration_hook('integrate_member_context', array($user, $display_custom_fields));
	return true;
}

/**
 * Loads information about what browser the user is viewing with and places it in $context
 *  - uses the class from Class-BrowerDetect.php
 *
 */
function detectBrowser()
{
	// Load the current user's browser of choice
	$detector = new browser_detector;
	$detector->detectBrowser();
}

/**
 * Are we using this browser?
 *
 * Wrapper function for detectBrowser
 * @param $browser: browser we are checking for.
*/
function isBrowser($browser)
{
	global $context;

	// @todo REMOVE THIS BEFORE BETA 1 RELEASE.
	if (in_array($browser, array('ie7', 'ie6', 'ie5.5', 'ie5', 'ie5', 'ie4', 'mac_ie', 'firefox1')))
	{
		$line = $file = null;
		foreach (debug_backtrace() as $step)
		{
			// Found it?
			if (strpos($step['function'], 'query') === false && !in_array(substr($step['function'], 0, 7), array('smf_db_', 'preg_re', 'db_erro', 'call_us')) && strpos($step['function'], '__') !== 0)
			{
				$function = '<br />Function: ' . $step['function'];
				break;
			}

			if (isset($step['line']))
			{
				$file = $step['file'];
				$line = $step['line'];
			}
		}

		log_error('Old browser support' . $function, 'debug', $file, $line);
	}

	// Don't know any browser!
	if (empty($context['browser']))
		detectBrowser();

	return !empty($context['browser'][$browser]) || !empty($context['browser']['is_' . $browser]) ? true : false;
}

/**
 * Load a theme, by ID.
 *
 * @param int $id_theme = 0
 * @param bool $initialize = true
 */
function loadTheme($id_theme = 0, $initialize = true)
{
	global $user_info, $user_settings, $sc, $boarddir;
	global $txt, $boardurl, $scripturl, $mbname, $modSettings, $language;
	global $context, $settings, $options, $sourcedir, $ssi_theme, $smcFunc;

	// The theme was specified by parameter.
	if (!empty($id_theme))
		$id_theme = (int) $id_theme;
	// The theme was specified by REQUEST.
	elseif (!empty($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
	{
		$id_theme = (int) $_REQUEST['theme'];
		$_SESSION['id_theme'] = $id_theme;
	}
	// The theme was specified by REQUEST... previously.
	elseif (!empty($_SESSION['id_theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = (int) $_SESSION['id_theme'];
	// The theme is just the user's choice.
	elseif (!empty($user_info['theme']) && !isset($_REQUEST['theme']) && (!empty($modSettings['theme_allow']) || allowedTo('admin_forum')))
		$id_theme = $user_info['theme'];
	// The theme is the forum's default.
	else
		$id_theme = $modSettings['theme_guests'];

	// Verify the id_theme... no foul play.
	if (!empty($ssi_theme) && $id_theme == $ssi_theme)
		$id_theme = (int) $id_theme;
	elseif (!empty($modSettings['knownThemes']) && !allowedTo('admin_forum'))
	{
		$themes = explode(',', $modSettings['knownThemes']);
		if (!in_array($id_theme, $themes))
			$id_theme = $modSettings['theme_guests'];
		else
			$id_theme = (int) $id_theme;
	}
	else
		$id_theme = (int) $id_theme;

	$member = empty($user_info['id']) ? -1 : $user_info['id'];

	if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2 && ($temp = cache_get_data('theme_settings-' . $id_theme . ':' . $member, 60)) != null && time() - 60 > $modSettings['settings_updated'])
	{
		$themeData = $temp;
		$flag = true;
	}
	elseif (($temp = cache_get_data('theme_settings-' . $id_theme, 90)) != null && time() - 60 > $modSettings['settings_updated'])
		$themeData = $temp + array($member => array());
	else
		$themeData = array(-1 => array(), 0 => array(), $member => array());

	if (empty($flag))
	{
		// Load variables from the current or default theme, global or this user's.
		$result = $smcFunc['db_query']('', '
			SELECT variable, value, id_member, id_theme
			FROM {db_prefix}themes
			WHERE id_member' . (empty($themeData[0]) ? ' IN (-1, 0, {int:id_member})' : ' = {int:id_member}') . '
				AND id_theme' . ($id_theme == 1 ? ' = {int:id_theme}' : ' IN ({int:id_theme}, 1)'),
			array(
				'id_theme' => $id_theme,
				'id_member' => $member,
			)
		);
		// Pick between $settings and $options depending on whose data it is.
		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
			// There are just things we shouldn't be able to change as members.
			if ($row['id_member'] != 0 && in_array($row['variable'], array('actual_theme_url', 'actual_images_url', 'base_theme_dir', 'base_theme_url', 'default_images_url', 'default_theme_dir', 'default_theme_url', 'default_template', 'images_url', 'number_recent_posts', 'smiley_sets_default', 'theme_dir', 'theme_id', 'theme_layers', 'theme_templates', 'theme_url')))
				continue;

			// If this is the theme_dir of the default theme, store it.
			if (in_array($row['variable'], array('theme_dir', 'theme_url', 'images_url')) && $row['id_theme'] == '1' && empty($row['id_member']))
				$themeData[0]['default_' . $row['variable']] = $row['value'];

			// If this isn't set yet, is a theme option, or is not the default theme..
			if (!isset($themeData[$row['id_member']][$row['variable']]) || $row['id_theme'] != '1')
				$themeData[$row['id_member']][$row['variable']] = substr($row['variable'], 0, 5) == 'show_' ? $row['value'] == '1' : $row['value'];
		}
		$smcFunc['db_free_result']($result);

		if (!empty($themeData[-1]))
			foreach ($themeData[-1] as $k => $v)
			{
				if (!isset($themeData[$member][$k]))
					$themeData[$member][$k] = $v;
			}

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= 2)
			cache_put_data('theme_settings-' . $id_theme . ':' . $member, $themeData, 60);
		// Only if we didn't already load that part of the cache...
		elseif (!isset($temp))
			cache_put_data('theme_settings-' . $id_theme, array(-1 => $themeData[-1], 0 => $themeData[0]), 90);
	}

	$settings = $themeData[0];
	$options = $themeData[$member];

	$settings['theme_id'] = $id_theme;

	$settings['actual_theme_url'] = $settings['theme_url'];
	$settings['actual_images_url'] = $settings['images_url'];
	$settings['actual_theme_dir'] = $settings['theme_dir'];

	$settings['template_dirs'] = array();
	// This theme first.
	$settings['template_dirs'][] = $settings['theme_dir'];

	// Based on theme (if there is one).
	if (!empty($settings['base_theme_dir']))
		$settings['template_dirs'][] = $settings['base_theme_dir'];

	// Lastly the default theme.
	if ($settings['theme_dir'] != $settings['default_theme_dir'])
		$settings['template_dirs'][] = $settings['default_theme_dir'];

	if (!$initialize)
		return;

	// Check to see if they're accessing it from the wrong place.
	if (isset($_SERVER['HTTP_HOST']) || isset($_SERVER['SERVER_NAME']))
	{
		$detected_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on' ? 'https://' : 'http://';
		$detected_url .= empty($_SERVER['HTTP_HOST']) ? $_SERVER['SERVER_NAME'] . (empty($_SERVER['SERVER_PORT']) || $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT']) : $_SERVER['HTTP_HOST'];
		$temp = preg_replace('~/' . basename($scripturl) . '(/.+)?$~', '', strtr(dirname($_SERVER['PHP_SELF']), '\\', '/'));
		if ($temp != '/')
			$detected_url .= $temp;
	}
	if (isset($detected_url) && $detected_url != $boardurl)
	{
		// Try #1 - check if it's in a list of alias addresses.
		if (!empty($modSettings['forum_alias_urls']))
		{
			$aliases = explode(',', $modSettings['forum_alias_urls']);

			foreach ($aliases as $alias)
			{
				// Rip off all the boring parts, spaces, etc.
				if ($detected_url == trim($alias) || strtr($detected_url, array('http://' => '', 'https://' => '')) == trim($alias))
					$do_fix = true;
			}
		}

		// Hmm... check #2 - is it just different by a www?  Send them to the correct place!!
		if (empty($do_fix) && strtr($detected_url, array('://' => '://www.')) == $boardurl && (empty($_GET) || count($_GET) == 1) && SMF != 'SSI')
		{
			// Okay, this seems weird, but we don't want an endless loop - this will make $_GET not empty ;).
			if (empty($_GET))
				redirectexit('wwwRedirect');
			else
			{
				list ($k, $v) = each($_GET);

				if ($k != 'wwwRedirect')
					redirectexit('wwwRedirect;' . $k . '=' . $v);
			}
		}

		// #3 is just a check for SSL...
		if (strtr($detected_url, array('https://' => 'http://')) == $boardurl)
			$do_fix = true;

		// Okay, #4 - perhaps it's an IP address?  We're gonna want to use that one, then. (assuming it's the IP or something...)
		if (!empty($do_fix) || preg_match('~^http[s]?://(?:[\d\.:]+|\[[\d:]+\](?::\d+)?)(?:$|/)~', $detected_url) == 1)
		{
			// Caching is good ;).
			$oldurl = $boardurl;

			// Fix $boardurl and $scripturl.
			$boardurl = $detected_url;
			$scripturl = strtr($scripturl, array($oldurl => $boardurl));
			$_SERVER['REQUEST_URL'] = strtr($_SERVER['REQUEST_URL'], array($oldurl => $boardurl));

			// Fix the theme urls...
			$settings['theme_url'] = strtr($settings['theme_url'], array($oldurl => $boardurl));
			$settings['default_theme_url'] = strtr($settings['default_theme_url'], array($oldurl => $boardurl));
			$settings['actual_theme_url'] = strtr($settings['actual_theme_url'], array($oldurl => $boardurl));
			$settings['images_url'] = strtr($settings['images_url'], array($oldurl => $boardurl));
			$settings['default_images_url'] = strtr($settings['default_images_url'], array($oldurl => $boardurl));
			$settings['actual_images_url'] = strtr($settings['actual_images_url'], array($oldurl => $boardurl));

			// And just a few mod settings :).
			$modSettings['smileys_url'] = strtr($modSettings['smileys_url'], array($oldurl => $boardurl));
		}
	}
	// Set up the contextual user array.
	$context['user'] = array(
		'id' => $user_info['id'],
		'is_logged' => !$user_info['is_guest'],
		'is_guest' => &$user_info['is_guest'],
		'is_admin' => &$user_info['is_admin'],
		'is_mod' => &$user_info['is_mod'],
		// A user can mod if they have permission to see the mod center, or they are a group/approval moderator.
		'can_mod' => allowedTo('access_mod_center') || (!$user_info['is_guest'] && ($user_info['mod_cache']['gq'] != '0=1' || $user_info['mod_cache']['bq'] != '0=1' || ($modSettings['postmod_active'] && !empty($user_info['mod_cache']['ap'])))),
		'username' => $user_info['username'],
		'language' => $user_info['language'],
		'email' => $user_info['email'],
		'ignoreusers' => $user_info['ignoreusers'],
	);
	if (!$context['user']['is_guest'])
		$context['user']['name'] = $user_info['name'];
	elseif ($context['user']['is_guest'] && !empty($txt['guest_title']))
		$context['user']['name'] = $txt['guest_title'];

	// Determine the current smiley set.
	$user_info['smiley_set'] = (!in_array($user_info['smiley_set'], explode(',', $modSettings['smiley_sets_known'])) && $user_info['smiley_set'] != 'none') || empty($modSettings['smiley_sets_enable']) ? (!empty($settings['smiley_sets_default']) ? $settings['smiley_sets_default'] : $modSettings['smiley_sets_default']) : $user_info['smiley_set'];
	$context['user']['smiley_set'] = $user_info['smiley_set'];

	// Some basic information...
	if (!isset($context['html_headers']))
		$context['html_headers'] = '';
	if (!isset($context['javascript_files']))
		$context['javascript_files'] = array();
	if (!isset($context['css_files']))
		$context['css_files'] = array();
	if (!isset($context['javascript_inline']))
		$context['javascript_inline'] = array('standard' => array(), 'defer' => array());
	if (!isset($context['javascript_vars']))
		$context['javascript_vars'] = array();

	$context['menu_separator'] = !empty($settings['use_image_buttons']) ? ' ' : ' | ';
	$context['session_var'] = $_SESSION['session_var'];
	$context['session_id'] = $_SESSION['session_value'];
	$context['forum_name'] = $mbname;
	$context['forum_name_html_safe'] = $smcFunc['htmlspecialchars']($context['forum_name']);
	$context['header_logo_url_html_safe'] = empty($settings['header_logo_url']) ? '' : $smcFunc['htmlspecialchars']($settings['header_logo_url']);
	$context['current_action'] = isset($_REQUEST['action']) ? $_REQUEST['action'] : null;
	$context['current_subaction'] = isset($_REQUEST['sa']) ? $_REQUEST['sa'] : null;
	$context['can_register'] = empty($modSettings['registration_method']) || $modSettings['registration_method'] != 3;
	if (isset($modSettings['load_average']))
		$context['load_average'] = $modSettings['load_average'];

	// Set some permission related settings.
	$context['show_login_bar'] = $user_info['is_guest'] && !empty($modSettings['enableVBStyleLogin']);

	// This determines the server... not used in many places, except for login fixing.
	$context['server'] = array(
		'is_iis' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false,
		'is_apache' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false,
		'is_litespeed' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'LiteSpeed') !== false,
		'is_lighttpd' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'lighttpd') !== false,
		'is_nginx' => isset($_SERVER['SERVER_SOFTWARE']) && strpos($_SERVER['SERVER_SOFTWARE'], 'nginx') !== false,
		'is_cgi' => isset($_SERVER['SERVER_SOFTWARE']) && strpos(php_sapi_name(), 'cgi') !== false,
		'is_windows' => strpos(PHP_OS, 'WIN') === 0,
		'iso_case_folding' => ord(strtolower(chr(138))) === 154,
	);
	// A bug in some versions of IIS under CGI (older ones) makes cookie setting not work with Location: headers.
	$context['server']['needs_login_fix'] = $context['server']['is_cgi'] && $context['server']['is_iis'];

	// Detect the browser.
	detectBrowser();

	// This allows sticking some HTML on the page output - useful for controls.
	$context['insert_after_template'] = '';

	if (!isset($txt))
		$txt = array();
	$simpleActions = array(
		'findmember',
		'quotefast',
		'spellcheck',
	);

	// Output is fully XML, so no need for the index template.
	if (isset($_REQUEST['xml']))
	{
		loadLanguage('index+Modifications+Blog');
		loadTemplate('Xml');
		$context['template_layers'] = array();
	}
	// These actions don't require the index template at all.
	elseif (!empty($_REQUEST['action']) && in_array($_REQUEST['action'], $simpleActions))
	{
		loadLanguage('index+Modifications+Blog');
		$context['template_layers'] = array();
	}
	else
	{
		// Custom templates to load, or just default?
		if (isset($settings['theme_templates']))
			$templates = explode(',', $settings['theme_templates']);
		else
			$templates = array('index');

		// Load each template...
		foreach ($templates as $template)
			loadTemplate($template);

		// ...and attempt to load their associated language files.
		$required_files = implode('+', array_merge($templates, array('Modifications+Blog')));
		loadLanguage($required_files, '', false);

		// Custom template layers?
		if (isset($settings['theme_layers']))
			$context['template_layers'] = explode(',', $settings['theme_layers']);
		else
			$context['template_layers'] = array('html', 'body');
	}

	// Initialize the theme.
	loadSubTemplate('init', 'ignore');

	// Guests may still need a name.
	if ($context['user']['is_guest'] && empty($context['user']['name']))
		$context['user']['name'] = $txt['guest_title'];

	// Any theme-related strings that need to be loaded?
	if (!empty($settings['require_theme_strings']))
		loadLanguage('ThemeStrings', '', false);

	// We allow theme variants, because we're cool.
	$context['theme_variant'] = '';
	$context['theme_variant_url'] = '';
	if (!empty($settings['theme_variants']))
	{
		// Overriding - for previews and that ilk.
		if (!empty($_REQUEST['variant']))
			$_SESSION['id_variant'] = $_REQUEST['variant'];
		// User selection?
		if (empty($settings['disable_user_variant']) || allowedTo('admin_forum'))
			$context['theme_variant'] = !empty($_SESSION['id_variant']) ? $_SESSION['id_variant'] : (!empty($options['theme_variant']) ? $options['theme_variant'] : '');
		// If not a user variant, select the default.
		if ($context['theme_variant'] == '' || !in_array($context['theme_variant'], $settings['theme_variants']))
			$context['theme_variant'] = !empty($settings['default_variant']) && in_array($settings['default_variant'], $settings['theme_variants']) ? $settings['default_variant'] : $settings['theme_variants'][0];

		// Do this to keep things easier in the templates.
		$context['theme_variant'] = '_' . $context['theme_variant'];
		$context['theme_variant_url'] = $context['theme_variant'] . '/';
	}

	// Let's be compatible with old themes!
	if (!function_exists('template_html_above') && in_array('html', $context['template_layers']))
		$context['template_layers'] = array('main');

	// Allow overriding the board wide time/number formats.
	if (empty($user_settings['time_format']) && !empty($txt['time_format']))
		$user_info['time_format'] = $txt['time_format'];

	if (isset($settings['use_default_images']) && $settings['use_default_images'] == 'always')
	{
		$settings['theme_url'] = $settings['default_theme_url'];
		$settings['images_url'] = $settings['default_images_url'];
		$settings['theme_dir'] = $settings['default_theme_dir'];
	}
	// Make a special URL for the language.
	$settings['lang_images_url'] = $settings['images_url'] . '/' . (!empty($txt['image_lang']) ? $txt['image_lang'] : $user_info['language']);

	// Set the character set from the template.
	$context['character_set'] = empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set'];
	$context['utf8'] = $context['character_set'] === 'UTF-8';
	$context['right_to_left'] = !empty($txt['lang_rtl']);

	$context['tabindex'] = 1;

	// Compatibility.
	if (!isset($settings['theme_version']))
		$modSettings['memberCount'] = $modSettings['totalMembers'];

	// This allows us to change the way things look for the admin.
	$context['admin_features'] = isset($modSettings['admin_features']) ? explode(',', $modSettings['admin_features']) : array('cd,cp,k,w,rg,ml,pm');

	// Default JS variables for use in every theme
	$context['javascript_vars'] = array(
		'smf_theme_url' => '"' . $settings['theme_url'] . '"',
		'smf_default_theme_url' => '"' . $settings['default_theme_url'] . '"',
		'smf_images_url' => '"' . $settings['images_url'] . '"',
		'smf_scripturl' => '"' . $scripturl . '"',
		'smf_default_theme_url' => '"' . $settings['default_theme_url'] . '"',
		'smf_iso_case_folding' => $context['server']['iso_case_folding'] ? 'true' : 'false',
		'smf_charset' => '"' . $context['character_set'] . '"',
		'smf_session_id' => '"' . $context['session_id'] . '"',
		'smf_session_var' => '"' . $context['session_var'] . '"',
		'smf_member_id' => $context['user']['id'],
		'ajax_notification_text' => JavaScriptEscape($txt['ajax_in_progress']),
		'ajax_notification_cancel_text' => JavaScriptEscape($txt['modify_cancel']),
	);

	// Add the JQuery library to the list of files to load.
	if (isset($modSettings['jquery_source']) && $modSettings['jquery_source'] == 'cdn')
		loadJavascriptFile('https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js', array(), 'jquery');
	elseif (isset($modSettings['jquery_source']) && $modSettings['jquery_source'] == 'local')
		loadJavascriptFile('jquery-1.9.1.min.js', array('default_theme' => true, 'seed' => false), 'jquery');
	// Auto loading? template_javascript() will take care of the local half of this.
	else
		loadJavascriptFile('https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js', array(), 'jquery');

	// Queue our JQuery plugins!
	loadJavascriptFile('smf_jquery_plugins.js', array('default_theme' => true));

	// script.js and theme.js, always required, so always add them! Makes index.template.php cleaner and all.
	loadJavascriptFile('script.js', array('default_theme' => true), 'smf_scripts');
	loadJavascriptFile('theme.js', array(), 'theme_scripts');

	// If we think we have mail to send, let's offer up some possibilities... robots get pain (Now with scheduled task support!)
	if ((!empty($modSettings['mail_next_send']) && $modSettings['mail_next_send'] < time() && empty($modSettings['mail_queue_use_cron'])) || empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
	{
		if (isBrowser('possibly_robot'))
		{
			// @todo Maybe move this somewhere better?!
			require_once($sourcedir . '/ScheduledTasks.php');

			// What to do, what to do?!
			if (empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time())
				AutoTask();
			else
				ReduceMailQueue();
		}
		else
		{
			$type = empty($modSettings['next_task_time']) || $modSettings['next_task_time'] < time() ? 'task' : 'mailq';
			$ts = $type == 'mailq' ? $modSettings['mail_next_send'] : $modSettings['next_task_time'];

			addInlineJavascript('
		function smfAutoTask()
		{
			var tempImage = new Image();
			tempImage.src = smf_scripturl + "?scheduled=' . $type . ';ts=' . $ts . '";
		}
		window.setTimeout("smfAutoTask();", 1);');

		}
	}

	// Any files to include at this point?
	if (!empty($modSettings['integrate_theme_include']))
	{
		$theme_includes = explode(',', $modSettings['integrate_theme_include']);
		foreach ($theme_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	// Call load theme integration functions.
	call_integration_hook('integrate_load_theme');

	// We are ready to go.
	$context['theme_loaded'] = true;
}

/**
 * Load a template - if the theme doesn't include it, use the default.
 * What this function does:
 *  - loads a template file with the name template_name from the current, default, or base theme.
 *  - detects a wrong default theme directory and tries to work around it.
 *
 * @uses the template_include() function to include the file.
 * @param string $template_name
 * @param array $style_sheets = array()
 * @param bool $fatal = true if fatal is true, dies with an error message if the template cannot be found
 * @return boolean
 */
function loadTemplate($template_name, $style_sheets = array(), $fatal = true)
{
	global $context, $settings, $txt, $scripturl, $boarddir, $db_show_debug;

	// Do any style sheets first, cause we're easy with those.
	if (!empty($style_sheets))
	{
		if (!is_array($style_sheets))
			$style_sheets = array($style_sheets);

		foreach ($style_sheets as $sheet)
			loadCSSFile($sheet . '.css', array(), $sheet);
	}

	// No template to load?
	if ($template_name === false)
		return true;

	$loaded = false;
	foreach ($settings['template_dirs'] as $template_dir)
	{
		if (file_exists($template_dir . '/' . $template_name . '.template.php'))
		{
			$loaded = true;
			template_include($template_dir . '/' . $template_name . '.template.php', true);
			break;
		}
	}

	if ($loaded)
	{
		// For compatibility reasons, if this is the index template without new functions, include compatible stuff.
		if (substr($template_name, 0, 5) == 'index' && !function_exists('template_button_strip'))
			loadTemplate('Compat');

		if ($db_show_debug === true)
			$context['debug']['templates'][] = $template_name . ' (' . basename($template_dir) . ')';

		// If they have specified an initialization function for this template, go ahead and call it now.
		if (function_exists('template_' . $template_name . '_init'))
			call_user_func('template_' . $template_name . '_init');
	}
	// Hmmm... doesn't exist?!  I don't suppose the directory is wrong, is it?
	elseif (!file_exists($settings['default_theme_dir']) && file_exists($boarddir . '/Themes/default'))
	{
		$settings['default_theme_dir'] = $boarddir . '/Themes/default';
		$settings['template_dirs'][] = $settings['default_theme_dir'];

		if (!empty($context['user']['is_admin']) && !isset($_GET['th']))
		{
			loadLanguage('Errors');
			echo '
<div class="alert errorbox">
	<a href="', $scripturl . '?action=admin;area=theme;sa=list;th=1;' . $context['session_var'] . '=' . $context['session_id'], '" class="alert">', $txt['theme_dir_wrong'], '</a>
</div>';
		}

		loadTemplate($template_name);
	}
	// Cause an error otherwise.
	elseif ($template_name != 'Errors' && $template_name != 'index' && $fatal)
		fatal_lang_error('theme_template_error', 'template', array((string) $template_name));
	elseif ($fatal)
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load Themes/default/%s.template.php!', (string) $template_name), 'template'));
	else
		return false;
}


/**
 * Load a sub-template.
 * What it does:
 * 	- loads the sub template specified by sub_template_name, which must be in an already-loaded template.
 *  - if ?debug is in the query string, shows administrators a marker after every sub template
 *    for debugging purposes.
 *
 * @todo get rid of reading $_REQUEST directly
 *
 * @param string $sub_template_name
 * @param bool $fatal = false, $fatal = true is for templates that shouldn't get a 'pretty' error screen.
 */
function loadSubTemplate($sub_template_name, $fatal = false)
{
	global $context, $settings, $options, $txt, $db_show_debug;

	if ($db_show_debug === true)
		$context['debug']['sub_templates'][] = $sub_template_name;

	// Figure out what the template function is named.
	$theme_function = 'template_' . $sub_template_name;
	if (function_exists($theme_function))
		$theme_function();
	elseif ($fatal === false)
		fatal_lang_error('theme_template_error', 'template', array((string) $sub_template_name));
	elseif ($fatal !== 'ignore')
		die(log_error(sprintf(isset($txt['theme_template_error']) ? $txt['theme_template_error'] : 'Unable to load the %s sub template!', (string) $sub_template_name), 'template'));

	// Are we showing debugging for templates?  Just make sure not to do it before the doctype...
	if (allowedTo('admin_forum') && isset($_REQUEST['debug']) && !in_array($sub_template_name, array('init', 'main_below')) && ob_get_length() > 0 && !isset($_REQUEST['xml']))
	{
		echo '
<div style="font-size: 8pt; border: 1px dashed red; background: orange; text-align: center; font-weight: bold;">---- ', $sub_template_name, ' ends ----</div>';
	}
}

/**
 * Add a CSS file for output later
 *
 * @param string $filename
 * @param array $params
 * Keys are the following:
 * 	- ['local'] (true/false): define if the file is local
 * 	- ['default_theme'] (true/false): force use of default theme url
 * 	- ['force_current'] (true/false): if this is false, we will attempt to load the file from the default theme if not found in the current theme
 *  - ['validate'] (true/false): if true script will validate the local file exists
 *  - ['seed'] (true/false/string): if true or null, use cache stale, false do not, or used a supplied string
 * @param string $id
 */
function loadCSSFile($filename, $params = array(), $id = '')
{
	global $settings, $context;

	$params['seed'] = (!isset($params['seed']) || $params['seed'] === true) ? '?alph21' : (is_string($params['seed']) ? ($params['seed'] = $params['seed'][0] === '?' ? $params['seed'] : '?' . $params['seed']) : '');
	$params['force_current'] = !empty($params['force_current']) ? $params['force_current'] : false;
	$theme = !empty($params['default_theme']) ? 'default_theme' : 'theme';

	// account for shorthand like admin.css?alp21 filenames
	$has_seed = strpos($filename, '.css?');
	$id = empty($id) ? strtr(basename($filename), '?', '_') : $id;

	// Is this a local file?
	if (strpos($filename, 'http') === false || !empty($params['local']))
	{
		// Are we validating the the file exists?
		if (!empty($params['validate']) && !file_exists($settings[$theme . '_dir'] . '/css/' . $filename))
		{
			// Maybe the default theme has it?
			if ($theme === 'theme' && !$params['force_current'] && file_exists($settings['default_theme_dir'] . '/' . $filename))
				$filename = $settings['default_theme_url'] . '/css/' . $filename . ($has_seed ? '' : $params['seed']);
			else
				$filename = false;
		}
		else
			$filename = $settings[$theme . '_url'] . '/css/' . $filename . ($has_seed ? '' : $params['seed']);
	}

	// Add it to the array for use in the template
	if (!empty($filename))
		$context['css_files'][$id] = array('filename' => $filename, 'options' => $params);
}

/**
 * Add a Javascript file for output later

 * @param string $filename
 * @param array $params
 * Keys are the following:
 * 	- ['local'] (true/false): define if the file is local
 * 	- ['default_theme'] (true/false): force use of default theme url
 * 	- ['defer'] (true/false): define if the file should load in <head> or before the closing <html> tag
 * 	- ['force_current'] (true/false): if this is false, we will attempt to load the file from the
 *    default theme if not found in the current theme
 *	- ['async'] (true/false): if the script should be loaded asynchronously (HTML5)
 *  - ['validate'] (true/false): if true script will validate the local file exists
 *  - ['seed'] (true/false/string): if true or null, use cache stale, false do not, or used a supplied string
 *
 * @param string $id
 */
function loadJavascriptFile($filename, $params = array(), $id = '')
{
	global $settings, $context;

	$params['seed'] = (!isset($params['seed']) || $params['seed'] === true) ? '?alph21' : (is_string($params['seed']) ? ($params['seed'] = $params['seed'][0] === '?' ? $params['seed'] : '?' . $params['seed']) : '');
	$params['force_current'] = !empty($params['force_current']) ? $params['force_current'] : false;
	$theme = !empty($params['default_theme']) ? 'default_theme' : 'theme';

	// account for shorthand like admin.js?alp21 filenames
	$has_seed = strpos($filename, '.js?');
	$id = empty($id) ? strtr(basename($filename), '?', '_') : $id;

	// Is this a local file?
	if (strpos($filename, 'http') === false || !empty($params['local']))
	{
		// Are we validating it exists on disk?
		if (!empty($params['validate']) && !file_exists($settings[$theme . '_dir'] . '/scripts/' . $filename))
		{
			// can't find it in this theme, how about the default?
			if ($theme === 'theme' && !$params['force_current'] && file_exists($settings['default_theme_dir'] . '/' . $filename))
				$filename = $settings['default_theme_url'] . '/scripts/' . $filename . ($has_seed ? '' : $params['seed']);
			else
				$filename = false;
		}
		else
			$filename = $settings[$theme . '_url'] . '/scripts/' . $filename . ($has_seed ? '' : $params['seed']);
	}

	// Add it to the array for use in the template
	if (!empty($filename))
		$context['javascript_files'][$id] = array('filename' => $filename, 'options' => $params);
}

/**
 * Add a Javascript variable for output later (for feeding text strings and similar to JS)
 * Cleaner and easier (for modders) than to use the function below.
 *
 * @param string $key
 * @param string $value
 * @param bool $escape = false, whether or not to escape the value
 */
function addJavascriptVar($key, $value, $escape = false)
{
	global $context;

	if (!empty($key) && !empty($value))
		$context['javascript_vars'][$key] = !empty($escape) ? JavaScriptEscape($value) : $value;
}

/**
 * Add a block of inline Javascript code to be executed later
 *
 * - only use this if you have to, generally external JS files are better, but for very small scripts
 *   or for scripts that require help from PHP/whatever, this can be useful.
 * - all code added with this function is added to the same <script> tag so do make sure your JS is clean!
 *
 * @param string $javascript
 * @param bool $defer = false, define if the script should load in <head> or before the closing <html> tag
 */
function addInlineJavascript($javascript, $defer = false)
{
	global $context;

	if (!empty($javascript))
		$context['javascript_inline'][(!empty($defer) ? 'defer' : 'standard')][] = $javascript;
}

/**
 * Load a language file.  Tries the current and default themes as well as the user and global languages.
 *
 * @param string $template_name
 * @param string $lang
 * @param bool $fatal = true
 * @param bool $force_reload = false
 * @return string The language actually loaded.
 */
function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false)
{
	global $user_info, $language, $settings, $context, $modSettings;
	global $cachedir, $db_show_debug, $sourcedir, $txt;
	static $already_loaded = array();

	// Default to the user's language.
	if ($lang == '')
		$lang = isset($user_info['language']) ? $user_info['language'] : $language;

	// Do we want the English version of language file as fallback?
	if (empty($modSettings['disable_language_fallback']) && $lang != 'english')
		loadLanguage($template_name, 'english', false);

	if (!$force_reload && isset($already_loaded[$template_name]) && $already_loaded[$template_name] == $lang)
		return $lang;

	// Make sure we have $settings - if not we're in trouble and need to find it!
	if (empty($settings['default_theme_dir']))
	{
		require_once($sourcedir . '/ScheduledTasks.php');
		loadEssentialThemeData();
	}

	// What theme are we in?
	$theme_name = basename($settings['theme_url']);
	if (empty($theme_name))
		$theme_name = 'unknown';

	// For each file open it up and write it out!
	foreach (explode('+', $template_name) as $template)
	{
		// Obviously, the current theme is most important to check.
		$attempts = array(
			array($settings['theme_dir'], $template, $lang, $settings['theme_url']),
			array($settings['theme_dir'], $template, $language, $settings['theme_url']),
		);

		// Do we have a base theme to worry about?
		if (isset($settings['base_theme_dir']))
		{
			$attempts[] = array($settings['base_theme_dir'], $template, $lang, $settings['base_theme_url']);
			$attempts[] = array($settings['base_theme_dir'], $template, $language, $settings['base_theme_url']);
		}

		// Fall back on the default theme if necessary.
		$attempts[] = array($settings['default_theme_dir'], $template, $lang, $settings['default_theme_url']);
		$attempts[] = array($settings['default_theme_dir'], $template, $language, $settings['default_theme_url']);

		// Fall back on the English language if none of the preferred languages can be found.
		if (!in_array('english', array($lang, $language)))
		{
			$attempts[] = array($settings['theme_dir'], $template, 'english', $settings['theme_url']);
			$attempts[] = array($settings['default_theme_dir'], $template, 'english', $settings['default_theme_url']);
		}

		// Try to find the language file.
		$found = false;
		foreach ($attempts as $k => $file)
		{
			if (file_exists($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php'))
			{
				// Include it!
				template_include($file[0] . '/languages/' . $file[1] . '.' . $file[2] . '.php');

				// Note that we found it.
				$found = true;

				break;
			}
		}

		// That couldn't be found!  Log the error, but *try* to continue normally.
		if (!$found && $fatal)
		{
			log_error(sprintf($txt['theme_language_error'], $template_name . '.' . $lang, 'template'));
			break;
		}

		// For the sake of backward compatibility
		if (!empty($txt['emails']))
		{
			foreach ($txt['emails'] as $key => $value)
			{
				$txt[$key . '_subject'] = $value['subject'];
				$txt[$key . '_body'] = $value['body'];
			}
			$txt['emails'] = array();
		}
		if (!empty($birthdayEmails))
		{
			foreach ($birthdayEmails as $key => $value)
			{
				$txtBirthdayEmails[$key . '_subject'] = $value['subject'];
				$txtBirthdayEmails[$key . '_body'] = $value['body'];
				$txtBirthdayEmails[$key . '_author'] = $value['author'];
			}
			$birthdayEmails = array();
		}
	}

	// Keep track of what we're up to soldier.
	if ($db_show_debug === true)
		$context['debug']['language_files'][] = $template_name . '.' . $lang . ' (' . $theme_name . ')';

	// Remember what we have loaded, and in which language.
	$already_loaded[$template_name] = $lang;

	// Return the language actually loaded.
	return $lang;
}

/**
 * Attempt to reload our known languages.
 * It will try to choose only utf8 or non-utf8 languages.
 *
 * @param bool $use_cache = true
 * @param bool $favor_utf8 = true
 * @return array
 */
function getLanguages($use_cache = true, $favor_utf8 = true)
{
	global $context, $smcFunc, $settings, $modSettings;

	// Either we don't use the cache, or its expired.
	if (!$use_cache || ($context['languages'] = cache_get_data('known_languages' . ($favor_utf8 ? '' : '_all'), !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600)) == null)
	{
		// If we don't have our theme information yet, lets get it.
		if (empty($settings['default_theme_dir']))
			loadTheme(0, false);

		// Default language directories to try.
		$language_directories = array(
			$settings['default_theme_dir'] . '/languages',
			$settings['actual_theme_dir'] . '/languages',
		);

		// We possibly have a base theme directory.
		if (!empty($settings['base_theme_dir']))
			$language_directories[] = $settings['base_theme_dir'] . '/languages';

		// Remove any duplicates.
		$language_directories = array_unique($language_directories);

		foreach ($language_directories as $language_dir)
		{
			// Can't look in here... doesn't exist!
			if (!file_exists($language_dir))
				continue;

			$dir = dir($language_dir);
			while ($entry = $dir->read())
			{
				// Look for the index language file....
				if (!preg_match('~^index\.(.+)\.php$~', $entry, $matches))
					continue;

				$context['languages'][$matches[1]] = array(
					'name' => $smcFunc['ucwords'](strtr($matches[1], array('_' => ' '))),
					'selected' => false,
					'filename' => $matches[1],
					'location' => $language_dir . '/index.' . $matches[1] . '.php',
				);

			}
			$dir->close();
		}

		// Favoring UTF8? Then prevent us from selecting non-UTF8 versions.
		if ($favor_utf8)
		{
			foreach ($context['languages'] as $lang)
				if (substr($lang['filename'], strlen($lang['filename']) - 5, 5) != '-utf8' && isset($context['languages'][$lang['filename'] . '-utf8']))
					unset($context['languages'][$lang['filename']]);
		}

		// Lets cash in on this deal.
		if (!empty($modSettings['cache_enable']))
			cache_put_data('known_languages' . ($favor_utf8 ? '' : '_all'), $context['languages'], !empty($modSettings['cache_enable']) && $modSettings['cache_enable'] < 1 ? 86400 : 3600);
	}

	return $context['languages'];
}

/**
 * Replace all vulgar words with respective proper words. (substring or whole words..)
 * What this function does:
 *  - it censors the passed string.
 *  - if the theme setting allow_no_censored is on, and the theme option
 *    show_no_censored is enabled, does not censor, unless force is also set.
 *  - it caches the list of censored words to reduce parsing.
 *
 * @param string &$text
 * @param bool $force = false
 * @return string The censored text
 */
function censorText(&$text, $force = false)
{
	global $modSettings, $options, $settings, $txt;
	static $censor_vulgar = null, $censor_proper;

	if ((!empty($options['show_no_censored']) && $settings['allow_no_censored'] && !$force) || empty($modSettings['censor_vulgar']) || trim($text) === '')
		return $text;

	// If they haven't yet been loaded, load them.
	if ($censor_vulgar == null)
	{
		$censor_vulgar = explode("\n", $modSettings['censor_vulgar']);
		$censor_proper = explode("\n", $modSettings['censor_proper']);

		// Quote them for use in regular expressions.
		if (!empty($modSettings['censorWholeWord']))
		{
			for ($i = 0, $n = count($censor_vulgar); $i < $n; $i++)
			{
				$censor_vulgar[$i] = str_replace(array('\\\\\\*', '\\*', '&', '\''), array('[*]', '[^\s]*?', '&amp;', '&#039;'), preg_quote($censor_vulgar[$i], '/'));
				$censor_vulgar[$i] = '/(?<=^|\W)' . $censor_vulgar[$i] . '(?=$|\W)/' . (empty($modSettings['censorIgnoreCase']) ? '' : 'i') . ((empty($modSettings['global_character_set']) ? $txt['lang_character_set'] : $modSettings['global_character_set']) === 'UTF-8' ? 'u' : '');

				// @todo I'm thinking the old way is some kind of bug and this is actually fixing it.
				//if (strpos($censor_vulgar[$i], '\'') !== false)
					//$censor_vulgar[$i] = str_replace('\'', '&#039;', $censor_vulgar[$i]);
			}
		}
	}

	// Censoring isn't so very complicated :P.
	if (empty($modSettings['censorWholeWord']))
		$text = empty($modSettings['censorIgnoreCase']) ? str_ireplace($censor_vulgar, $censor_proper, $text) : str_replace($censor_vulgar, $censor_proper, $text);
	else
		$text = preg_replace($censor_vulgar, $censor_proper, $text);

	return $text;
}

/**
 * Load the template/language file using eval or require? (with eval we can show an error message!)
 * 	- loads the template or language file specified by filename.
 * 	- uses eval unless disableTemplateEval is enabled.
 * 	- outputs a parse error if the file did not exist or contained errors.
 * 	- attempts to detect the error and line, and show detailed information.
 *
 * @param string $filename
 * @param bool $once = false, if true only includes the file once (like include_once)
 */
function template_include($filename, $once = false)
{
	global $context, $settings, $options, $txt, $scripturl, $modSettings;
	global $user_info, $boardurl, $boarddir, $sourcedir;
	global $maintenance, $mtitle, $mmessage;
	static $templates = array();

	// We want to be able to figure out any errors...
	@ini_set('track_errors', '1');

	// Don't include the file more than once, if $once is true.
	if ($once && in_array($filename, $templates))
		return;
	// Add this file to the include list, whether $once is true or not.
	else
		$templates[] = $filename;

	// Are we going to use eval?
	if (empty($modSettings['disableTemplateEval']))
	{
		$file_found = file_exists($filename) && eval('?' . '>' . rtrim(file_get_contents($filename))) !== false;
		$settings['current_include_filename'] = $filename;
	}
	else
	{
		$file_found = file_exists($filename);

		if ($once && $file_found)
			require_once($filename);
		elseif ($file_found)
			require($filename);
	}

	if ($file_found !== true)
	{
		ob_end_clean();
		if (!empty($modSettings['enableCompressedOutput']))
			@ob_start('ob_gzhandler');
		else
			ob_start();

		// Don't cache error pages!!
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-cache');

		if (!isset($txt['template_parse_error']))
		{
			$txt['template_parse_error'] = 'Template Parse Error!';
			$txt['template_parse_error_message'] = 'It seems something has gone sour on the forum with the template system.  This problem should only be temporary, so please come back later and try again.  If you continue to see this message, please contact the administrator.<br /><br />You can also try <a href="javascript:location.reload();">refreshing this page</a>.';
			$txt['template_parse_error_details'] = 'There was a problem loading the <tt><strong>%1$s</strong></tt> template or language file.  Please check the syntax and try again - remember, single quotes (<tt>\'</tt>) often have to be escaped with a slash (<tt>\\</tt>).  To see more specific error information from PHP, try <a href="' . $boardurl . '%1$s" class="extern">accessing the file directly</a>.<br /><br />You may want to try to <a href="javascript:location.reload();">refresh this page</a> or <a href="' . $scripturl . '?theme=1">use the default theme</a>.';
		}

		// First, let's get the doctype and language information out of the way.
		echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
	<head>';
		if (isset($context['character_set']))
			echo '
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />';

		if (!empty($maintenance) && !allowedTo('admin_forum'))
			echo '
		<title>', $mtitle, '</title>
	</head>
	<body>
		<h3>', $mtitle, '</h3>
		', $mmessage, '
	</body>
</html>';
		elseif (!allowedTo('admin_forum'))
			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', $txt['template_parse_error_message'], '
	</body>
</html>';
		else
		{
			require_once($sourcedir . '/Subs-Package.php');

			$error = fetch_web_data($boardurl . strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));
			if (empty($error) && ini_get('track_errors'))
				$error = $php_errormsg;

			$error = strtr($error, array('<b>' => '<strong>', '</b>' => '</strong>'));

			echo '
		<title>', $txt['template_parse_error'], '</title>
	</head>
	<body>
		<h3>', $txt['template_parse_error'], '</h3>
		', sprintf($txt['template_parse_error_details'], strtr($filename, array($boarddir => '', strtr($boarddir, '\\', '/') => '')));

			if (!empty($error))
				echo '
		<hr />

		<div style="margin: 0 20px;"><tt>', strtr(strtr($error, array('<strong>' . $boarddir => '<strong>...', '<strong>' . strtr($boarddir, '\\', '/') => '<strong>...')), '\\', '/'), '</tt></div>';

			// I know, I know... this is VERY COMPLICATED.  Still, it's good.
			if (preg_match('~ <strong>(\d+)</strong><br( /)?' . '>$~i', $error, $match) != 0)
			{
				$data = file($filename);
				$data2 = highlight_php_code(implode('', $data));
				$data2 = preg_split('~\<br( /)?\>~', $data2);

				// Fix the PHP code stuff...
				if (!isBrowser('gecko'))
					$data2 = str_replace("\t", '<span style="white-space: pre;">' . "\t" . '</span>', $data2);
				else
					$data2 = str_replace('<pre style="display: inline;">' . "\t" . '</pre>', "\t", $data2);

				// Now we get to work around a bug in PHP where it doesn't escape <br />s!
				$j = -1;
				foreach ($data as $line)
				{
					$j++;

					if (substr_count($line, '<br />') == 0)
						continue;

					$n = substr_count($line, '<br />');
					for ($i = 0; $i < $n; $i++)
					{
						$data2[$j] .= '&lt;br /&gt;' . $data2[$j + $i + 1];
						unset($data2[$j + $i + 1]);
					}
					$j += $n;
				}
				$data2 = array_values($data2);
				array_unshift($data2, '');

				echo '
		<div style="margin: 2ex 20px; width: 96%; overflow: auto;"><pre style="margin: 0;">';

				// Figure out what the color coding was before...
				$line = max($match[1] - 9, 1);
				$last_line = '';
				for ($line2 = $line - 1; $line2 > 1; $line2--)
					if (strpos($data2[$line2], '<') !== false)
					{
						if (preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line2], $color_match) != 0)
							$last_line = $color_match[1];
						break;
					}

				// Show the relevant lines...
				for ($n = min($match[1] + 4, count($data2) + 1); $line <= $n; $line++)
				{
					if ($line == $match[1])
						echo '</pre><div style="background-color: #ffb0b5;"><pre style="margin: 0;">';

					echo '<span style="color: black;">', sprintf('%' . strlen($n) . 's', $line), ':</span> ';
					if (isset($data2[$line]) && $data2[$line] != '')
						echo substr($data2[$line], 0, 2) == '</' ? preg_replace('~^</[^>]+>~', '', $data2[$line]) : $last_line . $data2[$line];

					if (isset($data2[$line]) && preg_match('~(<[^/>]+>)[^<]*$~', $data2[$line], $color_match) != 0)
					{
						$last_line = $color_match[1];
						echo '</', substr($last_line, 1, 4), '>';
					}
					elseif ($last_line != '' && strpos($data2[$line], '<') !== false)
						$last_line = '';
					elseif ($last_line != '' && $data2[$line] != '')
						echo '</', substr($last_line, 1, 4), '>';

					if ($line == $match[1])
						echo '</pre></div><pre style="margin: 0;">';
					else
						echo "\n";
				}

				echo '</pre></div>';
			}

			echo '
	</body>
</html>';
		}

		die;
	}
}

/**
 * Initialize a database connection.
 */
function loadDatabase()
{
	global $db_persist, $db_connection, $db_server, $db_user, $db_passwd;
	global $db_type, $db_name, $ssi_db_user, $ssi_db_passwd, $sourcedir, $db_prefix;

	// Figure out what type of database we are using.
	if (empty($db_type) || !file_exists($sourcedir . '/Subs-Db-' . $db_type . '.php'))
		$db_type = 'mysql';

	// Load the file for the database.
	require_once($sourcedir . '/Subs-Db-' . $db_type . '.php');

	// If we are in SSI try them first, but don't worry if it doesn't work, we have the normal username and password we can use.
	if (SMF == 'SSI' && !empty($ssi_db_user) && !empty($ssi_db_passwd))
		$db_connection = smf_db_initiate($db_server, $db_name, $ssi_db_user, $ssi_db_passwd, $db_prefix, array('persist' => $db_persist, 'non_fatal' => true, 'dont_select_db' => true));

	// Either we aren't in SSI mode, or it failed.
	if (empty($db_connection))
		$db_connection = smf_db_initiate($db_server, $db_name, $db_user, $db_passwd, $db_prefix, array('persist' => $db_persist, 'dont_select_db' => SMF == 'SSI'));

	// Safe guard here, if there isn't a valid connection lets put a stop to it.
	if (!$db_connection)
		display_db_error();

	// If in SSI mode fix up the prefix.
	if (SMF == 'SSI')
		db_fix_prefix($db_prefix, $db_name);
}

/**
 * Try to retrieve a cache entry. On failure, call the appropriate function.
 *
 * @param string $key
 * @param string $file
 * @param string $function
 * @param array $params
 * @param int $level = 1
 * @return string
 */
function cache_quick_get($key, $file, $function, $params, $level = 1)
{
	global $modSettings, $sourcedir;

	// @todo Why are we doing this if caching is disabled?

	if (function_exists('call_integration_hook'))
		call_integration_hook('pre_cache_quick_get', array($key, $file, $function, $params, $level));

	/* Refresh the cache if either:
		1. Caching is disabled.
		2. The cache level isn't high enough.
		3. The item has not been cached or the cached item expired.
		4. The cached item has a custom expiration condition evaluating to true.
		5. The expire time set in the cache item has passed (needed for Zend).
	*/
	if (empty($modSettings['cache_enable']) || $modSettings['cache_enable'] < $level || !is_array($cache_block = cache_get_data($key, 3600)) || (!empty($cache_block['refresh_eval']) && eval($cache_block['refresh_eval'])) || (!empty($cache_block['expires']) && $cache_block['expires'] < time()))
	{
		require_once($sourcedir . '/' . $file);
		$cache_block = call_user_func_array($function, $params);

		if (!empty($modSettings['cache_enable']) && $modSettings['cache_enable'] >= $level)
			cache_put_data($key, $cache_block, $cache_block['expires'] - time());
	}

	// Some cached data may need a freshening up after retrieval.
	if (!empty($cache_block['post_retri_eval']))
		eval($cache_block['post_retri_eval']);

	if (function_exists('call_integration_hook'))
		call_integration_hook('post_cache_quick_get', array($cache_block));

	return $cache_block['data'];
}

/**
 * Puts value in the cache under key for ttl seconds.
 *
 * - It may "miss" so shouldn't be depended on
 * - Uses the cache engine chosen in the ACP and saved in settings.php
 * - It supports:
 *     Turck MMCache: http://turck-mmcache.sourceforge.net/index_old.html#api
 *     Xcache: http://xcache.lighttpd.net/wiki/XcacheApi
 *     memcache: http://www.php.net/memcache
 *     APC: http://www.php.net/apc
 *     eAccelerator: http://bart.eaccelerator.net/doc/phpdoc/
 *     Zend: http://files.zend.com/help/Zend-Platform/output_cache_functions.htm
 *     Zend: http://files.zend.com/help/Zend-Platform/zend_cache_functions.htm
 *
 * @param string $key
 * @param mixed $value
 * @param int $ttl = 120
 */
function cache_put_data($key, $value, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;
	global $cache_accelerator, $cache_enable;

	if (empty($cache_enable))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'put', 's' => $value === null ? 0 : strlen(serialize($value)));
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':/', '-_');
	$value = $value === null ? null : serialize($value);

	switch ($cache_accelerator)
	{
		case 'memcached':
			// The simple yet efficient memcached.
			if (function_exists('memcached_set') || function_exists('memcache_set') && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return;

				memcache_set($memcached, $key, $value, 0, $ttl);
			}
			break;
		case 'eaccelerator':
			// eAccelerator...
			if (function_exists('eaccelerator_put'))
			{
				if (mt_rand(0, 10) == 1)
					eaccelerator_gc();

				if ($value === null)
					@eaccelerator_rm($key);
				else
					eaccelerator_put($key, $value, $ttl);
			}
			break;
		case 'mmcache':
			// Turck MMCache?
			if (function_exists('mmcache_put'))
			{
				if (mt_rand(0, 10) == 1)
					mmcache_gc();

				if ($value === null)
					@mmcache_rm($key);
				else
				{
					mmcache_lock($key);
					mmcache_put($key, $value, $ttl);
					mmcache_unlock($key);
				}
			}
			break;
		case 'apc':
			// Alternative PHP Cache, ahoy!
			if (function_exists('apc_store'))
			{
				// An extended key is needed to counteract a bug in APC.
				if ($value === null)
					apc_delete($key . 'smf');
				else
					apc_store($key . 'smf', $value, $ttl);
			}
			break;
		case 'zend':
			// Zend Platform/ZPS/etc.
			if (function_exists('zend_shm_cache_store'))
				zend_shm_cache_store('SMF::' . $key, $value, $ttl);
			elseif (function_exists('output_cache_put'))
				output_cache_put($key, $value);
			break;
		case 'xcache':
			if (function_exists('xcache_set') && ini_get('xcache.var_size') > 0)
			{
				if ($value === null)
					xcache_unset($key);
				else
					xcache_set($key, $value, $ttl);
			}
			break;
		default:
			// Otherwise custom cache?
			if ($value === null)
				@unlink($cachedir . '/data_' . $key . '.php');
			else
			{
				$cache_data = '<' . '?' . 'php if (!defined(\'SMF\')) die; if (' . (time() + $ttl) . ' < time()) $expired = true; else{$expired = false; $value = \'' . addcslashes($value, '\\\'') . '\';}' . '?' . '>';

				// Write out the cache file, check that the cache write was successful; all the data must be written
				// If it fails due to low diskspace, or other, remove the cache file
				if (file_put_contents($cachedir . '/data_' . $key . '.php', $cache_data, LOCK_EX) !== strlen($cache_data))
					@unlink($cachedir . '/data_' . $key . '.php');
			}
			break;
	}

	if (function_exists('call_integration_hook') && isset($value))
		call_integration_hook('cache_put_data', array($key, $value, $ttl));

	if (isset($db_show_debug) && $db_show_debug === true)
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
}

/**
 * Gets the value from the cache specified by key, so long as it is not older than ttl seconds.
 * - It may often "miss", so shouldn't be depended on.
 * - It supports the same as cache_put_data().
 *
 * @param string $key
 * @param int $ttl = 120
 * @return string
 */
function cache_get_data($key, $ttl = 120)
{
	global $boardurl, $sourcedir, $modSettings, $memcached;
	global $cache_hits, $cache_count, $db_show_debug, $cachedir;
	global $cache_accelerator, $cache_enable;

	if (empty($cache_enable))
		return;

	$cache_count = isset($cache_count) ? $cache_count + 1 : 1;
	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count] = array('k' => $key, 'd' => 'get');
		$st = microtime();
	}

	$key = md5($boardurl . filemtime($sourcedir . '/Load.php')) . '-SMF-' . strtr($key, ':/', '-_');

	switch ($cache_accelerator)
	{
		case 'memcache':
			// Okay, let's go for it memcached!
			if ((function_exists('memcache_get') || function_exists('memcached_get')) && isset($modSettings['cache_memcached']) && trim($modSettings['cache_memcached']) != '')
			{
				// Not connected yet?
				if (empty($memcached))
					get_memcached_server();
				if (!$memcached)
					return null;

				$value = (function_exists('memcache_get')) ? memcache_get($cache['connection'], $key) : memcached_get($cache['connection'], $key);
			}
			break;
		case 'eaccelerator':
			// Again, eAccelerator.
			if (function_exists('eaccelerator_get'))
				$value = eaccelerator_get($key);
			break;
		case 'mmcache':
			// The older, but ever-stable, Turck MMCache...
			if (function_exists('mmcache_get'))
				$value = mmcache_get($key);
			break;
		case 'apc':
			// This is the free APC from PECL.
			if (function_exists('apc_fetch'))
				$value = apc_fetch($key . 'smf');
			break;
		case 'zend':
			// Zend's pricey stuff.
			if (function_exists('zend_shm_cache_fetch'))
				$value = zend_shm_cache_fetch('SMF::' . $key, $ttl);
			elseif (function_exists('output_cache_get'))
				$value = output_cache_get($key, $ttl);
			break;
		case 'xcache':
			if (function_exists('xcache_get') && ini_get('xcache.var_size') > 0)
				$value = xcache_get($key);
			break;
		default:
			// Otherwise it's SMF data!
			if (file_exists($cachedir . '/data_' . $key . '.php') && filesize($cachedir . '/data_' . $key . '.php') > 10)
			{
				// php will cache file_exists et all, we can't 100% depend on its results so proceed with caution
				@include($cachedir . '/data_' . $key . '.php');
				if (!empty($expired) && isset($value))
				{
					@unlink($cachedir . '/data_' . $key . '.php');
					unset($value);
				}
			}
			break;
	}

	if (isset($db_show_debug) && $db_show_debug === true)
	{
		$cache_hits[$cache_count]['t'] = array_sum(explode(' ', microtime())) - array_sum(explode(' ', $st));
		$cache_hits[$cache_count]['s'] = isset($value) ? strlen($value) : 0;
	}

	if (function_exists('call_integration_hook') && isset($value))
		call_integration_hook('cache_get_data', array($key, $ttl, $value));

	return empty($value) ? null : @unserialize($value);
}

/**
 * Get memcache servers.
 *
 * - This function is used by cache_get_data() and cache_put_data().
 * - It attempts to connect to a random server in the cache_memcached setting.
 * - It recursively calls itself up to $level times.
 *
 * @param int $level = 3
 */
function get_memcached_server($level = 3)
{
	global $modSettings, $memcached, $db_persist, $cache_memcached;

	$servers = explode(',', $cache_memcached);
	$server = explode(':', trim($servers[array_rand($servers)]));
	$cache = (function_exists('memcache_get')) ? 'memcache' : ((function_exists('memcached_get') ? 'memcached' : ''));

	// Don't try more times than we have servers!
	$level = min(count($servers), $level);

	// Don't wait too long: yes, we want the server, but we might be able to run the query faster!
	if (empty($db_persist))
	{
		if ($cache === 'memcached')
			$memcached = memcached_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
		if ($cache === 'memcache')
			$memcached = memcache_connect($server[0], empty($server[1]) ? 11211 : $server[1]);
	}
	else
	{
		if ($cache === 'memcached')
			$memcached = memcached_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);
		if ($cache === 'memcache')
			$memcached = memcache_pconnect($server[0], empty($server[1]) ? 11211 : $server[1]);
	}

	if (!$memcached && $level > 0)
		get_memcached_server($level - 1);
}

function get_gravatar($email)
{
	global $modSettings;
		
	$url = 'http://www.gravatar.com/avatar/';
	$url .= md5(strtolower(trim($email)));
	$url .= '?s=80&amp;r=g';
	return $url;
}

?>