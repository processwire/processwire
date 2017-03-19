<?php namespace ProcessWire;

/**
 * _main.php
 * Main markup file
 *
 * This file contains all the main markup for the site and outputs the regions 
 * defined in the initialization (_init.php) file. These regions include: 
 * 
 *   $title: The page title/headline 
 *   $content: The markup that appears in the main content/body copy column
 *   $sidebar: The markup that appears in the sidebar column
 * 
 * Of course, you can add as many regions as you like, or choose not to use
 * them at all! This _init.php > [template].php > _main.php scheme is just
 * the methodology we chose to use in this particular site profile, and as you
 * dig deeper, you'll find many others ways to do the same thing. 
 * 
 * This file is automatically appended to all template files as a result of 
 * $config->appendTemplateFile = '_main.php'; in /site/config.php. 
 *
 * In any given template file, if you do not want this main markup file 
 * included, go in your admin to Setup > Templates > [some-template] > and 
 * click on the "Files" tab. Check the box to "Disable automatic append of
 * file _main.php". You would do this if you wanted to echo markup directly 
 * from your template file or if you were using a template file for some other
 * kind of output like an RSS feed or sitemap.xml, for example. 
 *
 * See the README.txt file for more information. 
 *
 */
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo $title; ?></title>
	<meta name="description" content="<?php echo $page->summary; ?>" />
	<link href='//fonts.googleapis.com/css?family=Lusitana:400,700|Quattrocento:400,700' rel='stylesheet' type='text/css' />
	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates?>styles/main.css" />
</head>
<body class="<?php if($sidebar) echo "has-sidebar "; ?>">

	<a href="#main" class="visually-hidden element-focusable bypass-to-main">Skip to content</a>

	<!-- top navigation -->
	<ul class='topnav' role='navigation'><?php
		// top navigation consists of homepage and its visible children
		foreach($homepage->and($homepage->children) as $item) {
			if($item->id == $page->rootParent->id) {
				echo "<li class='current' aria-current='true'><span class='visually-hidden'>Current page: </span>";
			} else {
				echo "<li>";
			}
			echo "<a href='$item->url'>$item->title</a></li>";
		}

		// output an "Edit" link if this page happens to be editable by the current user
		if($page->editable()) echo "<li class='edit'><a href='$page->editUrl'>Edit</a></li>";
	?></ul>

	<!-- search form-->
	<form class='search' action='<?php echo $pages->get('template=search')->url; ?>' method='get'>
		<label for='search' class='visually-hidden'>Search:</label>
		<input type='text' name='q' placeholder='Search' id='search' value='<?php echo $sanitizer->entities($input->whitelist('q')); ?>' />
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

	<div id='main'>

		<!-- main content -->
		<div id='content'>
			<h1><?php echo $title; ?></h1>
			<?php echo $content; ?>
		</div>

		<!-- sidebar content -->
		<?php if($sidebar): ?>
		<aside id='sidebar'>
			<?php echo $sidebar; ?>
		</aside>
		<?php endif; ?>

	</div>

	<!-- footer -->
	<footer id='footer'>
		<p>
		Powered by <a href='http://processwire.com'>ProcessWire CMS</a>  &nbsp; / &nbsp; 
		<?php 
		if($user->isLoggedin()) {
			// if user is logged in, show a logout link
			echo "<a href='{$config->urls->admin}login/logout/'>Logout ($user->name)</a>";
		} else {
			// if user not logged in, show a login link
			echo "<a href='{$config->urls->admin}'>Admin Login</a>";
		}
		?>
		</p>
	</footer>

</body>
</html>
