<?php

/**
 * ProjectGLS
 *
 * @copyright 2013 ProjectGLS
 * @license http://next.mmobrowser.com/projectgls/license.txt
 *
 * Simple Machines Forum (SMF)
 *
 * @package SMF
 * @author Simple Machines
 * @copyright 2012 Simple Machines
 * @license http://www.simplemachines.org/about/smf/license.php BSD
 *
 * @version 1.0 Alpha 1
 */

function template_main()
{
	global $context, $settings, $txt, $scripturl, $modSettings;

	echo '
	<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '" name="searchform" id="searchform">
		<h3 class="catbg">
			', !empty($settings['use_buttons']) ? '<img src="' . $settings['images_url'] . '/buttons/search_hd.png" alt="" class="icon" />' : ' ', $txt['set_parameters'], '
		</h3>';

	if (!empty($context['search_errors']))
		echo '
		<p class="errorbox">', implode('<br />', $context['search_errors']['messages']), '</p>';

	if (!empty($context['search_ignored']))
		echo '
		<p class="noticebox">', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '</p>';
		
	// Simple Search?
	if ($context['simple_search'])
	{
		echo '
		<fieldset id="simple_search">
			<div class="roundframe">
				<div id="search_term_input">
					<strong>', $txt['search_for'], ':</strong>
					<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
					', $context['require_verification'] ? '' : '&nbsp;<input type="submit" name="s_search" value="' . $txt['search'] . '" class="btn" />
				</div>';

		if (empty($modSettings['search_simple_fulltext']))
			echo '
				<p class="smalltext">', $txt['search_example'], '</p>';

		if ($context['require_verification'])
			echo '
				<div class="verification>
					<strong>', $txt['search_visual_verification_label'], ':</strong>
					<br />', template_control_verification($context['visual_verification_id'], 'all'), '<br />
					<input id="submit" type="submit" name="s_search" value="' . $txt['search'] . '" class="btn" />
				</div>';

		echo '
				<a href="', $scripturl, '?action=search;advanced" onclick="this.href += \';search=\' + escape(document.forms.searchform.search.value);">', $txt['search_advanced'], '</a>
				<input type="hidden" name="advanced" value="0" />
			</div>
		</fieldset>';
	}

	// Advanced search!
	else
	{
		echo '
		<fieldset id="advanced_search">
			<div class="roundframe">
				<dl class="settings" id="search_options">
					<dt class="righttext">
						<strong><label for="searchfor">', $txt['search_for'], ':</label></strong>
					</dt>
					<dd>
						<input type="text" name="search" id="searchfor" ', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />';

		if (empty($modSettings['search_simple_fulltext']))
			echo '
						<em class="smalltext">', $txt['search_example'], '</em>';

		echo '
					</dd>

					<dt class="righttext"><label for="searchtype">',
						$txt['search_match'], ':</label>
					</dt>
					<dd>
						<select name="searchtype" id="searchtype">
							<option value="1"', empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['all_words'], '</option>
							<option value="2"', !empty($context['search_params']['searchtype']) ? ' selected="selected"' : '', '>', $txt['any_words'], '</option>
						</select>
					</dd>
					<dt class="righttext"><label for="userspec">',
						$txt['by_user'], ':</label>
					</dt>
					<dd>
						<input id="userspec" type="text" name="userspec" value="', empty($context['search_params']['userspec']) ? '*' : $context['search_params']['userspec'], '" size="40" class="input_text" />
					</dd>
					<dt class="righttext"><label for="sort">',
						$txt['search_order'], ':</label>
					</dt>
					<dd>
						<select id="sort" name="sort">
							<option value="relevance|desc">', $txt['search_orderby_relevant_first'], '</option>
							<option value="num_replies|desc">', $txt['search_orderby_large_first'], '</option>
							<option value="num_replies|asc">', $txt['search_orderby_small_first'], '</option>
							<option value="id_msg|desc">', $txt['search_orderby_recent_first'], '</option>
							<option value="id_msg|asc">', $txt['search_orderby_old_first'], '</option>
						</select>
					</dd>
					<dt class="righttext options">',
						$txt['search_options'], ':
					</dt>
					<dd class="options">
						<label for="show_complete">', $txt['search_show_complete_messages'], '
							<input type="checkbox" name="show_complete" id="show_complete" value="1"', !empty($context['search_params']['show_complete']) ? ' checked="checked"' : '', ' class="input_check" />
						</label><br />
						<label for="subject_only">', $txt['search_subject_only'], '
							<input type="checkbox" name="subject_only" id="subject_only" value="1"', !empty($context['search_params']['subject_only']) ? ' checked="checked"' : '', ' class="input_check" />
						</label>
					</dd>
					<dt class="righttext between">',
						$txt['search_post_age'], ':
					</dt>
					<dd><label for="minage">',
						$txt['search_between'], '</label><input type="text" name="minage" id="minage" value="', empty($context['search_params']['minage']) ? '0' : $context['search_params']['minage'], '" size="5" maxlength="4" class="input_text" />&nbsp;<label for="maxage">', $txt['search_and'], '&nbsp;</label><input type="text" name="maxage" id="maxage" value="', empty($context['search_params']['maxage']) ? '9999' : $context['search_params']['maxage'], '" size="5" maxlength="4" class="input_text" /> ', $txt['days_word'], '
					</dd>
				</dl>
				<script><!-- // --><![CDATA[
					createEventListener(window);
					window.addEventListener("load", initSearch, false);
				// ]]></script>
				<input type="hidden" name="advanced" value="1" />';

		// Require an image to be typed to save spamming?
		if ($context['require_verification'])
		{
			echo '
				<p>
					<strong>', $txt['verification'], ':</strong>
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</p>';
		}

		// If $context['search_params']['topic'] is set, that means we're searching just one topic.
		if (!empty($context['search_params']['topic']))
			echo '
				<p>', $txt['search_specific_topic'], ' &quot;', $context['search_topic']['link'], '&quot;.</p>
				<input type="hidden" name="topic" value="', $context['search_topic']['id'], '" />';

		echo '
		<div class="pull-right">
			<input type="submit" name="b_search" value="', $txt['search'], '" class="btn" />
		</div>
			</div>
		</fieldset>';

	echo '
		<script src="', $settings['default_theme_url'], '/scripts/suggest.js?alp21"></script>
		<script><!-- // --><![CDATA[
			var oAddMemberSuggest = new smc_AutoSuggest({
				sSelf: \'oAddMemberSuggest\',
				sSessionId: smf_session_id,
				sSessionVar: smf_session_var,
				sControlId: \'userspec\',
				sSearchType: \'member\',
				bItemList: false
			});
		// ]]></script>';
	}

	echo '
	</form>';
}

