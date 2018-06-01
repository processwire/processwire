<?php namespace ProcessWire; 

// get most recent blog post
$blog = pages()->get('/blog/');
$blogPost = $blog->child();

?>

<h1 id='content-head'>
	<?=page()->headline?>
</h1>

<div class='uk-margin-top' id='content-body'>
	<?=page()->body?>
	<hr>
	<p class='uk-margin-small'>
		<a class='uk-button uk-button-link uk-link-muted' href='<?=$blog->url?>'>
			In the blog
		</a>
	</p>
	<?=ukBlogPost($blogPost)?>
	<p class='uk-margin-small'>
		<a href='<?=$blog->url?>'>More blog posts <?=ukIcon('arrow-right')?></a>
	</p>
</div>

<aside id='sidebar'>
	<?=ukNav(pages()->get('/categories/')->children)?>
	<div class='uk-card uk-card-default uk-card-hover uk-card-body uk-margin-medium-top'>
		<?=page()->sidebar?>
	</div>
</aside>
