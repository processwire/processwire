You should not modify any files in the /wire/ directory as this comprises
the ProcessWire core and is typically replaced entirely during upgrades. 

To install new modules, you would place them in /site/modules/ rather than
/wire/modules. 

To install a new admin theme, you would place it in /site/templates-admin/
and leave the one that is in /wire/templates-admin/.

To install a new version of ProcessWire, replace this /wire/ directory 
completely with the one from the new version. See the main README.txt
file in the root installation directory for more information about
performing upgrades. 

