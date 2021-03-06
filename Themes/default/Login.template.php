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

// This is just the basic "login" form.
function template_login()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	echo '
		<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
		<div class="panel panel-sd">
			<div class="panel-heading">
				<h3 class="panel-title">
					<span class="glyphicon glyphicon-log-in"></span> ', $txt['login'], '
				</h3>
			</div>
			<div class="panel-body">
				<form action="', $scripturl, '?action=login2" name="frmLogin" id="frmLogin" method="post" accept-charset="', $context['character_set'], '" ', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', ' class="form-horizontal" role="form">';

	// Did they make a mistake last time?
	if (!empty($context['login_errors']))
		echo '
					<div class="alert alert-danger">', implode('<br />', $context['login_errors']), '</div><br />';

	// Or perhaps there's some special description for this time?
	if (isset($context['description']))
		echo '
					<div class="alert alert-info">', $context['description'], '</div>';

	// Now just get the basic information - username, password, etc.
	echo '
					<div class="form-group">
						<label class="col-sm-2 control-label" for="user">', $txt['username'], ':</label>
						<div class="col-sm-10">
							<input type="text" name="user" id="user" size="20" value="', $context['default_username'], '" class="form-control">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="passwrd">', $txt['password'], ':</label>
						<div class="col-sm-10">
							<input type="password" name="passwrd" id="passwrd" value="', $context['default_password'], '" class="form-control">
						</div>
					</div>
					<div class="form-group">
						<label class="col-sm-2 control-label" for="cookielength">', $txt['mins_logged_in'], ':</label>
						<div class="col-sm-2 ">
							<input type="text" name="cookielength" id="cookielength" value="', $modSettings['cookieTime'], '"', $context['never_expire'] ? ' disabled="disabled"' : '', ' class="form-control">
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<div class="checkbox">
								<label><input type="checkbox" name="cookieneverexp" id="cookieneverexp"', $context['never_expire'] ? ' checked="checked"' : '', ' onclick="this.form.cookielength.disabled = this.checked;" /> ', $txt['always_logged_in'], '</label>';
	// If they have deleted their account, give them a chance to change their mind.
	if (isset($context['login_show_undelete']))
		echo '
								<label class="checkbox"><input type="checkbox" name="undelete" /> <span class="alert-error">', $txt['undelete_account'], '</span></label>';
	echo '
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-offset-2 col-sm-10">
							<input type="submit" value="', $txt['login'], '" class="btn btn-primary" />
							<a href="', $scripturl, '?action=reminder" class="btn btn-link">', $txt['forgot_your_password'], '</a>
						</div>
					</div>
					<input type="hidden" name="hash_passwrd" value="" />
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
		</form>
				</div>
			</div>';

	// Focus on the correct input - username or password.
	echo '
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.', isset($context['default_username']) && $context['default_username'] != '' ? 'passwrd' : 'user', '.focus();
		// ]]></script>';
}

// Tell a guest to get lost or login!
function template_kick_guest()
{
	global $context, $settings, $scripturl, $modSettings, $txt;

	// This isn't that much... just like normal login but with a message at the top.
	echo '
	<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
	<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
		<div class="tborder login">
				<h3 class="catbg">', $txt['warning'], '</h3>';

	// Show the message or default message.
	echo '
			<p class="information centertext">
				', empty($context['kick_message']) ? $txt['only_members_can_access'] : $context['kick_message'], '<br />';


	if ($context['can_register'])
		echo sprintf($txt['login_below_or_register'], $scripturl . '?action=register', $context['forum_name_html_safe']);
	else
		echo $txt['login_below'];

	// And now the login information.
	echo '
				<h3 class="catbg">
					<img src="', $settings['images_url'], '/icons/login_hd.png" alt="" class="icon" /> ', $txt['login'], '
				</h3>
			<div class="roundframe">
				<dl>
					<dt>', $txt['username'], ':</dt>
					<dd><input type="text" name="user" size="20" class="input_text" /></dd>
					<dt>', $txt['password'], ':</dt>
					<dd><input type="password" name="passwrd" size="20" class="input_password" /></dd>
					<dt>', $txt['mins_logged_in'], ':</dt>
					<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" /></dd>
					<dt>', $txt['always_logged_in'], ':</dt>
					<dd><input type="checkbox" name="cookieneverexp" class="input_check" onclick="this.form.cookielength.disabled = this.checked;" /></dd>
				</dl>
				<p class="centertext"><input type="submit" value="', $txt['login'], '" class="btn" /></p>
				<p class="centertext smalltext"><a href="', $scripturl, '?action=reminder">', $txt['forgot_your_password'], '</a></p>
			</div>
			<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
			<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
			<input type="hidden" name="hash_passwrd" value="" />
		</div>
	</form>';

	// Do the focus thing...
	echo '
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.user.focus();
		// ]]></script>';
}

// This is for maintenance mode.
function template_maintenance()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Display the administrator's message at the top.
	echo '
