Welcome to the Default/Basic Site Profile (Beginner Edition)
============================================================

This is a plain text document. If you are currently online with 
internet access, you will find it much nicer to read an HTML
formatted version of this document located at:

http://processwire.com/docs/tutorials/default-site-profile/

Are you already somewhat familiar with ProcessWire and/or PHP? You
might also want to look into the Intermediate Edition of this profile.

Need multi-language support? The multi-language version of this 
default site profile is a good place to start. 

Both the intermediate and multi-language versions of this site 
profile are available as installation options when installing 
ProcessWire. 

Introduction
============

Just getting started with ProcessWire and aren't totally clear on what
template files are? The good news is that template files aren't anything 
other than regular HTML or PHP files, and you can use them however you 
want! 

If you know enough to create an HTML or PHP document, then you already 
know how to use ProcessWire template files. The only difference is that
ProcessWire provides your template files with certain variables that 
you may choose to use, or not use. Most notable is the $page variable,
which contains all the fields of text or other information contained
by the page being viewed.

For instance, $page->title contains the text contained in the Title 
field of the current page, and $page->body contains the text for the 
Body field of the current page. You can choose to output those wherever
you want. A really simple template file might look like a regular HTML 
document except for where you want to output the dynamic portions (like 
title and body). Here's an example: 

  <html>
    <head>
      <title><?= $page->title ?></title>
    </head>
    <body>
      <h1><?= $page->title ?></h1>
      <?= $page->body ?>
    </body>
  </html>

That's all that a template file is. Now when we're building something
for real, we like to save ourselves as much work as possible and avoid
writing the same HTML markup in multiple places. In order to do that
we'll usually isolate the repetitive markup into separate files or
functions so that we don't have to write it more than once. That's 
not required of course, but it's a good strategy to save you time and
make it easier to maintain your site further down the road. 

Template file strategies
========================

The two most popular strategies for template files are:

  1. Direct Output is the simplest strategy and the one used by the
     beginner edition of this site profile. While it doesn't scale as 
     well as other strategies, it is a very good point to start from. 
     If you've ever worked with WordPress templates, chances are you 
     already know how Direct Output works. Read more about the Direct 
     Output strategy:
     http://processwire.com/to/direct-output/

  2. Delayed Output is the strategy used by the intermediate edition 
     of this site profile. It is also quite simple but involves 
     populating content to placeholder variables rather than outputting
     directly. As a result it may take a few more seconds to understand
     than direct output, but the result is more scalable and 
     maintainable. Read more about Delayed Output here: 
     http://processwire.com/to/delayed-output/


How this Default Site Profile works (Beginner Edition)
======================================================

This Default Site Profile (beginner edition) uses the Direct Output
strategy. When a page is viewed on your site, here's what happens:

  1. The initialization file is loaded (_init.php).
     Here we use it just to define a shared function for navigation. 

  2. The template file is loaded (i.e. basic-page.php or another).
     It outputs the content for the page.  


Below are more details on exactly what takes place and in these two
steps outlined above: 

  1. The initialization file is loaded (_init.php)
     ---------------------------------------------
     This step is completely optional with direct output, but we find
     it handy to use this file to define our shared functions (if any).
     In the case of this profile, we define a single renderNavTree() 
     function. It is useful to have this as a re-usable function since
     we use it to generate markup for more than one place (specifically,
     for sidebar navigation and for the sitemap). However, if you have
     any confusion about this, ignore it for now and focus on #2 below
     as an initialization file is completely optional. 


  2. The template file is loaded (i.e. basic-page.php or another)
     ------------------------------------------------------
     Next, ProcessWire loads the template file used by the page being
     viewed. For example, most pages here use basic-page.php. 

     The first thing that our template file does is include the HTML
     header markup, which we've put in a file called _head.php:

     include("./_head.php"); 

     The above is simply a PHP function that says "include this file".
     The leading "./" just means "from the current directory". We also
     have an underscore "_" prepended to our filename here as a way
     to identify this as an include file rather than a regular template
     file. While completely optional, the underscore does also make 
     ProcessWire ignore it when looking for new template files, so you
     may find it handy to use this convention in your own include files.
     An alternate would be to use .inc as an extension rather than .php.

     Have a look in the _head.php file now so you can see what's there.
     It is basically half of an HTML file. Now have a look in _foot.php,
     that's the other half. Notice that all the template files that 
     include _head.php at the beginning also include _foot.php at the
     ending. This is to ensure there is a complete HTML document being
     output. 

     To conclude, our template files (using direct output) are focused
     on outputting what goes in-between the _head.php and _foot.php.
     In our case, this is always a <div id='content'>...</div> and 
     optionally a <div id='sidebar'>...</div>. But for your own
     template files you might choose to output something completely 
     different. 

Files that make up this profile
===============================

Here is a summary of what is in each of the files in this directory. 
We also recommend reviewing them in this order: 

- _head.php
  HTML header (top half of HTML document)

- _foot.php
  HTML footer (bottom half of HTML document)

- basic-page.php
  Template file outputting #content and #sidebar columns. This 
  template file is used by most pages in this small site. 

- home.php
  Template file used by homepage. Note that since the homepage uses
  nearly the same layout as the other pages in the site, this 
  template file simply includes basic-page.php. No need two have 
  more than one template file with the same contents. 

- sitemap.php
  Outputs a sitemap of the entire site. 

- search.php
  Outputs results of site search queries. 

- _init.php
  Initialization file that we use to define a shared function for
  generating navigation markup. 


More template file resources
============================

- How do template files work?
  https://processwire.com/api/templates/
  Official documentation on template files. 

- API variables
  https://processwire.com/api/variables/
  We mentioned $page above, but here are all the other API variables 
  your template file can make use of. 

- API cheatsheet
  http://cheatsheet.processwire.com/
  Once you've got the basics down, this cheatsheet is invaluable in 
  describing all the properties and functions available to your
  template files. 


Tutorials that help with template files
=======================================

- Hello Worlds Tutoral, by Ryan Cramer
  http://processwire.com/docs/tutorials/hello-worlds/
  The Hello Worlds tutorial gently introduces ProcessWire and template 
  files, starting from a blank slate.

- "But what if I don't know how to code?", by Joss Sanglier
  http://processwire.com/docs/tutorials/but-what-if-i-dont-know-how-to-code/
  This particular series of tutorials will not only introduce you to 
  ProcessWire, but step by step, will give you those small bits of coding 
  knowledge that will get you going and open up this amazing world of a 
  Content Management Framework.

- Installing a CSS Framework, by Joss Sanglier
  http://processwire.com/docs/tutorials/installing-a-css-framework/
  A quick demonstration about how easy it is to use one of the many CSS 
  frameworks available to designers.

- How to structure your template files, by Ryan Cramer
  http://processwire.com/docs/tutorials/how-to-structure-your-template-files/
  This tutorial contrasts and compares the direct output and delayed
  output strategies and more. It is a very good introduction to using
  ProcessWire template files. 


