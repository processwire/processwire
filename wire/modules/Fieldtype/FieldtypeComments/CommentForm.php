<?php namespace ProcessWire;

/**
 * ProcessWire CommentFormInterface and CommentForm
 *
 * Defines the CommentFormInterface and provides a base/example of this interface with the CommentForm class. 
 *
 * Use of this is optional, and it's primarily here for example purposes. 
 * You can make your own markup/output for the form directly in your own templates. 
 * 
 * ProcessWire 3.x, Copyright 2016 by Ryan Cramer
 * https://processwire.com
 *
 */

/**
 * Interface for building CommentForms, followed by an example/default implementation in the CommentForm class
 *
 */
interface CommentFormInterface {
	public function __construct(Page $page, CommentArray $comments, $options = array());
	public function render(); 
	public function processInput();
}

/**
 * Default/example implementation of the CommentFormInterface
 * 
 * Generates a user input form for comments, processes comment input, and saves to the page
 *
 * @see CommentArray::renderForm()
 *
 */
class CommentForm extends Wire implements CommentFormInterface {

	/**
	 * Page object where the comment is being submitted
	 * 
	 * @var Page
	 *
	 */
	protected $page; 

	/**
	 * Reference to the Field object used by this CommentForm
	 * 
	 * @var CommentField
	 *
	 */
	protected $commentsField; 

	/**
	 * Instance of CommentArray, containing all Comment instances for this Page
	 * 
	 * @var CommentArray
	 *
	 */
	protected $comments;

	/**
	 * Key/values of the fields that are input by the user
	 *
	 */
	protected $inputValues = array(
		'cite' => '',
		'email' => '',
		'text' => '',
		'notify' => '', 
	); 

	protected $postedComment = null;

	/**
	 * Default options to modify the behavior of this class and it's output
	 *
	 */
	protected $options = array(
		'headline' => '',	// Headline (with markup) above form, or specify false for no headline 
		'successMessage' => '',	// Success messsage with markup. "<p>Thank you, your submission has been saved</p>"
		'pendingMessage' => '', // Comment pending message with markup. "<p>Your comment has been submitted and will appear once approved by the moderator.</p>"
		'errorMessage' => '',	// Error message with markup. "<p>Your submission was not saved due to one or more errors. Try again.</p>"
		'processInput' => true, 
		'encoding' => 'UTF-8', 
		'attrs' => array(
			'id' => 'CommentForm', 
			'action' => './', 
			'method' => 'post',
			'class' => '',
			'rows' => 5,
			'cols' => 50,
			'form' => '', // any extra attributes for form element
		),
		'inputWrapTag' => 'p', // tag that wraps label/inputs
		'labels' => array(
			'headline' => '', // Post Comment
			'cite' => '',	// Your Name
			'email' => '',	// Your E-Mail
			'website' => '',// Website
			'stars' => '',	// Your Rating 
			'text' => '',	// Comments
			'submit' => '', // Submit
			'starsRequired' => '', // Please select a star rating
			'success' => '', 
			'pending' => '',
			'error' => '',
			'notify' => '', // E-Mail Notifications:
			'notifyReplies' => '', 
			'notifyAll' => '',
			'notifyOff' => '',
		),
		'classes' => array(
			'form' => '',
			'wrapper' => '', // when specified, wrapper inside <form>...</form>
			'label' => '',
			'labelSpan' => '',
			'cite' => 'CommentFormCite {id}_cite',
			'citeInput' => 'required',
			'email' => 'CommentFormEmail {id}_email',
			'emailInput' => 'required email',
			'text' => 'CommentFormText {id}_text',
			'textInput' => 'required',
			'website' => 'CommentFormWebsite {id}_website',
			'websiteInput' => 'website',
			'stars' => 'CommentFormStars {id}_stars',
			'starsRequired' => 'CommentFormStarsRequired',
			'honeypot' => 'CommentFormHP {id}_hp',
			'notify' => 'CommentFormNotify',
			'radioLabel' => '',
			'radioInput' => '',
			'submit' => 'CommentFormSubmit {id}_submit',
			'submitButton' => '',
			'success' => 'success',
			'pending' => 'pending success',
			'error' => 'error',
		),
		'markup' => array(
			'headline' => "<{tag}>{headline}</{tag}>", // feel free to replace {tag} with "h1", "h2", "h3"
			'notification' => "<p class='{class}'><strong>{message}</strong></p>",
			'wrapNotification' => "<div id='CommentPostNote'>{out}</div>", 
			'wrapAll' => "<div id='{id}' class='{class}'>{headline}{note}{form}</div><!--/{id}-->",
		),

		// values that will be already set, perhaps pulled from a user profile for instance (null = ignore)
		// note that using presets is NOT cache safe: do not use for non-logged-in users if output caches are active
		'presets' => array(
			'cite' => null,
			'email' => null, 
			'website' => null,
			'text' => null,
		),

		// whether or not the preset values above will be changeable by the user
		// applies only for preset values that are not null. 
		'presetsEditable' => false,

		// the name of a field that must be set (and have any non-blank value), typically set in Javascript to keep out spammers
		// to use it, YOU must set this with a <input hidden> field from your own javascript, somewhere in the form
		'requireSecurityField' => '',

		// the name of a field that must NOT be set 
		// creates an input field that a (human) visitor should ignore, maybe hiding it with css is a good idea
		'requireHoneypotField' => '',

		// should a redirect be performed immediately after a comment is successfully posted?
		'redirectAfterPost' => null, // null=unset (must be set to true to enable)
	
		// use threaded comments?
		'depth' => 0, 
		
		// When a comment is saved to a page, avoid updating the modified time/user
		'quietSave' => false,
	
		// default value for the notify option (when used)
		'notifyDefault' => 0, 
	
		// interial use: have options been initialized and are ready to use?
		'_ready' => false, 

	);


