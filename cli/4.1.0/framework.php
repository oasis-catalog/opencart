<?php

require_once(DIR_SYSTEM . 'startup.php');

// Autoloader
$autoloader = new \Opencart\System\Engine\Autoloader();
$autoloader->register('Opencart\\' . APPLICATION, DIR_APPLICATION);
$autoloader->register('Opencart\Extension', DIR_EXTENSION);
$autoloader->register('Opencart\System', DIR_SYSTEM);

require_once(DIR_SYSTEM . 'vendor.php');

// Registry
$registry = new \Opencart\System\Engine\Registry();
$registry->set('autoloader', $autoloader);

// Config
$config = new \Opencart\System\Engine\Config();
$registry->set('config', $config);

// Load the default config
$config->addPath(DIR_CONFIG);
$config->load('default');
$config->load(strtolower(APPLICATION));
$config->set('application', APPLICATION);

// Set the default time zone
date_default_timezone_set($config->get('date_timezone'));

// Store
$config->set('config_store_id', 0);

// Logging
$log = new \Opencart\System\Library\Log($config->get('error_filename'));
$registry->set('log', $log);

// Error Handler
set_error_handler(function(int $code, string $message, string $file, int $line) use ($log, $config) {
	// error suppressed with @
	if (@error_reporting() === 0) {
		return false;
	}

	switch ($code) {
		case E_NOTICE:
		case E_USER_NOTICE:
			$error = 'Notice';
			break;
		case E_WARNING:
		case E_USER_WARNING:
			$error = 'Warning';
			break;
		case E_ERROR:
		case E_USER_ERROR:
			$error = 'Fatal Error';
			break;
		default:
			$error = 'Unknown';
			break;
	}

	if ($config->get('error_log')) {
		$log->write('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
	}

	if ($config->get('error_display')) {
		echo '<b>' . $error . '</b>: ' . $message . ' in <b>' . $file . '</b> on line <b>' . $line . '</b>';
	} else {
		header('Location: ' . $config->get('error_page'));
		exit();
	}

	return true;
});

// Exception Handler
set_exception_handler(function(\Throwable $e) use ($log, $config): void {
	if ($config->get('error_log')) {
		$log->write(get_class($e) . ':  ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
	}

	if ($config->get('error_display')) {
		echo '<b>' . get_class($e) . '</b>: ' . $e->getMessage() . ' in <b>' . $e->getFile() . '</b> on line <b>' . $e->getLine() . '</b>';
	} else {
		header('Location: ' . $config->get('error_page'));
		exit();
	}
});

// Event
$event = new \Opencart\System\Engine\Event($registry);
$registry->set('event', $event);

// Event Register
if ($config->has('action_event')) {
	foreach ($config->get('action_event') as $key => $value) {
		foreach ($value as $priority => $action) {
			$event->register($key, new \Opencart\System\Engine\Action($action), $priority);
		}
	}
}

// Factory
$registry->set('factory', new \Opencart\System\Engine\Factory($registry));

// Loader
$loader = new \Opencart\System\Engine\Loader($registry);
$registry->set('load', $loader);

// Request
$request = new \Opencart\System\Library\Request();
$registry->set('request', $request);

// Response
$response = new \Opencart\System\Library\Response();
$registry->set('response', $response);

// Database
if ($config->get('db_autostart')) {
	$db = new \Opencart\System\Library\DB($config->get('db_engine'), $config->get('db_hostname'), $config->get('db_username'), $config->get('db_password'), $config->get('db_database'), $config->get('db_port'), $config->get('db_ssl_key'), $config->get('db_ssl_cert'), $config->get('db_ssl_ca'));
	$registry->set('db', $db);

	// Sync PHP and DB time zones
	$db->query("SET `time_zone` = '" . $db->escape(date('P')) . "'");
}

// Session
if ($config->get('session_autostart')) {
	$session = new \Opencart\System\Library\Session($config->get('session_engine'), $registry);
	$registry->set('session', $session);

	if (isset($request->cookie[$config->get('session_name')])) {
		$session_id = $request->cookie[$config->get('session_name')];
	} else {
		$session_id = '';
	}

	$session->start($session_id);

	// Require higher security for session cookies
	$option = [
		'expires'  => 0,
		'path'     => $config->get('session_path'),
		'domain'   => $config->get('session_domain'),
		'secure'   => $request->server['HTTPS'],
		'httponly' => false,
		'SameSite' => $config->get('session_samesite')
	];

	setcookie($config->get('session_name'), $session->getId(), $option);
}

// Cache
$registry->set('cache', new \Opencart\System\Library\Cache($config->get('cache_engine'), $config->get('cache_expire')));

// Template
$template = new \Opencart\System\Library\Template($config->get('template_engine'));
$registry->set('template', $template);
$template->addPath(DIR_TEMPLATE);

// Language
$language = new \Opencart\System\Library\Language($config->get('language_code'));
$registry->set('language', $language);
$language->addPath(DIR_LANGUAGE);
$loader->load->language($config->get('language_code'));

// Url
$registry->set('url', new \Opencart\System\Library\Url($config->get('site_url')));

// Pre Actions
foreach ($config->get('action_pre_action') as $pre_action) {
	$loader->controller($pre_action);
}


// Access CLI
$params = [
	'short' => 'k:u',
	'long'  => ['key:', 'oid:', 'up', 'up_image', 'add_image', 'debug', 'debug_log'],
];

// Default values
$errors = '';
$options = getopt($params['short'], $params['long']);

if (isset($options['key']) || isset($options['k'])) {
	$cron_key = $options['key'] ?? $options['k'];
} else {
	$errors = 'key required';
}

if ($errors) {
	$help = "
usage: php /path/to/site/cli/cli.php [-k|--key=secret] [-u|--up]

Options:
-k  --key      substitute your secret key from the Oasis module
-u  --up       specify this key to use the update
--add_image    add image if empty
--up_image     update only image
--debug        show log
--debug_log    wrire log to file
Example import products:
		php /path/to/site/cli/cli.php --key=secret
Example update stock (quantity) products:
		php /path/to/site/cli/cli.php --key=secret --up

Errors: " . $errors . PHP_EOL;
	die($help);
}


require_once(realpath(__DIR__ . '/../../helper/cli.php'));
require_once(realpath(__DIR__ . '/../../helper/api.php'));
require_once(realpath(__DIR__ . '/../../helper/main.php'));
require_once(realpath(__DIR__ . '/../../helper/config.php'));

if(isset($options['up']) || isset($options['u'])){
	$cron_opt = [
		'task' => 'up'
	];
}
else if(isset($options['up_image'])){
	$cron_opt = [
		'task' => 'up_image',
		'oid' => $cliOptions['oid'] ?? ''
	];
}
else if(isset($options['add_image'])){
	$cron_opt = [
		'task' => 'add_image',
		'oid' => $options['oid'] ?? ''
	];
}
else {
	$cron_opt = [
		'task' => 'import'
	];
}

\Opencart\Admin\Controller\Extension\Oasis\Config::instance($registry, [
	'debug' => isset($options['debug']),
	'debug_log' => isset($options['debug_log'])
]);
$cli = new \Opencart\Admin\Controller\Extension\Oasis\Cli($registry);
$cli->runCron($cron_key, $cron_opt);

die();