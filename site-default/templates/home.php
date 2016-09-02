<?php namespace ProcessWire;

// home.php (homepage) template file. 
// See README.txt for more information

// Primary content is the page body copy and navigation to children. 
// See the _func.php file for the renderNav() function example
$content = $page->body . renderNav($page->children); 

// if there are images, lets choose one to output in the sidebar
if(count($page->images)) {
	// if the page has images on it, grab one of them randomly... 
	$image = $page->images->getRandom();
	// resize it to 400 pixels wide
	$image = $image->width(400); 
	// output the image at the top of the sidebar...
	$sidebar = "<img src='$image->url' alt='$image->description' />";
	// ...and append sidebar text under the image
	$sidebar .= $page->sidebar;	
} else {
	// no images... 
	// append sidebar text if the page has it
	$sidebar = $page->sidebar; 
}