	/**
	 * Construct a CommentForm
	 *
	 * @param Page $page The page with the comments
	 * @param CommentArray $comments The field value from $page
	 * @param array $options Optional modifications to default behavior (see CommentForm::$options)
	 *
	 */
	public function __construct(Page $page, CommentArray $comments, $options = array()) {
		
		parent::__construct();

		$this->page = $page;
		$this->comments = $comments;
		
		// determine which field on the page is the commentsField and save the Field instance
		$this->commentsField = $this->comments->getField();
		
		/*
		foreach($this->wire('fields') as $field) {
			if(!$field->type instanceof FieldtypeComments) continue; 
			$value = $this->page->get($field->name); 
			if($value === $this->comments) {
				$this->commentsField = $field;
				break;
			}
		}
		*/
		
		// populate the value of redirectAfterPost
		if($this->commentsField && is_null($this->options['redirectAfterPost'])) {
			$this->options['redirectAfterPost'] = (bool) $this->commentsField->redirectAfterPost;
		}
		if($this->commentsField && $this->commentsField->quietSave) {
			$this->options['quietSave'] = true; 
		}
		
		$this->setOptions($options);
	}

	/**
	 * Initialize and set options
	 * 
	 * @param array $options
	 * 
	 */
	public function setOptions(array $options) {
	
		if(!$this->options['_ready']) {
			// default labels
			$this->options['labels'] = array(
				'headline' => '', // $this->_('Post Comment'),
				'cite' => $this->_('Your Name'),
				'email' => $this->_('Your E-Mail'),
				'website' => $this->_('Your Website URL'),
				'text' => $this->_('Comments'),
				'submit' => $this->_('Submit'),
				'stars' => '', // i.e. "Your Rating"
				'starsRequired' => $this->_('Please choose a star rating'),
				'success' => $this->_('Thank you, your submission has been saved.'),
				'pending' => $this->_('Your comment has been submitted and will appear once approved by the moderator.'),
				'error' => $this->_('Your submission was not saved due to one or more errors. Please check that you have completed all fields before submitting again.'),
				'notify' => $this->_('E-Mail Notifications:'), 
				'notifyReplies' => $this->_('Replies'), 
				'notifyAll' => $this->_('All'), 
				'notifyOff' => $this->_('Off'),
			);
		}

		// if request URL does not end with a slash, use the page URL as default action attribute
		if(substr($this->wire('input')->url(), -1) != '/') {
			$this->options['attrs']['action'] = $this->page->url;
		}

		// merge any user supplied array-based options to overwrite defaults
		foreach(array('labels', 'attrs', 'classes', 'markup', 'presets') as $key) {
			if(!isset($options[$key])) continue;
			$this->options[$key] = array_merge($this->options[$key], $options[$key]);
			unset($options[$key]);
		}

		// merge user supplied options with defaults
		$this->options = array_merge($this->options, $options);
		$options = &$this->options;

		// default headline
		$headline = $options['headline'];
		$headlineLabel = $options['labels']['headline'];
		if($headline && strpos($headline, '</') === false) {
			// headline present but has no markup, so we will treat it as a non-markup label instead
			$headlineLabel = $headline;
			$headline = '';
		}
		if(empty($headline) && $headline !== false && $headlineLabel) {
			$tag = $this->_('h3'); // Default headline tag
			$markup = $options['markup']['headline'];
			$options['headline'] = str_replace(array('{headline}', '{tag}'), array($headlineLabel, $tag), $markup); 
		}

		// populate markup version of successMessage, pendingMessage, errorMessage
		foreach(array('success', 'pending', 'error') as $type) {
			$property = $type . 'Message';
			$value = $options[$property];
			if(empty($value)) {
				// option not yet populated
				$label = $options['labels'][$type];
			} else {
				// if already populated and has markup, so we will leave it as-is...
				if(strpos($value, '</')) continue; 
				// ...otherwise if it has no markup, use it as the label
				$label = $value;
			}
			$class = $options['classes'][$type];
			$markup = $options['markup']['notification']; // For example: <p class='{class}'><strong>{message}</strong></p>
			$options[$property] = str_replace(array('{class}', '{message}'), array($class, $label), $markup);
		}
		
		$options['_ready'] = true;
	}

