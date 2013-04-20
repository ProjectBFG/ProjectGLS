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

/**
 * Before showing users a registration form, show them the registration agreement.
 */
function template_registration_agreement()
{
	global $context, $scripturl, $txt;

	echo '
		<form action="', $scripturl, '?action=register" method="post" accept-charset="', $context['character_set'], '" id="registration">
				<h3 class="catbg">', $txt['registration_agreement'], '</h3>
			<div class="roundframe">
				<p>', $context['agreement'], '</p>
			</div>
			<div id="confirm_buttons">
                            <input type="submit" name="accept_agreement" value="', $txt['agreement_agree'], '" class="btn" />
                            <input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
                            <input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '" />
			</div>
			<input type="hidden" name="step" value="1" />
		</form>';

}

// Before registering - get their information.
function template_registration_form()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	echo '
		<script src="', $settings['default_theme_url'], '/scripts/register.js"></script>
		<script><!-- // --><![CDATA[
			function verifyAgree()
			{
				if (currentAuthMethod == \'passwd\' && document.forms.registration.smf_autov_pwmain.value != document.forms.registration.smf_autov_pwverify.value)
				{
					alert("', $txt['register_passwords_differ_js'], '");
					return false;
				}

				return true;
			}

			var currentAuthMethod = \'passwd\';
		// ]]></script>';

	// Any errors?
	if (!empty($context['registration_errors']))
	{
		echo '
		<div class="errorbox">
			<span>', $txt['registration_errors_occurred'], '</span>
			<ul class="reset">';

		// Cycle through each error and display an error message.
		foreach ($context['registration_errors'] as $error)
				echo '
				<li>', $error, '</li>';

		echo '
			</ul>
		</div>';
	}

	echo '
		<form action="', $scripturl, '?action=register2" method="post" accept-charset="', $context['character_set'], '" name="registration" id="registration" onsubmit="return verifyAgree();">
				<h3 class="catbg">', $txt['registration_form'], '</h3>
			<div class="title_bar">
				<h4 class="titlebg">', $txt['required_info'], '</h4>
			</div>
			<div class="windowbg2">
				<fieldset class="content">
					<dl class="register_form">
						<dt><strong><label for="smf_autov_username">', $txt['username'], ':</label></strong></dt>
						<dd>
							<input type="text" name="user" id="smf_autov_username" size="30" tabindex="', $context['tabindex']++, '" maxlength="25" value="', isset($context['username']) ? $context['username'] : '', '" class="input_text" />
							<span id="smf_autov_username_div" style="display: none;">
								<a id="smf_autov_username_link" href="#">
									<img id="smf_autov_username_img" src="', $settings['images_url'], '/icons/field_check.png" alt="*" />
								</a>
							</span>
						</dd>
						<dt><strong><label for="smf_autov_reserve1">', $txt['user_email_address'], ':</label></strong></dt>
						<dd>
							<input type="text" name="email" id="smf_autov_reserve1" size="30" tabindex="', $context['tabindex']++, '" value="', isset($context['email']) ? $context['email'] : '', '" class="input_text" />
						</dd>
						<dt><strong><label for="allow_email">', $txt['allow_user_email'], ':</label></strong></dt>
						<dd>
							<input type="checkbox" name="allow_email" id="allow_email" tabindex="', $context['tabindex']++, '" class="input_check" />
						</dd>
					</dl>';

	echo '
					<dl class="register_form" id="password1_group">
						<dt><strong><label for="smf_autov_pwmain">', ucwords($txt['choose_pass']), ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd1" id="smf_autov_pwmain" size="30" tabindex="', $context['tabindex']++, '" class="input_password" />
							<span id="smf_autov_pwmain_div" style="display: none;">
								<img id="smf_autov_pwmain_img" src="', $settings['images_url'], '/icons/field_invalid.png" alt="*" />
							</span>
						</dd>
					</dl>
					<dl class="register_form" id="password2_group">
						<dt><strong><label for="smf_autov_pwverify">', ucwords($txt['verify_pass']), ':</label></strong></dt>
						<dd>
							<input type="password" name="passwrd2" id="smf_autov_pwverify" size="30" tabindex="', $context['tabindex']++, '" class="input_password" />
							<span id="smf_autov_pwverify_div" style="display: none;">
								<img id="smf_autov_pwverify_img" src="', $settings['images_url'], '/icons/field_valid.png" alt="*" />
							</span>
						</dd>
					</dl>';

	// If there is any field marked as required, show it here!
	if (!empty($context['custom_fields_required']) && !empty($context['custom_fields']))
	{
		echo '

					<dl class="register_form">';

		foreach ($context['custom_fields'] as $field)
			if ($field['show_reg'] > 1)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' style="color: red;"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', preg_replace_callback('~<(input|select|textarea) ~', create_function('$matches', '
							global $context;
							return \'<\' . $matches[1] . \' tabindex="\' . $context[\'tabindex\']++ . \'"\';
						')
					, $field['input_html']), '</dd>';

		echo '
					</dl>';
	}

	echo '
				</fieldset>
			</div>';

	// If we have either of these, show the extra group.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
	{
		echo '
			<div class="title_bar">
				<h4 class="titlebg">', $txt['additional_information'], '</h4>
			</div>
			<div class="windowbg2">
				<fieldset class="content">
					<dl class="register_form" id="custom_group">';
	}

	if (!empty($context['profile_fields']))
	{
		// Any fields we particularly want?
		foreach ($context['profile_fields'] as $key => $field)
		{
			if ($field['type'] == 'callback')
			{
				if (isset($field['callback_func']) && function_exists('template_profile_' . $field['callback_func']))
				{
					$callback_func = 'template_profile_' . $field['callback_func'];
					$callback_func();
				}
			}
			else
			{
					echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' style="color: red;"' : '', '>', $field['label'], ':</strong>';

				// Does it have any subtext to show?
				if (!empty($field['subtext']))
					echo '
							<span class="smalltext">', $field['subtext'], '</span>';

				echo '
						</dt>
						<dd>';

				// Want to put something infront of the box?
				if (!empty($field['preinput']))
					echo '
							', $field['preinput'];

				// What type of data are we showing?
				if ($field['type'] == 'label')
					echo '
							', $field['value'];

				// Maybe it's a text box - very likely!
				elseif (in_array($field['type'], array('int', 'float', 'text', 'password')))
					echo '
							<input type="', $field['type'] == 'password' ? 'password' : 'text', '" name="', $key, '" id="', $key, '" size="', empty($field['size']) ? 30 : $field['size'], '" value="', $field['value'], '" tabindex="', $context['tabindex']++, '" ', $field['input_attr'], ' class="input_', $field['type'] == 'password' ? 'password' : 'text', '" />';

				// You "checking" me out? ;)
				elseif ($field['type'] == 'check')
					echo '
							<input type="hidden" name="', $key, '" value="0" /><input type="checkbox" name="', $key, '" id="', $key, '" ', !empty($field['value']) ? ' checked="checked"' : '', ' value="1" tabindex="', $context['tabindex']++, '" class="input_check" ', $field['input_attr'], ' />';

				// Always fun - select boxes!
				elseif ($field['type'] == 'select')
				{
					echo '
							<select name="', $key, '" id="', $key, '" tabindex="', $context['tabindex']++, '">';

					if (isset($field['options']))
					{
						// Is this some code to generate the options?
						if (!is_array($field['options']))
							$field['options'] = eval($field['options']);
						// Assuming we now have some!
						if (is_array($field['options']))
							foreach ($field['options'] as $value => $name)
								echo '
								<option value="', $value, '" ', $value == $field['value'] ? 'selected="selected"' : '', '>', $name, '</option>';
					}

					echo '
							</select>';
				}

				// Something to end with?
				if (!empty($field['postinput']))
					echo '
							', $field['postinput'];

				echo '
						</dd>';
			}
		}
	}

	// Are there any custom fields?
	if (!empty($context['custom_fields']))
	{
		foreach ($context['custom_fields'] as $field)
		{
			if ($field['show_reg'] < 2)
				echo '
						<dt>
							<strong', !empty($field['is_error']) ? ' style="color: red;"' : '', '>', $field['name'], ':</strong>
							<span class="smalltext">', $field['desc'], '</span>
						</dt>
						<dd>', $field['input_html'], '</dd>';
		}
	}

	// If we have either of these, close the list like a proper gent.
	if (!empty($context['profile_fields']) || !empty($context['custom_fields']))
	{
		echo '
					</dl>
				</fieldset>
			</div>';
	}

	if ($context['visual_verification'])
	{
		echo '
			<div class="title_bar">
				<h4 class="titlebg">', $txt['verification'], '</h4>
			</div>
			<div class="windowbg2">
				<fieldset class="content centertext">
					', template_control_verification($context['visual_verification_id'], 'all'), '
				</fieldset>
			</div>';
	}

	echo '
			<div id="confirm_buttons flow_auto">
                            <input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="btn" />
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['register_token_var'], '" value="', $context['register_token'], '" />
			<input type="hidden" name="step" value="2" />
		</form>
		<script><!-- // --><![CDATA[
			var regTextStrings = {
				"username_valid": "', $txt['registration_username_available'], '",
				"username_invalid": "', $txt['registration_username_unavailable'], '",
				"username_check": "', $txt['registration_username_check'], '",
				"password_short": "', $txt['registration_password_short'], '",
				"password_reserved": "', $txt['registration_password_reserved'], '",
				"password_numbercase": "', $txt['registration_password_numbercase'], '",
				"password_no_match": "', $txt['registration_password_no_match'], '",
				"password_valid": "', $txt['registration_password_valid'], '"
			};
			var verificationHandle = new smfRegister("registration", ', empty($modSettings['password_strength']) ? 0 : $modSettings['password_strength'], ', regTextStrings);
			// Update the authentication status.
			updateAuthMethod();
		// ]]></script>';
}

// After registration... all done ;).
function template_after()
{
	global $context;

	// Not much to see here, just a quick... "you're now registered!" or what have you.
	echo '
		<div id="registration_success">
				<h3 class="catbg">', $context['title'], '</h3>
			<div class="windowbg">
				<p class="content">', $context['description'], '</p>
			</div>
		</div>';
}

// Show a window containing the spoken verification code.
function template_verification_sound()
{
	global $context, $settings, $txt;

	echo '<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
		<title>', $txt['visual_verification_sound'], '</title>
		<meta name="robots" content="noindex" />
		<link rel="stylesheet" type="text/css" href="', $settings['theme_url'], '/css/index', $context['theme_variant'], '.css?alp21" />
		<style type="text/css">';

	// Just show the help text and a "close window" link.
	echo '
		</style>
	</head>
	<body style="margin: 1ex;">
		<div class="windowbg description" style="text-align: center;">';
	if (isBrowser('is_ie'))
		echo '
			<object classid="clsid:22D6F312-B0F6-11D0-94AB-0080C74C7E95" type="audio/x-wav">
				<param name="AutoStart" value="1" />
				<param name="FileName" value="', $context['verification_sound_href'], '" />
			</object>';
	else
		echo '
			<object type="audio/x-wav" data="', $context['verification_sound_href'], '">
				<a href="', $context['verification_sound_href'], '" rel="nofollow">', $context['verification_sound_href'], '</a>
			</object>';
	echo '
		<br />
		<a href="', $context['verification_sound_href'], ';sound" rel="nofollow">', $txt['visual_verification_sound_again'], '</a><br />
		<a href="', $context['verification_sound_href'], '" rel="nofollow">', $txt['visual_verification_sound_direct'], '</a><br /><br />
		<a href="javascript:self.close();">', $txt['visual_verification_sound_close'], '</a><br />
		</div>
	</body>
</html>';
}

function template_admin_register()
{
	global $context, $scripturl, $txt, $modSettings;

	echo '
	<div id="admincenter">
		<div id="admin_form_wrapper">
			<form id="postForm" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', $context['character_set'], '" name="postForm">
				<h3 class="catbg">', $txt['admin_browse_register_new'], '</h3>
				<div class="well content" id="register_screen">';

	if (!empty($context['registration_done']))
		echo '
					<div class="alert alert-success">
						', $context['registration_done'], '
					</div>';

	echo '
					<dl class="register_form" id="admin_register_form">
						<dt>
							<strong><label for="user_input">', $txt['admin_register_username'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_username_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="user" id="user_input" tabindex="', $context['tabindex']++, '" size="30" maxlength="25" class="input_text" />
						</dd>
						<dt>
							<strong><label for="email_input">', $txt['admin_register_email'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_email_desc'], '</span>
						</dt>
						<dd>
							<input type="text" name="email" id="email_input" tabindex="', $context['tabindex']++, '" size="30" class="input_text" />
						</dd>
						<dt>
							<strong><label for="password_input">', $txt['admin_register_password'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_password_desc'], '</span>
						</dt>
						<dd>
							<input type="password" name="password" id="password_input" tabindex="', $context['tabindex']++, '" size="30" class="input_password" onchange="onCheckChange();" />
						</dd>';

	if (!empty($context['member_groups']))
	{
		echo '
						<dt>
							<strong><label for="group_select">', $txt['admin_register_group'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_group_desc'], '</span>
						</dt>
						<dd>
							<select name="group" id="group_select" tabindex="', $context['tabindex']++, '">';

		foreach ($context['member_groups'] as $id => $name)
			echo '
								<option value="', $id, '">', $name, '</option>';

		echo '
							</select>
						</dd>';
	}

	echo '
						<dt>
							<strong><label for="emailPassword_check">', $txt['admin_register_email_detail'], ':</label></strong>
							<span class="smalltext">', $txt['admin_register_email_detail_desc'], '</span>
						</dt>
						<dd>
							<input type="checkbox" name="emailPassword" id="emailPassword_check" tabindex="', $context['tabindex']++, '" checked="checked" disabled="disabled" class="input_check" />
						</dd>
						<dt>
							<strong><label for="emailActivate_check">', $txt['admin_register_email_activate'], ':</label></strong>
						</dt>
						<dd>
							<input type="checkbox" name="emailActivate" id="emailActivate_check" tabindex="', $context['tabindex']++, '"', !empty($modSettings['registration_method']) && $modSettings['registration_method'] == 1 ? ' checked="checked"' : '', ' onclick="onCheckChange();" class="input_check" />
						</dd>
					</dl>
					<div class="flow_auto">
						<input type="submit" name="regSubmit" value="', $txt['register'], '" tabindex="', $context['tabindex']++, '" class="btn" />
						<input type="hidden" name="sa" value="register" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-regc_token_var'], '" value="', $context['admin-regc_token'], '" />
					</div>
				</div>
			</form>
		</div>
	</div>
	<br class="clear" />';
}

