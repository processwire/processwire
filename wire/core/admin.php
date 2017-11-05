<?php namespace ProcessWire;

/**
 * Controller for ProcessWire Admin
 *
 * This file is designed for inclusion by /site/templates/admin.php template and all the variables 
 * it references are from your template namespace. 
 *
 * Copyright 2016 by Ryan Cramer
 * 
 * @var Config $config
 * @var User $user
 * @var Modules $modules
 * @var Pages $pages
 * @var Page $page
 * @var ProcessWire $wire
 * @var WireInput $input
 * @var Sanitizer $sanitizer
 * @var Session $session
 * @var Notices $notices
 * 
 *
 */

if(!defined("PROCESSWIRE")) die("This file may not be accessed directly.");

header("X-Frame-Options: SAMEORIGIN"); 

/**
 * Ensures a modal GET variable is retained through redirects, when appropriate
 * 
 * @param HookEvent $event
 *
 */
function _hookSessionRedirectModal(HookEvent $event) {
	$url = $event->arguments(0);    
	if(strpos($url, 'modal=1') === false && strpos($url, '://') === false) {
		$url .= (strpos($url, '?') === false ? '?' : '&') . 'modal=1';
		$event->arguments(0, $url);    
	}
}

/**
 * Check if the current HTTP host is recognized and generate error if not
 * 
 * @param Config $config
 * 
 */
function _checkForHttpHostError(Config $config) {

	$valid = false;
	$httpHost = strtolower($config->httpHost); 

	if(isset($_SERVER['HTTP_HOST']) && $httpHost === strtolower($_SERVER['HTTP_HOST'])) {
		$valid = true; 
	} else if(isset($_SERVER['SERVER_NAME']) && $httpHost === strtolower($_SERVER['SERVER_NAME'])) {
		$valid = true; 
	}

	if(!$valid) $config->error(
		__('Unrecognized HTTP host:') . "'"  . 
		htmlentities($_SERVER['HTTP_HOST'], ENT_QUOTES, 'UTF-8') . "' - " . 
		__('Please update your $config->httpHosts setting in /site/config.php') . " - " . 
		"<a target='_blank' href='http://processwire.com/api/variables/config/#httphosts'>" . __('read more') . "</a>", 
		Notice::allowMarkup
		); 
}

// notify superuser if there is an http host error
if($user->isSuperuser()) _checkForHttpHostError($config); 

// ensure core jQuery modules are loaded before others
$modules->get("JqueryCore"); 
$modules->get("JqueryUI"); 

// tell ProcessWire that any pages loaded from this point forward should have their outputFormatting turned off
$pages->setOutputFormatting(false); 

// setup breadcrumbs to current page, and the Process may modify, add to or replace them as needed
$breadcrumbs = $wire->wire('breadcrumbs', new Breadcrumbs()); 
foreach($page->parents() as $p) {
	if($p->id > 1) $breadcrumbs->add(new Breadcrumb($p->url, $p->get("title|name"))); 
}
$controller = null;
$content = '';


// enable modules to output their own ajax responses if they choose to
if($config->ajax) ob_start();

if($page->process && $page->process != 'ProcessPageView') {
	try {

		if($config->demo && !in_array($page->process, array('ProcessLogin'))) {
			if(count($_POST)) $wire->error("Features that use POST variables are disabled in this demo"); 
			foreach($_POST as $k => $v) unset($_POST[$k]); 
			foreach($_FILES as $k => $v) unset($_FILES[$k]); 
			$input->post->removeAll();
		}

		$controller = new ProcessController(); 
		$controller->setProcessName($page->process); 
		$initFile = $config->paths->adminTemplates . 'init.php'; 
		if(is_file($initFile)) {
			if(strpos($initFile, $config->paths->site) === 0) {
				// admin themes in /site/modules/ may be compiled
				$initFile = $wire->files->compile($initFile);
			}
			/** @noinspection PhpIncludeInspection */
			include_once($initFile);
		}
		if($input->get('modal')) $session->addHookBefore('redirect', null, '_hookSessionRedirectModal'); 
		$content = $controller->execute();
		$process = $controller->wire('process');

	} catch(Wire404Exception $e) {
		$wire->error($e->getMessage()); 

	} catch(WirePermissionException $e) {

		if($controller && $controller->isAjax()) {
			$content = $controller->jsonMessage($e->getMessage(), true); 

		} else if($user->isGuest()) {
			/** @var Process $process */
			$process = $modules->get("ProcessLogin"); 
			$content = $process->execute();
		} else {
			$wire->error($e->getMessage()); 	
		}

	} catch(\Exception $e) {
		$msg = $e->getMessage(); 
		if($config->debug) {
			$msg = $sanitizer->entities($msg);
			$msg .= "<pre>\n" . 
				__('DEBUG MODE BACKTRACE') . " " . 
				"(\$config->debug == true):\n" . 
				$sanitizer->entities($e->getTraceAsString()) . 
				"</pre>";
			$wire->error("$page->process: $msg", Notice::allowMarkup);
		} else {
			$wire->error($msg);
		}
		if($controller && $controller->isAjax()) {
			$content = $controller->jsonMessage($e->getMessage(), true);
			$wire->trackException($e, false);
		} else {
			$wire->trackException($e, true);
		}
	}

} else {
	$content = '<p>' . __('This page has no process assigned.') . '</p>';
}

if($config->ajax) {
	// enable modules to output their own ajax responses if they choose to
	if(!$content) $content = ob_get_contents();
	ob_end_clean();
}

$config->js(array('httpHost', 'httpHosts'), true); 

if($controller && $controller->isAjax()) {
	if(empty($content) && count($notices)) $content = $controller->jsonMessage($notices->last()->text); 
	echo $content; 
} else {
	if(!strlen($content)) $content = '<p>' . __('The process returned no content.') . '</p>';
	$adminThemeFile = $config->paths->adminTemplates . 'default.php';
	if(strpos($adminThemeFile, $config->paths->site) === 0) {
		// @todo determine if compilation needed
		$adminThemeFile = $wire->files->compile($adminThemeFile);
	}
	/** @noinspection PhpIncludeInspection */
	require($adminThemeFile);
	$session->removeNotices();
}

