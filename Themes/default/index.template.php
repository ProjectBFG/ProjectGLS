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

/*	This template is, perhaps, the most important template in the theme. It
	contains the main template layer that displays the header and footer of
	the forum, namely with main_above and main_below. It also contains the
	menu sub template, which appropriately displays the menu; the init sub
	template, which is there to set the theme up; (init can be missing.).

	The init sub template should load any data and set any hardcoded options.

	The main_above sub template is what is shown above the main content, and
	should contain anything that should be shown up there.

	The main_below sub template, conversely, is shown after the main content.
	It should probably contain the copyright statement and some other things.

	The menu sub template should display all the relevant buttons the user
	wants and or needs.

	For more information on the templating system, please see the site at:
	http://www.simplemachines.org/
*/

/**
 * Initialize the template... mainly little settings.
 */
function template_init()
{
	global $settings;

	/* Use images from default theme when using templates from the default theme?
		if this is 'always', images from the default theme will be used.
		if this is 'defaults', images from the default theme will only be used with default templates.
		if this is 'never' or isn't set at all, images from the default theme will not be used. */
	$settings['use_default_images'] = 'never';

	/* What document type definition is being used? (for font size and other issues.)
		'xhtml' for an XHTML 1.0 document type definition.
		'html' for an HTML 4.01 document type definition. */
	$settings['doctype'] = 'HTML';

	// The version this template/theme is for. This should probably be the version of SMF it was created for.
	$settings['theme_version'] = '1.0';

	// Set a setting that tells the theme that it can render the tabs.
	$settings['use_tabs'] = true;

	// Use plain buttons - as opposed to text buttons?
	$settings['use_buttons'] = true;

	// Show sticky and lock status separate from topic icons?
	$settings['separate_sticky_lock'] = true;

	// Does this theme use the strict doctype?
	$settings['strict_doctype'] = false;

	// Set the following variable to true if this theme requires the optional theme strings file to be loaded.
	$settings['require_theme_strings'] = false;
}

/**
 * The main sub template above the content.
 */
function template_html_above()
{
	global $context, $settings, $scripturl, $txt, $modSettings;

	// Show right to left and the character set for ease of translating.
	echo '<!DOCTYPE html>
<html lang="en">
<head>';

	// The ?alp21 part of this link is just here to make sure browsers don't cache it wrongly.
	echo '
	<link rel="stylesheet" href="', $settings['theme_url'], '/css/bootstrap.min.css" />
	<link rel="stylesheet" href="', $settings['theme_url'], '/css/index.css?alp21" />
    <!-- <link href="', $settings['theme_url'], '/css/bootstrap-responsive.min.css" rel="stylesheet"> -->';

	// load in any css from mods or themes so they can overwrite if wanted
	template_css();

	// load in any javascript files from mods and themes
	template_javascript();

	echo '
	<meta http-equiv="Content-Type" content="text/html; charset=', $context['character_set'], '" />
	<meta name="description" content="', $context['page_title_html_safe'], '" />', !empty($context['meta_keywords']) ? '
	<meta name="keywords" content="' . $context['meta_keywords'] . '" />' : '', '
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>', $context['page_title_html_safe'], '</title>';

	// Please don't index these Mr Robot.
	if (!empty($context['robot_no_index']))
		echo '
	<meta name="robots" content="noindex" />';

	// Present a canonical url for search engines to prevent duplicate content in their indices.
	if (!empty($context['canonical_url']))
		echo '
	<link rel="canonical" href="', $context['canonical_url'], '" />';

	// Show all the relative links, such as help, search, contents, and the like.
	echo '
	<link rel="help" href="', $scripturl, '?action=help" />
	<link rel="contents" href="', $scripturl, '" />', ($context['allow_search'] ? '
	<link rel="search" href="' . $scripturl . '?action=search" />' : '');

	// If RSS feeds are enabled, advertise the presence of one.
	if (!empty($modSettings['xmlnews_enable']) && (!empty($modSettings['allow_guestAccess']) || $context['user']['is_logged']))
		echo '
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['rss'], '" href="', $scripturl, '?type=rss2;action=.xml" />
	<link rel="alternate" type="application/rss+xml" title="', $context['forum_name_html_safe'], ' - ', $txt['atom'], '" href="', $scripturl, '?type=atom;action=.xml" />';

	// If we're viewing a topic, these should be the previous and next topics, respectively.
	if (!empty($context['links']['next']))
		echo '<link rel="next" href="', $context['links']['next'], '" />';
	else if (!empty($context['current_topic']))
		echo '<link rel="next" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=next" />';
	if (!empty($context['links']['prev']))
		echo '<link rel="prev" href="', $context['links']['prev'], '" />';
	else if (!empty($context['current_topic']))
		echo '<link rel="prev" href="', $scripturl, '?topic=', $context['current_topic'], '.0;prev_next=prev" />';

	// Output any remaining HTML headers. (from mods, maybe?)
	echo $context['html_headers'];

	echo '
</head>
<body id="', $context['browser_body_id'], '" class="action_', !empty($context['current_action']) ? htmlspecialchars($context['current_action']) : '', '">';
}

