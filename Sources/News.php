<?php

/**
 * This file contains the files necessary to display news as an XML feed.
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
 * Outputs xml data representing recent information or a profile.
 * Can be passed 4 subactions which decide what is output:
 *  'recent' for recent posts,
 *  'news' for news topics,
 *  'members' for recently registered members,
 *  'profile' for a member's profile.
 * To display a member's profile, a user id has to be given. (;u=1)
 * Outputs an rss feed instead of a proprietary one if the 'type' $_GET
 * parameter is 'rss' or 'rss2'.
 * Accessed via ?action=.xml.
 * Does not use any templates, sub templates, or template layers.
 *
 * @uses Stats language file.
 */
function ShowXmlFeed()
{
	global $context, $scripturl, $boardurl, $txt, $modSettings, $user_info;
	global $smcFunc, $forum_version, $cdata_override;

	// If it's not enabled, die.
	if (empty($modSettings['xmlnews_enable']))
		obExit(false);

	loadLanguage('Stats');

	// Default to latest 5.  No more than 255, please.
	$_GET['limit'] = empty($_GET['limit']) || (int) $_GET['limit'] < 1 ? 5 : min((int) $_GET['limit'], 255);

	$context['optimize_msg'] = array(
		'highest' => 'm.id_msg <= b.id_last_msg',
		'lowest' => 'm.id_msg >= ' . max(0, $modSettings['maxMsgID'] - 100 - $_GET['limit'] * 5),
	);

	// Show in rss or proprietary format?
	$xml_format = isset($_GET['type']) && in_array($_GET['type'], array('smf', 'rss', 'rss2', 'atom', 'rdf', 'webslice')) ? $_GET['type'] : 'smf';

	// @todo Birthdays?

	// List all the different types of data they can pull.
	$subActions = array(
		'recent' => array('getXmlRecent', 'recent-post'),
		'news' => array('getXmlNews', 'article'),
		'members' => array('getXmlMembers', 'member'),
		'profile' => array('getXmlProfile', null),
	);

	// Easy adding of sub actions
 	call_integration_hook('integrate_xmlfeeds', array($subActions));

	if (empty($_GET['sa']) || !isset($subActions[$_GET['sa']]))
		$_GET['sa'] = 'recent';

	// @todo Temp - webslices doesn't do everything yet.
	if ($xml_format == 'webslice' && $_GET['sa'] != 'recent')
		$xml_format = 'rss2';
	// If this is webslices we kinda cheat - we allow a template that we call direct for the HTML, and we override the CDATA.
	elseif ($xml_format == 'webslice')
	{
		$context['user'] += $user_info;
		$cdata_override = true;
		loadTemplate('Xml');
	}

	// We only want some information, not all of it.
	$cachekey = md5(serialize(array($xml_format, $_GET['action'], $_GET['limit'], $_GET['sa'])));
	$cache_t = microtime();

	// Get the associative array representing the xml.
	if (!empty($modSettings['cache_enable']) && (!$user_info['is_guest'] || $modSettings['cache_enable'] >= 3))
		$xml = cache_get_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, 240);
	if (empty($xml))
	{
		$xml = $subActions[$_GET['sa']][0]($xml_format);

		if (!empty($modSettings['cache_enable']) && (($user_info['is_guest'] && $modSettings['cache_enable'] >= 3)
		|| (!$user_info['is_guest'] && (array_sum(explode(' ', microtime())) - array_sum(explode(' ', $cache_t)) > 0.2))))
			cache_put_data('xmlfeed-' . $xml_format . ':' . ($user_info['is_guest'] ? '' : $user_info['id'] . '-') . $cachekey, $xml, 240);
	}

	$feed_title = htmlspecialchars(strip_tags($context['forum_name'])) . (isset($feed_title) ? $feed_title : '');

	// This is an xml file....
	ob_end_clean();
	if (!empty($modSettings['enableCompressedOutput']))
		@ob_start('ob_gzhandler');
	else
		ob_start();

	if ($xml_format == 'smf' || isset($_REQUEST['debug']))
		header('Content-Type: text/xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	elseif ($xml_format == 'rss' || $xml_format == 'rss2' || $xml_format == 'webslice')
		header('Content-Type: application/rss+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	elseif ($xml_format == 'atom')
		header('Content-Type: application/atom+xml; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));
	elseif ($xml_format == 'rdf')
		header('Content-Type: ' . (isBrowser('ie') ? 'text/xml' : 'application/rdf+xml') . '; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

	// First, output the xml header.
	echo '<?xml version="1.0" encoding="', $context['character_set'], '"?' . '>';

	// Are we outputting an rss feed or one with more information?
	if ($xml_format == 'rss' || $xml_format == 'rss2')
	{
		// Start with an RSS 2.0 header.
		echo '
<rss version=', $xml_format == 'rss2' ? '"2.0"' : '"0.92"', ' xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
	<channel>
		<title>', $feed_title, '</title>
		<link>', $scripturl, '</link>
		<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>';

		// Output all of the associative array, start indenting with 2 tabs, and name everything "item".
		dumpTags($xml, 2, 'item', $xml_format);

		// Output the footer of the xml.
		echo '
	</channel>
</rss>';
	}
	elseif ($xml_format == 'webslice')
	{
		$context['recent_posts_data'] = $xml;
		$context['can_pm_read'] = allowedTo('pm_read');

		// This always has RSS 2
		echo '
<rss version="2.0" xmlns:mon="http://www.microsoft.com/schemas/rss/monitoring/2007" xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">
	<channel>
		<title>', $feed_title, ' - ', $txt['recent_posts'], '</title>
		<link>', $scripturl, '?action=recent</link>
		<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>
		<item>
			<title>', $feed_title, ' - ', $txt['recent_posts'], '</title>
			<link>', $scripturl, '?action=recent</link>
			<description><![CDATA[
				', template_webslice_header_above(), '
				', template_webslice_recent_posts(), '
				', template_webslice_header_below(), '
			]]></description>
		</item>
	</channel>
</rss>';
	}
	elseif ($xml_format == 'atom')
	{
		echo '
<feed xmlns="http://www.w3.org/2005/Atom">
	<title>', $feed_title, '</title>
	<link rel="alternate" type="text/html" href="', $scripturl, '" />
	<link rel="self" type="application/rss+xml" href="', $scripturl, '?type=atom;action=.xml', !empty($url_parts) ? ';' . implode(';', $url_parts) : '', '" />
	<id>', $scripturl, '</id>
	<icon>', $boardurl, '/favicon.ico</icon>

	<updated>', gmstrftime('%Y-%m-%dT%H:%M:%SZ'), '</updated>
	<subtitle><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></subtitle>
	<generator uri="http://www.simplemachines.org" version="', strtr($forum_version, array('SMF' => '')), '">SMF</generator>
	<author>
		<name>', strip_tags($context['forum_name']), '</name>
	</author>';

		dumpTags($xml, 2, 'entry', $xml_format);

		echo '
</feed>';
	}
	elseif ($xml_format == 'rdf')
	{
		echo '
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns="http://purl.org/rss/1.0/">
	<channel rdf:about="', $scripturl, '">
		<title>', $feed_title, '</title>
		<link>', $scripturl, '</link>
		<description><![CDATA[', strip_tags($txt['xml_rss_desc']), ']]></description>
		<items>
			<rdf:Seq>';

		foreach ($xml as $item)
			echo '
				<rdf:li rdf:resource="', $item['link'], '" />';

		echo '
			</rdf:Seq>
		</items>
	</channel>
';

		dumpTags($xml, 1, 'item', $xml_format);

		echo '
</rdf:RDF>';
	}
	// Otherwise, we're using our proprietary formats - they give more data, though.
	else
	{
		echo '
<smf:xml-feed xmlns:smf="http://www.simplemachines.org/" xmlns="http://www.simplemachines.org/xml/', $_GET['sa'], '" xml:lang="', strtr($txt['lang_locale'], '_', '-'), '">';

		// Dump out that associative array.  Indent properly.... and use the right names for the base elements.
		dumpTags($xml, 1, $subActions[$_GET['sa']][1], $xml_format);

		echo '
</smf:xml-feed>';
}

	obExit(false);
}

/**
 * Called from dumpTags to convert data to xml
 * Finds urls for local sitte and santizes them
 *
 * @param type $val
 * @return type
 */
function fix_possible_url($val)
{
	global $modSettings, $context, $scripturl;

	if (substr($val, 0, strlen($scripturl)) != $scripturl)
		return $val;

	call_integration_hook('integrate_fix_url', array(&$val));

	if (empty($modSettings['queryless_urls']) || ($context['server']['is_cgi'] && ini_get('cgi.fix_pathinfo') == 0 && @get_cfg_var('cgi.fix_pathinfo') == 0) || (!$context['server']['is_apache'] && !$context['server']['is_lighttpd']))
		return $val;

	$val = preg_replace('/^' . preg_quote($scripturl, '/') . '\?((?:topic)=[^#"]+)(#[^"]*)?$/e', '\'\' . $scripturl . \'/\' . strtr(\'$1\', \'&;=\', \'//,\') . \'.html$2\'', $val);
	return $val;
}

/**
 * Ensures supplied data is properly encpsulated in cdata xml tags
 * Called from getXmlProfile in News.php
 *
 * @param type $data
 * @param type $ns
 * @return type
 */
function cdata_parse($data, $ns = '')
{
	global $smcFunc, $cdata_override;

	// Are we not doing it?
	if (!empty($cdata_override))
		return $data;

	$cdata = '<![CDATA[';

	for ($pos = 0, $n = $smcFunc['strlen']($data); $pos < $n; null)
	{
		$positions = array(
			$smcFunc['strpos']($data, '&', $pos),
			$smcFunc['strpos']($data, ']', $pos),
		);
		if ($ns != '')
			$positions[] = $smcFunc['strpos']($data, '<', $pos);
		foreach ($positions as $k => $dummy)
		{
			if ($dummy === false)
				unset($positions[$k]);
		}

		$old = $pos;
		$pos = empty($positions) ? $n : min($positions);

		if ($pos - $old > 0)
			$cdata .= $smcFunc['substr']($data, $old, $pos - $old);
		if ($pos >= $n)
			break;

		if ($smcFunc['substr']($data, $pos, 1) == '<')
		{
			$pos2 = $smcFunc['strpos']($data, '>', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			if ($smcFunc['substr']($data, $pos + 1, 1) == '/')
				$cdata .= ']]></' . $ns . ':' . $smcFunc['substr']($data, $pos + 2, $pos2 - $pos - 1) . '<![CDATA[';
			else
				$cdata .= ']]><' . $ns . ':' . $smcFunc['substr']($data, $pos + 1, $pos2 - $pos) . '<![CDATA[';
			$pos = $pos2 + 1;
		}
		elseif ($smcFunc['substr']($data, $pos, 1) == ']')
		{
			$cdata .= ']]>&#093;<![CDATA[';
			$pos++;
		}
		elseif ($smcFunc['substr']($data, $pos, 1) == '&')
		{
			$pos2 = $smcFunc['strpos']($data, ';', $pos);
			if ($pos2 === false)
				$pos2 = $n;
			$ent = $smcFunc['substr']($data, $pos + 1, $pos2 - $pos - 1);

			if ($smcFunc['substr']($data, $pos + 1, 1) == '#')
				$cdata .= ']]>' . $smcFunc['substr']($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';
			elseif (in_array($ent, array('amp', 'lt', 'gt', 'quot')))
				$cdata .= ']]>' . $smcFunc['substr']($data, $pos, $pos2 - $pos + 1) . '<![CDATA[';

			$pos = $pos2 + 1;
		}
	}

	$cdata .= ']]>';

	return strtr($cdata, array('<![CDATA[]]>' => ''));
}

/**
 * Formats data retrieved in other functions into xml format.
 * Additionally formats data based on the specific format passed.
 * This function is recursively called to handle sub arrays of data.

 * @param array $data the array to output as xml data
 * @param int $i the amount of indentation to use.
 * @param string $tag if specified, it will be used instead of the keys of data.
 * @param string $xml_format
 */
function dumpTags($data, $i, $tag = null, $xml_format = '')
{
	global $modSettings, $context, $scripturl;

	// For every array in the data...
	foreach ($data as $key => $val)
	{
		// Skip it, it's been set to null.
		if ($val === null)
			continue;

		// If a tag was passed, use it instead of the key.
		$key = isset($tag) ? $tag : $key;

		// First let's indent!
		echo "\n", str_repeat("\t", $i);

		// Grr, I hate kludges... almost worth doing it properly, here, but not quite.
		if ($xml_format == 'atom' && $key == 'link')
		{
			echo '<link rel="alternate" type="text/html" href="', fix_possible_url($val), '" />';
			continue;
		}

		// If it's empty/0/nothing simply output an empty tag.
		if ($val == '')
			echo '<', $key, ' />';
		elseif ($xml_format == 'atom' && $key == 'category')
			echo '<', $key, ' term="', $val, '" />';
		else
		{
			// Beginning tag.
			if ($xml_format == 'rdf' && $key == 'item' && isset($val['link']))
			{
				echo '<', $key, ' rdf:about="', fix_possible_url($val['link']), '">';
				echo "\n", str_repeat("\t", $i + 1);
				echo '<dc:format>text/html</dc:format>';
			}
			elseif ($xml_format == 'atom' && $key == 'summary')
				echo '<', $key, ' type="html">';
			else
				echo '<', $key, '>';

			if (is_array($val))
			{
				// An array.  Dump it, and then indent the tag.
				dumpTags($val, $i + 1, null, $xml_format);
				echo "\n", str_repeat("\t", $i), '</', $key, '>';
			}
			// A string with returns in it.... show this as a multiline element.
			elseif (strpos($val, "\n") !== false || strpos($val, '<br />') !== false)
				echo "\n", fix_possible_url($val), "\n", str_repeat("\t", $i), '</', $key, '>';
			// A simple string.
			else
				echo fix_possible_url($val), '</', $key, '>';
		}
	}
}

/**
 * Retrieve the list of members from database.
 * The array will be generated to match the format.
 * @todo get the list of members from Subs-Members.
 *
 * @param string $xml_format
 * @return array
 */
function getXmlMembers($xml_format)
{
	global $scripturl, $smcFunc;

	if (!allowedTo('view_mlist'))
		return array();

	// Find the most recent members.
	$request = $smcFunc['db_query']('', '
		SELECT id_member, member_name, real_name, date_registered, last_login
		FROM {db_prefix}members
		ORDER BY id_member DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Make the data look rss-ish.
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'comments' => $scripturl . '?action=pm;sa=send;u=' . $row['id_member'],
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['date_registered']),
				'guid' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		elseif ($xml_format == 'rdf')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'title' => cdata_parse($row['real_name']),
				'link' => $scripturl . '?action=profile;u=' . $row['id_member'],
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['date_registered']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['last_login']),
				'id' => $scripturl . '?action=profile;u=' . $row['id_member'],
			);
		// More logical format for the data, but harder to apply.
		else
			$data[] = array(
				'name' => cdata_parse($row['real_name']),
				'time' => htmlspecialchars(strip_tags(timeformat($row['date_registered']))),
				'id' => $row['id_member'],
				'link' => $scripturl . '?action=profile;u=' . $row['id_member']
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the recent topics to display.
 * The returned array will be generated to match the xml_format.
 * @todo does not belong here.
 *
 * @param $xml_format
 * @return array, of recent posts
 */
function getXmlRecent($xml_format)
{
	global $user_info, $scripturl, $modSettings;
	global $smcFunc, $settings, $context;

	$done = false;
	$loops = 0;
	while (!$done)
	{
		$optimize_msg = implode(' AND ', $context['optimize_msg']);
		$request = $smcFunc['db_query']('', '
			SELECT m.id_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			WHERE ' . (empty($optimize_msg) ? '' : '
				AND {raw:optimize_msg}') . ($modSettings['postmod_active'] ? '
				AND m.approved = {int:is_approved}' : '') . '
			ORDER BY m.id_msg DESC
			LIMIT {int:limit}',
			array(
				'limit' => $_GET['limit'],
				'is_approved' => 1,
				'optimize_msg' => $optimize_msg,
			)
		);
		// If we don't have $_GET['limit'] results, try again with an unoptimized version covering all rows.
		if ($loops < 2 && $smcFunc['db_num_rows']($request) < $_GET['limit'])
		{
			$smcFunc['db_free_result']($request);
			$context['optimize_msg']['lowest'] = $loops ? 'm.id_msg >= t.id_first_msg' : 'm.id_msg >= (t.id_last_msg - t.id_first_msg) / 2';
			$loops++;
		}
		else
			$done = true;
	}
	$messages = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$messages[] = $row['id_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($messages))
		return array();

	// Find the most recent posts this user can see.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.smileys_enabled, m.poster_time, m.id_msg, m.subject, m.body, m.id_topic,
			b.name AS bname, t.num_replies, m.id_member, mf.id_member AS id_first_member,
			IFNULL(mem.real_name, m.poster_name) AS poster_name, mf.subject AS first_subject,
			IFNULL(memf.real_name, mf.poster_name) AS first_poster_name, mem.hide_email,
			IFNULL(mem.email_address, m.poster_email) AS poster_email, m.modified_time
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
		WHERE m.id_msg IN ({array_int:message_list})
		ORDER BY m.id_msg DESC
		LIMIT {int:limit}',
		array(
			'limit' => $_GET['limit'],
			'message_list' => $messages,
		)
	);
	$data = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Limit the length of the message, if the option is set.
		if (!empty($modSettings['xmlnews_maxlen']) && $smcFunc['strlen'](str_replace('<br />', "\n", $row['body'])) > $modSettings['xmlnews_maxlen'])
			$row['body'] = strtr($smcFunc['substr'](str_replace('<br />', "\n", $row['body']), 0, $modSettings['xmlnews_maxlen'] - 3), array("\n" => '<br />')) . '...';

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		censorText($row['body']);
		censorText($row['subject']);

		// Doesn't work as well as news, but it kinda does..
		if ($xml_format == 'rss' || $xml_format == 'rss2')
			$data[] = array(
				'title' => $row['subject'],
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'description' => cdata_parse($row['body']),
				'author' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
				'category' => cdata_parse($row['bname']),
				'comments' => $scripturl . '?action=post;topic=' . $row['id_topic'] . '.0',
				'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $row['poster_time']),
				'guid' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
			);
		elseif ($xml_format == 'rdf')
			$data[] = array(
				'title' => $row['subject'],
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'description' => cdata_parse($row['body']),
			);
		elseif ($xml_format == 'atom')
			$data[] = array(
				'title' => $row['subject'],
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
				'summary' => cdata_parse($row['body']),
				'category' => $row['bname'],
				'author' => array(
					'name' => $row['poster_name'],
					'email' => in_array(showEmailAddress(!empty($row['hide_email']), $row['id_member']), array('yes', 'yes_permission_override')) ? $row['poster_email'] : null,
					'uri' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
				),
				'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $row['poster_time']),
				'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', empty($row['modified_time']) ? $row['poster_time'] : $row['modified_time']),
				'id' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg'],
			);
		// A lot of information here.  Should be enough to please the rss-ers.
		else
			$data[] = array(
				'time' => htmlspecialchars(strip_tags(timeformat($row['poster_time']))),
				'id' => $row['id_msg'],
				'subject' => cdata_parse($row['subject']),
				'body' => cdata_parse($row['body']),
				'starter' => array(
					'name' => cdata_parse($row['first_poster_name']),
					'id' => $row['id_first_member'],
					'link' => !empty($row['id_first_member']) ? $scripturl . '?action=profile;u=' . $row['id_first_member'] : ''
				),
				'poster' => array(
					'name' => cdata_parse($row['poster_name']),
					'id' => $row['id_member'],
					'link' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : ''
				),
				'topic' => array(
					'subject' => cdata_parse($row['first_subject']),
					'id' => $row['id_topic'],
					'link' => $scripturl . '?topic=' . $row['id_topic'] . '.new#new'
				),
				'link' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . '#msg' . $row['id_msg']
			);
	}
	$smcFunc['db_free_result']($request);

	return $data;
}

/**
 * Get the profile information for member into an array,
 * which will be generated to match the xml_format.
 * @todo refactor.
 *
 * @param $xml_format
 * @return array, of profile data.
 */
function getXmlProfile($xml_format)
{
	global $scripturl, $memberContext, $user_profile, $modSettings, $user_info;

	// You must input a valid user....
	if (empty($_GET['u']) || loadMemberData((int) $_GET['u']) === false)
		return array();

	// Make sure the id is a number and not "I like trying to hack the database".
	$_GET['u'] = (int) $_GET['u'];
	// Load the member's contextual information!
	if (!loadMemberContext($_GET['u']) || !allowedTo('profile_view_any'))
		return array();

	// Okay, I admit it, I'm lazy.  Stupid $_GET['u'] is long and hard to type.
	$profile = &$memberContext[$_GET['u']];

	if ($xml_format == 'rss' || $xml_format == 'rss2')
		$data = array(array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'description' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
			'comments' => $scripturl . '?action=pm;sa=send;u=' . $profile['id'],
			'pubDate' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['date_registered']),
			'guid' => $scripturl . '?action=profile;u=' . $profile['id'],
		));
	elseif ($xml_format == 'rdf')
		$data = array(array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'description' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
		));
	elseif ($xml_format == 'atom')
		$data[] = array(
			'title' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'summary' => cdata_parse(isset($profile['group']) ? $profile['group'] : $profile['post_group']),
			'author' => array(
				'name' => $profile['real_name'],
				'email' => in_array(showEmailAddress(!empty($profile['hide_email']), $profile['id']), array('yes', 'yes_permission_override')) ? $profile['email'] : null,
				'uri' => !empty($profile['website']) ? $profile['website']['url'] : ''
			),
			'published' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['date_registered']),
			'updated' => gmstrftime('%Y-%m-%dT%H:%M:%SZ', $user_profile[$profile['id']]['last_login']),
			'id' => $scripturl . '?action=profile;u=' . $profile['id'],
		);
	else
	{
		$data = array(
			'username' => $user_info['is_admin'] || $user_info['id'] == $profile['id'] ? cdata_parse($profile['username']) : '',
			'name' => cdata_parse($profile['name']),
			'link' => $scripturl . '?action=profile;u=' . $profile['id'],
			'posts' => $profile['posts'],
			'post-group' => cdata_parse($profile['post_group']),
			'language' => cdata_parse($profile['language']),
			'last-login' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['last_login']),
			'registered' => gmdate('D, d M Y H:i:s \G\M\T', $user_profile[$profile['id']]['date_registered'])
		);

		// Everything below here might not be set, and thus maybe shouldn't be displayed.
		if ($profile['gender']['name'] != '')
			$data['gender'] = cdata_parse($profile['gender']['name']);

		// If they are online, show an empty tag... no reason to put anything inside it.
		if ($profile['online']['is_online'])
			$data['online'] = '';

		if ($profile['signature'] != '')
			$data['signature'] = cdata_parse($profile['signature']);
		if ($profile['blurb'] != '')
			$data['blurb'] = cdata_parse($profile['blurb']);
		if ($profile['location'] != '')
			$data['location'] = cdata_parse($profile['location']);
		if ($profile['title'] != '')
			$data['title'] = cdata_parse($profile['title']);

		if ($profile['website']['title'] != '')
			$data['website'] = array(
				'title' => cdata_parse($profile['website']['title']),
				'link' => $profile['website']['url']
			);

		if ($profile['group'] != '')
			$data['position'] = cdata_parse($profile['group']);

		if (in_array($profile['show_email'], array('yes', 'yes_permission_override')))
			$data['email'] = $profile['email'];

		if (!empty($profile['birth_date']) && substr($profile['birth_date'], 0, 4) != '0000')
		{
			list ($birth_year, $birth_month, $birth_day) = sscanf($profile['birth_date'], '%d-%d-%d');
			$datearray = getdate(forum_time());
			$data['age'] = $datearray['year'] - $birth_year - (($datearray['mon'] > $birth_month || ($datearray['mon'] == $birth_month && $datearray['mday'] >= $birth_day)) ? 0 : 1);
		}
	}

	// Save some memory.
	unset($profile, $memberContext[$_GET['u']]);

	return $data;
}

?>