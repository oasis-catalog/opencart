<?php
/*
 * Oasiscatalog OC3 CLI - v1.0
 * Require OpenCart 3.x
 */

define('OPENCART_ADMIN_DIR', '');
define('MIN_VERSION', '3.0.3.7');

$root_dir = realpath(str_replace(['oasis_cli.php', 'cli'], ['', ''], dirname(__FILE__)));

// Admin directory
$admin_dir = '';
if (file_exists($root_dir . '/admin/config.php')) {
	$admin_dir = $root_dir . '/admin';
} else {
	foreach (new DirectoryIterator($root_dir) as $dir_info) {
		if (!$dir_info->isDot() && $dir_info->isDir()) {
			$path = $dir_info->getPathname();
			if (file_exists($path . '/config.php')) {
				$admin_dir = $path;
				break;
			}
		}
	}
}

if (!$admin_dir) {
	if (file_exists(OPENCART_ADMIN_DIR . '/config.php')) {
		$admin_dir = OPENCART_ADMIN_DIR;
	}
}

if (!$admin_dir) {
	die("ERROR: cli cannot access to config.php");
}

// Config file
require_once ($admin_dir . '/config.php');

// Get VERSION
$content = file_get_contents($admin_dir . '/index.php');
preg_match("/define\('VERSION', '([0-9\.]+)/i", $content, $matches);

if (!isset($matches[1])) {
	die("ERROR: cli cannot get index.php");
} else {
	$version = $matches[1];
}

$version = substr($version, 0, 7);

if ((int)str_replace('.', '', $version) < (int)str_replace('.', '', MIN_VERSION)) {
	die('Для работы скрипта необходима минимальная версия OpenCart ' . MIN_VERSION . ', текущая версия OpenCart ' . $version);
}

// Startup
$version = substr($version, 0, 5);
if (file_exists($root_dir . '/cli/' . $version . '/oasis_cli_framework.php')) {
	require_once($root_dir . '/cli/' . $version . '/oasis_cli_framework.php');
} else {
	die("ERROR: cli error startup oasis_cli_framework");
}
?>