function template_body_above()
{
	global $context, $scripturl, $txt;

	echo '
	<nav class="navbar navbar-inverse navbar-fixed-top">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-ex1-collapse">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a class="navbar-brand" href="', $scripturl, '">', $context['forum_name'], '</a>
		</div>
		<div class="collapse navbar-collapse navbar-ex1-collapse">
			', template_menu(), '
			<form class="navbar-form navbar-right" action="', $scripturl, '?action=search2" method="post" accept-charset="', $context['character_set'], '" role="search">
				<div class="form-group">
                    <div class="input-group">
					   <input type="text" id="main_search" autocomplete="off" name="search" placeholder="', $txt['search'], '" class="form-control" />
				        <span class="input-group-btn">
                            <button type="submit" class="btn btn-primary"><span class="glyphicon glyphicon-search"></span></button> 
                        </span>
                    </div>
                    <!-- <a href="', $scripturl, '?action=search" class="btn btn-primary"><span class="glyphicon glyphicon-cog"></span></a> -->
                </div>
			</form>
		</div>
	</nav>
	<div class="container">';
}

function template_body_below()
{

	echo '
		<hr />
		<div id="footer">
			', theme_copyright();
}

function template_html_below()
{
	global $settings;

	// load in any javascipt that could be defered to the end of the page
	template_javascript(true);

	echo '
		</div>
	</div>
	<script src="', $settings['theme_url'], '/scripts/bootstrap.min.js"></script>
</body>
</html>';
}

/**
 * Show the menu up top. Something like [home] [help] [profile] [logout]...
 */
function template_menu()
{
	global $context;

	echo '
				<ul class="nav navbar-nav">';

	// Note: Menu markup has been cleaned up to remove unnecessary spans and classes.
	foreach ($context['menu_buttons'] as $act => $button)
	{

		if ($button['active_button'] && empty($button['sub_buttons']))
			$li_class = 'active';
		elseif (!empty($button['sub_buttons']) && !$button['active_button'])
			$li_class = 'dropdown';
		elseif ($button['active_button'] && !empty($button['sub_buttons']))
			$li_class = 'active dropdown';
		else
			$li_class = '';

		echo '
					<li', !empty($li_class) ? ' class="' . $li_class . '"' : '', '>
						<a', !empty($button['sub_buttons']) ? ' class="dropdown-toggle" data-toggle="dropdown"' : '' , ' href="', $button['href'], '" ', isset($button['target']) ? 'target="' . $button['target'] . '"' : '', '>
							', $button['title'], !empty($button['sub_buttons']) ? '
							<b class="caret"></b>' : '' , '
						</a>';

		if (!empty($button['sub_buttons']))
		{
			echo '
						<ul class="dropdown-menu">';

			foreach ($button['sub_buttons'] as $childbutton)
			{
				echo '
							<li>
								<a href="', $childbutton['href'], '"' , isset($childbutton['target']) ? ' target="' . $childbutton['target'] . '"' : '', '>
									', $childbutton['title'], '
								</a>
							</li>';
			}
				echo '
						</ul>';
		}
		echo '
					</li>';
	}

	echo '
				</ul>';
				
}

/**
 * Generate a strip of buttons.
 * @param array $button_strip
 * @param string $direction = ''
 * @param array $strip_options = array()
 */
function template_button_strip($button_strip, $direction = '', $strip_options = array(), $additionnal_class)
{
	global $context, $txt;

	if (!is_array($strip_options))
		$strip_options = array();

	// Create the buttons...
	$buttons = array();
	foreach ($button_strip as $key => $value)
	{
		// @todo this check here doesn't make much sense now (from 2.1 on), it should be moved to where the button array is generated
		// Kept for backward compatibility
		if (!isset($value['test']) || !empty($context[$value['test']]))
			$buttons[] = '
				<a' . (isset($value['id']) ? ' id="button_strip_' . $value['id'] . '"' : '') . ' class="btn btn-default' . (isset($value['active']) ? ' active' : '') . '" href="' . $value['url'] . '"' . (isset($value['custom']) ? ' ' . $value['custom'] : '') . '>' . $txt[$value['text']] . '</a>';
	}

	// No buttons? No button strip either.
	if (empty($buttons))
		return;

	echo '
		<div class="', !empty($additionnal_class) ? $additionnal_class : '', ' btn-group', !empty($direction) ? ' pull-' . $direction : '', '"', (empty($buttons) ? ' style="display: none;"' : ''), (!empty($strip_options['id']) ? ' id="' . $strip_options['id'] . '"': ''), '>',
				implode('', $buttons), '
		</div>';
}

?>