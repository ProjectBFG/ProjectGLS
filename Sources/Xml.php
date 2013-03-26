<?php

/**
 * Maintains all XML-based interaction (mainly XMLhttp)
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


function XMLhttpMain()
{
	loadTemplate('Xml');

	$sub_actions = array(
		'corefeatures' => array(
			'function' => 'EnableCoreFeatures',
		),
		'previews' => array(
			'function' => 'RetrievePreview',
		),
	);

	// Easy adding of sub actions
 	call_integration_hook('integrate_xmlhttp', array($sub_actions));

	if (!isset($_REQUEST['sa'], $sub_actions[$_REQUEST['sa']]))
		fatal_lang_error('no_access', false);

	$sub_actions[$_REQUEST['sa']]['function']();
}

function EnableCoreFeatures()
{
	global $context, $smcFunc, $sourcedir, $modSettings, $txt, $boarddir, $settings;

	$context['xml_data'] = array();
	// Just in case, maybe we don't need it
	loadLanguage('Errors');

	// We need (at least) this to ensure that mod files are included
	if (!empty($modSettings['integrate_admin_include']))
	{
		$admin_includes = explode(',', $modSettings['integrate_admin_include']);
		foreach ($admin_includes as $include)
		{
			$include = strtr(trim($include), array('$boarddir' => $boarddir, '$sourcedir' => $sourcedir, '$themedir' => $settings['theme_dir']));
			if (file_exists($include))
				require_once($include);
		}
	}

	$errors = array();
	$returns = array();
	$tokens = array();
	if (allowedTo('admin_forum'))
	{
		$validation = validateSession();
		if (empty($validation))
		{
			require_once($sourcedir . '/ManageSettings.php');
			$result = ModifyCoreFeatures();

			if (empty($result))
			{
				$id = isset($_POST['feature_id']) ? $_POST['feature_id'] : '';

				if (!empty($id) && isset($context['features'][$id]))
				{
					$feature = $context['features'][$id];

					$returns[] = array(
						'value' => (!empty($_POST['feature_' . $id]) && $feature['url'] ? '<a href="' . $feature['url'] . '">' . $feature['title'] . '</a>' : $feature['title']),
					);

					createToken('admin-core', 'post');
					$tokens = array(
						array(
							'value' => $context['admin-core_token'],
							'attributes' => array('type' => 'token_var'),
						),
						array(
							'value' => $context['admin-core_token_var'],
							'attributes' => array('type' => 'token'),
						),
					);
				}
				else
				{
					$errors[] = array(
						'value' => $txt['feature_no_exists'],
					);
				}
			}
			else
			{
				$errors[] = array(
					'value' => $txt[$result],
				);
			}
		}
		else
		{
			$errors[] = array(
				'value' => $txt[$validation],
			);
		}
	}
	else
	{
		$errors[] = array(
			'value' => $txt['cannot_admin_forum']
		);
	}

	$context['sub_template'] = 'generic_xml';
	$context['xml_data'] = array (
		'corefeatures' => array (
			'identifier' => 'corefeature',
			'children' => $returns,
		),
		'tokens' => array (
			'identifier' => 'token',
			'children' => $tokens,
		),
		'errors' => array (
			'identifier' => 'error',
			'children' => $errors,
		),
	);
}

function RetrievePreview()
{
	global $context;

	$items = array(
		'newspreview',
		'newsletterpreview',
		'sig_preview',
		'warning_preview',
	);

	$context['sub_template'] = 'generic_xml';

	if (!isset($_POST['item']) || !in_array($_POST['item'], $items))
		return false;

	$_POST['item']();
}

function newspreview()
{
	global $context, $sourcedir, $smcFunc;

	require_once($sourcedir . '/Subs-Post.php');

	$errors = array();
	$news = !isset($_POST['news'])? '' : $smcFunc['htmlspecialchars']($_POST['news'], ENT_QUOTES);
	if (empty($news))
		$errors[] = array('value' => 'no_news');
	else
		preparsecode($news);

	$context['xml_data'] = array(
		'news' => array(
			'identifier' => 'parsedNews',
			'children' => array(
				array(
					'value' => parse_bbc($news),
				),
			),
		),
		'errors' => array(
			'identifier' => 'error',
			'children' => $errors
		),
	);
}
function newsletterpreview()
{
	global $context, $sourcedir, $smcFunc, $txt;

	require_once($sourcedir . '/Subs-Post.php');
	require_once($sourcedir . '/ManageNews.php');
	loadLanguage('Errors');

	$context['post_error']['messages'] = array();
	$context['send_pm'] = !empty($_POST['send_pm']) ? 1 : 0;
	$context['send_html'] = !empty($_POST['send_html']) ? 1 : 0;

	if (empty($_POST['subject']))
		$context['post_error']['messages'][] = $txt['error_no_subject'];
	if (empty($_POST['message']))
		$context['post_error']['messages'][] = $txt['error_no_message'];

	prepareMailingForPreview();

	$context['sub_template'] = 'pm';
}

function sig_preview()
{
	global $context, $sourcedir, $smcFunc, $txt, $user_info;

	require_once($sourcedir . '/Profile-Modify.php');
	loadLanguage('Profile');
	loadLanguage('Errors');

	$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;
	$is_owner = $user == $user_info['id'];

	// @todo Temporary
	// Borrowed from loadAttachmentContext in Display.php
	$can_change = $is_owner ? allowedTo(array('profile_extra_any', 'profile_extra_own')) : allowedTo('profile_extra_any');

	$errors = array();
	if (!empty($user) && $can_change)
	{
		$request = $smcFunc['db_query']('', '
			SELECT signature
			FROM {db_prefix}members
			WHERE id_member = {int:id_member}
			LIMIT 1',
			array(
				'id_member' => $user,
			)
		);
		list($current_signature) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
		censorText($current_signature);
		$current_signature = parse_bbc($current_signature, true, 'sig' . $user);

		$preview_signature = !empty($_POST['signature']) ? $_POST['signature'] : '';
		$validation = profileValidateSignature($preview_signature);

		if ($validation !== true && $validation !== false)
			$errors[] = array('value' => $txt['profile_error_' . $validation], 'attributes' => array('type' => 'error'));

		censorText($preview_signature);
		$preview_signature = parse_bbc($preview_signature, true, 'sig' . $user);
	}
	elseif (!$can_change)
	{
		if ($is_owner)
			$errors[] = array('value' => $txt['cannot_profile_extra_own'], 'attributes' => array('type' => 'error'));
		else
			$errors[] = array('value' => $txt['cannot_profile_extra_any'], 'attributes' => array('type' => 'error'));
	}
	else
		$errors[] = array('value' => $txt['no_user_selected'], 'attributes' => array('type' => 'error'));

	$context['xml_data']['signatures'] = array(
			'identifier' => 'signature',
			'children' => array()
		);
	if (isset($current_signature))
		$context['xml_data']['signatures']['children'][] = array(
					'value' => $current_signature,
					'attributes' => array('type' => 'current'),
				);
	if (isset($preview_signature))
		$context['xml_data']['signatures']['children'][] = array(
					'value' => $preview_signature,
					'attributes' => array('type' => 'preview'),
				);
	if (!empty($errors))
		$context['xml_data']['errors'] = array(
			'identifier' => 'error',
			'children' => array_merge(
				array(
					array(
						'value' => $txt['profile_errors_occurred'],
						'attributes' => array('type' => 'errors_occurred'),
					),
				),
				$errors
			),
		);
}

function warning_preview()
{
	global $context, $sourcedir, $smcFunc, $txt, $user_info, $scripturl, $mbname;

	require_once($sourcedir . '/Subs-Post.php');
	loadLanguage('Errors');
	loadLanguage('ModerationCenter');

	$user = isset($_POST['user']) ? (int) $_POST['user'] : 0;

	$context['post_error']['messages'] = array();
	if (allowedTo('issue_warning'))
	{
		$warning_body = !empty($_POST['body']) ? trim(censorText($_POST['body'])) : '';
		$context['preview_subject'] = !empty($_POST['title']) ? trim($smcFunc['htmlspecialchars']($_POST['title'])) : '';
		if (isset($_POST['issuing']))
		{
			if (empty($_POST['title']) || empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['warning_notify_blank'];
		}
		else
		{
			if (empty($_POST['title']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_title'];
			if (empty($_POST['body']))
				$context['post_error']['messages'][] = $txt['mc_warning_template_error_no_body'];
			// Add in few replacements.
			/**
			* These are the defaults:
			* - {MEMBER} - Member Name. => current user for review
			* - {MESSAGE} - Link to Offending Post. (If Applicable) => not applicable here, so not replaced
			* - {FORUMNAME} - Forum Name.
			* - {SCRIPTURL} - Web address of forum.
			* - {REGARDS} - Standard email sign-off.
			*/
			$find = array(
				'{MEMBER}',
				'{FORUMNAME}',
				'{SCRIPTURL}',
				'{REGARDS}',
			);
			$replace = array(
				$user_info['name'],
				$mbname,
				$scripturl,
				$txt['regards_team'],
			);
			$warning_body = str_replace($find, $replace, $warning_body);
		}

		if (!empty($_POST['body']))
		{
			preparsecode($warning_body);
			$warning_body = parse_bbc($warning_body, true);
		}
		$context['preview_message'] = $warning_body;
	}
	else
		$context['post_error']['messages'][] = array('value' => $txt['cannot_issue_warning'], 'attributes' => array('type' => 'error'));

	$context['sub_template'] = 'pm';
}

?>