	/**
	 * Get options
	 * 
	 * @return array
	 * 
	 */
	public function getOptions() {
		return $this->options;
	}
	
	public function option($key, $value = null) {
		if($value === null) {
			$value = isset($this->options[$key]) ? $this->options[$key] : null;
		} else {
			$this->options[$key] = $value; 
		}
		return $value; 
	}
	
	/**
	 * Get or set array property
	 *
	 * @param string $property Name of array property: labels, markup, classes, attrs, presets
	 * @param string|array $name Name of item to get or set or omit to get all, or assoc array to set all/multiple (and omit $value)
	 * @param string|null $value Value to set (if setting) or omit if getting
	 * @return string|array
	 * @throws WireException
	 * @since 3.0.153
	 *
	 */
	protected function arrayOption($property, $name = '', $value = null) {
		if(!is_array($this->options[$property])) {
			// invalid
			throw new WireException("Invalid array property: $property");
		} else if(empty($name)) {
			// get all
			$value = $this->options[$property];
		} else if(is_array($name)) {
			// set all or multiple
			$this->options[$property] = array_merge($this->options[$property], $value);
		} else if($value !== null) {
			// set one
			$this->options[$property][$name] = $value; 
		} else if(isset($this->options[$property][$name])) {
			// get one
			$value = $this->options[$property][$name];
		} else {
			// unknown
			$value = '';
		}
		
		if($value !== null && in_array($name, array('success', 'pending', 'error', 'notification'))) {
			// reset notifications rendered by setOptions
			$this->options['successMessage'] = '';
			$this->options['pendingMessage'] = '';
			$this->options['errorMessage'] = '';
			$this->setOptions($this->options);
		}
		
		return $value;
	}

	/**
	 * Get or set label
	 * 
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 * @since 3.0.153
	 * 
	 */
	public function labels($name, $value = null) {
		$result = $this->arrayOption('labels', $name, $value); 
		return $result;
	}
	
	/**
	 * Get or set attribute
	 *
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 * @since 3.0.153
	 *
	 */
	public function attrs($name, $value = null) {
		return $this->arrayOption('attrs', $name, $value);
	}
	
	/**
	 * Get or set class(es)
	 *
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 * @since 3.0.153
	 *
	 */
	public function classes($name, $value = null) {
		return $this->arrayOption('classes', $name, $value);
	}
	
	/**
	 * Get or set markup
	 *
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 * @since 3.0.153
	 *
	 */
	public function markup($name, $value = null) {
		return $this->arrayOption('markup', $name, $value);
	}

