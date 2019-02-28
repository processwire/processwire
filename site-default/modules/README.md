ABOUT /SITE/MODULES/ 
====================
This directory /site/modules/ is where you may install additional plugin modules.
These modules are specific to your site only. There is also a corresponding 
/wire/modules/ directory, which contains ProcessWire's core modules (and best to 
leave those alone). 

If safe for your hosting environment, you may wish to make this directory 
writable to PHP so that the installation of your modules can be managed from 
ProcessWire's admin. However, this is not necessarily safe in all shared hosting
environments and is completely optional. 


Where to get modules?
---------------------
Visit the modules directory at: http://modules.processwire.com


Installing modules from the ProcessWire admin
---------------------------------------------
If your /site/modules/ directory is writable, you can install modules from 
ProcessWire's admin directly from the Modules Directory, from a ZIP file or from
a URL to a ZIP file. In your ProcessWire admin, see Modules > New for
installation options. 


Installing modules from the file system 
---------------------------------------
Each module (and any related files) should live in a directory of its own. The 
directory should generally carry the same name as the module. For instance, if
you are installing a module named ProcessDatabaseBackups.module, then it should 
live in the directory /site/modules/ProcessDatabaseBackups/. 

Once you have placed a new module in this directory, you need to let ProcessWire
know about it. Login to the admin and click "Modules". Then click the "Check for
new modules" button. It will find your new module(s). Click the "Install" button
next to any new modules that you want to install.


Removing modules
----------------
The first step in removing a module is to uninstall it from ProcessWire (if it
isn't already). You do this by going to the "Modules" page, and "Site" tab in 
your ProcessWire admin. Click the "Uninstall" button next to the module you 
want to remove. 

After the module is uninstalled, you may remove the module files. If your 
modules file system is writable to ProcessWire, it will give you a "Delete" 
button next to the module in your "Modules" admin page. You may click that to
remove the module files. 

If your file system is not writable, you may remove the module files manually
from the file system (via SFTP or whatever tool you are using to manage your
files on the server). 


Interested in learning how to make your own modules?
----------------------------------------------------
We've created two "Hello World" modules as examples for those interested in
learning module development: 

- Helloworld.module demonstrates the basics of modules and hooks. 
  http://modules.processwire.com/modules/helloworld/

- ProcessHello.module demonstrates the basics of how to create a Process
  module. Process modules are those that create applications in the admin.
  http://modules.processwire.com/modules/process-hello/

There is a module development forum located at:
https://processwire.com/talk/forum/19-moduleplugin-development/


Additional resources
--------------------

To find and download new modules, see the modules directory at:
http://modules.processwire.com/ 

For more information about modules, see the documentation at:
http://processwire.com/api/modules/

For discussion and support of modules, see:
http://processwire.com/talk/forum/4-modulesplugins/


