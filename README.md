## Intro to this fork

This is a fork of processwire. I only make some small changes to the blank template

* change the html5 structure of the _main.php
* make a simple css files structure
* add a simple css reset: credit -> [Josh Comeau](https://www.joshwcomeau.com/css/custom-css-reset/)

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
Now more than a decade later (2023), we’re just getting started, as ProcessWire 
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
stay. ProcessWire is addictive, easy to maintain for the long term, and doesn’t
have the security and upgrade woes of other platforms. But don’t take our word 
for it; unless your livelihood depends on some other platform, find out for 
yourself. 


### Community

ProcessWire is more than just software, it is also a friendly community
of web professionals dedicated to building great sites and applications, and 
helping others do so too. 

Please visit and join our 
[friendly community](https://processwire.com/talk/)
in the ProcessWire forums, subscribe to our
[weekly newsletter](https://processwire.com/community/newsletter/subscribe/)
for the latest ProcessWire news, check out our
[website showcase](https://processwire.com/sites/)
to see what others are building with ProcessWire, and read our 
[blog](https://processwire.com/blog/) 
to stay up-to-date with the latest ProcessWire versions.

Weekly ProcessWire news is posted by Teppo Koivula on his site 
[ProcessWire Weekly](https://weekly.pw). 
Weekly core updates and related topics are posted by Ryan Cramer in the 
ProcessWire support forum 
[News and Announcements](https://processwire.com/talk/forum/7-news-amp-announcements/) 
board. 

### Learn more 

* [ProcessWire website](https://processwire.com)
* [About ProcessWire](https://processwire.com/about/)
* [Support forums](https://processwire.com/talk/)
* [Documentation](https://processwire.com/docs/)
* [API reference](https://processwire.com/api/ref/)
* [Downloads](https://processwire.com/download/)
* [Modules/plugins](https://processwire.com/modules/)
* [Showcase](https://processwire.com/sites/)

-----------------------------------------------------------------

## Installing ProcessWire

Simply extract the ProcessWire files to an http accessible location and
load the URL in your web browser. This will start the installer. See our
[Installation Guide](https://processwire.com/docs/start/install/new/) for more 
details and instructions. If you run into any trouble, please see our 
[Troubleshooting Guide](https://processwire.com/docs/start/install/troubleshooting/). 


## Upgrading ProcessWire

Upgrading is easy and usually just a matter of replacing your `/wire/` directory
with the one from the new version. But to be safe, before proceeding with any version upgrade, please see the
[Upgrading ProcessWire](https://processwire.com/docs/start/install/upgrade/)
guide and perhaps keep it open during your upgrade in case you need to refer back to it. 

When upgrading from one 3.x version to another, please use the 
[general upgrade process](https://processwire.com/docs/start/install/upgrade/#general-upgrade-process).
This consists primarily of making sure you've got everything backed up and then just 
replacing your `/wire/` directory with the one from the newer version.

- If you are upgrading from a 3.x version prior to 3.0.135 then please also follow 
  [these instructions](https://processwire.com/docs/start/install/upgrade/from-3.x/). 

- If you are upgrading from any 2.x version then please see 
  [upgrading from ProcessWire 2.x](https://processwire.com/docs/start/install/upgrade/from-2.x/).

- If you run into any trouble upgrading, please see our 
  [troubleshooting upgrades guide](https://processwire.com/docs/start/install/troubleshooting/#troubleshooting-upgrades).


### Pro module version upgrade notes (if applicable)

- [FormBuilder](https://processwire.com/store/form-builder/)
  version 0.5.3 or newer recommended.
- [ListerPro](https://processwire.com/store/lister-pro/)
  version 1.1.5 or newer recommended. 
- [ProFields](https://processwire.com/store/pro-fields/)
  the latest versions of all ProFields (10 modules) are recommended.
- [LoginRegisterPro](https://processwire.com/store/login-register-pro/)
  version 7 or newer recommended.   
- [ProCache](https://processwire.com/store/pro-cache/)
  version 4.0.3 or newer recommended. After upgrading, go to your ProCache 
  settings in the admin (Setup > ProCache) and see if it suggests any 
  modifications to your .htaccess file.
 
- For all other Pro modules not mentioned above we recommend using the 
  latest available versions when possible.

## Debug Mode

Debug mode causes all errors to be reported to the screen. This can be
helpful during development or troubleshooting. When in the admin, it also
enables a “Debug” link (see footer) for reporting of extra information in a 
panel. Debug mode is not intended for live or production sites, as the 
information reported is for the developer only. Do not leave debug mode 
on for any live/production sites, as it could be a security concern. However, 
we think you'll find it very handy during development or when resolving issues. 

1. Edit this file: `/site/config.php`
2. Find this line: `$config->debug = false;` 
3. Change the `false` to `true` like below, and save. 

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
* [Follow @processwire on X-Twitter](http://twitter.com/processwire/)
* [Contact ProcessWire developer](https://processwire.com/contact/)
* [Report issue](https://github.com/processwire/processwire-issues/issues)

------

Copyright 2023 by Ryan Cramer / Ryan Cramer Design, LLC
