# Welcome to ProcessWire 3.x 

This document is in Markdown. An HTML formatted version of this document 
can be read at: https://github.com/processwire/processwire/blob/master/README.md


## Table of Contents

1. [About](#about-processwire)
2. [Installation](#installing-processwire)
3. [Upgrading](#upgrading-processwire)
4. [Troubleshooting](https://processwire.com/docs/install/troubleshooting/)
5. [Support](#support-and-links)


## About ProcessWire

ProcessWire is an open source content management system (CMS) and web 
application framework aimed at the needs of designers, developers and their 
clients. ProcessWire gives you more control over your fields, templates and 
markup than other platforms, and provides a powerful template system that 
works the way you do. Not to mention, ProcessWire's API makes working with 
your content easy and enjoyable. Managing and developing a site in 
ProcessWire is shockingly simple compared to what you may be used to.

* [ProcessWire Home](https://processwire.com)
* [API Reference](https://processwire.com/api/ref/)
* [Download](https://processwire.com/download/)
* [Support](https://processwire.com/talk/)
* [Modules/Plugins](http://modules.processwire.com)


## Installing ProcessWire

Simply extract the ProcessWire files to an http accessible location and
load the URL in your web browser. This will start the installer. See our
[Installation Guide](https://processwire.com/docs/install/new/) for more 
details and instructions. If you run into any trouble, please see our 
[Troubleshooting Guide](https://processwire.com/docs/install/troubleshooting/). 


## Upgrading ProcessWire

Before proceeding with any version upgrade, please read the
[Upgrading ProcessWire](https://processwire.com/docs/install/upgrade/)
guide and keep it open during your upgrade in case you need to refer back to it. 

If upgrading from one 3.x version to another, please use the 
[General Upgrade Process](https://processwire.com/docs/install/upgrade/#general-upgrade-process).
Chances are that you can upgrade simply by replacing the /wire/ directory. 


### Upgrading from ProcessWire 2.x

If upgrading from ProcessWire 2.5 or older, we recommend that you upgrade
to ProcessWire [2.8](https://github.com/processwire/processwire-legacy) or
[2.7](https://github.com/ryancramerdesign/processwire) first. Both of those 
versions include details in the README file on how to upgrade from these 
older versions of ProcessWire. To upgrade from ProcessWire 2.6 (or newer) 
to ProcessWire 3.x, please follow the instructions below. 

1. Login to the admin of your site. 

2. Edit your `/site/config.php` file and set `$config->debug = true;` to ensure 
   you can see error messages. This is optional but recommended.

3. Replace your `/wire/` directory and `/index.php` file with the new ones from here.
   
4. Click a navigation link in your admin, such as "Pages". You may notice a delay. 
   This is ProcessWire compiling 3rd party modules into a format that is
   compatible with version 3.x. Keep an eye out for any error messages. 
   If you see any issues, it's possible you may need to upgrade one or more
   3rd party modules. If you see messages about it applying updates, keep hitting
   reload in your browser until you no longer see any update messages. 
   
5. Once you've resolved error messages in your admin, you'll want to test out 
   the front end of your site. Again, expect a delay while ProcessWire compiles
   any files to make them compatible with 3.x. Depending on your template file 
   strategy, updates may or may not be necessary. If you run into any pages 
   that aren't working, see the section further down on troubleshooting. 
   Thoroughly test every aspect if your site to ensure that everything is 
   working as you expect. 
   
6. When you've confirmed a successful upgrade, remember to restore the 
   `$config->debug` setting back to `false` in your /site/config.php file. 
   
**Troubleshooting a 3.x upgrade**
If you run into any trouble upgrading, please see our troubleshooting guide
located at <https://processwire.com/download/troubleshooting/#upgrades>.


### Pro module upgrade notes

- If using [FormBuilder](https://processwire.com/api/modules/form-builder/),
  we recommend using only v0.3.0 or newer.
- If using [ProCache](https://processwire.com/api/modules/procache/), 
  we recommend using only v3.1.4 or newer. 
- If using [ListerPro](https://processwire.com/api/modules/lister-pro/), 
  we recommend using only v1.0.9 or newer.
- If using [ProFields](https://processwire.com/api/modules/profields/), 
  we recommend grabbing the latest versions in the ProFields support board. 
- If using ProCache and you upgraded your .htaccess file, you should 
  go to your ProCache settings after the upgrade to have it update 
  your .htaccess file again. If no upgrades to your .htaccess file
  are necessary, then the ProCache settings page won't mention it.
  

## Debug Mode

Debug mode causes all errors to be reported to the screen, which can be
helpful during development or troubleshooting. When in the admin, it also
enables reporting of extra information in the footer. Debug mode is not
intended for live or production sites, as the information reported could
be a problem for security. So be sure not to leave debug mode on for
any live/production sites. However, we think you'll find it very handy
during development or when resolving issues. 

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


## Support and Links

* [ProcessWire Support](https://processwire.com/talk/)
* [ProcessWire Weekly](https://weekly.pw/)
* [ProcessWire Blog](https://processwire.com/blog/)
* [Follow @processwire on Twitter](http://twitter.com/processwire/)
* [Contact ProcessWire](https://processwire.com/contact/)
* [Sites running ProcessWire](https://processwire.com/about/sites/)

------

Copyright 2019 by Ryan Cramer / Ryan Cramer Design, LLC