	/**
	 * Get or set presets
	 *
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 * @since 3.0.153
	 *
	 */
	public function presets($name, $value = null) {
		return $this->arrayOption('presets', $name, $value);
	}

	/**
	 * Set attribute
	 * 
	 * @param string $attr
	 * @param string $value
	 * @deprecated Use attrs() method instead
	 * 
	 */
	public function setAttr($attr, $value) {
		$this->attrs($attr, $value); 
	}

	/**
	 * Set label 
	 * 
	 * @param string $label Label name
	 * @param string $value Label value
	 * @deprecated Use labels() method instead
	 * 
	 */
	public function setLabel($label, $value) {
		$this->labels($label, $value); 
	}
	
	/**
	 * Replaces the output of the render() method when a Comment is posted
	 *
	 * A success message is shown rather than the form.
	 * 
	 * @param Comment|null $comment
	 * @return string
	 *
	 */
	protected function renderSuccess(?Comment $comment = null) {

		$pageID = (int) $this->wire('input')->post('page_id'); 
		
		if($pageID && $this->options['redirectAfterPost']) {
			// redirectAfterPost option
			$page = $this->wire('pages')->get($pageID); 
			if(!$page->viewable() || !$page->id) $page = $this->wire('page');
			$url = $page->id ? $page->url : './';
			$url .= "?comment_success=1";
			if($comment && $comment->id && $comment->status > Comment::statusPending) {
				$url .= "&comment_approved=1#Comment$comment->id";
			} else {
				$url .= "#CommentPostNote";
			}
			$this->wire('session')->set('PageRenderNoCachePage', $page->id); // tell PageRender not to use cache if it exists for this page
			$this->wire('session')->redirect($url);
			return '';
		}
		
		if(!$this->commentsField || $this->commentsField->moderate == FieldtypeComments::moderateAll) {
			// all comments are moderated
			$message = $this->options['pendingMessage'];
			
		} else if($this->commentsField->moderate == FieldtypeComments::moderateNone) {
			// no moderation in service
			$message = $this->options['successMessage'];
			
		} else if($comment && $comment->status > Comment::statusPending) {
			// comment is approved
			$message = $this->options['successMessage'];

		} else if($this->wire('input')->get('comment_approved') == 1) {
			// comment was approved in previous request
			$message = $this->options['successMessage'];
			
		} else {
			// other/comment still pending
			$message = $this->options['pendingMessage'];
		}
	
		return str_replace('{out}', $message, $this->options['markup']['wrapNotification']); 
	}

	/**
	 * Render the CommentForm output and process the input if it's been submitted
	 *
	 * @return string
	 *
	 */
	public function render() {

		if(!$this->commentsField) return "Unable to determine comments field";
		$options = $this->options; 	
		$labels = $options['labels'];
		$attrs = $options['attrs'];
		$id = $attrs['id'];
		$submitKey = $id . "_submit";
		$honeypot = $options['requireHoneypotField'];
		$inputValues = array('cite' => '', 'email' => '', 'website' => '', 'stars' => '', 'text' => '', 'notify' => '');
		$sanitizer = $this->wire('sanitizer'); /** @var Sanitizer $sanitizer */
		if($honeypot) $inputValues[$honeypot] = '';
		
		$user = $this->wire('user'); 

		if($user->isLoggedin()) {
			$inputValues['cite'] = $sanitizer->entities($user->name); 
			$inputValues['email'] = $sanitizer->entities($user->email);
		}
	
		/** @var WireInput $input */
		$input = $this->wire('input'); 
		$divClass = 'new';
		$class = trim("CommentForm " . $attrs['class']); 
		$note = '';

		foreach($options['presets'] as $key => $value) {
			if(!is_null($value)) $inputValues[$key] = $value; 
		}

		if($options['processInput'] && $input->post($submitKey) == 1) {
			$comment = $this->processInput(); 
			if($comment) { 
				$note = $this->renderSuccess($comment); // success, return
			} else {
				$inputValues = array_merge($inputValues, $this->inputValues);
				foreach($inputValues as $key => $value) {
					$inputValues[$key] = htmlentities($value, ENT_QUOTES, $this->options['encoding']);
				}
				$note = "$options[errorMessage]";
				$divClass = 'error';
			}

		} else if($this->options['redirectAfterPost'] && $input->get('comment_success') === "1") {
			$note = $this->renderSuccess();
		}

		$form = $this->renderForm($id, $class, $attrs, $labels, $inputValues);
		
		if(!$options['presetsEditable']) {
			foreach($options['presets'] as $key => $value) {
				$a = array(" name='$key'", " name=\"$key\"");
				if(!is_null($value)) $form = str_replace($a, " name='$key' disabled='disabled'", $form); 
			}
		}

		// <div id='{id}' class='{class}'>\n{headline}{note}{form}\n</div><!--/{id}-->
		$replacements = array(
			'{id}' => $id,
			'{class}' => "{$id}_$divClass", 
			'{headline}' => $this->options['headline'], 
			'{note}' => $note, 
			'{form}' => $form,
		);
		
		return str_replace(array_keys($replacements), array_values($replacements), $this->markup('wrapAll'));
	}