<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
<form action="', $scripturl, '?action=login2" method="post" accept-charset="', $context['character_set'], '"', empty($context['disable_login_hashing']) ? ' onsubmit="hashLoginPassword(this, \'' . $context['session_id'] . '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');"' : '', '>
	<div class="tborder login" id="maintenance_mode">
			<h3 class="catbg">', $context['title'], '</h3>
		<p class="description">
			<img class="pull-left" src="', $settings['images_url'], '/construction.png" width="40" height="40" alt="', $txt['in_maintain_mode'], '" />
			', $context['description'], '<br class="clear" />
		</p>
		<div class="title_bar">
			<h4 class="titlebg">', $txt['admin_login'], '</h4>
		</div>
		<div class="roundframe">
			<dl>
				<dt>', $txt['username'], ':</dt>
				<dd><input type="text" name="user" size="20" class="input_text" /></dd>
				<dt>', $txt['password'], ':</dt>
				<dd><input type="password" name="passwrd" size="20" class="input_password" /></dd>
				<dt>', $txt['mins_logged_in'], ':</dt>
				<dd><input type="text" name="cookielength" size="4" maxlength="4" value="', $modSettings['cookieTime'], '" class="input_text" /></dd>
				<dt>', $txt['always_logged_in'], ':</dt>
				<dd><input type="checkbox" name="cookieneverexp" class="input_check" /></dd>
			</dl>
			<input type="submit" value="', $txt['login'], '" class="btn" />
			<br class="clear" />
		</div>
		<input type="hidden" name="hash_passwrd" value="" />
		<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
		<input type="hidden" name="', $context['login_token_var'], '" value="', $context['login_token'], '" />
	</div>
</form>';
}

// This is for the security stuff - makes administrators login every so often.
function template_admin_login()
{
	global $context, $settings, $scripturl, $txt;

	// Since this should redirect to whatever they were doing, send all the get data.
	echo '
		<script src="', $settings['default_theme_url'], '/scripts/sha1.js"></script>
	
		<div class="panel panel-sd">
			<div class="panel-heading">', $txt['login'], '</div>
			<div class="panel-body">
				<form action="', $scripturl, $context['get_data'], '" method="post" accept-charset="', $context['character_set'], '" name="frmLogin" id="frmLogin" class="form-inline" onsubmit="hash', ucfirst($context['sessionCheckType']), 'Password(this, \'', $context['user']['username'], '\', \'', $context['session_id'], '\', \'' . (!empty($context['login_token']) ? $context['login_token'] : '') . '\');" role="form">';

	if (!empty($context['incorrect_password']))
		echo '
					<div class="error">', $txt['admin_incorrect_password'], '</div>';

	echo '
					<div class="form-group">
						<label class="sr-only" for="password">', $txt['password'], '</label>
						<input type="password" name="', $context['sessionCheckType'], '_pass" size="24" id="password" placeholder="', $txt['password'], '" class="form-control">
					</div>
					<input type="hidden" name="', $context['session_var'], '" value="', $context['session_id'], '" />
					<input type="hidden" name="', $context['admin-login_token_var'], '" value="', $context['admin-login_token'], '" />
					<input type="submit" value="', $txt['login'], '" class="btn btn-default" />';

	// Make sure to output all the old post data.
	echo $context['post_data'], '
					<input type="hidden" name="', $context['sessionCheckType'], '_hash_pass" value="" />
				</form>
			</div>
		</div>';

	// Focus on the password box.
	echo '
		<script><!-- // --><![CDATA[
			document.forms.frmLogin.', $context['sessionCheckType'], '_pass.focus();
		// ]]></script>';
}

// Activate your account manually?
function template_retry_activate()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;u=', $context['member_id'], '" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<div class="roundframe">';

	// You didn't even have an ID?
	if (empty($context['member_id']))
		echo '
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="30" class="input_text" /></dd>';

	echo '
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30" class="input_text" /></dd>
				</dl>
				<p><input type="submit" value="', $txt['invalid_activation_submit'], '" class="btn" /></p>
			</div>
		</form>';
}

// Activate your account manually?
function template_resend()
{
	global $context, $txt, $scripturl;

	// Just ask them for their code so they can try it again...
	echo '
		<form action="', $scripturl, '?action=activate;sa=resend" method="post" accept-charset="', $context['character_set'], '">
			<div class="title_bar">
				<h3 class="titlebg">', $context['page_title'], '</h3>
			</div>
			<div class="roundframe">
				<dl>
					<dt>', $txt['invalid_activation_username'], ':</dt>
					<dd><input type="text" name="user" size="40" value="', $context['default_username'], '" class="input_text" /></dd>
				</dl>
				<p>', $txt['invalid_activation_new'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_new_email'], ':</dt>
					<dd><input type="text" name="new_email" size="40" class="input_text" /></dd>
					<dt>', $txt['invalid_activation_password'], ':</dt>
					<dd><input type="password" name="passwd" size="30" class="input_password" /></dd>
				</dl>';

	if ($context['can_activate'])
		echo '
				<p>', $txt['invalid_activation_known'], '</p>
				<dl>
					<dt>', $txt['invalid_activation_retry'], ':</dt>
					<dd><input type="text" name="code" size="30" class="input_text" /></dd>
				</dl>';

	echo '
				<p><input type="submit" value="', $txt['invalid_activation_resend'], '" class="btn" /></p>
			</div>
		</form>';
}

?>