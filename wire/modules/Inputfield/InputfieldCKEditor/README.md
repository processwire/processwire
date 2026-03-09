# CKEditor for ProcessWire

[CKEditor](http://ckeditor.com/) is a web text editor like TinyMCE. 
This module can be used anywhere TinyMCE can be used in ProcessWire. 

CKEditor has a nice *inline* mode that is desirable in the page editor 
when you may have lots of rich text inputs. The reason for this is 
that the page editor loads a lot faster. Some people also prefer 
CKEditor for other reasons. 

This module is tested and confirmed compatible with both repeaters and 
multi-language support. 

1. Go to **Setup > Fields** and locate a textarea field that you want 
   to use CKEditor (or create a new textarea field).

2. When editing the settings for a textarea field, click the **Details** tab. 
   Change the **Inputfield Type** to **CKEditor** and save.

3. While still editing the field settings, click to the **Input** tab for 
   CKEditor-specific settings you may optionally configure. 

--------------------------------------------------------------------------

### Inline Mode vs. Regular Mode

When configuring your CKEditor fields, you may notice the option of either Regular 
Mode or Inline Mode. 

#### When you should use inline mode

The Inline Mode is new to CKEditor 4.x and is preferable if
you need to have several instances of CKEditor on the same page at once. Inline
mode editors are not initialized until hovered, making the initial page load time
faster (at least versus several Regular Mode editors). Another benefit of inline 
mode is that the editor interface and buttons are not visible until the field is 
focused, which some may prefer visually. Another aspect of inline mode worth 
noting is that the editor area is always as tall as the content it contains... 
this may be good or bad, depending on your preferences. We recommend using 
inline mode in these instances:

- Multi-language textarea fields
- Textarea fields in repeaters
- Any instance where you have multiple (3+) CKEditor textarea fields
  in the same page editor. 


#### When you should use regular mode

If you only have one or two instances of CKEditor on a page, you may prefer to 
use Regular Mode because it has been around longer and tends to be more reliable. 
Regular Mode runs in a separate frame, making it more isolated and thus less prone 
to any other CSS or Javascript interference. Regular mode editors are also more
obviously rich text editors, as their interface and buttons are always visible. 
We recommend using Regular Mode in these instances:

- Page editors with only 1-2 CKEditor textarea fields (like `body` and `sidebar`). 
- When you do not want CKEditor to consume too much vertical real estate on the editor page.
- When you want the greatest amount of compatibility with other CKEditor plugins. 

--------------------------------------------------------------------------

## Advanced Tips

This section contains contextual instructions that are linked to from 
within the module. These cover specific needs during field configuration
and you do not need to read this section in order to use CKEditor.


### Custom Editor JS Styles Set

**This is an option that appears in your CKEditor field settings when editing
a textarea field in the ProcessWire field editor. Use this option if you 
want to have a *Styles* dropdown that contains your own custom styles that 
users can choose from your editor.**

#### Instructions

1. Edit your CKEditor field in **Setup > Fields**, and click to the **Input** tab.

2. In the **CKEditor Toolbar** field, enter `Styles` somewhere. Here is an example
   of what my first toolbar line looks like:  
   `Format, Styles, Bold, Italic, -, RemoveFormat`

3. Open the **Custom Editor JS Styles Set** field, and type or paste in:
   `mystyles:/site/modules/InputfieldCKEditor/mystyles.js`
   
   - ProcessWire's default profile comes with a
     [ready-to-go mystyles.js](https://github.com/processwire/site-default/blob/main/modules/InputfieldCKEditor/mystyles.js)
     in the exact location mentioned above. Feel free to use this. 

   - The term **mystyles** is just something we made up, and you
     may use whatever keyword you like, but note you will have to use that same
     keyword in the file itself. In our example below, you will see where we 
     used the term **mystyles** again. If you want to keep things simple, then
     just stick with using mystyles.
     
   - If you don't have a mystyles.js file in /site/modules/InputfieldCKEditor/,
     then you can copy the one in /wire/modules/Inputfield/InputfieldCKEditor/
     to /site/modules/InputfieldCKEditor/mystyles.js.

4. Edit and modify the mystyles.js file to suit your particular 
   needs. Save and you are done. 
   
When editing a page that uses your CKEditor field, you should now see a 
**Styles** dropdown with the styles you specified in your `mystyles.js` file. 
Note that you may not initially see all styles in the dropdown, as they may 
be contextual. For instance, you will not see the styles related to images in the 
example above unless you are focused on an `img` element in your editor. 

You may wish to combine your settings here with a custom editor CSS file
covered in the section below. 

[Read more about the CKEditor styles set option](http://docs.ckeditor.com/#!/api/CKEDITOR.config-cfg-stylesSet). 

---------------------------------------------------------------------

### Custom Editor CSS File

**This option enables you to modify the way that text and other elements 
appear in your editor. This covers how they look in the administrative
environment only, and has nothing to do with the front-end of your site.
Typically you would use this option if you wanted to modify the default
look-and-feel in the editor to something that is more stylistically 
similar to the front-end of your site.**

Example editor CSS files can be found here:

- **Regular Mode:** /site/modules/InputfieldCKEditor/contents.css
- **Inline Mode:** /site/modules/InputfieldCKEditor/contents-inline.css

You can use this files directory, or you can copy them somewhere else. 
However, since they are located in your /site/ directory, they won't
get overwritten during upgrades. For the same reason, it's also possible
that you don't have those files in the location mentioned above. If
that's the case, you can copy the versions in /wire/modules/Inputfield/InputfieldCKEditor/
instead, and I recommend putting them in /site/modules/InputfieldCKEditor/.

*There is also a Sass (SCSS) file called `contents-inline.scss` which you
may prefer to use as your starting point if using Sass. Just remember to
compile it to a contents-inline.css file when done. The Sass file is located
here: /wire/modules/Inputfield/InputfieldCKEditor/contents-inline.scss* 

As you can see above, you will be using a different CSS file depending
on whether you need to modify the styles in a regular editor, or an
inline-mode editor. 

1. Modify the file(s) mentioned above and style the elements as you wish,
   or you may return to this step later. If using more than one CKEditor
   inline editor in your page, please see the important note below these
   instructions. 

3. Edit your textarea field that uses CKEditor in **Setup > Fields > your_field**.
   Go to the **Input** tab and see the **Custom Editor CSS File** option. Type
   or paste in the location of the file you copied in step 1 (relative 
   to web root). Example: `/site/modules/InputfieldCKEditor/contents.css`. Save. 

4. Your editor should now be using your new `contents.css` or
   `contents-inline.css` file rather than the defaults. Note that sometimes it
   can be difficult to see the results of your new file immediately, 
   especially in regular mode, due to browser caching. If you run into trouble,
   quit your browser and re-open (or use Incognito mode).

#### Important Note About Inline Mode (contents-inline.css)

If using more than one CKEditor inline mode editor on your page, and 
not using the same `contents-inline.css` file on all of them, then you
will end up having them compete with each other or fall back to the 
default `contents-inline.css`. In order to avoid this, you would need
to make your CSS selectors more specific to the field they apply to.

Lets say that you had a field named `body`. You could target it specifically
in your `contents-inline.css` (or whatever you named the file), by replacing 
this CSS selector...
```
.InputfieldForm .InputfieldCKEditorInline 
```
...with this CSS selector:
```
.InputfieldForm .Inputfield_body .InputfieldCKEditorInline 
```
Note the `.Inputfield_body` part, as `body` is the name of the field 
in ProcessWire. 

If you will use the same `contents-inline.css` file for all of your
inline fields, then just make sure it is specified with the settings for 
each of your CKEditor inline textarea fields, otherwise you may end up 
reverting back to the default `contents-inline.css`.