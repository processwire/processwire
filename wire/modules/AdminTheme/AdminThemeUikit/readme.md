# AdminThemeUikit

## Wording

We use two terms that are important to know:

* When we use the term `theme` we talk about the admin theme module including all server side features like markup generation etc.
* When we talk about a `style` we refer to a visual style of the theme's stylesheet.

That means one admin theme can have multiple styles. But one theme always belongs to one admin theme (and uses the theme's less sourcefiles).

## Customizations

You can easily customize a theme by adding a LESS file to `/site/templates/admin.less`.

Example:

```less
div { border: 1px solid red; }
```

Simply save the file and the admin theme will catch up the change and recompile the CSS stylesheet. Note that changes in `admin.less` will be applied to ANY style of your admin theme (see next section).

## Using another style

By default the admin theme will use the `reno` style located in `uikit-pw/styles/reno.less`.

While the reno style definitely looks great it is a little hard to make it fit to your clients CI colors. That's why the core now ships with another style called `rock`. The goal of this style is to use the default UIkit UI as much as possible and have only one single main color that can easily be changed without destroying the overall look and feel.

Using another style is easy. Add this to your `/site/config.php`:

```php
$config->adminStyle = "rock";
```

Then you can easily set the main color in `/site/templates/admin.less`:

```less
@rock-primary: red;
```

You can modify ANY variable of the style here including all UIkit variables like `@global-font-family` or `@global-margin` etc...
