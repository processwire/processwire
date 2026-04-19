# Konkat Theme — Developer Guide

This document covers the Konkat admin theme for ProcessWire. It is written for two audiences: **module developers** who need their modules to look native inside the admin, and **skin writers** who want to create a new visual variant of the theme.

---

## Background

Konkat is the default theme shipped with AdminThemeUikit starting from ProcessWire 3.0.255. It was built as a skin on top of the existing AdminThemeUikit framework — a practical decision to maintain compatibility with the ProcessWire core and the large body of existing third-party modules, while keeping the scope of the project manageable. It also introduces a native dark theme option.

The most significant architectural change from the previous default is the shift from hardcoded values to **CSS Custom Properties** (CSS variables). In the previous theme, colors were defined in LESS files and compiled into static CSS. Changing a color meant recompiling assets. In Konkat, colors and geometry are defined as runtime variables. This has two practical consequences:

1. **Module developers** can reference theme variables instead of hardcoding colors, so their modules automatically adapt to whatever skin the site is using — including light and dark mode.
2. **Skin writers** can create a completely different-looking theme by overriding a handful of variables in a plain CSS file, with no build step required.

---

## For Module Developers

### Modules using the ProcessWire Inputfield API

If your module uses standard [ProcessWire Inputfields](https://processwire.com/api/ref/inputfield/) , you generally don't need to do anything. The theme styles all standard form elements, buttons, and layout components. Your fields will inherit the correct background, border color, focus states, and typography automatically.

### Modules with custom HTML (Process modules)

When your module renders its own HTML — a dashboard, a custom list, a statistics view, anything that isn't a standard Inputfield — you need to make deliberate choices about how it integrates with the theme.

#### The `pw-wrap` container

The `pw-wrap` class is the primary tool for encapsulating custom content. It gives your HTML the same background, border, and internal spacing used by the core admin panels (like the Page Edit screen and the Templates list). Without it, custom content sits directly on the page background and can look disconnected from the rest of the admin.

```html
<div class="pw-wrap">
    <h2>My Module</h2>
    <p>Content here will look native to the admin.</p>
</div>
```

Use `pw-wrap` when your module renders a distinct content area that should feel like a panel. You don't have to use it — content can also sit directly on the page background if that suits the layout — but it's the right choice for most Process module views.

#### Using Uikit components

You can use standard Uikit 3 components in your module markup. The theme has remapped Uikit's internal color variables to the `--pw-*` system, so components like `.uk-table`, `.uk-card`, `.uk-tab`, `.uk-modal`, and `.uk-badge` will automatically adopt the active skin's colors.

The Uikit documentation applies, with one caveat: because the theme overrides Uikit's defaults, some components will look different from the official Uikit examples. This is intentional — they are styled to match the admin rather than the Uikit default aesthetic. If you use a Uikit component and it looks right in the admin, it's working as intended.

**Containers and sections**: The theme normalizes `.uk-container` and `.uk-section` to align with the admin's internal spacing grid. If you use `.uk-section > .uk-container`, the horizontal padding will match the rest of the admin content, not Uikit's default responsive padding.

#### Using CSS variables in your module

When you need to style elements that aren't covered by Uikit classes, use the `--pw-*` variables directly. This ensures your module adapts to the active skin and responds correctly to light/dark mode.

**In a module CSS file:**

Always scope your selectors to your module's container to prevent styles from leaking into the rest of the admin.

```css
/* Good: scoped to your module */
.MyModule .status-indicator {
    background-color: var(--pw-main-color);
    border: 1px solid var(--pw-border-color);
    color: var(--pw-blocks-background);
}

.MyModule .secondary-label {
    color: var(--pw-muted-color);
}

/* Bad: leaks into the whole admin */
.uk-card {
    border-top: 3px solid #007bff;
}
```

**Inline in PHP templates:**

```php
echo "<div style='background: var(--pw-blocks-background); border: 1px solid var(--pw-border-color); padding: 1rem;'>";
echo $content;
echo "</div>";
```

The one rule: **do not hardcode colors**. A hardcoded `background: #ffffff` will look wrong in dark mode. A hardcoded `color: #2196F3` will clash with a site using a green or red main color. Use the variables instead.

#### Buttons

The easiest way to add buttons in your module markup is to use standard Uikit button classes. The theme supports the full Uikit button component, including button groups and groups with dropdowns. Refer to the [Uikit Button documentation](https://getuikit.com/docs/button) for the complete reference.

**The filled button** — use `.uk-button` or `.uk-button-primary`. Both render the same solid button style, driven by `--pw-button-background` and `--pw-button-color`. In the default Konkat skin these are black (light mode) / white (dark mode), not the main brand color. This is a deliberate choice to match the ProcessWire admin's own button conventions.

```html
<button class="uk-button uk-button-primary">Save</button>
```

**The bordered/ghost button** — use `.uk-button.uk-button-default`. This renders a transparent button with a muted border, which on hover fills with the main color.

```html
<button class="uk-button uk-button-default">Cancel</button>
```

> **Note for Uikit users:** In stock Uikit, `uk-button-default` is the "normal" button and `uk-button-primary` is the highlighted one. In Konkat the roles are reversed: `uk-button-primary` (and plain `uk-button`) is the solid filled button, while `uk-button-default` is the transparent/bordered variant. This is intentional — it keeps the admin's button hierarchy consistent with ProcessWire's own UI — but it may be surprising if you're used to standard Uikit conventions.

**Button groups and dropdowns** work exactly as documented in Uikit:

```html
<div class="uk-button-group">
    <button class="uk-button uk-button-primary">Action</button>
    <div class="uk-inline">
        <button class="uk-button uk-button-primary" type="button">▾</button>
        <div uk-dropdown="mode: click; boundary: !.uk-button-group; boundary-align: true;">
            <ul class="uk-nav uk-dropdown-nav">
                <li><a href="#">Option 1</a></li>
                <li><a href="#">Option 2</a></li>
            </ul>
        </div>
    </div>
</div>
```

**If you need a button that explicitly uses the main brand color** (regardless of the skin's button defaults), use the CSS variables directly:

```css
.MyModule .action-button {
    background-color: var(--pw-main-color);
    color: var(--pw-blocks-background);
    border: var(--pw-button-border);
    border-radius: var(--pw-button-radius);
}

.MyModule .action-button:hover {
    background-color: var(--pw-button-hover-background);
    color: var(--pw-button-hover-color);
}
```

For secondary/muted actions:

```css
.MyModule .secondary-button {
    background-color: var(--pw-button-muted-background);
    color: var(--pw-button-muted-color);
    border: 1px solid var(--pw-button-muted-border);
    border-radius: var(--pw-button-radius);
}
```

#### Alerts and status colors

For feedback elements (success messages, warnings, errors), use the alert variables rather than hardcoded colors:

```css
.MyModule .notice-success {
    background-color: var(--pw-alert-success);
    color: var(--pw-alert-text-color);
}

.MyModule .notice-warning {
    background-color: var(--pw-alert-warning);
    color: var(--pw-alert-text-color);
}

.MyModule .notice-error {
    background-color: var(--pw-alert-danger);
    color: var(--pw-alert-text-color);
}
```

#### Utility classes

The theme provides two small utility classes for quickly applying the main brand color to inline elements without writing custom CSS:

| Name                  | Summary                                                                                                                                    |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `.pw-text-main-color` | Sets `color` to `var(--pw-main-color)`                                                                                                     |
| `.pw-bg-main-color`   | Sets `background-color` and `border-color` to `var(--pw-main-color)`, and `color` to `var(--pw-blocks-background)` (auto-contrasting text) |

```html
<span class="text-main-color">Highlighted label</span>

<span class="uk-badge bg-main-color">Active</span>
```

These are intentionally minimal — they exist for quick inline use. For anything more complex, use the variables directly in your module's CSS file.

---

## For Skin Writers

### How skins are loaded

Skins are plain CSS files. You point the theme to your file via the **Custom CSS file** field in the AdminThemeUikit settings page (under the theme's configuration). Enter a server-relative path, for example:

```
/site/templates/styles/my-skin.css
```

The file is loaded after the theme's own CSS, so your variable definitions override the defaults.

### What a skin file looks like

A skin is just a `:root {}` block that redefines variables. The `borderless.css` example in the `examples/` folder is two lines:

```css
:root {
  --pw-border-color: var(--pw-main-background);
  --pw-inputs-background: var(--pw-blocks-background);
}
```

That's enough to create a visually distinct variant. You don't need to touch any other file.

### Light and dark in a skin

Variables can carry both light and dark values using the `light-dark()` function. The browser resolves the correct value based on the active `color-scheme`:

```css
:root {
    --pw-blocks-background: light-dark(white, #1a1a1a);
    --pw-text-color: light-dark(#111, #f0f0f0);
    --pw-border-color: light-dark(rgba(0,0,0,0.15), #444);
}
```

This means a single skin file handles both modes. You don't need separate light and dark stylesheets.

### The variable reference

These are all the variables defined in `admin-custom.css`. Override any of them in your skin file.

#### Core palette

| Name                | Summary                                                                                                                                                        |
| ------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `--pw-main-color`   | Primary brand color. Used for active states, the logo, and focus indicators. Can be set to `light-dark(lightValue, darkValue)` for separate light/dark colors. |
| `--pw-text-color`   | Primary text color                                                                                                                                             |
| `--pw-muted-color`  | Secondary/muted text, labels, helper text                                                                                                                      |
| `--pw-border-color` | All structural borders and dividers                                                                                                                            |

#### Backgrounds

| Name                     | Summary                                         |
| ------------------------ | ----------------------------------------------- |
| `--pw-main-background`   | The page/app background (behind all panels)     |
| `--pw-inputs-background` | Background for form inputs and muted areas      |
| `--pw-blocks-background` | Background for content panels, cards, `pw-wrap` |

#### Buttons

| Name                           | Summary                           |
| ------------------------------ | --------------------------------- |
| `--pw-button-background`       | Primary button background         |
| `--pw-button-color`            | Primary button text color         |
| `--pw-button-border`           | Primary button border             |
| `--pw-button-muted-background` | Secondary/muted button background |
| `--pw-button-muted-color`      | Secondary button text color       |
| `--pw-button-muted-border`     | Secondary button border           |
| `--pw-button-hover-background` | Button background on hover        |
| `--pw-button-hover-color`      | Button text color on hover        |
| `--pw-button-hover-border`     | Button border on hover            |

#### Masthead

| Name                                       | Summary                                |
| ------------------------------------------ | -------------------------------------- |
| `--pw-masthead-background`                 | Masthead/header background             |
| `--pw-masthead-active-color`               | Active/selected item color in masthead |
| `--pw-masthead-text-color`                 | Default text color in masthead         |
| `--pw-masthead-border-color`               | Masthead border                        |
| `--pw-masthead-logo-color`                 | Logo color                             |
| `--pw-masthead-menu-item-background-hover` | Menu item hover background             |
| `--pw-masthead-input-background`           | Search input background                |
| `--pw-masthead-input-color`                | Search input text color                |
| `--pw-masthead-input-border`               | Search input border                    |

#### Alerts and notices

| Name                    | Summary                       |
| ----------------------- | ----------------------------- |
| `--pw-alert-text-color` | Text color inside alerts      |
| `--pw-alert-primary`    | Primary/info alert background |
| `--pw-alert-warning`    | Warning alert background      |
| `--pw-alert-success`    | Success alert background      |
| `--pw-alert-danger`     | Error/danger alert background |
| `--pw-notes-background` | Notes/annotation background   |

#### Code and inline elements

| Name                           | Summary                      |
| ------------------------------ | ---------------------------- |
| `--pw-code-color`              | Inline code text color       |
| `--pw-code-background`         | Inline code background       |
| `--pw-error-inline-text-color` | Inline validation error text |

#### Geometry

| Name                 | Summary                                                |
| -------------------- | ------------------------------------------------------ |
| `--pw-button-radius` | Corner radius for all buttons (default: fully rounded) |
| `--pw-input-radius`  | Corner radius for all form inputs (default: square)    |

#### Other

| Name                              | Summary                            |
| --------------------------------- | ---------------------------------- |
| `--pw-modal-color`                | Modal overlay/backdrop color       |
| `--pw-menu-item-background-hover` | General menu item hover background |

### Variable relationships

Many variables reference other variables. This is intentional — it creates a cascade where changing a base variable affects multiple components. For example, `--pw-masthead-background` defaults to `var(--pw-blocks-background)`, so changing `--pw-blocks-background` also changes the masthead background unless you override `--pw-masthead-background` separately.

The `masthead.css` example in the `examples/` folder shows how to give the masthead a colored background by overriding just the masthead-specific variables, while leaving the rest of the theme unchanged.

### The example skins

The `examples/` folder contains [three working skin files](https://github.com/processwire/processwire/tree/master/wire/modules/AdminTheme/AdminThemeUikit/themes/default/examples) that demonstrate different approaches:

**`borderless.css`** — Removes visible borders by setting `--pw-border-color` to match the background. Two lines of CSS.

**`masthead.css`** — Gives the masthead a colored background using the main color. Shows how the masthead variable group works together.

**`minimal.css`** — A comprehensive redesign that changes the color palette, button style, and masthead to create a minimal monochrome look. Also demonstrates using `light-dark()` inside a skin for full light/dark control.

These files are commented out in `admin-custom.css` for reference. To use one, copy its contents into your own CSS file.

### The main color setting

The theme settings page provides a color picker for the main color (`--pw-main-color`). This is injected as an inline `<style>` tag in `<head>`, after your custom CSS file. If you define `--pw-main-color` in your skin file, the settings picker will override it unless the user selects "Custom color" and leaves it at the default.

For skins where the main color is integral to the design (like `minimal.css`), set `--pw-main-color` in your skin and instruct users to leave the color picker at its default, or use the "Custom color" option and match it.

---

## TinyMCE editor integration

The theme extends into the TinyMCE rich text editor. When a TinyMCE field uses the default `oxide` skin, the theme automatically replaces it with its own skin (`skin.min.css`) and content stylesheet (`content.css`).

The `content.css` file imports `admin-custom.css`, which means all `--pw-*` variables are available inside the editor. The editor's text color, link color, border color, and code block styles all reference the same variables as the rest of the admin. When a user switches to dark mode, the editor updates along with everything else.

**For skin writers**: you don't need to do anything for TinyMCE. Your variable overrides in the custom CSS file will be picked up by `content.css` automatically, since it imports `admin-custom.css` which is loaded before your file.

**For module developers**: if you configure a TinyMCE field with a custom `content_css` setting, the theme will not override it. The theme only replaces the default wire.css content stylesheet.
