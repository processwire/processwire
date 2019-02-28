Welcome to the Default Site Profile (Intermediate Edition)
==========================================================

This is a plain text document. If you are currently online with 
internet access, you will find it much nicer to read an HTML
formatted version of this document located at:

http://processwire.com/docs/tutorials/default-site-profile/

If you are just getting started with ProcessWire, you might 
also want to look into the beginner edition of this site profile.

Need multi-language support? The multi-language version of this 
default site profile is a good place to start. 

Both the beginner and multi-language versions of this site 
profile are available as installation options when installing 
ProcessWire. 


Introduction
============

Just getting started with ProcessWire and aren't totally clear on what
template files are? The good news is that template files aren't anything 
other than regular HTML or PHP files, and you can use them however you 
want! This particular site profile uses a strategy called Delayed Output,
but you should use whatever strategy you prefer.

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
     Classic Site Profile. While it doesn't scale as well as other
     strategies, it is a very good point to start from. If you've
     ever worked with WordPress templates, chances are you already 
     know how Direct Output works. If you'd rather get started with
     this strategy, we recommend installing the Classic Site Profile
     rather than this one. Read more about the Direct Output strategy:
     http://processwire.com/to/direct-output/

  2. Delayed Output is the strategy used by this site profile. It
     is also quite simple but involves populating content to 
     placeholder variables rather than outputting directly. As a 
     result it may take a few more seconds to understand than direct
     output, but the result is more scalable and maintainable. Read
     more about Delayed Output here: 
     http://processwire.com/to/delayed-output/


How this Default Site Profile works
===================================

This Default Site Profile uses the Delayed Output strategy. Here's
how it works: 

  1. The initialization file is loaded (_init.php).
     We use it to define placeholder variables for content regions.

  2. The template file is loaded (i.e. home.php or another).
     We use it to populate values into the placeholder variables. 

  3. The main output file is loaded (_main.php).
     It is an HTML document that outputs the placeholder variables.

Below are more details on exactly what takes place and in the three
steps outlined above: 

  1. The initialization file is loaded (_init.php)
     ---------------------------------------------
     We define placeholder variables for the regions in our page in
     the _init.php file. These placeholders can be anything that you 
     like (and in any quantity) and usually depends entirely
     on the needs of your site design and content. 

     In this default site, our needs are simple so we've defined 
     placeholders for just 3 regions on the page. We usually name 
     these regions something consistent with the HTML tag, id or class 
     attribute just for ease of readability, but that's not required.
     These are the three placeholder variables we've defined in this site: 

     $title	- The headline or title (we use for <title> and <h1>)
     $content	- The main page content (we use for <div id='content'>)
     $sidebar	- Sidebar content (we use for <div id='sidebar'>)

     The leading "$" is what designates them as placeholder variables. 
     We do this in a file called _init.php. ProcessWire knows to load 
     this _init.php file first, before our actual template file. We 
     define these placeholder variables simply giving each a default 
     value, or by just making them blank. Go ahead and take a look at 
     the _init.php file now if you can. But to summarize, here's how
     you define a blank placeholder variable:
 
     $content = ''; 

     And here's how you define a placeholder variable with an initial
     or default value:

     $content = "<p>Hello World</p>";

     Here's how we would populate it with a dynamic value from $page:

     $content = $page->body; 

     The last thing we want to mention about _init.php is that we 
     might also use it to load any shared functions. You'll see a line
     in this site's _init.php the includes a file called _func.php. 
     That file simply contains a shared function (used by multiple
     template files) for generating navigation markup. This part is 
     not so important for now, so come back to it once you understand
     how everything else works. But the point to understand now is 
     that the _init.php file initializes everything that may be used
     by the site's template files. 


  2. The template file is loaded (i.e. home.php or another)
     ------------------------------------------------------
     Next, ProcessWire loads the template file used by the page being
     viewed. For example, the homepage uses home.php. We use our 
     template file to populate those placeholder variables we defined
     in _init.php with the values we want. 

     For instance, most often we populate our $content variable with 
     the body copy from the current page: 

     $content = $page->body; 

     But we might also do something more like append some navigation 
     under the body copy or prepend a photo... the sky is the limit. 

     $content = "<img src='/photo.jpg'>" . $page->body;

     Our search.php template file for example, populates $content with 
     a list of search results. 

     Because our placeholder variables were already defined in the
     _init.php file with default values, our template file (like 
     home.php or basic-page.php) need only focus on populating the
     placeholder variables that you want to modify. It does not 
     even need to mention those placeholder variables that it doesn't
     need or doesn't need to change. 
   
 
  3. Everything gets output by _main.php
     -----------------------------------
     After ProcessWire has loaded our template file (i.e. home.php) it
     then knows to load the _main.php file last. In the case of this 
     site, our _main.php file is an entire HTML document that outputs 
     our placeholder variables in the regions where they should appear. 
     For example, the $content variable gets output in #content <div>
     like this:

     <div id='content'>
       <?= $content ?>
     </div>

     Please go ahead and take a look at the _main.php file for context.

     Note that our _main.php uses "<?php echo $content; ?>" style,
     rather than "<?= $content ?>" style, like shown above, just in 
     case you happen to be running an older version of PHP. But more 
     than likely you can use the shorter syntax when preferred, as the 
     two are functionally equivalent.


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


