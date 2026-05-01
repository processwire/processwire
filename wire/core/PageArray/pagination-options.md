# Pagination custom options

## List of all options

Below is a list of all available customization options for the 
renderPager() and renderPagination() methods in `PageArray` and `PaginatedArray`.

| Option | Type           | Description                                                                                                                                                               |
| --- |----------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `numPageLinks` | int            | Number of links that the pagination navigation should have, minimum 5<br>(default=10)                                                                                     |
| `getVars` | array          | Get vars that should appear in the pagination, or leave empty and populate $input->whitelist (preferred)<br>(default=[])                                                  |
| `baseUrl` | string         | The baseUrl from which the navigation item links will start<br>(default='')                                                                                               |
| `page` | Page or null   | The current Page, or leave NULL to autodetect<br>(default=null)                                                                                                           |
| `listMarkup` | string         | List container markup. Place {out} where you want the individual items rendered<br>(default=`<ul class='{class}' aria-label='{aria-label}'>{out}</ul>`) |
| `listClass` | string         | Class attribute for the pagination list<br>(default='MarkupPagerNav')                                                                                                     |
| `itemMarkup` | string         | List item markup. Place '{class}' for item class (required), and '{out}' for item content<br>(default=`<li aria-label='{aria-label}' class='{class}' {attr}>{out}</li>`)  |
| `separatorItemMarkup` | string or null | Item separator "...", null makes it use the itemMarkup instead<br>(default=null)                                                                                          |
| `linkMarkup` | string         | Link markup. Place '{url}' for href attribute, and '{out}' for label content<br>(default=`<a href='{url}'><span>{out}</span></a>`)                                        |
| `currentLinkMarkup` | string         | Link markup for current page. Place '{url}' for href attribute and '{out}' for label content<br>(default=`<a href='{url}'><span>{out}</span></a>`)                        |
| `nextItemLabel` | string         | Label used for the 'Next' button <br>(default='Next')                                                                                                                     |
| `previousItemLabel` | string         | Label used for the 'Previous' button <br> (default='Prev')                                                                                                                |
| `separatorItemLabel` | string         | Label used in the separator item <br>(default='…')                                                                                                                        |
| `separatorItemClass` | string         | Class used for list item separator <br>(default='MarkupPagerNavSeparator')                                                                                                  |
| `firstItemClass` | string         | Class used for first item  <br>(default='MarkupPagerNavFirst')                                                                                                                |
| `firstNumberItemClass` | string         | Class used for first numbered nav item  <br>(default='MarkupPagerNavFirstNum')                                                                                                |
| `nextItemClass` | string         | Class used for "next" nav item  <br>(default='MarkupPagerNavNext')                                                                                                            |
| `previousItemClass` | string         | Class used for "previous" nav item  <br>(default='MarkupPagerNavPrevious')                                                                                                    |
| `lastItemClass` | string         | Class added to last nav item  <br>(default='MarkupPagerNavLast')                                                                                                              |
| `lastNumberItemClass` | string         | Class added to last numbered nav item  <br>(default='MarkupPagerNavLastNum')                                                                                                  |
| `currentItemClass` | string         | Class added to current/active nav item  <br>(default='MarkupPagerNavOn')                                                                                                      |
| `currentItemExtraAttr` | string         | Any extra attributes for current item  <br>(default=`aria-current='true'`)                                                                                                    |
| `listAriaLabel` | string         | Aria label for list `<ul>` <br>(default='Pagination links')                                                                                                                  |
| `itemAriaLabel` | string         | Aria label for list item `<li>` <br>(default='Page {n}')                                                                                                                     |
| `currentItemAriaLabel` | string         | Aria label for current list item  <br>(default='Page {n}, current page')                                                                                                      |
| `nextItemAriaLabel` | string         | Aria label for "next" nav item  <br>(default='Next page')                                                                                                                     |
| `previousItemAriaLabel` | string         | Aria label for "prev" nav item  <br>(default='Previous page')                                                                                                                 |
| `lastItemAriaLabel` | string         | Aria label for last nav item  <br>(default='Page {n}, last page')                                                                                                             |


Note: if you intend to render pagination more than once on in the output (such as
above and below a list of products) then render pagination to a variable first,
and then output it before rendering the products, and again after.

~~~~~
// find products
$products = $pages->find("parent=/products/, sort=name, limit=10"); 

// render products
foreach($products as $p) {
    echo "<p><a href='$p->url'>$p->title</a><br>$p->summary</p>";
}

// render pagination
echo $products->renderPagination([
    'numPageLinks' => 5, 
    'nextItemLabel' => "Next <span data-uk-pagination-next></span>",
    'nextItemClass' => '',
    'previousItemLabel' => "<span data-uk-pagination-previous></span> Prev"),
    'previousItemClass' => '',
    'lastItemClass' => '',
    'currentItemClass' => 'uk-active',
    'separatorItemLabel' => '<span>&hellip;</span>',
    'separatorItemClass' => 'uk-disabled',
    'listMarkup' => "<ul class='uk-pagination'>{out}</ul>",
    'itemMarkup' => "<li class='{class}'>{out}</li>",
    'linkMarkup' => "<a href='{url}'>{out}</a>",
    'currentLinkMarkup' => "<span><a href='{url}'>{out}</a></span>",
    'baseUrl' => $page->url(),
]); 
~~~~~