	/**
	 * Render form 
	 *
	 * @param string $id
	 * @param string $class
	 * @param array $attrs
	 * @param array $labels
	 * @param array $inputValues
	 * @return string
	 *
	 */
	protected function renderForm($id, $class, $attrs, $labels, $inputValues) {
		if($this->options['depth'] > 0) {
			$form = $this->renderFormThread($id, $class, $attrs, $labels, $inputValues);
		} else {
			$form = $this->renderFormNormal($id, $class, $attrs, $labels, $inputValues);
		}
		return $form;
	}

	/**
	 * Render normal form without threaded comments possibility
	 * 
	 * @param string $id
	 * @param string $class
	 * @param array $attrs
	 * @param array $labels
	 * @param array $inputValues
	 * @return string
	 * 
	 */
	protected function renderFormNormal($id, $class, $attrs, $labels, $inputValues) {
		
		$classes = $this->options['classes'];
		$tag = $this->options['inputWrapTag'];
		$labelClass = $classes['label'] ? " class='$classes[label]'" : "";
		
		foreach($classes as $k => $v) {
			$classes[$k] = str_replace('{id}', $id, $v); 
		}
		
		$formClass = trim("$class $classes[form] CommentFormNormal"); 
		
		$form = 
			"<form id='{$id}_form' class='$formClass' action='$attrs[action]#$id' method='$attrs[method]'>" .
				($classes['wrapper'] ? "<div class='$classes[wrapper]'>" : "") . 
				"<$tag class='$classes[cite]'>" .
					"<label$labelClass for='{$id}_cite'>$labels[cite]</label>" .
					"<input type='text' name='cite' class='$classes[citeInput]' required='required' id='{$id}_cite' value='$inputValues[cite]' maxlength='128' />" .
				"</$tag>" .
				"<$tag class='$classes[email]'>" .
					"<label$labelClass for='{$id}_email'>$labels[email]</label>" .
					"<input type='text' name='email' class='$classes[emailInput]' required='required' id='{$id}_email' value='$inputValues[email]' maxlength='255' />" .
				"</$tag>";

		if($this->commentsField && $this->commentsField->useWebsite && $this->commentsField->schemaVersion > 0) {
			$form .=
				"<$tag class='$classes[website]'>" .
					"<label$labelClass for='{$id}_website'>$labels[website]</label>" .
					"<input type='text' name='website' class='$classes[websiteInput]' id='{$id}_website' value='$inputValues[website]' maxlength='255' />" .
				"</$tag>";
		}

		if($this->commentsField->useStars && $this->commentsField->schemaVersion > 5) {
			$commentStars = new CommentStars();
			$starsClass = $classes['stars'];
			if($this->commentsField->useStars > 1) {
				$starsNote = $labels['starsRequired'];
				$starsClass = trim("$starsClass $classes[starsRequired]"); 
			} else {
				$starsNote = '';
			}
			$form .=
				"<$tag class='$starsClass' data-note='$starsNote'>" .
					($labels['stars'] ? "<label$labelClass for='{$id}_stars'>$labels[stars]</label>" : "") .
					"<input type='number' name='stars' id='{$id}_stars' value='$inputValues[stars]' min='0' max='5' />" .
						$commentStars->render(0, true) .
				"</$tag>";
		}

		// do we need to show the honeypot field?
		$honeypot = $this->options['requireHoneypotField'];
		if($honeypot) {
			$honeypotLabel = isset($labels[$honeypot]) ? $labels[$honeypot] : '';
			$honeypotValue = isset($inputValues[$honeypot]) ? $inputValues[$honeypot] : '';
			$form .=
				"<$tag class='$classes[honeypot]'>" .
					"<label$labelClass for='{$id}_$honeypot'>$honeypotLabel</label>" .
					"<input type='text' id='{$id}_$honeypot' name='$honeypot' value='$honeypotValue' size='3' />" .
				"</$tag>";
		}

		$form .=
				"<$tag class='$classes[text]'>" .
					"<label$labelClass for='{$id}_text'>$labels[text]</label>" .
					"<textarea name='text' class='$classes[textInput]' required='required' id='{$id}_text' rows='$attrs[rows]' cols='$attrs[cols]'>$inputValues[text]</textarea>" .
				"</$tag>" . 
				$this->renderNotifyOptions() . 
				"<$tag class='$classes[submit]'>" .
					"<button type='submit' name='{$id}_submit' id='{$id}_submit' class='$classes[submitButton]' value='1'>$labels[submit]</button>" .
					"<input type='hidden' name='page_id' value='{$this->page->id}' />" .
				"</$tag>" .
				($classes['wrapper'] ? "</div>" : "") . 
			"</form>";
		
		return $form; 
	}

