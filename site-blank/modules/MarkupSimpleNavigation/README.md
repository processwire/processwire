
MarkupSimpleNavigation
==============================

## Basic usage

Once installed you can load the module in your template code on demand using $modules variable:

    $menu = $modules->get("MarkupSimpleNavigation");

Simplest is to call render method with no options. It will return the markup string with a
nested UL markup tree on all levels expanded from the root page "/". Containing "parent"
and "current" class attributes on anchors by default. It doesn't print out directly so
you need to use echo to output the markup. This also allows to further manipulate the
returned string or pass it further from when using it in another function.

    $treeMenu = $modules->get("MarkupSimpleNavigation"); // load the module
    echo $treeMenu->render(); // render default menu

**Arguments:**

The render() method accepts some configuration options.

    render($options, $page, $rootPage)

- `$options`, is an array of options
- `$page`, is a page object for the current page
- `$rootPage`, is a page object for the root of the menu


## Default options

    $options = array(
        'parent_class' => 'parent',
        'current_class' => 'current',
        'has_children_class' => 'has_children',
        'levels' => true,
        'levels_prefix' => 'level-',
        'max_levels' => null,
        'firstlast' => false,
        'collapsed' => false,
        'show_root' => false,
        'selector' => '',
        'selector_field' => 'nav_selector',
        'outer_tpl' => '<ul>||</ul>',
        'inner_tpl' => '<ul>||</ul>',
        'list_tpl' => '<li%s>||</li>',
        'list_field_class' => '',
        'item_tpl' => '<a href="{url}">{title}</a>',
        'item_current_tpl' => '<a href="{url}">{title}</a>',
        'xtemplates' => '',
        'xitem_tpl' => '<a href="{url}">{title}</a>',
        'xitem_current_tpl' => '<span>{title}</span>',
        'date_format' => 'Y/m/d',
        'code_formatting' => false,
        'debug' => false
    );
    echo $treeMenu->render($options);


## Same with comments

    $options = array(
        'parent_class' => 'parent',
        // overwrite class name for current parent levels

        'current_class' => 'current',
        // overwrite current class

        'has_children_class' => 'has_children',
        // overwrite class name for entries with children

        'levels' => true,
        // wether to output "level-1, level-2, ..." as css class in links

        'levels_prefix' => 'level-',
        // prefix string that will be used for level class

        'max_levels' => null,
        // set the max level rendered

        'firstlast' => false,
        // puts last,first class to link items

        'collapsed' => false,
        // if you want to auto-collapse the tree you set this to true

        'show_root' => false,
        // set this to true if you want to rootPage to get prepended to the menu

        'selector' => '',
        // define custom PW selector, you may sanitize values from user input

        'selector_field' => 'nav_selector',
        // string (default 'nav_selector') define custom PW selector by using a property or field on a page. Use this setting if you want to overwrite the default nav_selector

        'outer_tpl' => '<ul>||</ul>',
        // template string for the outer most wrapper. || will contain entries

        'inner_tpl' => '<ul>||</ul>',
        // template string for inner wrappers. || will contain entries

        'list_tpl' => '<li%s>||</li>',
        // template string for the items. || will contain entries, %s will replaced with class="..." string

        'list_field_class' => '', // string (default '') add custom classes to each list_tpl using tags like {field} i.e. {template} p_{id}

        'item_tpl' => '<a href="{url}">{title}</a>',
        // template string for the inner items. Use {anyfield} and {url}, i.e. {headline|title}, if field is of type image it will return url to image (first image if multiple)

        'item_current_tpl' => '<a href="{url}">{title}</a>',
        // template string for the active inner items.

        'xtemplates' => '',
        // specify one or more templates separated with a pipe | to use the xitem_tpl and xitem_current_tpl markup

        'xitem_tpl' => '<a href="{url}">{title}</a>',
        // same as 'item_tpl' but for xtemplates pages, can be used to define placeholders

        'xitem_current_tpl' => '<span>{title}</span>',
        // same as 'item_current_tpl' but for xtemplates pages

        'date_format' => 'Y/m/d',
        // default date formatting for Datetime fields and native created/modified

        'code_formatting' => false,
        // enable or disable code indentations and newslines in markup output

        'debug' => false,
        // show some inline information about rendertime and selectors used as html comments

    );
    echo $treeMenu->render($options);


## Overwrite current page or root page

If you want to overwrite starting point for the root page to be another page you could do it like this:

    $rootPage = $pages->get("/en/"); // or any other page that has subpages
    echo $treeMenu->render(null, null, $rootPage); // null at first is to not have to specify options, just use default

Or to even overwrite the current active page

    $currentPage = $pages->get(1242);
    $rootPage = $pages->get("/en/");
    echo $treeMenu->render(null, $currentPage, $rootPage);

## Build a menu using a PageArray instead of a single root page

Since 1.3.3 you can also define a PageArray as the root page argument. Instead of a root it will take the PageArray as the first level entries. This can be used to build a menu from a page select field for example. Assuming you have a page field "navigation_entries" on your home root page:

    $entries = $pages->get("/")->navigation_entries;
    echo $treeMenu->render(null, null, $entries);


## Default markup output example

    <ul>
        <li><a href='/'>Home</a></li>
        <li class='parent has_children'><a href='/about/'>About22</a>
            <ul>
                <li><a href='/about/what/'>Child page example 1</a></li>
                <li><a href='/about/background/'>Child page example 2</a></li>
                <li class='parent has_children'><a href='/about/test/'>test</a>
                    <ul>
                        <li class='current'><a href='/about/test/test2/'>test2</a></li>
                    </ul>
                </li>
            </ul>
            </li>
        <li class='has_children'><a href='/templates/'>Templates</a>
            <ul>
                <li><a href='/templates/template2/'>template2</a></li>
            </ul>
        </li>
        <li><a href='/site-map/'>Site Map</a></li>
    </ul>

