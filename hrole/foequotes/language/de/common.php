<?php
/**
 *
 * Hide Foe Quotes. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2026
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'FOE_QUOTE_HIDDEN' => 'Ein Zitat eines von dir ignorierten Mitglieds wurde ausgeblendet.',
));
