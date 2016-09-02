<?php namespace ProcessWire;

foreach($pages->get($wire->config->adminRootPageID)->children("check_access=0") as $p) {

	if(!$p->viewable()) continue; 

	$showItem = $user->isSuperuser() ? true : false;
	$info = array();

	if(!$showItem) { 
		$checkPages = $p->numChildren ? $p->children("check_access=0") : array($p); 
		foreach($checkPages as $child) {
			if($child->viewable()) {
				$showItem = true;
				break;
			}
		}
	}

	if($showItem) { 
		$class = strpos($page->path, $p->path) === 0 ? " class='on'" : '';
		$title = strip_tags((string)$p->get('title|name')); 
		$title = __($title, dirname(__FILE__) . '/default.php'); // translate from context of default.php
		echo "\n\t\t\t\t<li><a href='{$p->url}'$class>$title</a></li>"; 
	}
}