## Changelog

### Changes in 1.3.6

- fix issue with root parent children property getting altered when show root option is used

### Changes in 1.3.5

- fix performance issue with large sub branches

### Changes in 1.3.4

- fix issue where PageArray as the root page would get saved on root page and affect other intances called later

### Changes in 1.3.3

- added support for PageArray as the root page argument

### Changes in 1.3.2

- fixed typo in default options xitem_tpl

### Changes in 1.3.1

- Minor refactoring and optimizations
- Added support for having placeholder in inner_tpl's opening tag.

So you can now also add dynamic values to the opening tag like, much like in the item_tpl

```
'inner_tpl' => "<ul id='{name}' class='drop-down'>||</ul>",
```

That will result in {name} being replaced by the name of the page (containing the inner childs)

To hook into the parsing of the the list opening template you can use the ```MarkupSimpleNavigation::getInnerStringOpen```


### Changes in 1.3.0

- Changed module setting *singular* to false. This enables you load the module as a new instance with `$nav = $modules->MarkupSimpleNavigation`. This changes behavior for hooks attached to such an instance as it will only be called for this instance. This allows you to create multiple instances of the module to create different navigations with separate hooks.
- Added support for `Page::listable`. So you can exclude listing of pages in runtime by modifying this page permission. This can be done using a system hook on `Page::listable` and set the hook event to return `false`.
- Added new option **date_format** for `created` and `modified` native page properties
- Added new option **code_formatting**, to enable or disable code formatting (indentation and newlines) of output markup
- Added new option **debug** that will output rendertime and selector infos as commented inline html

### Fix in 1.2.2

- fixed issue with select_level[n] not working on the right level

### added support for nav_selector property/field and selector_leveln (new in 1.2.1)

You can now add a special property "nav_selector' to page(s) to define custom selector for rendering their children. This can be done on runtime or via a custom field added to pages to remotely control the selector. You can configure the name of the field by using the newly added option "selector_field". MarkupSimpleNavigation will look out for it and use it, otherwise it will use the "selector" option.

You can now define selector on a per level basis. Simply use the _leveln suffix on the "selector" option, where n is the level number from the root parent. This is best illustrated by the following example:


    $pages->get(1001)->my_selector = "template=projects";

    $options = array(
      "selector_level1" => "template=ressort",
      "selector_level2" => "start=0, limit=10",
      "selector_level3" => "template=news|events, sort=-created",
      "selector_field" => "my_selector"
    );

    echo $nav->render($options);


Note that "my_selector" has priority and will overwrite any other selector or selector_leveln

### hook for custom item string (new in 1.2.0)

Added support for hooking into the item creation. MarkupSimpleNavigation::getItemString


    $nav = $modules->get("MarkupSimpleNavigation");

    function myItemString(HookEvent $event){
        $child = $event->arguments('page'); // current rendered child page
        // any logic with $child possible here
        if($child->id == 1001){
            // set the return value of this hook to a custom string
            $event->return .= "<a href='$child->url'><span>Some Text</span>$child->title</a>";
        }
    }

    // setup the hook after on ___getItemString($class, $page) method
    $nav->addHookAfter('getItemString', null, 'myItemString');
    echo $nav->render();


### hook for custom list classes (new in 1.1.9)

You can now also hook into the class string added to list templates. This example can be used in templates and adds a additional "youclass" to the page with id 1001.

    $nav = $modules->get("MarkupSimpleNavigation");

    function hookGetListClass(HookEvent $event){
        $child = $event->arguments('page'); // current rendered child page
        $class = $event->arguments('class'); // the class string already present
        // any check on that child to set your custom class
        if($child->id == 1001){
            $event->return .= " yourclass"; // add your custom class to the existing
        }
    }

    // setup the hook after on ___getListClass($class, $page) method
    $nav->addHookAfter('getListClass', null, 'hookGetListClass');
    echo $nav->render();


### alternative urls (new in 1.1.8)

You can now use page fields to set an alternative redirect url to another page. For example {pagefieldname|url} for a item href. Where `pagefieldname` is a page field setup to dereference as "single or false" which is used to get a url to another page.

Usage i.e:

    item_tpl' => "<a href='{redirect_page|url}'>{title}</a>

### "list field class" option (new in 1.1.6)

You can optionally define custom classes using a string that can contain tags for field names of pages.
For example `'list_field_class' => '{template} p{id}'`, will add `' basic-page p1001'` to each `list_tpl` class string.

### "xtemplates" Markup templates (new in 1.1.5)

You can now define custom markup for the item tpl's for pages with template(s) specified using `xtemplates`.
You can specify multiple templates by using `'basic-page|news'`.
Items with this templates will then use the `xitem_tpl'` and `xitem_current_tpl` instead of the standard `item_tpl` and `item_current_tpl`.

### Markup templates (new in 1.1.0)

If you want to define your own markup for the output you can use the options template to overwrite default.

Classes placeholder `%s`
is used in either list or inner list item to define where the classes will be appended (current, parent, etc)

Fields placeholder `{anyfield}`
Can be used on item tpl to output fields you want. You can also use `{headline|title}` so if headline is
empty it will chose title. If it's an image field, it will return the url to image. To output url you
just use `{url}`.


## Installation

1. Put the module folder named as MarkupSimpleNavigation into your site/modules/ folder
2. Go to modules install page and click "check for new modules"
3. Install the module. The module appears under "Markup" section.

