# InputfieldTinyMCE

### Rich text editor for ProcessWire 3.0.200+

This is an Inputfield for ProcessWire 3.0.200+ that uses TinyMCE 6. 
Note this is a development version and will eventually be added to the ProcessWire 
core as an alternative or replacement for the existing CKEditor 4.x Inputfield. 

<https://processwire.com/blog/posts/new-rte-for-pw/>

## Install

1. Copy all files and directories from this module into /site/modules/InputfieldTinyMCE/.
2. In your admin go to Modules > Refresh, and click "Install" for InputfieldTinyMCE.
3. Note all the module configuration settings, which you may want to return to later. 

## Usage

1. Create a new Textarea field or edit an existing one (Setup > Fields). 
2. While editing the field, on the "Details" tab, select "TinyMCE" for "Inputfield type".
3. Save. Then while still editing the field, click to the "Input" tab, review the 
   available settings and optionally modify them as needed. Save. 

-----

ProcessWire 3.x, Copyright 2024 by Ryan Cramer  
https://processwire.com

TinyMCE 6.x, Copyright (c) 2022 Ephox Corporation DBA Tiny Technologies, Inc.  
https://www.tiny.cloud/docs/tinymce/6/

---------


## PasteFilter tests (for internal testing purposes)

### MS Word
~~~~~
html = 
   "<p className=MsoNormal>This is <b>bold</b> text. <o:p></o:p></p>\n\n" + 
   "<h2>This is headline 2. <o:p></o:p></h2>\n\n" + 
   "<p className=MsoNormal>This is <I>italic</I> text<o:p></o:p></p>\n\n" + 
   "<p className=MsoListParagraphCxSpFirst style='text-indent:-.25in;mso-list:10 level1 lfo1'>" + 
   "<![if !supportsLists]><span style='iso-bidi-font-family:Aptos;mso-bidi-theme-font:minor-latin'>" + 
   "<span style='so-list:Ignore'>1.<span style='font:7.0pt \"Times New Roman\"'>" +
   "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></span></span><![endif]>One <o:p></o:p></p>";
~~~~~

### Google Docs #1
~~~~~
html = 
   '<meta charset="utf-8"><b id="docs-internal-guid-39578836-7fff-ffe8-df71-0199fecdd34e">' + 
   '<p dir="ltr"><span>This is </span><span>bold</span><span> text.</span></p><br /><p dir="ltr">' + 
   '<span>This is normal text but </span><span>this is italic</span><span>.</span></p><br />' + 
   '<p dir="ltr"><span>A line</span></p><p dir="ltr"><span>Another line without hitting enter twice.</span></p>' + 
   '<br /><p dir="ltr"><span>What about </span><span>bold italic</span><span>?</span></p><h2 dir="ltr">' +  
   '<span>This is headline 2.</span></h2><br /><p dir="ltr"><span>This is a bullet list:</span></p><br />' + 
   '<ul><li dir="ltr" aria-level="1"><p dir="ltr" role="presentation"><span>one</span></p></li>' + 
   '<li dir="ltr" aria-level="1"><p dir="ltr" role="presentation"><span>two is italic</span></p></li>' + 
   '<li dir="ltr" aria-level="1"><p dir="ltr" role="presentation"><span>three</span></p></li></ul><br />' + 
   '<p dir="ltr"><span>Another line of text.</span></p></b>';
~~~~~

### Google Docs #2
~~~~~
html =
   '<meta charset="utf-8"><b id="docs-internal-guid-e372d8f2-7fff-6b68-3080-4c08a524fa8d">' + 
   '<p dir="ltr"><span>bla bla bla&nbsp;</span></p><br />' + 
   '<p dir="ltr"><span>this is a line of text, then the [enter] key is pressed</span></p>' + 
   '<p dir="ltr"><span>here is the second line</span></p><br />' + 
   '<p dir="ltr"><span>this is a line of text, then the [shift+enter] keys are pressed</span><span><br /></span><span>here is the second line</span></p><br />' + 
   '<p dir="ltr"><span>bla bla bla</span></p></b>';
~~~~~