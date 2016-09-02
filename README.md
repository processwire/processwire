# Welcome to ProcessWire 3.x / 2.8.x

This document is in Markdown. An HTML formatted version of this document 
can be read at: https://github.com/processwire/processwire/blob/master/README.md

## Table of Contents

1. [About ProcessWire](#about-processwire)
2. [Installing ProcessWire](#installation)
   - [Requirements](#requirements)
   - [Installation from ZIP file](#installation-from-zip-file)
   - [Installation from GitHub](#installation-from-github)
   - [Troubleshooting Installation](#troubleshooting-installation)
       - [The homepage works but nothing else does](#the-homepage-works-but-nothing-else-does)
       - [Resolving an Apache 500 error](#resolving-an-apache-500-error)
       - [Resolving other error messages or a blank screen](#resolving-other-error-messages-or-a-blank-screen)
3. [Upgrading ProcessWire](#upgrades)
   - [Best Practices Before Upgrading](#best-practices-before-upgrading)
   - [General Upgrade Process](#general-upgrade-process)
       - [Replacing the /wire/ directory](#replacing-the-wire-directory)
       - [Replacing the /index.php file](#replacing-the-indexphp-file)
       - [Replacing the .htaccess file](#replacing-the-htaccess-file)
       - [Additional upgrade notes](#additional-upgrade-notes)
   - [Upgrading from ProcessWire 2.7](#upgrading-from-processwire-27)
   - [Upgrading from ProcessWire 2.6](#upgrading-from-processwire-26)
   - [Upgrading from ProcessWire 2.5](#upgrading-from-processwire-25)
   - [Upgrading from ProcessWire 2.4](#upgrading-from-processwire-24)
   - [Upgrading from ProcessWire 2.2 or 2.3](#upgrading-from-processwire-22-or-23)
   - [Upgrading from ProcessWire 2.1](#upgrading-from-processwire-21)
   - [Upgrading from ProcessWire 2.0](#upgrading-from-processwire-20)
   - [Troubleshooting an Upgrade](#troubleshooting-an-upgrade)
4. [Debug Mode](#debug-mode)
5. [Support](#support)

## About ProcessWire

ProcessWire is an open source content management system (CMS) and web 
application framework aimed at the needs of designers, developers and their 
clients. ProcessWire gives you more control over your fields, templates and 
markup than other platforms, and provides a powerful template system that 
works the way you do. Not to mention, ProcessWire's API makes working with 
your content easy and enjoyable. Managing and developing a site in 
ProcessWire is shockingly simple compared to what you may be used to.

* [Learn more about ProcessWire](https://processwire.com)
* [Download the latest ProcessWire](https://processwire.com/download/)
* [Get support for ProcessWire](https://processwire.com/talk/)
* [Browse and install ProcessWire modules/plugins](http://modules.processwire.com)
* [Follow @ProcessWire on Twitter](http://twitter.com/processwire/)
* [Contact ProcessWire](https://processwire.com/contact/)
* [API Cheatsheet](http://cheatsheet.processwire.com/)
* [Sites running ProcessWire](https://processwire.com/about/sites/)
* [Read the ProcessWire Blog](https://processwire.com/blog/)

## Installation

### Requirements

* A web server running Apache. 
* PHP version 5.3.8 or newer.
* MySQL 5.0.15 or newer.
* Apache must have mod_rewrite enabled. 
* Apache must support .htaccess files. 


### Installation from ZIP file

1. Unzip the ProcessWire installation file to the location where you want it
   installed on your web server. 

2. Load the location that you unzipped (or uploaded) the files to in your web
   browser. This will initiate the ProcessWire installer. The installer will
   guide you through the rest of the installation.


### Installation from GitHub

Git clone ProcessWire to the place where you want to install it:

```
git clone https://github.com/processwire/processwire.git
```

Load the location where you installed ProcessWire into your browser. 
This will initiate the ProcessWire installer. The installer will guide
you through the rest of the installation.  


### Troubleshooting Installation

#### The homepage works but nothing else does

This indicates that Apache is not properly reading your .htaccess file. 
First we need to determine if Apache is reading your .htaccess file at all.
To do this, open the .htaccess file in an editor and type in some random
characters at the top, like `lkjalefkjalkef` and save. Load your site in 
your browser. You should get a "500 Error". If you do not, that means 
Apache is not reading your .htaccess file at all. If this is your case,
contact your web host for further assistance. Or if maintaining your own
server, look into the Apache *AllowOverride* directive which you may need
to configure for the account in your httpd.conf file. 

If the above test did result in a 500 error, then that is good because we
know your .htaccess file is at least being used. Go ahead and remove the 
random characters you added at the top. Now look further down in the 
.htaccess file for suggested changes. Specially, you will want to look at 
the *RewriteBase* directive, which is commented out (disabled) by default. 
You may need to enable it.

#### Resolving an Apache 500 error

The presence of an Apache 500 error indicates that Apache does not
like one or more of the directives in the .htaccess file. Open the
.htaccess file in an editor and read the comments. Note those that 
indicate the term "500 NOTE" and they will provide further instructions
on optional directives you can try to comment out. Test one at a time,
save and reload in your browser till you determine which directive is
not working with your server.

#### Resolving other error messages or a blank screen

If you are getting an error message, a blank screen, or something
else unexpected, see the section at the end of this document on 
enabling debug mode. This will enable more detailed error reporting
which may help to resolve any issues. 

In addition, the ProcessWire error log is located in the file:
/site/assets/logs/errors.txt - look in here to see if more information
is available about the error message you have received. 

If the above suggestions do not help you to resolve the installation
error, please post in the [ProcessWire forums](http://processwire.com/talk). 


## Upgrades

### Best Practices Before Upgrading

1. Backup your database and backup all the files in your site.
2. When possible, test the upgrade on a development/staging site 
   before performing the upgrade on a live/production site. 
3. Login to your ProcessWire admin under a superuser account before 
   upgrading. This enables you to see more verbose output during the
   upgrade process. 
4. If you have 3rd party modules installed, confirm that they are 
   compatible with the ProcessWire version you are upgrading to. 
   If you cannot confirm compatibility, uninstall the 3rd party 
   modules before upgrading, when possible. You can attempt to
   re-install them after upgrading. If uninstalling is 
   inconvenient, just be sure you have the ability to revert if for 
   some reason one of your modules does not like the upgrade.
   Modules that are compatible with ProcessWire 2.4-2.7 are generally
   going to also be compatible with 3.0 with a few exceptions.

If you prefer an automatic/web-based upgrade, an
[upgrade module](https://github.com/ryancramerdesign/ProcessWireUpgrade)
is available. This upgrade utility can also help with upgrading other 
modules as well. However, the upgrade from 2.x to 3.x is a major upgrade
and we recommend performing this upgrade manually rather than with any
automated tools. 


### General Upgrade Process

Before upgrading, login to your ProcessWire admin under a superuser
account. This is not required to upgrade, but is recommended for more
verbose output during the upgrade. 

Upgrading from one version of ProcessWire to another is a matter of 
deleting these files/directories from your old version, and putting 
in fresh copies from the new version:  

```
/wire/
/index.php
/.htaccess 
```

Removing and replacing the above directory/files is typically the 
primary thing you need to do in order to upgrade. But please see 
the version-to-version specific upgrade notes documented further 
in this section. Further below are more details about how you should 
replace the files mentioned above.

After replacing the /wire/ directory (and the other two files if needed),
hit reload in your browser, anywhere in the ProcessWire admin. You
should see messages at the top of your screen about updates that were
applied. Depending on which version you are upgrading from, you might
also see error messages--this is normal. Keep hitting reload in your 
browser until you no longer see any upgrade related messages (up to 5 
reloads may be necessary). 

*NOTE: Renaming is an alternative to deleting, which gives you a quicker 
path to revert should you want to. For example, you might rename
your /wire/ directory to be /.wire-2.4.0/ with ".wire" rather than 
"wire" to ensure the directory is hidden, and the 2.4.0 indicating the 
version that it was. Once your upgrade is safely in place, you could 
delete that .wire-2.4.0 directory (or keep it around). If you keep old
version dirs/files in place, make sure they are not http accessible. 
This is typically done by preceding the directory with a period to make
it hidden.* 


#### Replacing the /wire/ directory

When you put in the new /wire/ directory, make sure that you remove or 
rename the old one first. If you just copy or FTP changed files into
the existing /wire/ directory, you will end up with both old and new
files, which will cause an error. 

Note that the /wire/ directory does not contain any files specific to 
your site, only to ProcessWire. All the files specific to your site 
are stored in /site/ and you would leave that directory alone during 
an upgrade. 


#### Replacing the /index.php file

This file doesn't change often between minor versions. As a result,
you don't need to replace this file unless it has changed. But when
in doubt, you should replace it. 


#### Replacing the .htaccess file

This is also a file that does not always change between versions.
But when it changes, it is usually important for security that you 
are up-to-date. When in doubt, replace your old .htaccess file with
the htaccess.txt from the new version. 

This file is initially named htaccess.txt in the ProcessWire source.
You will want to remove your existing .htaccess file and rename the
new htaccess.txt to .htaccess

Sometimes people have made changes to the .htaccess file. If this is
the case for your site, remember to migrate those changes to the new
.htaccess file. 

**If using ProCache**  
If you are using ProCache, it will have added some things to your 
.htaccess file. Copy these changes from your old .htaccess file to
your new one. The changes are easy to identify in your previous 
.htaccess file as they start and end with a "# ProCache" comment. 
Alternatively, you can have ProCache re-apply the changes itself by
logging in to your admin and going to Setup > ProCache. 


#### Additional upgrade notes

- Completing an upgrade typically requires hitting reload in your 
  browser 1-5 times to apply database updates. If logged into your
  admin, you will see notices about the updates that it is applying
  on each reload. 

- After completing the upgrade test out your site thoroughly
  to make sure everything continues to work as you expect. 

- If using Form Builder make sure you have the latest version,
  as past versions did not support ProcessWire 2.4+. With ProcessWire
  3.0 we recommend FormBuilder 0.2.6+. 

- If using ProCache and you upgraded your .htaccess file, you should 
  go to your ProCache settings after the upgrade to have it update 
  your .htaccess file again. If no upgrades to your .htaccess file
  are necessary, than the ProCache settings page own't mention it.
  
- If using ListerPro, we recommend using version 1.0.9+ with 
  ProcessWire 3.x.
  
  
### Upgrading from ProcessWire 2.7

**Upgrading from 2.7 to 3.x**  

1. Login to the admin of your site. 

2. Edit your /site/config.php and set `$config->debug = true;` to ensure you can
   see error messages. 

3. Replace your /wire/ directory and /index.php file with the ones from here.
   Don't forget the /index.php as it is definitely required (it will tell you
   if you forget). 
   
4. Click a tab page in your admin, such as "Pages". You may notice a delay. 
   This is ProcessWire compiling 3rd party modules into a format that is
   compatible with version 3.x. Keep an eye out for any error messages. 
   If you see any issues, it's possible you may need to upgrade one or more
   3rd party modules. 
   
5. Once you've resolved error messages in your admin, you'll want to test out 
   the front end of your site. Again, expect a delay while ProcessWire compiles
   any files to make them compatible with 3.x. Depending on your template file 
   strategy, updates may or may not be necessary. If you run into any pages 
   that aren't working, see the section further down on troubleshooting.
   
6. When you've confirmed a successful upgrade, remember to restore the 
   `$config->debug` setting back to `false` in your /site/config.php file. 
   

**Upgrading from 2.7 to 2.8.x**  
Follow the general upgrade process by replacing your /wire/ directory and 
index.php file with the new versions. After confirming successful upgrade, then replace or
update your .htaccess file (with the new provided htaccess.txt file). 

**Troubleshooting a 2.7 to 3.x upgrade**
Before we mention anything else, if you run into any troubles with the 3.x
upgrade, you may want to consider upgrading to version 2.8.x instead. It is identical
to 3.x in terms of features, except that it lacks namespace support (just like 2.7). 
Because of that omission, version 2.8 may be more of a turn-key upgrade from 2.7
if that is your preference. 

Any error messages you see in 3.x are likely related to the fact that this
version of the core is now running in a namespace called ProcessWire, rather than
in the root PHP namespace. Error messages will likely mention a file in your 
/site/modules/ directory or a file in your /site/templates/ directory. 

ProcessWire attempts to compile any module or template PHP files that it thinks
will have issues due to namespace. This should work well in most instances.
However, if you come across any instances where it does not work, you may need
to add the ProcessWire namespace to your file. To add the namespace to a file, 
simply edit the file and add this at the very top:

``````````
<?php namespace ProcessWire;
``````````

To prevent ProcessWire from attempting to compile a file, place the text 
`FileCompiler=0` anywhere in the file, and ProcessWire will skip over it. 

  
### Upgrading from ProcessWire 2.6

The general upgrade process may be followed to perform this upgrade.
It is not necessary to replace your .htaccess file. You should replace
these directories/files:

- /wire/
- /index.php
- /COPYRIGHT.txt (if present)
- /LICENSE.txt (if present)
  
### Upgrading from ProcessWire 2.5

The general upgrade process may be followed to perform this upgrade.
In addition, please note the following:

- **New index.php file**
  We recommend replacing your index.php file with this upgrade, though
  it is optional (the changes are not critical). 

### Upgrading from ProcessWire 2.4

The general upgrade process may be followed to perform this upgrade.
In addition, please note the following:

- **New .htaccess and index.php files**    
  While not urgent, you *will* want to replace your [.htaccess](#replacing-the-htaccess-file)
  and [index.php](#replacing-the-indexphp-file) files as part of the upgrade. 
  If you have modified either of those files, it's okay to leave them in 
  place temporarily, as you can still use ProcessWire 2.5 with the old 
  .htaccess and index.php files in place. But we recommend updating them
  when you can. 
  
- **Does your site depend on other sites loading it in an iframe?**  
  Related to the above point, the new .htaccess file contains an option
  that you will need to disable if your site relies upon other sites
  loading yours in an `<iframe>`. If this is your case, please delete or 
  comment out this line in your .htaccess file:   
  `Header always append X-Frame-Options SAMEORIGIN`

- **TinyMCE rich text editor was replaced with CKEditor**    
  2.5 dropped TinyMCE as the rich text editor and replaced it with 
  CKEditor. After installation of 2.7+, you will see an error message
  on any pages that use TinyMCE. From this point, you may either 
  [install TinyMCE](mods.pw/7H) or switch your fields using TinyMCE
  to CKEditor. To switch to CKEditor, go to Setup > Fields > [field] > Details,
  and change the *Inputfield Type* to CKEditor (it may already be
  selected), then be sure to Save. 
  
- **Already have CKEditor or HTML Purifier installed?**   
  A couple of modules that were previously 3rd party (site) modules 
  are now core (wire) modules in ProcessWire 2.7+. If you have either
  the *InputfieldCKEditor* or *MarkupHTMLPurifier* modules installed,
  you will get warnings about that after upgrading. The warnings will 
  tell you to remove the dirs/files for those modules that you have in 
  /site/modules/. Don't be alarmed, as this is not an error, just a 
  warning notice. But it is a good idea to remove duplicate copies 
  of these modules when possible. 


### Upgrading from ProcessWire 2.2 or 2.3

Newer versions of ProcessWire have these additional requirements:

- PHP 5.3.8+ (older versions supported PHP 5.2)
- PDO database driver (older versions only used mysqli)

Please confirm your server meets these requirements before upgrading.
If you are not certain, paste the following into a test PHP file and 
load it from your browser:

```
<?php phpinfo();
```

This will show your PHP configuration. The PHP version should show 
PHP 5.3.8 or newer and there should be a distinct PDO section 
(header and information) present in the output. 

**To proceed with the upgrade** follow the [general upgrade process](#general-upgrade-process)
above. You *will* want to replace your index.php and .htaccess 
files as well.

**In addition** we recommend adding the following line to your 
/site/config.php: 
```
$config->httpHosts = array('domain.com', 'www.domain.com'); 
```
Replace domain.com with the hostname(s) your site runs from.


### Upgrading from ProcessWire 2.1

1. First upgrade to [ProcessWire 2.2](https://github.com/ryancramerdesign/ProcessWire/tree/2.2.9).
2. Follow the instructions above to upgrade from ProcessWire 2.2.


### Upgrading from ProcessWire 2.0

1. [Download ProcessWire 2.2](https://github.com/ryancramerdesign/ProcessWire/tree/2.2.9) 
   and follow the upgrade instructions in that version's [README](https://github.com/ryancramerdesign/ProcessWire/blob/2.2.9/README.txt) 
   file to upgrade from 2.0 to 2.2. 
2. After successfully upgrading to 2.2, follow the general upgrade 
   process above.


### Troubleshooting an Upgrade

If you get an error message when loading your site after an upgrade,
hit "reload" in your browser until the error messages disappear. It
may take up to 5 reloads for ProcessWire to apply all updates. 

If using Form Builder, make sure you have version 0.2.5 or newer, as older
versions did not support ProcessWire 3.x. 

If your site still doesn't work, remove the /wire/ directory completely. 
Then upload a fresh copy of the /wire/ directory. 

If your site still doesn't work, view the latest entries in your error
log file to see if it clarifies anything. The error log can be found in:
/site/assets/logs/errors.txt

If your site still doesn't work, enable debug mode (as described in the 
next section) to see if the more verbose error messages help you to determine
what the issue is. If you need help, please post in the
[ProcessWire support forums](http://processwire.com/talk/). 


## Debug Mode

Debug mode causes all errors to be reported to the screen, which can be
helpful during development or troubleshooting. When in the admin, it also
enables reporting of extra information in the footer. Debug mode is not
intended for live or production sites, as the information reported could
be a problem for security. So be sure not to leave debug mode on for
any live/production sites. 

1. Edit this file: `/site/config.php`
2. Find this line: `$config->debug = false;` 
3. Change the `false` to `true`, like below, and save. 

```
$config->debug = true; 
```

This can be found near the bottom of the file, or you can add it if not
already there. It will make PHP and ProcessWire report all errors, warnings,
notices, etc. Of course, you'll want to set it back to false once you've 
resolved any issues. 


## Support

Get support in the ProcessWire forum at:
[https://processwire.com/talk/](https://processwire.com/talk/)

------

Copyright 2016 by Ryan Cramer / Ryan Cramer Design, LLC

