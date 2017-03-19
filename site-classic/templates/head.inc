<?php

/**
 * Demo site header include file (HTML5)
 *
 * Note that this file has nothing to do with ProcessWire. We just split our common 
 * header and footer markup into separate files (head.inc and foot.inc) like this, 
 * since it was common to all of our templates. 
 *
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8" />

	<title><?php echo $page->get("headline|title"); ?></title>

	<meta name="description" content="<?php echo $page->summary; ?>" />

	<meta name="generator" content="ProcessWire <?php echo $config->version; ?>" />

	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates?>styles/main.css" />

	<!--[if IE]>
	<link rel="stylesheet" type="text/css" href="<?php echo $config->urls->templates?>styles/ie.css" />
	<![endif]-->	

	<!-- This website is powered by ProcessWire CMF/CMS - https://processwire.com -->

</head>
<body>

	<a href="#bodycopy" class="visually-hidden element-focusable bypass-to-main">Skip to content</a>

	<p id='bgtitle'><?php 

		// print the section title as big faded text that appears near the top left of the page
		echo $page->rootParent->title; 

	?></p>	

	<div id="masthead" class="masthead">

		<div class="container">

			<a href='<?php echo $config->urls->root; ?>'><p id='logo'>ProcessWire</p></a>
		
			<ul id='topnav' role='navigation'><?php
			
				// Create the top navigation list by listing the children of the homepage. 
				// If the section we are in is the current (identified by $page->rootParent)
				// then note it with <a class='on'> so we can style it differently in our CSS
				// and add a text that is visually hidden, but available for screen readers.
				// In this case we also want the homepage to be part of our top navigation, 
				// so we prepend it to the pages we cycle through:
				
				$homepage = $pages->get("/"); 
				$children = $homepage->children;
				$children->prepend($homepage); 
		
				foreach($children as $child) {
					if ($child === $page->rootParent) {
						$class = " class='on'";
						$indicator = "<span class='visually-hidden'>Current page: </span>";
						$ariaState = " aria-current='true' ";
					}
					$class = $child === $page->rootParent ? " class='on'" : '';
					echo "<li><a$class$ariaState href='{$child->url}'>$indicator{$child->title}</a></li>";
				}

			?></ul>

			<ul id='breadcrumb' role='navigation' aria-label='You are here:'><?php

				// Create breadcrumb navigation by cycling through the current $page's
				// parents in order, linking to each:

				foreach($page->parents as $parent) {
					echo "<li><a href='{$parent->url}'>{$parent->title}</a> <span class='visually-hidden'>&gt;</span> </li>";
				}

			?></ul>

			<h1 id='title'><?php 

				// The statement below asks for the page's headline or title. 
				// Separating multiple fields with a pipe "|" returns the first
				// one that has a value. So in this case, we print the headline
				// field if it's there, otherwise we print the title. 
				
				echo $page->get("headline|title"); 

			?></h1>

			<form id='search_form' action='<?php echo $config->urls->root?>search/' method='get'>
				<label for='search_query' class='visually-hidden'>Search:</label>
				<input type='text' name='q' id='search_query' value='<?php echo htmlentities($input->whitelist('q'), ENT_QUOTES, 'UTF-8'); ?>' />
				<button type='submit' id='search_submit'>Search</button>
			</form>

			<?php

			// Grab a random image from the homepage and display it.
			// Note that $homepage was loaded above where we generated the top navigation.

			if(count($homepage->images)) {
				$image = $homepage->images->getRandom(); 
				$thumb = $image->size(232, 176); 	
				echo "<a href='{$image->url}'><img id='photo' src='{$thumb->url}' alt='{$thumb->description}' width='{$thumb->width}' height='{$thumb->height}' /></a>";
			}

			?>

		</div>
	</div>

	<div id="content" class="content">

		<div class="container">

			<div id="sidebar" role='complementary'>

				<?php

				// Output subnavigation
				// 
				// Below we check to see that we're not on the homepage, and that 
				// there are at least one or more pages in this section.
				// 
				// Note $page->rootParent is always the top level section the page is in, 
				// or to word differently: the first parent page that isn't the homepage.

				if($page->path != '/' && $page->rootParent->numChildren > 0) { 

					// We have determined that we're not on the homepage
					// and that this section has child pages, so make navigation: 

					echo "<ul id='subnav' class='nav' role='navigation'>";

					foreach($page->rootParent->children as $child) {
						$class = $page === $child ? " class='on'" : '';
						$ariaState = $page === $child ? " aria-current='true' " : '';
						echo "<li><a$class$ariaState href='{$child->url}'>{$child->title}</a></li>";
					}

					echo "</ul>";
				}

				?>

				<div class='sidebar_item'>

					<?php

					// if the current page has a populated 'sidebar' field, then print it,
					// otherwise print the sidebar from the homepage
					
					if($page->sidebar) echo $page->sidebar; 
						else echo $homepage->sidebar; 
					?>


				</div>

			</div><!--/sidebar-->

			<div id="bodycopy" role="main">
			
