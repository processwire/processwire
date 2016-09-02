<?php namespace ProcessWire;

/**
 * _main.php
 * Main markup file (multi-language)

 * MULTI-LANGUAGE NOTE: Please see the README.txt file
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
 * 
 */
?><!DOCTYPE html>
<html lang="<?php echo _x('en', 'HTML language code'); ?>">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo $title; ?></title>
	<meta name="description" content="<?php echo $page->summary; ?>" />
	<link href="//fonts.googleapis.com/css?family=Lusitana:400,700|Quattrocento:400,700" rel="stylesheet" type="text/css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates?>styles/main.css" />
	<?php
	
	// handle output of 'hreflang' link tags for multi-language
	// this is good to do for SEO in helping search engines understand
	// what languages your site is presented in	
	foreach($languages as $language) {
		// if this page is not viewable in the language, skip it
		if(!$page->viewable($language)) continue;
		// get the http URL for this page in the given language
		$url = $page->localHttpUrl($language); 
		// hreflang code for language uses language name from homepage
		$hreflang = $homepage->getLanguageValue($language, 'name'); 
		// output the <link> tag: note that this assumes your language names are the same as required by hreflang. 
		echo "\n\t<link rel='alternate' hreflang='$hreflang' href='$url' />";
	}
	
	?>
	
</head>
<body class="<?php if($sidebar) echo "has-sidebar"; ?>">

	<!-- language switcher / navigation -->
	<ul class='languages'><?php
		foreach($languages as $language) {
			if(!$page->viewable($language)) continue; // is page viewable in this language?
			if($language->id == $user->language->id) {
				echo "<li class='current'>";
			} else {
				echo "<li>";
			}
			$url = $page->localUrl($language); 
			$hreflang = $homepage->getLanguageValue($language, 'name'); 
			echo "<a hreflang='$hreflang' href='$url'>$language->title</a></li>";
		}
	?></ul>

	<!-- top navigation -->
	<ul class='topnav'><?php 
		// top navigation consists of homepage and its visible children
		foreach($homepage->and($homepage->children) as $item) {
			if($item->id == $page->rootParent->id) {
				echo "<li class='current'>";
			} else {
				echo "<li>";
			}
			echo "<a href='$item->url'>$item->title</a></li>";
		}

		// output an "Edit" link if this page happens to be editable by the current user
		if($page->editable()) echo "<li class='edit'><a href='$page->editUrl'>" . __('Edit') . "</a></li>";
	?></ul>

	<!-- breadcrumbs -->
	<div class='breadcrumbs'><?php 
		// breadcrumbs are the current page's parents
		foreach($page->parents() as $item) {
			echo "<span><a href='$item->url'>$item->title</a></span> "; 
		}
		// optionally output the current page as the last item
		echo "<span>$page->title</span> "; 
	?></div>

	<!-- search engine -->
	<form class='search' action='<?php echo $pages->get('template=search')->url; ?>' method='get'>
		<input type='text' name='q' placeholder='<?php echo _x('Search', 'placeholder'); ?>' />
		<button type='submit' name='submit'><?php echo _x('Search', 'button'); ?></button>
	</form>


	<div id='main'>

		<!-- main content -->
		<div id='content'>
			
			<h1><?php echo $title; ?></h1>
			<?php echo $content; ?>
			
		</div>

		<!-- sidebar content -->
		<?php if($sidebar): ?>
			
		<div id='sidebar'>
			
			<?php echo $sidebar; ?>
			
		</div>
			
		<?php endif; ?>

	</div>

	<!-- footer -->
	<footer id='footer'>
		<p>
		<a href='http://processwire.com'><?php echo __('Powered by ProcessWire CMS'); ?></a> &nbsp; / &nbsp; 
		<?php
		if($user->isLoggedin()) {
			// if user is logged in, show a logout link
			echo "<a href='{$config->urls->admin}login/logout/'>" . sprintf(__('Logout (%s)'), $user->name) . "</a>";
		} else {
			// if user not logged in, show a login link
			echo "<a href='{$config->urls->admin}'>" . __('Admin Login') . "</a>";
		}
		?>
			
		</p>
	</footer>

</body>
</html>
