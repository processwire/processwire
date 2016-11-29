<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo $page->title; ?></title>
	<meta name="description" content="<?php echo $page->summary; ?>" />
	<link href='//fonts.googleapis.com/css?family=Lusitana:400,700|Quattrocento:400,700' rel='stylesheet' type='text/css' />
	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates?>styles/main.css" />
</head>
<body class='has-sidebar'>

	<!-- top navigation -->
	<ul class='topnav' role='navigation'><?php

		// top navigation consists of homepage and its visible children
		$homepage = $pages->get('/'); 
		$children = $homepage->children();

		// make 'home' the first item in the navigation
		$children->prepend($homepage); 

		// render an <li> for each top navigation item
		foreach($children as $child) {
			if($child->id == $page->rootParent->id) {
				// this $child page is currently being viewed (or one of it's children/descendents)
				// so we highlight it as the current page in the navigation
				echo "<li class='current' aria-current='true'><span class='visually-hidden'>Current page: </span><a href='$child->url'>$child->title</a></li>";
			} else {
				echo "<li><a href='$child->url'>$child->title</a></li>";
			}
		}

		// output an "Edit" link if this page happens to be editable by the current user
		if($page->editable()) {
			echo "<li class='edit'><a href='$page->editUrl'>Edit</a></li>";
		}

	?></ul>

	<!-- search form -->
	<form class='search' action='<?php echo $pages->get('template=search')->url; ?>' method='get'>
		<label for='search' class='visually-hidden'>Search:</label>
		<input type='text' name='q' id='search' placeholder='Search' value='' />
		<button type='submit' name='submit' class='visually-hidden'>Search</button>
	</form>

	<!-- breadcrumbs -->
	<div class='breadcrumbs' role='navigation' aria-label='You are here:'><?php

		// breadcrumbs are the current page's parents
		foreach($page->parents() as $item) {
			echo "<span><a href='$item->url'>$item->title</a></span> "; 
		}
		// optionally output the current page as the last item
		echo "<span>$page->title</span> "; 

	?></div>

	<main id='main'>

