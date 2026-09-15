<?php namespace ProcessWire;

/**
 * Custom Page class for pages using the “home” template
 *
 * Page classes map to templates by name: template “home” uses class “HomePage”,
 * template “basic-page” uses class “BasicPagePage”, template “blog-post” would
 * use “BlogPostPage”, and so on. Custom page classes must extend class “Page”,
 * or a class derived from it.
 *
 * Add methods here for logic specific to the homepage.
 *
 * @property string $title
 * @property string $summary
 * @property string $body
 * @property Pageimage|null $image
 *
 */
class HomePage extends Page {
}
