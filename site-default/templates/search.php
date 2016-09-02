<?php namespace ProcessWire;

// search.php template file
// See README.txt for more information. 

// look for a GET variable named 'q' and sanitize it
$q = $sanitizer->text($input->get->q); 

// did $q have anything in it?
if($q) { 
	// Send our sanitized query 'q' variable to the whitelist where it will be
	// picked up and echoed in the search box by _main.php file. Now we could just use
	// another variable initialized in _init.php for this, but it's a best practice
	// to use this whitelist since it can be read by other modules. That becomes 
	// valuable when it comes to things like pagination. 
	$input->whitelist('q', $q); 

	// Sanitize for placement within a selector string. This is important for any 
	// values that you plan to bundle in a selector string like we are doing here.
	$q = $sanitizer->selectorValue($q); 

	// Search the title and body fields for our query text.
	// Limit the results to 50 pages. 
	$selector = "title|body~=$q, limit=50"; 

	// If user has access to admin pages, lets exclude them from the search results.
	// Note that 2 is the ID of the admin page, so this excludes all results that have
	// that page as one of the parents/ancestors. This isn't necessary if the user 
	// doesn't have access to view admin pages. So it's not technically necessary to
	// have this here, but we thought it might be a good way to introduce has_parent.
	if($user->isLoggedin()) $selector .= ", has_parent!=2"; 

	// Find pages that match the selector
	$matches = $pages->find($selector); 

	// did we find any matches?
	if($matches->count) {
		// yes we did
		$content = "<h2>Found $matches->count pages matching your query:</h2>";
		// we'll use our renderNav function (in _func.php) to render the navigation
		$content .= renderNav($matches); 
	} else {
		// we didn't find any
		$content = "<h2>Sorry, no results were found.</h2>";
	}

} else {
	// no search terms provided
	$content = "<h2>Please enter a search term in the search box (upper right corner)</h2>";
}