	/**
	 * Render form for threaded (depth) comments
	 *
	 * @param string $id
	 * @param string $class
	 * @param array $attrs
	 * @param array $labels
	 * @param array $inputValues
	 * @return string
	 *
	 */
	protected function renderFormThread($id, $class, $attrs, $labels, $inputValues) {
		
		$classes = $this->options['classes'];
		$classes['form'] .= " $class CommentFormThread";
		
		foreach($classes as $key => $value) {
			$classes[$key] = trim(str_replace('{id}', $id, $value));
		}
		
		$labelClass = $classes['label'];
		$labelClass = $labelClass ? " class='$labelClass'" : "";
		$labelSpanClass = $classes['labelSpan'];
		$labelSpanClass = $labelSpanClass ? " class='$labelSpanClass'" : "";
		
		$tag = $this->options['inputWrapTag'];
		$formAttrs = trim(
			"class='$classes[form]' " . 
			"action='$attrs[action]#$id' " . 
			"method='$attrs[method]' " . 
			$attrs['form']
		);
		
		$form = 
			"\n<form $formAttrs>" .
			"\n\t<$tag class='$classes[cite]'>" .
			"\n\t\t<label$labelClass>" . 
			"\n\t\t\t<span$labelSpanClass>$labels[cite]</span> " .
			"\n\t\t\t<input type='text' name='cite' class='$classes[citeInput]' required='required' value='$inputValues[cite]' maxlength='128' />" .
			"\n\t\t</label> " .
			"\n\t</$tag>" .
			"\n\t<$tag class='$classes[email]'>" .
			"\n\t\t<label$labelClass>" . 
			"\n\t\t\t<span$labelSpanClass>$labels[email]</span> " . 
			"\n\t\t\t<input type='email' name='email' class='$classes[emailInput]' required='required' value='$inputValues[email]' maxlength='255' />" .
			"\n\t\t</label>" . 
			"\n\t</$tag>";

		if($this->commentsField && $this->commentsField->useWebsite && $this->commentsField->schemaVersion > 0) {
			$form .=
				"\n\t<$tag class='$classes[website]'>" .
				"\n\t\t<label$labelClass>" . 
				"\n\t\t\t<span$labelSpanClass>$labels[website]</span> " .
				"\n\t\t\t<input type='text' name='website' class='$classes[websiteInput]' value='$inputValues[website]' maxlength='255' />" .
				"\n\t\t</label>" . 
				"\n\t</$tag>";
		}

		if($this->commentsField->useStars && $this->commentsField->schemaVersion > 5) {
			$commentStars = new CommentStars();
			$starsClass = $classes['stars'];
			if($this->commentsField->useStars > 1) {
				$starsNote = $labels['starsRequired'];
				$starsClass .= " $classes[starsRequired]";
			} else {
				$starsNote = '';
			}
			$form .=
				"\n\t<$tag class='$starsClass' data-note='$starsNote'>" .
				"\n\t\t<label$labelClass>" .
				"\n\t\t\t<span$labelSpanClass>$labels[stars]</span>" .
				"\n\t\t\t<input type='number' name='stars' value='$inputValues[stars]' min='0' max='5' />" .
				"\n\t\t\t" . $commentStars->render(0, true) .
				"\n\t\t</label>" .
				"\n\t</$tag>";
		}

		// do we need to show the honeypot field?
		$honeypot = $this->options['requireHoneypotField'];
		if($honeypot) {
			$honeypotLabel = isset($labels[$honeypot]) ? $labels[$honeypot] : '';
			$honeypotValue = isset($inputValues[$honeypot]) ? $inputValues[$honeypot] : '';
			$form .=
				"\n\t<$tag class='$classes[honeypot]'>" .
				"\n\t\t<label><span>$honeypotLabel</span>" .
				"\n\t\t<input type='text' name='$honeypot' value='$honeypotValue' size='3' />" .
				"\n\t\t</label>" .
				"\n\t</$tag>";
		}

		$form .=
			"\n\t<$tag class='$classes[text]'>" .
			"\n\t\t<label$labelClass>" .
			"\n\t\t\t<span$labelSpanClass>$labels[text]</span>" .
			"\n\t\t\t<textarea name='text' class='$classes[textInput]' required='required' rows='$attrs[rows]' cols='$attrs[cols]'>$inputValues[text]</textarea>" .
			"\n\t\t</label>" . 
			"\n\t</$tag>" .
			$this->renderNotifyOptions() . 
			"\n\t<$tag class='$classes[submit]'>" .
			"\n\t\t<button type='submit' class='$classes[submitButton]' name='{$id}_submit' value='1'>$labels[submit]</button>" .
			"\n\t\t<input type='hidden' name='page_id' value='{$this->page->id}' />" .
			"\n\t\t<input type='hidden' class='CommentFormParent' name='parent_id' value='0' />" .
			"\n\t</$tag>" .
			"\n</form>";
		
		return $form;
	}

