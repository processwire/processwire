<?php namespace ProcessWire; ?>

<head id='html-head' pw-append>
	<script src='<?=urls()->FieldtypeComments?>comments.min.js'></script>
	<link rel="stylesheet" href="<?=urls()->FieldtypeComments?>comments.css">
</head>	

<div id='content'>
	<?php 
	
	// blog post content
	echo ukBlogPost(page());
	
	// comments
	$comments = page()->comments;

	// comment list 
	if(count($comments)) {
		echo ukHeading3("Comments", "icon=comments");
		echo ukComments($comments);
	}

	// comment form
	echo ukHeading3("Post a comment", "icon=comment"); 
	echo ukCommentForm($comments);
	
	// link to the next blog post, if there is one
	$nextPost = page()->next();
	if($nextPost->id): ?>	
		<p class='next-blog-post'>
			Next <?=ukIcon('chevron-right')?>
			<a href='<?=$nextPost->url?>'><?=$nextPost->title?></a>
		</p>
	<?php endif; ?> 
</div>

<aside id='sidebar' pw-prepend>
	<?php 
	$img = page()->images->first(); 
	if($img) {
		$img = $img->width(600);
		echo "<p class='uk-text-center'><img src='$img->url' alt='$img->description'></p>";
	}
	?>
	
	<?=ukNav(page()->parent->children('limit=10'), [ 'heading' => 'Recent blog posts' ])?>
	<p><a href='<?=page()->parent->url?>'>More posts<?=ukIcon('arrow-right')?></a></p>
</aside>