// Form for editing the agreement shown for people registering to the forum.
function template_edit_agreement()
{
	global $context, $scripturl, $txt;

	// Just a big box to edit the text file ;).
	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', $context['character_set'], '">
				<h3 class="catbg">', $txt['registration_agreement'], '</h3>';

	// Warning for if the file isn't writable.
	if (!empty($context['warning']))
		echo '
			<p class="error">', $context['warning'], '</p>';

	echo '
			<div class="windowbg2" id="registration_agreement">
				<div class="content">';

	// Is there more than one language to choose from?
	if (count($context['editable_agreements']) > 1)
	{
		echo '
						<h3 class="catbg">', $txt['language_configuration'], '</h3>
					<div class="information">
						<form action="', $scripturl, '?action=admin;area=regcenter" id="change_reg" method="post" accept-charset="', $context['character_set'], '" style="display: inline;">
							<strong>', $txt['admin_agreement_select_language'], ':</strong>&nbsp;
							<select name="agree_lang" onchange="document.getElementById(\'change_reg\').submit();" tabindex="', $context['tabindex']++, '">';

		foreach ($context['editable_agreements'] as $file => $name)
			echo '
								<option value="', $file, '" ', $context['current_agreement'] == $file ? 'selected="selected"' : '', '>', $name, '</option>';

		echo '
							</select>
							<div class="righttext">
								<input type="hidden" name="sa" value="agreement" />
								<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
								<input type="submit" name="change" value="', $txt['admin_agreement_select_language_change'], '" tabindex="', $context['tabindex']++, '" class="btn" />
							</div>
						</form>
					</div>';
	}



	// Show the actual agreement in an oversized text box.
	echo '
					<p class="agreement">
						<textarea cols="70" rows="20" name="agreement" id="agreement">', $context['agreement'], '</textarea>
					</p>
					<p>
						<label for="requireAgreement"><input type="checkbox" name="requireAgreement" id="requireAgreement"', $context['require_agreement'] ? ' checked="checked"' : '', ' tabindex="', $context['tabindex']++, '" value="1" class="input_check" /> ', $txt['admin_agreement'], '.</label>
					</p>
					<div class="flow_auto" >
						<input type="submit" value="', $txt['save'], '" tabindex="', $context['tabindex']++, '" class="btn" />
						<input type="hidden" name="agree_lang" value="', $context['current_agreement'], '" />
						<input type="hidden" name="sa" value="agreement" />
						<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
						<input type="hidden" name="', $context['admin-rega_token_var'], '" value="', $context['admin-rega_token'], '" />
					</div>
				</div>
			</div>
		</form>';
}

