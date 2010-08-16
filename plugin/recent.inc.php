<?php
// $Id: recent.inc.php,v 1.25.4 2010/04/18 17:40:00 jo1upk Exp $
// Copyright (C)
//   2010      PukiPlus Team
//   2005-2008 PukiWiki Plus! Team
//   2002-2007 PukiWiki Developers Team
//   2002      Y.MASUI http://masui.net/pukiwiki/ masui@masui.net
// License: GPL version 2
//
// Recent plugin -- Show RecentChanges list
//   * Usually used at 'MenuBar' page
//   * Also used at special-page, without no #recnet at 'MenuBar'

// Default number of 'Show latest N changes'
define('PLUGIN_RECENT_DEFAULT_LINES', 10);

// Limit number of executions
define('PLUGIN_RECENT_EXEC_LIMIT', 3); // N times per one output

// ----

define('PLUGIN_RECENT_USAGE', '#recent(number-to-show)');

// Place of the cache of 'RecentChanges'
define('PLUGIN_RECENT_CACHE', CACHE_DIR . PKWK_MAXSHOW_CACHE);

function plugin_recent_convert()
{
	global $vars, $date_format, $show_passage, $page_title; // , $_recent_plugin_frame;
	static $exec_count = 1;

	$recent_lines = PLUGIN_RECENT_DEFAULT_LINES;
	if (func_num_args()) {
		$args = func_get_args();
		if (! is_numeric($args[0]) || isset($args[1])) {
			return PLUGIN_RECENT_USAGE . '<br />';
		} else {
			$recent_lines = $args[0];
		}
	}

	// Show only N times
	if ($exec_count > PLUGIN_RECENT_EXEC_LIMIT) {
		return '#recent(): You called me too much' . '<br />' . "\n";
	} else {
		++$exec_count;
	}

	if (! file_exists(PLUGIN_RECENT_CACHE)) {
		return '#recent(): Cache file of RecentChanges not found' . '<br />';
	}

	// Get latest N changes
	$lines = file_head(PLUGIN_RECENT_CACHE, $recent_lines);
	if ($lines == FALSE) return '#recent(): File can not open' . '<br />' . "\n";

	$_recent_title = sprintf(_('recent(%d)'),count($lines));
	$_recent_plugin_frame = '<h5>'.$_recent_title.'</h5>'.
				'<div class="hslice" id="webslice">'.
				'<span class="entry-title" style="display:none;">'.$page_title.'</span>'.
				'<div class="entry-content">';

	$auth_key = auth::get_user_info();
	$date = $items = '';
	foreach ($lines as $line) {
		list($time, $page) = explode("\t", rtrim($line));
		if (! auth::is_page_readable($page,$auth_key['key'],$auth_key['group'])) continue;

		$_date = get_date($date_format, $time);
		if ($date != $_date) {
			// End of the day
			if ($date != '') $items .= '</ul>' . "\n";

			// New day
			$date = $_date;
			$items .= '<strong>' . $date . '</strong>' . "\n" .
				'<ul class="recent_list">' . "\n";
		}

		$s_page = htmlspecialchars($page);

		if($page === $vars['page']) {
			// No need to link to the page you just read, or notify where you just read
			$items .= ' <li>' . $s_page . '</li>' . "\n";
		} else {
			$passage = $show_passage ? ' ' . get_passage($time) : '';
			$items .= ' <li><a href="' . get_page_uri($page) . '"' . 
				' title="' . $s_page . $passage . '">' . $s_page . '</a></li>' . "\n";
		}
	}
	// End of the day
	if ($date != '') $items .= '</ul>' . "\n";
	return $_recent_plugin_frame.$items.'</div></div>';
}
?>
