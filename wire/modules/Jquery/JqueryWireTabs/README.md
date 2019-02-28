jQuery Tabs for ProcessWire

ProcessWire 3.x (development), Copyright 2015 by Ryan Cramer
https://processwire.com

USAGE
=====

1a. Load the JqueryWireTabs module: 

  wire('modules')->get('JqueryWireTabs'); 

1b. OR: load the JS file directly: 

  <script src='/wire/modules/Jquery/JqueryWireTabs.js'></script>

2. The rest happens in JS. All options are optional except for 'items'. 

  $('#element').WireTabs({ // tabs will be prepended to #element
    items: $(".WireTab"), // items that it should tab (REQUIRED)
    rememberTabs: true, // whether it should remember current tab across requests
    skipRememberTabIDs: ['DeleteTab'], // array of tab IDs it should not remember between requests
    id: 'PageEditTabs', // id attribute for generated tabbed navigation (optional)
    itemsParent: null, // parent element for items (better to omit when possible)
    cookieName: 'WireTab', // Name of cookie it uses to remember tabs
  });
  
EVENTS
======

When a tab is clicked, a "wiretabclick" event is sent to $(document) with 
arguments $newTab and $oldTab to represent the tabs that changed. Example:

  $(document).on('wiretabclick', function($event, $newTab, $oldTab) {
    console.log("Tab changed to: " + $newTab.attr('id')); 
  }); 


