This InputfieldCKEditor directory is here to provide optional extra configuration options
and plugins to the CKEditor Inputfield module. 


plugins/ 
========
Directory to place additional CKEditor plugins in. You can then activate them
from your CKEditor field settings. 


contents.css
============
Example CSS file for the admin editor. To make CKEditor use this file, go to your CKEditor
field settings and specify /site/modules/InputfieldCKEditor/contents.css as the regular
mode Contents CSS file. 


contents-inline.css
===================
Same as contents.css but for the inline mode editor. 


mystyles.js
===========
Optional configuration for the CKEditor Styles option. To use this file, go to your 
CKEditor field settings and set the Custom Styles Set to be this file. 


config.js
=========
Custom config file used by all CKEditor instances (except instances configured by their
own custom config file, see below...)


config-body.js
==============
Example of field-specific custom config file. This one applies to a field named "body". 
Note that these config settings can also be specified directly in your CKEditor field
settings in the admin, which many may prefer. 