	/**
	 * Render the "notify me" options
	 * 
	 * @return string
	 * 
	 */
	protected function renderNotifyOptions() {
		
		if(!$this->commentsField->useNotify) return '';
		$out = '';
		$tag = $this->options['inputWrapTag'];
		$notifyDefault = (int) $this->options['notifyDefault'];
		
		$options = array();
		
		if($this->commentsField->depth > 0) {
			$options['2'] = $this->labels('notifyReplies');
		}

		if($this->commentsField->useNotify == Comment::flagNotifyAll) {
			$options['4'] = $this->labels('notifyAll');
		}
	
		$classes = array(
			'notify' => $this->options['classes']['notify'],
			'label' => $this->options['classes']['label'],
			'labelSpan' => $this->options['classes']['labelSpan'],
			'radioLabel' => $this->options['classes']['radioLabel'],
			'radioInput' => $this->options['classes']['radioInput'],
		);
		
		foreach($classes as $key => $value) {
			$classes[$key] = $value ? " class='" . trim($value) . "'" : "";
		}
	
		$checked = "checked='checked' ";
		$checkedNotify = array(
			0 => ($notifyDefault === 0 ? $checked : ''),
			Comment::flagNotifyAll => ($notifyDefault === Comment::flagNotifyAll ? $checked : ''),
			Comment::flagNotifyReply => ($notifyDefault === Comment::flagNotifyReply ? $checked : ''), 
		);
		
		if(count($options)) {
			$checked = $checkedNotify[0];
			$out = 
				"\n\t<$tag$classes[notify]>" . 
				"\n\t\t<label$classes[label]><span$classes[labelSpan]>" . $this->labels('notify') . "</span></label> " . 
				"\n\t\t<label$classes[radioLabel]><input$classes[radioInput] type='radio' name='notify' value='0' $checked/> " . $this->labels('notifyOff') . "</label> ";
			
			foreach($options as $value => $label) {
				$checked = $checkedNotify[(int)$value];
				$label = str_replace(' ', '&nbsp;', $label); 
				$out .= "\n\t\t<label$classes[radioLabel]><input$classes[radioInput] type='radio' name='notify' value='$value' $checked/> $label</label> ";
			}
			$out .= "\n\t</$tag>";
		}
	
		return $out; 
	}

