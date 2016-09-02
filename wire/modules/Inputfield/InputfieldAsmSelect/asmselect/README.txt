asmSelect - Alternate Select Multiple

Documentation and more information at:
http://code.google.com/p/jquery-asmselect/

See related article at: 
http://www.ryancramer.com/journal/entries/select_multiple/

VERSION 1.0.6 beta - August 2011

- Update for jQuery 1.6 compatibility

VERSION 1.0.5 beta - November 2010

- Updates and bugfixes in preparation for inclusion
  with ProcessWire 2.x core modules.

VERSION 1.0.4a beta - June 3rd, 2009

- Minor update to correct IE8 issue. 
  Thanks to Matthew Hutton for this fix. 


VERSION 1.0.4 beta - December 1, 2008

- Fixed issue that interfered with multiple asmSelects
  on the same page. This also solves an issue with 
  dynamically rendered (ajax) asmSelects on 1 page. 

- Changed options so that "animate" and "highlight"
  now default to "false". These are just a bit too
  slow on older computers, so I thought it would be
  better not to make them active defaults.

- Added code that triggers a change() event on the 
  original <select multiple> whenever a change is 
  made on the asmSelect. This means that other bits
  of javascript don't need to know about asmSelect
  if they happen to be monitoring the original
  <select multiple> for changes.

- Added some additional logic for dealing with IE and
  determining whether a click preceded an item being
  added to the list. This was necessary because IE 
  triggers change events when you are scrolling around
  in a select. Thankfully not an issue with other browsers.

- Added "optionDisabledClass" in program options. 
  This is a class assigned to <option> items that
  are disabled. This was necessary because only 
  Safari allows the "disabled" attribute with 
  option tags (as far as I can tell). This is 
  mostly for internal use with asmSelect, so you can 
  ignore this unless you want to come up with your own 
  styles for disabled option items.  

- Added logic to detect Opera and force a redraw of 
  the html list when original select is modified. 
  Previously, opera would not draw the new list items...
  They were in the DOM, just not on Opera's screen. 

- Updated documentation with note about the Firefox
  autocomplete issue, which can be a factor on some
  asmSelect implementations


VERSION 1.0.3 beta 

- This version was released in the issues section 
  of the Google code site, but never released as
  a full package. It fixed the issue with multiple
  asmSelects on a single page. 


VERSION 1.0.2 beta - July 15, 2008

- Updated license to consistent with jQuery and 
  jQuery UI: Dual MIT and GNU license.

- Fixed issue with IE6 where original select multiple 
  would reappear when sorting was enabled.

- Put in a partial fix for when IE6 select is being 
  scrolled without being focused. (ieClick)

- Updated for some other minor IE6 fixes, but still 
  not 100% on IE6, see 'Known Issues' in docs.

- Changed 'animate' and 'highlight' to be false by 
  default. These are too slow on old computers.

- Added new class to CSS 'optionDisabledClass' that 
  is applied to disabled options. This was necessary 
  becase Firefox and IE don't fade disabled options
  like Safari does.

- Removed some extraneous code.


VERSION 1.0.1 beta - July 7, 2008

- Corrected an issue with IE where asmSelect didn't work if option values were blank.


VERSION 1.0.0 beta - July 5, 2008

Initial release


Copyright 2008 by Ryan Cramer

