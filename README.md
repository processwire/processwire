# Welcome to ProcessWire 3.x 

This document is in Markdown. An HTML formatted version of this document 
can be read at: https://github.com/processwire/processwire/blob/master/README.md


## Table of Contents

1. [About](#about-processwire)
2. [Installation](#installing-processwire)
3. [Upgrading](#upgrading-processwire)
4. [Troubleshooting](https://processwire.com/docs/start/install/troubleshooting/)
5. [Support](#support-and-links)


## About ProcessWire

ProcessWire is a friendly and powerful open source CMS with an API that is a 
joy to use at any scale. It is both a content management system (CMS) and 
framework (CMF) built to save you time and work the way you do. With all custom 
fields, a secure foundation, proven scalability and performance, ProcessWire 
connects all of your content seamlessly, making your job fast, easy and fun.

ProcessWire gives you more control over your fields, templates and markup than 
other platforms, while ProcessWire’s API makes working with your content easy and 
enjoyable. Managing and developing a site in ProcessWire is shockingly simple 
compared to what you may be used to.

ProcessWire is widely trusted by web professionals for its exceptional consistency, 
stability and security; revered by web developers for its API that saves time and 
makes work fun; valued by web designers for its adaptability and flexibility with 
modern website/application content management needs; and loved by clients for its 
no-nonsense interface and ease-of-use in adding, updating and maintaining content. 
New versions of ProcessWire are released just about every week on the
development branch. 


### Background

ProcessWire is a timeless tool for web professionals that has always been 
committed to the long term. It started in 2003, gained the name ProcessWire
in 2006, and has been in active development as an open source project since 2010. 
Now more than a decade later (2020), we're just getting started, as ProcessWire 
continues to grow and develop into the next 10 years and beyond. 

While ProcessWire has been around for a long time, don’t feel bad if you haven’t 
heard of it till today. We are fundamentally different from other projects in 
that we don’t make a lot of noise, we’re not into promotion, we value quality 
over quantity, sustainability over growth, and a friendly community over 
popularity. ProcessWire is designed to be a silent partner, not easily 
identified from the front-end of any website. We don’t aim to be big, we are 
instead focused on being best-in-class. 

Web developers find ProcessWire when the time is right, after they’ve tried 
some other platforms. And once they start using ProcessWire, they tend to 
stay—ProcessWire is addictive, easy to maintain for the long term, and doesn’t
have the security and upgrade woes of other platforms. But don’t take our word 
for it; unless your livelihood depends on some other platform, find out for 
yourself. 


### Community

ProcessWire is more than just software, it is also a friendly community
of web professionals dedicated to building great sites and applications, and 
helping others do so too. Please visit and join our 
[friendly community](https://processwire.com/talk/)
in the ProcessWire forums, subscribe to our
[weekly newsletter](https://processwire.com/community/newsletter/subscribe/)
for the latest ProcessWire news, check out our
[website showcase](https://processwire.com/sites/)
to see what others are building with ProcessWire, and read our 
[blog](https://processwire.com/blog/) 
to stay up-to-date with the latest ProcessWire versions.


### Learn more 

* [ProcessWire website](https://processwire.com)
* [About ProcessWire](https://processwire.com/about/)
* [Support forums](https://processwire.com/talk/)
* [Documentation](https://processwire.com/docs/)
* [API reference](https://processwire.com/api/ref/)
* [Downloads](https://processwire.com/download/)
* [Modules/plugins](https://modules.processwire.com)
* [Showcase](https://processwire.com/sites/)

-----------------------------------------------------------------

## Installing ProcessWire

Simply extract the ProcessWire files to an http accessible location and
load the URL in your web browser. This will start the installer. See our
[Installation Guide](https://processwire.com/docs/start/install/new/) for more 
details and instructions. If you run into any trouble, please see our 
[Troubleshooting Guide](https://processwire.com/docs/start/install/troubleshooting/). 


## Upgrading ProcessWire

Before proceeding with any version upgrade, please see the
[Upgrading ProcessWire](https://processwire.com/docs/start/install/upgrade/)
guide and keep it open during your upgrade in case you need to refer back to it. 


### Upgrading from ProcessWire 3.x (earlier version)

When upgrading from one 3.x version to another, please use the 
[General Upgrade Process](https://processwire.com/docs/start/install/upgrade/#general-upgrade-process).
This consists primarily of making sure you've got everything backed up and then
just replacing your `/wire/` directory with the one from the newest version. 

In addition, if you are currently running any 3.x version prior to 3.0.135, 
you will also want to upgrade your root `.htaccess` file to the newest version:

#### Upgrading your .htaccess file

* If you haven't made any custom modifications to your .htaccess file then you 
  can simply replace the old one with the new one. The new one is in a file 
  named `htaccess.txt` so you'll rename it to `.htaccess` after removing
  your old one (all in the same directory as this README file). 

* If your .htaccess file does have custom modifications, you know what they
  are, and are comfortable applying them to the new one — go ahead and 
  follow the step above and then make those same modifications to the new 
  .htaccess file. 

* If you aren't sure what custom modifications your .htaccess file might 
  have, or how to apply them to the new one, please see this post which will 
  quickly guide you through it:
  [How to upgrade an existing .htaccess file](https://processwire.com/blog/posts/pw-3.0.135/#how-to-update-an-existing-htaccess-file)  

*If you are curious what's new in this latest .htaccess file version, 
please see [this post](https://processwire.com/blog/posts/pw-3.0.135/)
for all the details.* 



### Upgrading from ProcessWire 2.x

If upgrading from ProcessWire 2.5 or older, we recommend that you upgrade
to ProcessWire [2.7](https://github.com/ryancramerdesign/processwire) first. 
This version includes details in the README file on how to upgrade from that
older version of ProcessWire. To upgrade from ProcessWire 2.6 (or newer) 
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
If you run into any trouble upgrading, please see our 
[troubleshooting upgrades guide](https://processwire.com/docs/start/install/troubleshooting/#troubleshooting-upgrades).


### Pro module upgrade notes

- If using [FormBuilder](https://processwire.com/store/form-builder/),
  we recommend using only v0.3.0 or newer, but v0.4.0 or newer if possible.
- If using [ProCache](https://processwire.com/store/pro-cache/), 
  we recommend using only v3.1.4 or newer. 
- If using [ListerPro](https://processwire.com/store/lister-pro/), 
  we recommend using only v1.0.9 or newer.
- If using [ProFields](https://processwire.com/store/pro-fields/), 
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

* [ProcessWire Support Forums](https://processwire.com/talk/)
* [ProcessWire Weekly News](https://weekly.pw/)
* [ProcessWire Blog](https://processwire.com/blog/)
* [Sites running ProcessWire](https://processwire.com/sites/)
* [Subscribe to ProcessWire Weekly email](https://processwire.com/community/newsletter/subscribe/)
* [Submit your site to our directory](https://processwire.com/sites/submit/)
* [Follow @processwire on Twitter](http://twitter.com/processwire/)
* [Contact ProcessWire](https://processwire.com/contact/)

------

Copyright 2020 by Ryan Cramer / Ryan Cramer Design, LLC