function template_edit_reserved_words()
{
	global $context, $scripturl, $txt;

	echo '
		<form id="admin_form_wrapper" action="', $scripturl, '?action=admin;area=regcenter" method="post" accept-charset="', $context['character_set'], '">
			<h3 class="catbg">', $txt['admin_reserved_set'], '</h3>
			<div class="well content">
				<h3 class="titlebg">', $txt['admin_reserved_line'], '</h3>
				<p class="reserved_names">
					<textarea cols="30" rows="6" name="reserved" id="reserved">', implode("\n", $context['reserved_words']), '</textarea>
				</p>
				<dl class="settings">
					<dt>
						<label for="matchword">', $txt['admin_match_whole'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchword" id="matchword" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_word'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						<label for="matchcase">', $txt['admin_match_case'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchcase" id="matchcase" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_case'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						<label for="matchuser">', $txt['admin_check_user'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchuser" id="matchuser" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_user'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
					<dt>
						<label for="matchname">', $txt['admin_check_display'], '</label>
					</dt>
					<dd>
						<input type="checkbox" name="matchname" id="matchname" tabindex="', $context['tabindex']++, '" ', $context['reserved_word_options']['match_name'] ? 'checked="checked"' : '', ' class="input_check" />
					</dd>
				</dl>
				<div class="flow_auto" >
					<input type="submit" value="', $txt['save'], '" name="save_reserved_names" tabindex="', $context['tabindex']++, '" style="margin: 1ex;" class="btn" />
					<input type="hidden" name="sa" value="reservednames" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-regr_token_var'], '" value="', $context['admin-regr_token'], '" />
				</div>
			</div>
		</form>';
}

?>