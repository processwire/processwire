<?php

include('./_head.php'); // include header markup ?>

	<div id='content'><?php 
	
		// output 'headline' if available, otherwise 'title'
		echo "<h1>" . $page->get('headline|title') . "</h1>";
	
		// output bodycopy
		echo $page->body; 
	
		// render navigation to child pages
		renderNav($page->children); 
		
		// TIP: Notice that this <div id='content'> section is
		// identical between home.php and basic-page.php. You may
		// want to move this to a separate file, like _content.php
		// and then include('./_content.php'); here instead, on both
		// the home.php and basic-page.php template files. Then when
		// you make yet more templates that need the same thing, you
		// can simply include() it from them.
	
	?></div><!-- end content -->

	<aside id='sidebar'><?php
	
		// rootParent is the parent page closest to the homepage
		// you can think of this as the "section" that the user is in
		// so we'll assign it to a $section variable for clarity
		$section = $page->rootParent; 
	
		// if there's more than 1 page in this section...
		if($section->hasChildren > 1) {
			// output sidebar navigation
			// see _init.php for the renderNavTree function
			renderNavTree($section);
		}
	
		// output sidebar text if the page has it
		echo $page->sidebar; 
	
	?></aside><!-- end sidebar -->

<?php include('./_foot.php'); // include footer markup ?>
