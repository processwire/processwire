# AdminThemeUikit

This document currently covers customization of the Uikit styles, overriding admin theme
template files and markup, and instructions on how to upgrade the core Uikit version.

## Customizing Markup

### Overriding markup files

You can overwrite any of the markup files located in `wire/modules/AdminTheme/AdminThemeUikit/` 
by placing a file with the same name in `/site/templates/AdminThemeUikit/`. You could for example 
overwrite the footer by adding the file `/site/templates/AdminThemeUikit/_footer.php` to your 
installation:

```html
<div>My custom footer</div>
```

The files you can replace include:

 - `_head.php*` - Document `<head>`.
 - `_masthead.php` - Masthead and primary navigation.
 - `_search-form.php` - Search form that appears in the masthead. 
 - `_content.php` -  Main content area.
 - `_content-head.php` - Main content header, including breadcrumbs, headline, etc.
 - `_content-body.php` - Main content body where most output goes. 
 - `_footer.php` -  Footer area.
 - `_offcanvas.php` - Offcanvas navigation bar. 
 - `_body-scripts.php` - Scripts that appear before `</body>`.
 - `_main.php` - The main markup file that includes all the others above. 
 
For example, let's say you wanted to replace the main content `#pw-content-body`
with "Hello World", add file `/site/templates/AdminThemeUikit/_content-body.php`
and place the following in it:

```html
<p>Hello World</p>
```

This replaces all the content of every admin page with your Hello World message. That's not
very useful so let's instead copy the contents of the default `_content-body.php` and use 
that as our starting point, and append our Hello World message within it:

```php
<?php namespace ProcessWire;
if(!defined("PROCESSWIRE")) die(); ?>
<div id='pw-content-body'>
  <?php echo $page->get('body') . $content; ?>
  <p>Hello World</p>
</div>	
```

### Customizing markup with hooks

You can hook into rendering of the admin theme partials:

```php
$wire->addHookAfter('AdminThemeUikit::renderFile', function($event) {
  $file = $event->arguments(0); // full path/file being rendered 
  $vars = $event->arguments(1); // assoc array of vars sent to file
  if(basename($file) === '_footer.php') {
    $event->return = str_replace(
      "ProcessWire",
      "ProcessWire is the best CMS",
      $event->return
    );
  }
});
```

### Additional recognized extra markup regions

You can use `$adminTheme->addExtraMarkup($name, $value)` to add additional markup to
several recognized regions. 

```php
$adminTheme->addExtraMarkup("head", "<script>alert('test!');</script>");
```

The `$value` can be any additional markup that you want to insert and the `$name` can be 
any of the following (in order of appearance): 

- `head` - Inserted before `</head>`.
- `masthead` - Inserted at the end of `div#pw-mastheads`.
- `notices` - Inserted after notifications `ul#notices`.
- `content` - Inserted at end of `div#pw-content-body`. 
- `footer` - Inserted at end of `footer#pw-footer`. 
- `body` - Inserted before `</body>`. 
---

## Customizing CSS

### Summary

You can easily customize AdminThemeUikit in 3 simple steps:

