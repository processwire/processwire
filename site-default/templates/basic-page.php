<?php namespace ProcessWire;

// basic-page.php template file 
// See README.txt for more information

// Primary content is the page's body copy
$content = $page->body; 

// If the page has children, then render navigation to them under the body.
// See the _func.php for the renderNav example function.
if($page->hasChildren) {
	$content .= renderNav($page->children);
}

// if the rootParent (section) page has more than 1 child, then render 
// section navigation in the sidebar
if($page->rootParent->hasChildren > 1) {
	$sidebar = renderNavTree($page->rootParent, 3) . $page->sidebar; 
}

