<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 *
 * This file contains a couple of functions for the latests posts on forum.
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
 * Get the latest posts of a forum.
 *
 * @param array $latestPostOptions
 * @return array
 */
function getLastPosts($latestPostOptions)
{
	global $scripturl, $modSettings, $smcFunc;

	// Find all the posts.  Newer ones will have higher IDs.  (assuming the last 20 * number are accessable...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $smcFunc['db_query']('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg,
			IFNULL(mem.real_name, m.poster_name) AS poster_name,
			SUBSTRING(m.body, 1, 385) AS body, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg >= {int:likely_max_msg}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}
			AND m.approved = {int:is_approved}' : '') . '
		ORDER BY m.id_msg DESC
		LIMIT ' . $latestPostOptions['number_posts'],
		array(
			'likely_max_msg' => max(0, $modSettings['maxMsgID'] - 50 * $latestPostOptions['number_posts']),
			'is_approved' => 1,
		)
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor the subject and post for the preview ;).
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		if ($smcFunc['strlen']($row['body']) > 128)
			$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

		// Build the array.
		$posts[] = array(
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 24),
			'preview' => $row['body'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'],
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.msg' . $row['id_msg'] . ';topicseen#msg' . $row['id_msg'] . '" rel="nofollow">' . $row['subject'] . '</a>'
		);
	}
	$smcFunc['db_free_result']($request);

	return $posts;
}

function getLastTopics($count)
{
	global $scripturl, $modSettings, $smcFunc, $context;

	$request = $smcFunc['db_query']('', '
		SELECT COUNT(id_topic)
		FROM {db_prefix}topics' . ($modSettings['postmod_active'] ? '
		WHERE approved = {int:is_approved}' : ''),
		array(
			'is_approved' => 1,
		)
	);
	list ($maxtopics) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	
	$context['page_index'] = constructPageIndex($scripturl . '?start=%1$d', $_REQUEST['start'], $maxtopics, $count, true);

	// Find all the posts.  Newer ones will have higher IDs.  (assuming the last 20 * number are accessable...)
	// @todo SLOW This query is now slow, NEEDS to be fixed.  Maybe break into two?
	$request = $smcFunc['db_query']('substring', '
		SELECT
			m.poster_time, m.subject, m.id_topic, m.id_member, m.id_msg, t.num_views, t.num_replies,
			IFNULL(mem.real_name, m.poster_name) AS poster_name,
			SUBSTRING(m.body, 1, 385) AS body, m.smileys_enabled
		FROM {db_prefix}messages AS m
			INNER JOIN {db_prefix}topics AS t ON (t.id_first_msg = m.id_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)' . ($modSettings['postmod_active'] ? '
		WHERE t.approved = {int:is_approved}' : '') . '
		ORDER BY t.num_replies DESC
		LIMIT {int:offset}, {int:limit}',
		array(
			'is_approved' => 1,
			'offset' => $_REQUEST['start'],
			'limit' => $count,
		)
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// Censor the subject and post for the preview ;).
		censorText($row['subject']);
		censorText($row['body']);

		$row['body'] = strip_tags(strtr(parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']), array('<br />' => '&#10;')));
		if ($smcFunc['strlen']($row['body']) > 128)
			$row['body'] = $smcFunc['substr']($row['body'], 0, 128) . '...';

		// Build the array.
		$topics[] = array(
			'topic' => $row['id_topic'],
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => empty($row['id_member']) ? '' : $scripturl . '?action=profile;u=' . $row['id_member'],
				'link' => empty($row['id_member']) ? $row['poster_name'] : '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>'
			),
			'subject' => $row['subject'],
			'short_subject' => shorten_subject($row['subject'], 24),
			'preview' => $row['body'],
			'replies' => $row['num_replies'],
			'views' => $row['num_views'],
			'likes' => getTotalTopicLike($row['id_topic']),
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'raw_timestamp' => $row['poster_time'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0" rel="nofollow">' . $row['subject'] . '</a>'
		);
	}
	$smcFunc['db_free_result']($request);

	return $topics;
}

/**
 * Callback-function for the cache for getLastPosts().
 *
 * @param array $latestPostOptions
 */
function cache_getLastPosts($latestPostOptions)
{
	return array(
		'data' => getLastPosts($latestPostOptions),
		'expires' => time() + 60,
		'post_retri_eval' => '
			foreach ($cache_block[\'data\'] as $k => $post)
			{
				$cache_block[\'data\'][$k][\'time\'] = timeformat($post[\'raw_timestamp\']);
				$cache_block[\'data\'][$k][\'timestamp\'] = forum_time(true, $post[\'raw_timestamp\']);
			}',
	);
}


function cache_getLastTopics($count)
{
	return array(
		'data' => getLastTopics($count),
		'expires' => time() + 60,
		'post_retri_eval' => '
			foreach ($cache_block[\'data\'] as $k => $post)
			{
				$cache_block[\'data\'][$k][\'time\'] = timeformat($post[\'raw_timestamp\']);
				$cache_block[\'data\'][$k][\'timestamp\'] = forum_time(true, $post[\'raw_timestamp\']);
			}',
	);
}

?>