1. Install the ProcessWire [Less](https://processwire.com/modules/less/) module. 
2. Specify the “reno” or “rock” base style in `/site/config.php`.
3. Create and edit `/site/templates/admin.less` and see your changes in the admin.

Either step 2 or 3 can be optional too, more details below. 

### Full instructions

Now that you know what to do, let’s run through 3 steps above again, but with more details: 

#### 1. Install the “Less” module

Download and install the “Less” module from <https://processwire.com/modules/less/>.
This module is required to compile customizations to AdminThemeUikit. If this module is 
not installed then all of the capabilities described here will not be available.

#### 2. Choose a base style 

To proceed, edit the `/site/config.php` file and specify what base style you want to start 
from. Currently you can specify either “reno” or “rock”, like in the example below:
```php 
$config->AdminThemeUikit('style', 'rock'); 
``` 
Below are descriptions of both base styles:

- **Reno:**  The “reno” base style is the default that you see when using AdminThemeUikit. 
  It is named after Tom Reno (aka Renobird) creator of the AdminThemeReno module. The reno
  style of AdminThemeUikit attempts to retain much of the color scheme of Tom’s original theme.
  If you choose to use this style, you can optionally skip step #2 since reno is the default.
   
- **Rock:** The “rock” base style is designed to use the default UIkit UI as much as possible 
  and have only one single primary color that can easily be changed without destroying the 
  overall look and feel. This makes it easy to customize for your client’s color scheme. It is
  named after Bernhard Baumrock who is the creator of this style and the system that
  enables you to customize AdminThemeUikit in ProcessWire. Because this style is largely
  stock Uikit, it is intended as a base to build from for your own custom admin style. While
  it looks quite nice in its stock form, you should also think of it as your blank canvas. 
  
*If you selected the “rock” style and want to see what it looks like before moving to the 
next step, simply reload/refresh the ProcessWire admin in your web browser and it should 
compile and use it.*  

#### 3. Create and edit an admin.less file  

Create a new LESS file named `/site/templates/admin.less` for your customizations and add
a style or two just to test things out. For example:
```less
div { border: 1px solid red; }
```
Save the LESS file and the admin theme will automatically recompile and include your 
change(s). Click reload/refresh in your web browser to start the recompile and see the 
changes. 
   
- Whether using “reno” or “rock”, you can modify any LESS variable from UIkit or the base 
  style, including all UIkit variables like `@global-font-family` or `@global-margin`, and
  so on… there are hundreds of variables available to you. See the LESS variables reference
  later in this document.

- If you are using the “rock” base style, you can set the primary color like this:
  `@rock-primary: blue;`
     
- If there is a compile error, you will see it in an admin error notification. In that case, 
  correct the error in your admin.less file, save and refresh again.
   
- Note that the default compiled CSS file is: `/site/assets/admin.css`. Do not make changes
  to this css file directly as ProcessWire may periodically recompile it during version 
  upgrades and such.

#### Additional notes

- ProcessWire monitors the timestamps of your custom .less file and the resulting .css file.
  If your .less file is newer than the .css file, it will automatically recompile. 

- ProcessWire also monitors for changes to your `$config->AdminThemeUikit` settings. If any
  of those settings change, it will automatically recompile.

- If you are using `@import` statements in your .less file, ProcessWire does not monitor the 
  times of files that are imported. In this case, to trigger a recompile, you should either 
  make some minor change to your admin.less file so the timestamp is updated, or you should 
  use the `recompile` config setting described in the next section. 

#### LESS variables reference

- [Rock style](https://github.com/processwire/processwire/blob/dev/wire/modules/AdminTheme/AdminThemeUikit/uikit-pw/styles/rock.less)
- [Reno style](https://github.com/processwire/processwire/blob/dev/wire/modules/AdminTheme/AdminThemeUikit/uikit-pw/styles/reno.less)
- [Uikit base](https://github.com/uikit/uikit/blob/develop/src/less/components/base.less)
- [Uikit components](https://github.com/uikit/uikit/tree/develop/src/less/components)

#### Further reading

See the README in AdminStyleRock which has additional topics of interest related to 
customizing AdminThemeUikit: <https://github.com/baumrock/AdminStyleRock>

---

### AdminThemeUikit $config settings

The `$config->AdminThemeUikit` array has various settings you can customize in your 
`/site/config.php` file. Below is an example of all settings with their default values:

```php
$config->AdminThemeUikit = [
  'style' => '',
  'recompile' => false,
  'compress' => true, 
  'customCssFile' => '/site/assets/admin.css',
  'customLessFiles' => [ '/site/templates/admin.less' ], 
];
```
When modifying a setting from your `/site/config.php` file, you can specify one or
more of them in a PHP array like above, or you can specify any individually 
using a method call like in the example below. Use whatever definition style you prefer.
```php
$config->AdminThemeUikit('style', 'rock'); 
$config->AdminThemeUikit('compress', false);
```
### Description of all settings

#### `style` (string)

Admin theme base style: `reno`, `rock`, or blank for system default. 
The default value is blank. When blank, ProcessWire uses the current system default, 
which is presently “reno”. 

**Advanced option:** you may also specify a .less filename relative to the ProcessWire 
installation root. However, if you are looking to start a theme from scratch, the 
the “rock” style combined with your own `/site/templates/admin.less` may be what you 
really want. 

#### `recompile` (boolean)

This is a runtime-only setting that when set to `true` forces the recompile of the 
admin theme. When necessary, set this to true for one request, then set it back
to false, otherwise it will recompile the admin theme on every admin page load 
(maybe that’s what you want in some cases too). But in most cases you should not 
need this setting as ProcessWire already monitors your admin.less file for changes. 

#### `compress` (boolean)

When true, compiled CSS will be compressed or minified so that it consumes less
bandwidth. The default value is `true`. You might choose to set this to false when
doing custom admin style development and debugging. Otherwise you should leave this
at the default value, which is true.

#### `customCssFile` (string)

The target custom .css file to compile custom .less file(s) into. The default value is 
`/site/assets/admin.css`. If you modify this value, it must be in a location that 
ProcessWire can write to. Within `/site/assets/` is the only directory that is known
writable to all ProcessWire installations, though individual installations may vary. 

#### `customLessFiles` (array)

These are the custom .less files that you want to be compiled. The default value is 
one file: `/site/templates/admin.less`. The given file(s) must be relative to the 
ProcessWire installation root directory. 

#### Note for `customCssFile` and `customLessFiles` 

Chances are you won’t ever need to change these settings, but just in case you do,
please make note of the following. Your values for these settings should literally 
begin with one of the following paths below. Meaning, the literal words, regardless 
of your actual `/site/` directory name (if you happen to be using something different). 

- `/site/assets/` 
- `/site/templates/` 
- `/site/modules/`

This is because the paths above are automatically converted to their actual values at 
runtime. This ensures the same value works everywhere (development, production, staging, 
etc.) and regardless of whether the site is running from a subdirectory or not. 

Should you choose to use a different location, that’s also okay, but just note that 
ProcessWire will not perform any runtime conversion on it. 


---

## Upgrading the core Uikit version 

*This section is likely only of interest to core or admin theme developers.*

1. Download a fresh copy of Uikit from GitHub:
   <https://github.com/uikit/uikit/archive/refs/heads/develop.zip>

2. For this step choose either option A or B below, depending on what is simpler for you, 
   likely option A: 

   **Option A:** Replace the `/src/` and `/dist/` directories from the old Uikit 
   installation in `AdminThemeUikit/uikit/` with those directories from the new installation.   
   Then you may remove these unnecessary directories from it: 

   - `src/scss/`
   - `src/js/`
   
   **Option B:** Replace the `uikit/` directory with the new one, and then remove unnecessary 
   files and directories in the new `uikit/` directory, including:
   
   - `build/`
   - `tests/`
   - `src/scss/`
   - `src/js/`
   - `*.json`
   - `*.lock`
   - `.*` (all hidden files)
   
3. Edit the `AdminThemeUikit::upgrade` constant and set it to `true` temporarily. 

4. Increment the `AdminThemeUikit::requireCssVersion` constant by 1. This will ensure that 
   custom /site/assets/admin.css files will also be recompiled on individual installations.
   If you don’t want that to happen then skip this step. 

5. In your browser, reload any page in the admin and it will generate a new 
   `pw.min.css` file in the correct location, overwriting the old one.
   
6. Set the `AdminThemeUikit::upgrade` constant back to false. Changes can now be committed.

