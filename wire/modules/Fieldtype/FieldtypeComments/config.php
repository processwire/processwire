<?php namespace ProcessWire;

/**
 * FieldtypeComments field configuration
 * 
 * @param Field $field
 * @param InputfieldWrapper $inputfields
 * @throws WireException
 * @throws WirePermissionException
 * 
 */
function FieldtypeComments_getConfigInputfields(Field $field, InputfieldWrapper $inputfields) {
	
	/** @var CommentsField $field */
	
	$modules = $field->wire()->modules;
	$config = $field->wire()->config;
	$disabledLabel = __('Disabled');
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Behavior');
	$fieldset->icon = 'comment-o';
	$inputfields->add($fieldset);
	
	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$name = 'moderate';
	$f->attr('name', $name);
	$f->addOption(FieldtypeComments::moderateNone, __('None - Comments posted immediately'));
	$f->addOption(FieldtypeComments::moderateAll, __('All - All comments must be approved by user with page edit access'));
	$f->addOption(FieldtypeComments::moderateNew, __('Only New - Only comments from users without prior approved comments require approval'));
	$f->attr('value', (int) $field->$name);
	$f->label = __('Comment moderation');
	$f->description = __('This determines when a newly posted comment will appear on your site.');
	$fieldset->append($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'redirectAfterPost';
	$f->attr('name', $name);
	$f->attr('value', 1);
	$f->attr('checked', $field->$name ? 'checked' : '');
	$f->label = __('Redirect after comment post?');
	$f->description = __('When checked, ProcessWire will issue a redirect after the comment is posted in order to prevent double submissions. Recommended.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'quietSave';
	$f->attr('name', $name);
	$f->attr('value', 1);
	$f->attr('checked', $field->$name ? 'checked' : '');
	$f->label = __('Quiet save?');
	$f->columnWidth = 50;
	$f->description = __('When checked, the page modification time and user will not be updated when a comment is added.');
	$fieldset->append($f);
	
	/*
	$name = 'notificationType';
	$f = $modules->get('InputfieldRadios');
	$f->attr('name', $name);
	$f->label = __('Notification Type');
	$f->addOption(FieldtypeComments::notificationNone, __('Do not send notifications'));
	$f->addOption(FieldtypeComments::notificationEmail, __('Send notifications to specific email address'));
	$f->addOption(FieldtypeComments::notificationCreated, __('Send notifications to user that created the page'));
	$f->addOption(FieldtypeComments::notificationUser, __('Send notifications to specific user')); 
	$f->attr('value', $field->$name);
	$inputfields->append($f);
	*/
	
	// ----------------------------
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Notifications');
	$fieldset->icon = 'bell-o';
	$inputfields->add($fieldset);
	
	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$name = 'notificationEmail';
	$f->attr('name', $name);
	$f->attr('value', $field->$name);
	$f->label = __('Admin notification email');
	$f->description = __('E-mail address to be notified when a new comment is posted. Separate multiple email addresses with commas or spaces.') . ' ';
	$f->description .= __('Users receiving this email will have the ability to approve or deny posts directly from links in the email.');
	$f->notes =
		__('In addition to (or instead of) email addresses, you may also use one or more of the following:') . "\n" .
		__('1. Enter **user:karen** to email a specific user, replacing "karen" with the name of the actual user.') . "\n" .
		__('2. Enter **field:email** to pull the email from a field on the page, replacing "email" with name of field containing email address.') . "\n" .
		__('3. Enter **123:email** to pull the email from an given page ID and field name, replacing "123" with the page ID and "email" with name of field containing email address.') . "\n" .
		__('4. Enter **/path/to/page:email** to pull the email from an given page path and field name, replacing "/path/to/page" with the page path and "email" with name of field containing email address.');
	$fieldset->append($f);
	
	/** @var InputfieldEmail $f */
	$f = $modules->get('InputfieldEmail');
	$name = 'fromEmail';
	$f->attr('name', $name);
	$f->attr('value', $field->$name);
	$f->label = __('Notifications from email');
	$f->description = __('Optional e-mail address that notifications will appear from. Leave blank to use the default server email.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'notifySpam';
	$f->attr('name', $name);
	$f->attr('value', 1);
	if($field->$name) $f->attr('checked', 'checked');
	$f->label = __('Send e-mail notification on spam?');
	$f->description = __('When checked, ProcessWire will still send you an e-mail notification even if the message is identified as spam.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$name = 'useNotify';
	$f->attr('name', $name);
	$f->label = __('Allow commenter e-mail notifications?');
	$f->description = __('This option enables anyone that posts a comment to receive email notifications of new comments.');
	$f->addOption(0, $disabledLabel);
	$f->addOption(Comment::flagNotifyReply, __('Users can receive email notifications of replies to their comment only'));
	$f->addOption(Comment::flagNotifyAll, __('Users can receive email notifications for all new comments on the page'));
	$f->attr('value', (int) $field->get('useNotify'));
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'useNotifyText';
	$f->attr('name', $name);
	$f->attr('value', 1);
	if($field->$name) $f->attr('checked', 'checked');
	$f->label = __('Allow comment text in notifications?');
	$f->description = __('When checked, the entire comment text will also appear in the notification emails, rather than just a link to it.');
	$f->columnWidth = 50;
	$f->showIf = 'useNotify>0';
	$fieldset->append($f);
	
	// ---------------------------------------
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Spam');
	$fieldset->icon = 'fire-extinguisher';
	$inputfields->add($fieldset);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'useAkismet';
	$f->attr('name', $name);
	$f->attr('value', 1);
	$f->attr('checked', $field->$name ? 'checked' : '');
	$f->label = __('Use Akismet Spam Filter Service?');
	$f->description = __('This service will automatically identify most spam. Before using it, please ensure that you have entered an Akismet API key under Modules > Comment Filter: Akismet.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$name = 'deleteSpamDays';
	$f->attr('name', $name);
	$value = $field->$name;
	if(is_null($value)) $value = 3; // default
	$f->attr('value', $value);
	$f->label = __('Number of days after which to delete spam');
	$f->description = __('After the number of days indicated, spam will be automatically deleted.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	// ---------------------------------------
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Output');
	$fieldset->icon = 'comments-o';
	$inputfields->add($fieldset);
	
	/** @var InputfieldInteger $f */
	$f = $modules->get('InputfieldInteger');
	$name = 'depth';
	$f->attr('name', $name);
	$f->attr('value', (int) $field->$name);
	$f->label = __('Reply depth');
	$f->description = __('Specify 0 for traditional flat chronological comments. For threaded comments (replies appear with comment being replied to) specify the maximum depth allowed for replies (0 to 4 recommended).');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'sortNewest';
	$f->attr('name', $name);
	$f->attr('value', 1);
	$f->attr('checked', $field->$name ? 'checked' : '');
	$f->label = __('Sort newest to oldest?');
	$f->description = __('By default, comments will sort chronologically (oldest to newest). To reverse that behavior check this box.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldCheckbox $f */
	$f = $modules->get('InputfieldCheckbox');
	$name = 'useWebsite';
	$f->attr('name', $name);
	$f->attr('value', 1);
	$f->attr('checked', $field->$name ? 'checked' : '');
	$f->label = __('Use website field in comment form?');
	$f->description = __('When checked, the comment submission form will also include a website field.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldText $f */
	$f = $modules->get('InputfieldText');
	$name = 'dateFormat';
	$f->attr('name', $name);
	$f->attr('value', $field->get('dateFormat') ? $field->get('dateFormat') : 'relative');
	$f->label = __('Date/time format (for comment list)');
	$f->description =
		__('Enter the date/time format you want the default comment list output to use.') . ' ' .
		sprintf(
			__('May be a PHP [date](%s) or [strftime](%s) format.'),
			'https://php.net/manual/en/function.date.php',
			'https://php.net/manual/en/function.strftime.php'
		) . ' ' .
		__('May also be "relative" for relative date format.');
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$name = 'useVotes';
	$f->attr('name', $name);
	$f->label = __('Allow comment voting?');
	$f->description = __('Comment voting enables visitors to upvote and/or downvote comments. Vote counts are displayed alongside each comment. Only one upvote and/or downvote is allowed per comment, per IP address, per hour.');
	$f->addOption(0, __('Voting off'));
	$f->addOption(1, __('Allow upvoting'));
	$f->addOption(2, __('Allow upvoting and downvoting'));
	$f->attr('value', (int) $field->$name);
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$name = 'useStars';
	$f->attr('name', $name);
	$f->label = __('Use stars rating?');
	$f->description = __('Star ratings enable the commenter to rate the subject they are commenting on, using a scale of 1 to 5 stars.');
	$f->notes = __('To change default star used for output (HTML is okay too):') . "\nCommentStars::setDefault('star', '★');";
	$f->addOption(0, _x('Disabled', 'star-rating'));
	$f->addOption(1, __('Yes (star rating optional)'));
	$f->addOption(2, __('Yes (star rating required)'));
	$f->attr('value', (int) $field->$name);
	$f->columnWidth = 50;
	$fieldset->append($f);
	
	/** @var InputfieldRadios $f */
	$f = $modules->get('InputfieldRadios');
	$name = 'useGravatar';
	$f->attr('name', $name);
	$f->addOption('', $disabledLabel);
	$f->addOption('g', __('G: Suitable for display on all websites with any audience type.'));
	$f->addOption('pg', __('PG: May contain rude gestures, provocatively dressed individuals, the lesser swear words, or mild violence.'));
	$f->addOption('r', __('R: May contain such things as harsh profanity, intense violence, nudity, or hard drug use.'));
	$f->addOption('x', __('X: May contain hardcore sexual imagery or extremely disturbing violence.'));
	$f->attr('value', $field->get('useGravatar'));
	$f->label = __('Use Gravatar?');
	$f->description = __('This service provides an avatar image with each comment (unique to the email address). To enable, select the maximum gravatar rating. These are the same as movie ratings, where G is the most family friendly and X is not.');
	$f->notes = __('Rating descriptions provided by [Gravatar](https://en.gravatar.com/site/implement/images/).');
	$fieldset->append($f);
	
	// -----------------------------
	
	/** @var InputfieldFieldset $fieldset */
	$fieldset = $modules->get('InputfieldFieldset');
	$fieldset->label = __('Implementation');
	$fieldset->icon = 'file-code-o';
	$fieldset->description = __('This section is here to help you get started with outputting comments on the front-end of your site. Everything here is optional.');
	$fieldset->notes = __('If using a cache for output, configure it to bypass the cache when the GET variable "comment_success" is present.');
	$inputfields->add($fieldset);
	
	/** @var InputfieldMarkup $f */
	$f = $modules->get('InputfieldMarkup');
	$f->label = __('PHP code to output comments');
	$f->value =
		"<p>Please copy and paste the following into your template file(s) where you would like the comments to be output:</p>" .
		"<pre style='border-left: 4px solid #ccc; padding-left: 1em;'>&lt;?php echo \$page-&gt;{$field->name}-&gt;renderAll(); ?&gt;</pre>" .
		"<p>For more options please see the <a href='https://processwire.com/api/fieldtypes/comments/' target='_blank'>comments documentation page</a>.</p>";
	$fieldset->add($f);
	
	/** @var InputfieldMarkup $f */
	$f = $modules->get('InputfieldMarkup');
	$f->label = __('CSS for front-end comments output');
	$ftUrl = $config->urls('FieldtypeComments');
	$f->value =
		"<p>Please copy and paste the following into the document <code>&lt;head&gt;</code> of your site:</p>" .
		"<pre style='border-left: 4px solid #ccc; padding-left: 1em;'>&lt;link rel='stylesheet' type='text/css' href='&lt;?=\$config-&gt;urls-&gt;FieldtypeComments?&gt;comments.css' /&gt;</pre>" .
		"<p>Or if you prefer, copy the <a target='_blank' href='{$ftUrl}comments.css'>comments.css</a> file to your own location, " .
		"modify it as desired, and link to it in your document head as you do with your other css files.</p>";
	$fieldset->add($f);
	
	/** @var InputfieldMarkup $f */
	$f = $modules->get('InputfieldMarkup');
	$f->label = __('JS for front-end comments output');
	$f->value =
		"<p>If you are using threaded comments (i.e. reply depth > 0), please also copy and paste the following into the document <code>&lt;head&gt;</code> " .
		"or before the closing <code>&lt;/body&gt;</code> tag. In either case, jQuery is required to have been loaded first.</p>" .
		"<pre style='border-left: 4px solid #ccc; padding-left: 1em;'>&lt;script type='text/javascript' src='&lt;?=\$config-&gt;urls-&gt;FieldtypeComments?&gt;comments.min.js'&gt;&lt;/script&gt;</pre>" .
		"<p>Like with the comments.css file, feel free to copy and link to the <a target='_blank' href='{$ftUrl}comments.js'>comments.js</a> file from your own " .
		"location if you prefer it.</p>";
	$fieldset->add($f);
	
	/** @var InputfieldHidden $f */
	$f = $modules->get('InputfieldHidden');
	$name = 'schemaVersion';
	$f->attr('name', $name);
	$value = (int) $field->$name;
	$f->attr('value', $value);
	$f->label = 'Schema Version';
	$inputfields->append($f);
}