function template_results()
{
	global $context, $settings, $options, $txt, $scripturl, $message;

	if (isset($context['did_you_mean']) || empty($context['topics']) || !empty($context['search_ignored']))
	{
		echo '
	<div id="search_results">
			<h3 class="catbg">
				', $txt['search_adjust_query'], '
			</h3>
		<div class="roundframe">';

		// Did they make any typos or mistakes, perhaps?
		if (isset($context['did_you_mean']))
			echo '
			<p>', $txt['search_did_you_mean'], ' <a href="', $scripturl, '?action=search2;params=', $context['did_you_mean_params'], '">', $context['did_you_mean'], '</a>.</p>';
			
		if (!empty($context['search_ignored']))
			echo '
			<p>', $txt['search_warning_ignored_word' . (count($context['search_ignored']) == 1 ? '' : 's')], ': ', implode(', ', $context['search_ignored']), '</p>';

		echo '
			<form action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '">
				<dl class="settings">
					<dt class="righttext">
						<strong>', $txt['search_for'], ':</strong>
					</dt>
					<dd>
						<input type="text" name="search"', !empty($context['search_params']['search']) ? ' value="' . $context['search_params']['search'] . '"' : '', ' maxlength="', $context['search_string_limit'], '" size="40" class="input_text" />
					</dd>
				</dl>
				<div class="flow_auto" >
					<input type="submit" name="edit_search" value="', $txt['search_adjust_submit'], '" class="btn" />
					<input type="hidden" name="searchtype" value="', !empty($context['search_params']['searchtype']) ? $context['search_params']['searchtype'] : 0, '" />
					<input type="hidden" name="userspec" value="', !empty($context['search_params']['userspec']) ? $context['search_params']['userspec'] : '', '" />
					<input type="hidden" name="show_complete" value="', !empty($context['search_params']['show_complete']) ? 1 : 0, '" />
					<input type="hidden" name="subject_only" value="', !empty($context['search_params']['subject_only']) ? 1 : 0, '" />
					<input type="hidden" name="minage" value="', !empty($context['search_params']['minage']) ? $context['search_params']['minage'] : '0', '" />
					<input type="hidden" name="maxage" value="', !empty($context['search_params']['maxage']) ? $context['search_params']['maxage'] : '9999', '" />
					<input type="hidden" name="sort" value="', !empty($context['search_params']['sort']) ? $context['search_params']['sort'] : 'relevance', '" />
				</div>
			</form>
		</div>
	</div><br />';
	}

	if ($context['compact'])
	{
		// Quick moderation set to checkboxes? Oh, how fun :/.
		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
			echo '
	<form action="', $scripturl, '?action=quickmod" method="post" accept-charset="', $context['character_set'], '" name="topicForm">';

	echo '
			<h3 class="catbg">
				<span class="pull-right">';
					if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1)
					echo '
							<input type="checkbox" onclick="invertAll(this, this.form, \'topics[]\');" class="input_check" />';
				echo '
				</span>
				<img src="' . $settings['images_url'] . '/buttons/search.png" alt="?" class="centericon" />&nbsp;', $txt['mlist_search_results'],':&nbsp;',$context['search_params']['search'],'
			</h3>';

		// was anything even found?
		if (!empty($context['topics']))
		echo'
		<div class="pagination">
			<span>', $context['page_index'], '</span>
		</div>';
		else
			echo '
			<div class="roundframe">', $txt['find_no_results'], '</div>';

		// while we have results to show ...
		while ($topic = $context['get_topics']())
		{
			$color_class = '';
			if ($topic['is_sticky'])
				$color_class = 'stickybg';
			if ($topic['is_locked'])
				$color_class .= 'lockedbg';

			echo '
			<div class="search_results_posts">
			<div class="', $message['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
				<div class="content flow_auto">';

			foreach ($topic['matches'] as $message)
			{
				echo '
					<div class="topic_details pull-left" style="width: 94%">
						<div class="counter">', $message['counter'], '</div>
						<h5><a href="', $scripturl, '?topic=', $topic['id'], '.msg', $message['id'], '#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
						<span class="smalltext">&#171;&nbsp;',$txt['by'],'&nbsp;<strong>', $message['member']['link'], '</strong>&nbsp;',$txt['on'],'&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
					</div>';

				if (!empty($options['display_quick_mod']))
				{
					echo '
					<div class="pull-right">';

					if ($options['display_quick_mod'] == 1)
					{
						echo '
						<input type="checkbox" name="topics[]" value="', $topic['id'], '" class="input_check" />';
					}
					else
					{
						if ($topic['quick_mod']['remove'])
							echo '
						<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=remove;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_remove.png" width="16" alt="', $txt['remove_topic'], '" title="', $txt['remove_topic'], '" /></a>';

						if ($topic['quick_mod']['lock'])
							echo '
						<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=lock;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_lock.png" width="16" alt="', $txt['set_lock'], '" title="', $txt['set_lock'], '" /></a>';

						if ($topic['quick_mod']['lock'] || $topic['quick_mod']['remove'])
							echo '
						<br />';

						if ($topic['quick_mod']['sticky'])
							echo '
						<a href="', $scripturl, '?action=quickmod;actions[', $topic['id'], ']=sticky;', $context['session_var'], '=', $context['session_id'], '" onclick="return confirm(\'', $txt['quickmod_confirm'], '\');"><img src="', $settings['images_url'], '/icons/quick_sticky.png" width="16" alt="', $txt['set_sticky'], '" title="', $txt['set_sticky'], '" /></a>';

						if ($topic['quick_mod']['move'])
							echo '
						<a href="', $scripturl, '?action=movetopic;topic=', $topic['id'], '.0"><img src="', $settings['images_url'], '/icons/quick_move.png" width="16" alt="', $txt['move_topic'], '" title="', $txt['move_topic'], '" /></a>';
					}

					echo '
					</div>';
				}

				if ($message['body_highlighted'] != '')
					echo '
					<br class="clear" />
					<div class="list_posts double_height">', $message['body_highlighted'], '</div>';
			}

			echo '
				</div>
			</div>
		</div>';

		}
		if (!empty($context['topics']))
		echo '
		<div class="pagination">
			<span>', $context['page_index'], '</span>
		</div>';

		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
		{
			echo '
			<div class="titlebg2" style="padding: 4px;">
				<div class="pull-right flow_auto">
					<select class="qaction" name="qaction"', $context['can_move'] ? ' onchange="this.form.move_to.disabled = (this.options[this.selectedIndex].value != \'move\');"' : '', '>
						<option value="">--------</option>';

			foreach ($context['qmod_actions'] as $qmod_action)
				if ($context['can_' . $qmod_action])
					echo '
							<option value="' . $qmod_action . '">' . $txt['quick_mod_'  . $qmod_action] . '</option>';

			echo '
					</select>';

			if ($context['can_move'])
				echo '
				<span id="quick_mod_jump_to">&nbsp;</span>';

			echo '
					<input type="hidden" name="redirect_url" value="', $scripturl . '?action=search2;params=' . $context['params'], '" />
					<input type="submit" value="', $txt['quick_mod_go'], '" onclick="return this.form.qaction.value != \'\' &amp;&amp; confirm(\'', $txt['quickmod_confirm'], '\');" class="btn" style="float: none;font-size: .8em;"/>
				</div>
			</div>';
		}


		if (!empty($options['display_quick_mod']) && $options['display_quick_mod'] == 1 && !empty($context['topics']))
			echo '
			<input type="hidden" name="' . $context['session_var'] . '" value="' . $context['session_id'] . '" />
		</form>';

	}
	else
	{
		echo '
			<h3 class="catbg">
				<img class="centericon" src="' . $settings['images_url'] . '/buttons/search_hd.png" alt="?" />&nbsp;', $txt['mlist_search_results'],':&nbsp;',$context['search_params']['search'],'
			</h3>
		<div class="pagination">
			<span>', $context['page_index'], '</span>
		</div>';

		if (empty($context['topics']))
			echo '
		<div class="information">(', $txt['search_no_results'], ')</div>';

		while ($topic = $context['get_topics']())
		{
			foreach ($topic['matches'] as $message)
			{
				echo '
			<div class="search_results_posts">
				<div class="', $message['alternate'] == 0 ? 'windowbg' : 'windowbg2', ' core_posts">
					<div class="content">
						<div class="counter">', $message['counter'], '</div>
						<div class="topic_details">
							<h5><a href="', $scripturl, '?topic=', $topic['id'], '.', $message['start'], ';topicseen#msg', $message['id'], '">', $message['subject_highlighted'], '</a></h5>
							<span class="smalltext">&#171;&nbsp;', $txt['message'], ' ', $txt['by'], ' <strong>', $message['member']['link'], ' </strong>', $txt['on'], '&nbsp;<em>', $message['time'], '</em>&nbsp;&#187;</span>
						</div>
						<div class="list_posts">', $message['body_highlighted'], '</div>';

				if ($topic['can_reply'] || $topic['can_mark_notify'])
					echo '
						<div class="quickbuttons_wrap">
							<ul class="reset smalltext quickbuttons">';

				// If they *can* reply?
				if ($topic['can_reply'])
					echo '
								<li><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'], '" class="reply_button">', $txt['reply'], '</a></li>';

				// If they *can* quote?
				if ($topic['can_quote'])
					echo '
								<li><a href="', $scripturl . '?action=post;topic=' . $topic['id'] . '.' . $message['start'] . ';quote=' . $message['id'] . '" class="quote_button">', $txt['quote'], '</a></li>';

				// Can we request notification of topics?
				if ($topic['can_mark_notify'])
					echo '
								<li><a href="', $scripturl . '?action=notify;topic=' . $topic['id'] . '.' . $message['start'], '" class="notify_button">', $txt['notify'], '</a></li>';

				if ($topic['can_reply'] || $topic['can_mark_notify'])
					echo '
							</ul>
						</div>';
				echo '
						<br class="clear" />
					</div>
				</div>
			</div>';
			}
		}

		echo '
		<div class="pagination">
			<span>', $context['page_index'], '</span>
		</div>';
	}

}

function template_json_results()
{
	global $context;

	$results = array();

	while ($topic = $context['get_topics']())
	{
		foreach ($topic['matches'] as $message)
		{
			$results[] = array(
				'id' => $topic['id'],
				'subject' => $message['subject'],
			);
		}
	}
	echo json_encode($results);
}

?>