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
	 */
	protected $page; 

	/**
	 * Reference to the Field object used by this CommentForm
	 *
	 */
	protected $commentsField; 

	/**
	 * Instance of CommentArray, containing all Comment instances for this Page
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
		'headline' => '',	// Post Comment
		'successMessage' => '',	// Thank you, your submission has been saved
		'pendingMessage' => '', // Your comment has been submitted and will appear once approved by the moderator.
		'errorMessage' => '',	// Your submission was not saved due to one or more errors. Try again.
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
			'cite' => '',	// Your Name
			'email' => '',	// Your E-Mail
			'website' => '',// Website
			'stars' => '',	// Your Rating 
			'text' => '',	// Comments
			'submit' => '', // Submit
			'starsRequired' => '', // Please select a star rating
		),
		'classes' => array(
			'form' => '',
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
		),

		// values that will be already set, perhaps pulled from a user profile for instance (null = ignore)
		// note that using presets is NOT cache safe: do not use for non-logged-in users if output caches are active
		'presets' => array(
			'cite' => null,
			'email' => null, 
			'website' => null,
			'text' => null,
		),

		// whether or not eht preset values above will be changeable by the user
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

		$this->page = $page;
		$this->comments = $comments; 

		// default messages
		$h3 = $this->_('h3'); // Headline tag
		$this->options['headline'] = "<$h3>" . $this->_('Post Comment') . "</$h3>"; // Form headline
		$this->options['successMessage'] = "<p class='success'><strong>" . $this->_('Thank you, your submission has been saved.') . "</strong></p>"; 
		$this->options['pendingMessage'] = "<p class='success pending'><strong>" . $this->_('Your comment has been submitted and will appear once approved by the moderator.') . "</strong></p>"; 
		$this->options['errorMessage'] = "<p class='error'><strong>" . $this->_('Your submission was not saved due to one or more errors. Please check that you have completed all fields before submitting again.') . "</strong></p>"; 

		// default labels
		$this->options['labels']['cite'] = $this->_('Your Name'); 
		$this->options['labels']['email'] = $this->_('Your E-Mail'); 
		$this->options['labels']['website'] = $this->_('Your Website URL');
		$this->options['labels']['stars'] = ''; // i.e. "Your Rating"
		$this->options['labels']['starsRequired'] = $this->_('Please choose a star rating');
		$this->options['labels']['text'] = $this->_('Comments'); 
		$this->options['labels']['submit'] = $this->_('Submit'); 

		if(isset($options['labels'])) {
			$this->options['labels'] = array_merge($this->options['labels'], $options['labels']); 
			unset($options['labels']); 
		}
		if(isset($options['attrs'])) {
			$this->options['attrs'] = array_merge($this->options['attrs'], $options['attrs']); 
			unset($options['attrs']); 
		}
		$this->options = array_merge($this->options, $options); 

		// determine which field on the page is the commentsField and save the Field instance
		foreach($this->wire('fields') as $field) {
			if(!$field->type instanceof FieldtypeComments) continue; 
			$value = $this->page->get($field->name); 
			if($value === $this->comments) {
				$this->commentsField = $field;
				break;
			}
		}
		// populate the vlaue of redirectAfterPost
		if($this->commentsField && is_null($this->options['redirectAfterPost'])) {
			$this->options['redirectAfterPost'] = (bool) $this->commentsField->redirectAfterPost;
		}
		if($this->commentsField && $this->commentsField->quietSave) {
			$this->options['quietSave'] = true; 
		}
	}

	public function setAttr($attr, $value) {
		$this->options['attrs'][$attr] = $value; 
	}

	public function setLabel($label, $value) {
		$this->options['labels'][$label] = $value; 
	}
	
	public function getOptions() {
		return $this->options;
	}
	
	public function setOptions(array $options) {
		$this->options = array_merge($this->options, $options);
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
	protected function renderSuccess(Comment $comment = null) {

		$pageID = (int) $this->wire('input')->post->page_id; 
		
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
		
		return "<div id='CommentPostNote'>$message</div>"; 
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
		if($honeypot) $inputValues[$honeypot] = '';
		
		$user = $this->wire('user'); 

		if($user->isLoggedin()) {
			$inputValues['cite'] = $user->name; 
			$inputValues['email'] = $user->email;
		}
		
		$input = $this->wire('input'); 
		$divClass = 'new';
		$class = trim("CommentForm " . $attrs['class']); 
		$note = '';

		/*
		 * Removed because this is not cache safe! Converted to JS cookie. 
		 * 
		if(is_array($this->session->CommentForm)) {
			// submission data available in the session
			$sessionValues = $this->session->CommentForm;
			foreach($inputValues as $key => $value) {
				if($key == 'text') continue; 
				if(!isset($sessionValues[$key])) $sessionValues[$key] = '';
				$inputValues[$key] = htmlentities($sessionValues[$key], ENT_QUOTES, $this->options['encoding']); 
			}
			unset($sessionValues);
		}
		*/

		foreach($options['presets'] as $key => $value) {
			if(!is_null($value)) $inputValues[$key] = $value; 
		}

		$out = '';
		$showForm = true; 
		
		if($options['processInput'] && $input->post->$submitKey == 1) {
			$comment = $this->processInput(); 
			if($comment) { 
				$out .= $this->renderSuccess($comment); // success, return
			} else {
				$inputValues = array_merge($inputValues, $this->inputValues);
				foreach($inputValues as $key => $value) {
					$inputValues[$key] = htmlentities($value, ENT_QUOTES, $this->options['encoding']);
				}
				$note = "\n\t$options[errorMessage]";
				$divClass = 'error';
			}

		} else if($this->options['redirectAfterPost'] && $input->get('comment_success') === "1") {
			$note = $this->renderSuccess();
		}

		$form = '';
		if($showForm) {
			if($this->options['depth'] > 0) {
				$form = $this->renderFormThread($id, $class, $attrs, $labels, $inputValues);
			} else {
				$form = $this->renderFormNormal($id, $class, $attrs, $labels, $inputValues); 
			}
			if(!$options['presetsEditable']) {
				foreach($options['presets'] as $key => $value) {
					if(!is_null($value)) $form = str_replace(" name='$key'", " name='$key' disabled='disabled'", $form); 
				}
			}
		}

		$out .= 
			"\n<div id='{$id}' class='{$id}_$divClass'>" . 	
			"\n" . $this->options['headline'] . $note . $form . 
			"\n</div><!--/$id-->";


		return $out; 
	}
	
	protected function renderFormNormal($id, $class, $attrs, $labels, $inputValues) {
		$form = 
			"\n<form id='{$id}_form' class='$class CommentFormNormal' action='$attrs[action]#$id' method='$attrs[method]'>" .
			"\n\t<p class='CommentFormCite {$id}_cite'>" .
			"\n\t\t<label for='{$id}_cite'>$labels[cite]</label>" .
			"\n\t\t<input type='text' name='cite' class='required' required='required' id='{$id}_cite' value='$inputValues[cite]' maxlength='128' />" .
			"\n\t</p>" .
			"\n\t<p class='CommentFormEmail {$id}_email'>" .
			"\n\t\t<label for='{$id}_email'>$labels[email]</label>" .
			"\n\t\t<input type='text' name='email' class='required email' required='required' id='{$id}_email' value='$inputValues[email]' maxlength='255' />" .
			"\n\t</p>";

		if($this->commentsField && $this->commentsField->useWebsite && $this->commentsField->schemaVersion > 0) {
			$form .=
				"\n\t<p class='CommentFormWebsite {$id}_website'>" .
				"\n\t\t<label for='{$id}_website'>$labels[website]</label>" .
				"\n\t\t<input type='text' name='website' class='website' id='{$id}_website' value='$inputValues[website]' maxlength='255' />" .
				"\n\t</p>";
		}

		if($this->commentsField->useStars && $this->commentsField->schemaVersion > 5) {
			$commentStars = new CommentStars();
			$starsClass = 'CommentFormStars';
			if($this->commentsField->useStars > 1) {
				$starsNote = $labels['starsRequired'];
				$starsClass .= ' CommentFormStarsRequired';
			} else {
				$starsNote = '';
			}
			$form .=
				"\n\t<p class='$starsClass {$id}_stars' data-note='$starsNote'>" .
				($labels['stars'] ? "\n\t\t<label for='{$id}_stars'>$labels[stars]</label>" : "") .
				"\n\t\t<input type='number' name='stars' id='{$id}_stars' value='$inputValues[stars]' min='0' max='5' />" .
				"\n\t\t" . $commentStars->render(0, true) .
				"\n\t</p>";
		}

		// do we need to show the honeypot field?
		$honeypot = $this->options['requireHoneypotField'];
		if($honeypot) {
			$honeypotLabel = isset($labels[$honeypot]) ? $labels[$honeypot] : '';
			$honeypotValue = isset($inputValues[$honeypot]) ? $inputValues[$honeypot] : '';
			$form .=
				"\n\t<p class='CommentFormHP {$id}_hp'>" .
				"\n\t\t<label for='{$id}_$honeypot'>$honeypotLabel</label>" .
				"\n\t\t<input type='text' id='{$id}_$honeypot' name='$honeypot' value='$honeypotValue' size='3' />" .
				"\n\t</p>";
		}

		$form .=
			"\n\t<p class='CommentFormText {$id}_text'>" .
			"\n\t\t<label for='{$id}_text'>$labels[text]</label>" .
			"\n\t\t<textarea name='text' class='required' required='required' id='{$id}_text' rows='$attrs[rows]' cols='$attrs[cols]'>$inputValues[text]</textarea>" .
			"\n\t</p>" . 
			$this->renderNotifyOptions() . 
			"\n\t<p class='CommentFormSubmit {$id}_submit'>" .
			"\n\t\t<button type='submit' name='{$id}_submit' id='{$id}_submit' value='1'>$labels[submit]</button>" .
			"\n\t\t<input type='hidden' name='page_id' value='{$this->page->id}' />" .
			"\n\t</p>" .
			"\n</form>";
		
		return $form; 
	}
	
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
	
	protected function renderNotifyOptions() {
		
		if(!$this->commentsField->useNotify) return '';
		$out = '';
		$tag = $this->options['inputWrapTag'];
		
		$options = array();
		
		if($this->commentsField->depth > 0) {
			$options['2'] = $this->_('Replies');
		}

		if($this->commentsField->useNotify == Comment::flagNotifyAll) {
			$options['4'] = $this->_('All');
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
		
		if(count($options)) {
			$out = 
				"\n\t<$tag$classes[notify]>" . 
				"\n\t\t<label$classes[label]><span$classes[labelSpan]>" . $this->_('E-Mail Notifications:') . "</span></label> " . 
				"\n\t\t<label$classes[radioLabel]><input$classes[radioInput] type='radio' name='notify' checked='checked' value='0' /> " . $this->_('Off') . "</label> ";
			
			foreach($options as $value => $label) {
				$label = str_replace(' ', '&nbsp;', $label); 
				$out .= "\n\t\t<label$classes[radioLabel]><input$classes[radioInput] type='radio' name='notify' value='$value' /> $label</label> ";
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
		if(!count($data)) return false; 	

		if($key = $this->options['requireSecurityField']) {
			if(empty($data[$key])) return false; 
		}

		if($key = $this->options['requireHoneypotField']) {
			if(!empty($data[$key])) return false;
		}

		$comment = $this->wire(new Comment()); 
		$comment->user_agent = $_SERVER['HTTP_USER_AGENT']; 
		$comment->ip = $this->wire('session')->getIP();
		$comment->created_users_id = $this->user->id; 
		$comment->sort = count($this->comments)+1; 
		$comment->parent_id = (int) $data->parent_id; 

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

		if(!count($errors)) {
			if($this->comments->add($comment) && $this->commentsField) {
				$outputFormatting = $this->page->outputFormatting; 
				$this->page->setOutputFormatting(false);
				$saveOptions = array();
				if($this->options['quietSave']) $saveOptions['quiet'] = true; 
				$this->page->save($this->commentsField->name, $saveOptions); 
				$this->page->setOutputFormatting($outputFormatting); 
				$this->postedComment = $comment; 
				// $this->wire('session')->set('CommentForm', $sessionData);
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
