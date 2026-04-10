# FieldtypeComments

Stores user-posted comments for a page. The value is a `CommentArray` containing `Comment`
objects. Provides built-in rendering for comment lists and submission forms.

---

## Value type

`CommentArray` (extends `PaginatedArray`). Each item is a `Comment` (extends `WireData`).

Each comment's status is stored in a `$comment->status` property, which can have any one of the following values:

| Status constant | Value | Meaning |
|---|---|---|
| `Comment::statusSpam` | -2 | Identified as spam |
| `Comment::statusPending` | 0 | Awaiting moderation |
| `Comment::statusApproved` | 1 | Approved and visible |
| `Comment::statusFeatured` | 2 | Approved and featured |

Each `Comment` object also includes several other properties (`cite`, `text`, `email`, etc.), which are outlined in the section below.

---

## Getting and setting values

```php
// Iterate comments on a page
foreach($page->comments as $comment) {
    echo $comment->cite;        // poster name
    echo $comment->text;        // comment text
    echo $comment->email;       // poster email (not shown publicly by default)
    echo $comment->website;     // poster website
    echo $comment->created;     // unix timestamp
    echo $comment->status;      // see Comment::status* constants
    echo $comment->stars;       // 1–5 or 0 if stars not enabled
    echo $comment->upvotes;
    echo $comment->downvotes;
    echo $comment->depth;       // nesting depth when threading enabled (0=top level)
    // See Comment.php for the full list of Comment properties
}

// Add a new comment programmatically (import, no filtering or notifications)
$comment = new Comment();
$comment->cite = 'John Smith';
$comment->email = 'john@example.com';
$comment->text = 'Great article!';
$comment->status = Comment::statusApproved;
$field = $fields->get('comments'); // get the CommentField object
$field->addComment($page, $comment, false); // false = no filtering/notifications

// Add a comment from user input (triggers moderation filtering and notifications)
$field->addComment($page, $comment, true);

// Update a comment property
$field->updateComment($page, $comment, ['status' => Comment::statusApproved]);

// Delete a comment
$field->deleteComment($page, $comment);

// Get a comment by ID
$comment = $field->getCommentByID($page, 123);

// Get a comment by its approval code (e.g. from a notification email link)
$comment = $field->getCommentByCode($page, $code);

// Count comments on a page
$total = $field->getNumComments($page);
$approved = $field->getNumComments($page, ['minStatus' => Comment::statusApproved]);

// Find comments across all pages in the field (database query)
$comments = $field->find('status=1, sort=-created, limit=10');
$comments = $field->find("pages_id=$page->id, status>=1");
$comments = $field->find('stars>=4, status=1, sort=-created');

// Vote on a comment (requires useVotes enabled on field)
$field->voteComment($page, $comment, true);   // upvote
$field->voteComment($page, $comment, false);  // downvote

// Threaded comments (requires depth > 0 on field)
$parent   = $comment->parent;    // parent Comment or null
$children = $comment->children;  // CommentArray of immediate children
$parents  = $comment->parents;   // CommentArray of all ancestors

// Store/retrieve arbitrary extra data on a comment (stored in the meta column)
$comment->meta('key', 'value');          // set
$value = $comment->meta('key');          // get
$all = $comment->meta();                 // get all as array
$comment->meta(['key1' => 1, 'key2' => 2]); // set multiple at once
$field->updateComment($page, $comment, ['meta' => $comment->meta()]); // save
```

---

## Selectors

Page-level selectors (via `$pages->find()`):

```php
// Pages with at least one approved comment
$pages->find('comments.count>0');

// Match comment text content
$pages->find('comments.data*=keyword');
```

Comment-level selectors (via `$field->find()`):

*This enables you to find comments regardless of which page they appear on.*

```php
// Approved comments, newest first
$field->find('status=1, sort=-created, limit=20');

// Featured comments
$field->find('status=' . Comment::statusFeatured);

// Pending moderation
$field->find('status=' . Comment::statusPending);

// Comments on a specific page only
$field->find("pages_id=$page->id, status>=1");

// Comments with star rating of 4 or above
$field->find('stars>=4, status=1');
```

Examples of supported `find()` selector fields: `id`, `status`, `flags`, `created`, `pages_id`,
`parent_id`, `created_users_id`, `upvotes`, `downvotes`, `stars`, `text`, `cite`, `email`,
`website`, `ip`, `user_agent`.

---

## Output / markup

```php
// Render the comment list (uses CommentList class)
echo $page->comments->render();

// Render only the submission form (uses CommentForm class)
echo $page->comments->renderForm();

// Render both list and form
echo $page->comments->renderAll();

// Pass options to the renderer
echo $page->comments->render([
    'dateFormat' => 'Y-m-d',
    'useGravatar' => '',
]);

// Get object instances for full control
$commentList = $page->comments->getCommentList();
$commentForm = $page->comments->getCommentForm();
echo $commentList->render();
echo $commentForm->render();
```

Rendering can be customized by extending `CommentList` or `CommentForm` (or using the
`CommentListCustom` / `CommentFormCustom` drop-in classes).

---

## Notes

- **Moderation constants** (`moderate` field setting):
  - `FieldtypeComments::moderateNone` (0) — no moderation; all comments published immediately
  - `FieldtypeComments::moderateAll` (1) — all comments require manual approval
  - `FieldtypeComments::moderateNew` (2) — only first-time commenters require approval

- **Votes constants** (`useVotes` field setting):
  - `FieldtypeComments::useVotesNo` (0) — voting disabled
  - `FieldtypeComments::useVotesUp` (1) — upvotes only
  - `FieldtypeComments::useVotesAll` (2) — upvotes and downvotes

- **Stars constants** (`useStars` field setting):
  - `FieldtypeComments::useStarsNo` (0) — disabled
  - `FieldtypeComments::useStarsYes` (1) — optional
  - `FieldtypeComments::useStarsRequired` (2) — required on submission

- `addComment($page, $comment, $send)`: pass `$send=true` when the comment comes from
  live user input (triggers moderation filtering and notification emails); pass `$send=false`
  for programmatic imports where no filtering or emails should run.

- Database columns (one row per comment): `id`, `pages_id`, `parent_id`, `data` (comment text,
  FULLTEXT indexed), `sort`, `status`, `flags`, `created`, `email`, `cite`, `website`, `ip`,
  `user_agent`, `created_users_id`, `code`, `subcode`, `upvotes`, `downvotes`, `stars`, `meta`.

- Compatible fieldtypes: `FieldtypeComments` only.