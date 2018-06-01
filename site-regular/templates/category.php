<?php namespace ProcessWire; ?>

<div id='content'>
	<?php
	echo ukHeading1(page()->title, 'divider');
	$posts = pages()->get('/blog/')->children("categories=$page, limit=10");
	echo ukBlogPosts($posts); 
	?>
</div>

<aside id='sidebar'>
	<?php
	$categories = page()->parent->children();
	echo ukNav($categories); 
	?>		
</aside>
