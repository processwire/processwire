# HTML Purifier module for ProcessWire

HTML sanitization and validation for ProcessWire. Serves as a front-end to the [HTML Purifier](http://htmlpurifier.org) software.

From htmlpurifier.org:

> "HTML Purifier is a standards-compliant HTML filter library written in PHP. HTML Purifier will not only remove all malicious code (better known as XSS) with a thoroughly audited, secure yet permissive whitelist, it will also make sure your documents are standards compliant, something only achievable with a comprehensive knowledge of W3C's specifications."

## Usage

```
$purifier = $modules->get('MarkupHTMLPurifier');
$cleanHTML = $purifier->purify($dirtyHTML); 
```

To specify custom settings to HTML Purifier, perform set() calls before calling purify(). For example, UTF-8 encoding is assumed, so if you wanted ISO-8859-1 instead, you'd do:

```
$purifier->set('Core.Encoding', 'ISO-8859-1'); 
```

[Full list of HTML Purifier config options](http://htmlpurifier.org/live/configdoc/plain.html)

## Install

- Place the files from this module in /site/modules/MarkupHTMLPurifier/
- In ProcessWire Admin > Modules, click *check for new modules*, and click *install*. 

## Updates

The version number of this module represents the version number of HTML Purifier. I will do my best to keep this module up-to-date with the HTML Purifier version. But before installing this module, you may want to check if a newer version of the HTML Purifier software is available from the [HTML Purifier downloads](http://htmlpurifier.org/download) page.

We are using the *standalone* distribution of HTML Purifier. To update it, download the latest standalone distribution and replace the `htmlpurifier` directory with the new version you downloaded.


------

HTML Purifier by Edward Z. Yang (http://htmlpurifier.org)  

ProcessWire module by Ryan Cramer (http://processwire.com)