	/**
	 * Process a submitted CommentForm, insert the Comment, and save the Page
	 * 
	 * @return Comment|bool
	 *
	 */
	public function processInput() {

		$data = $this->wire('input')->post; 
		if(!count($data) || !$this->commentsField) return false; 	

		if($key = $this->options['requireSecurityField']) {
			if(empty($data[$key])) return false; 
		}

		if($key = $this->options['requireHoneypotField']) {
			if(!empty($data[$key])) return false;
		}
		
		$maxDepth = $this->commentsField->depth;

		/** @var Comment $comment */
		$comment = $this->wire(new Comment()); 
		$comment->user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''; 
		$comment->ip = $this->wire('session')->getIP();
		$comment->created_users_id = $this->user->id; 
		//$comment->sort = count($this->comments)+1; 
		$comment->parent_id = $maxDepth ? (int) abs((int) $data->parent_id) : 0; 
		
		if($comment->parent_id && $maxDepth) {
			$parent = $this->commentsField->getCommentByID($this->page, $comment->parent_id); 
			if($parent) {
				// validate that depth is in allowed limit
				$parents = $this->commentsField->getCommentParents($this->page, $comment); 
				if($parents->count() > $maxDepth) {
					if($parent->parent_id) {
						$comment->parent_id = $parent->parent_id;
					} else {
						$comment->parent_id = 0;
					}
				}
			} else {
				// parent does not exist on this page
				$comment->parent_id = 0;
			}
		}

		$errors = array();
		// $sessionData = array(); 

		foreach(array('cite', 'email', 'website', 'stars', 'text') as $key) {
			
			if($key == 'website' && (!$this->commentsField || !$this->commentsField->useWebsite)) continue;
			if($key == 'stars' && (!$this->commentsField || !$this->commentsField->useStars)) continue;
			
			if($this->options['presetsEditable'] || !isset($this->options['presets'][$key]) || $this->options['presets'][$key] === null) {
				$comment->$key = $data->$key; // Comment performs sanitization/validation
			} else {
				$comment->$key = $this->options['presets'][$key];
			}
			if($key != 'website' && $key != 'stars' && !$comment->$key) $errors[] = $key;
			$this->inputValues[$key] = $comment->$key;
			//if($key != 'text') $sessionData[$key] = $comment->$key; 
		}

		$flags = 0;
		$notify = (int) $data->notify;
		if($this->commentsField->useNotify && $notify) {
			if($notify == Comment::flagNotifyAll && $this->commentsField->useNotify == Comment::flagNotifyAll) {
				$flags = Comment::flagNotifyAll;
			} else {
				$flags = Comment::flagNotifyReply;
			}
			if($flags) {
				// $sessionData['notify'] = $notify;
				$this->inputValues['notify'] = $notify;
				// send confirmation email
			}
		}
		
		$comment->flags = $flags;

		if(!count($errors) && $this->commentsField) {
			$result = $this->commentsField->addComment($this->page, $comment, true); 
			if($result) {
				// added successfully
				if($this->comments->getPage()) $this->comments->add($comment);
			} else {
				// fallback legacy add process
				$this->comments->add($comment);
				$outputFormatting = $this->page->outputFormatting;
				$this->page->setOutputFormatting(false);
				$saveOptions = array();
				if($this->options['quietSave']) $saveOptions['quiet'] = true;
				$result = $this->page->save($this->commentsField->name, $saveOptions);
				$this->page->setOutputFormatting($outputFormatting); 
			}
			// $this->wire('session')->set('CommentForm', $sessionData);
			if($result) {
				$this->postedComment = $comment;
				return $comment;
			}
		}

		return false;
	}

	/**
	 * Return the Comment that was posted or NULL if not yet posted
	 *
	 */
	public function getPostedComment() {
		return $this->postedComment; 
	}
}
