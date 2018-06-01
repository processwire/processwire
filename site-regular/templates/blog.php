<?php namespace ProcessWire; 
// This is the template file for main /blog/ page that lists blog post summaries.
// If there are more than 10 posts, it also paginates them. 
?>

<div id='content'>
	<?php
	echo ukHeading1(page()->title, 'divider'); 
	$posts = page()->children('limit=10');
	echo ukBlogPosts($posts); 
	?>
</div>

<aside id='sidebar'>
	<?php 
	$categories = pages()->get('/categories/'); 
	echo ukNav($categories->children, [ 'header' => $categories->title ]); 
	?>		
</aside>

