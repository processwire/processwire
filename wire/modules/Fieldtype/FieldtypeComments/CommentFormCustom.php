<?php namespace ProcessWire;

/**
 * CommentFormCustom 
 * 
 * CommentForm with 100% customizable form markup
 *
 * ~~~~~~
 * $form = $page->comments->getCommentForm([
 *   'className' => 'CommentFormCustom',
 * ]);
 * 
 * $formMarkup = "
 *   <form class='{form.class}' action='{form.action}' method='{form.method}' {form.attrs}>
 *     ...copy/paste what's in the getFormMarkup() method for a starting point...
 *   </form> 
 * ";
 * 
 * $form->markup('form', $formMarkup);
 * $form->labels('submit', 'Submit Feedback');
 * $form->labels('notify', 'Email Me');
 * 
 * // form notifications
 * $form->markup('notification', "<div class='uk-alert {class}'>{message}</div>");
 * $form->classes('success', 'uk-alert-success');
 * $form->classes('pending', 'uk-alert-warning');
 * $form->classes('error', 'uk-alert-danger');

 * echo $form->render();
 * ~~~~~~
 *
 * ProcessWire 3.x, Copyright 2020 by Ryan Cramer
 * https://processwire.com
 *
 *
 */

class CommentFormCustom extends CommentForm {

	/**
	 * Get form markup
	 * 
	 * To set form markup use the markup in this method as your starting point and set your custom markup with this: 
	 * ~~~~~
	 * $markup = "...your custom form markup...";
	 * $commentForm->markup('form', $markup); 
	 * ~~~~~
	 * 
	 * @return string
	 * 
	 */
	public function getFormMarkup() {
		$out = $this->markup('form'); 
		
		if(empty($out)) $out = "
			<form class='{form.class}' action='{form.action}' method='{form.method}' {form.attrs}>
			
				<p class='{cite.wrap.class}'>
					<label class='{label.class}'>
						<span class='{label.span.class}'>{cite.label}</span> 
						<input type='text' name='{cite.input.name}' class='{cite.input.class}' required='required' value='{cite.input.value}' maxlength='128' />
					</label>
				</p>
				
				<p class='{email.wrap.class}'>
					<label class='{label.class}'>
						<span class='{label.span.class}'>{email.label}</span> 
						<input type='email' name='{email.input.name}' class='{email.input.class}' required='required' value='{email.input.value}' maxlength='255' />
					</label>
				</p>
				
				{if.website}
				<p class='{website.wrap.class}'>
					<label class='{label.class}'>
						<span class='{label.span.class}'>{website.label}</span> 
						<input type='text' name='{website.input.name}' class='{website.input.class}' value='{website.input.value}' maxlength='255' />
					</label>
				</p>
				{endif.website}
				
				{if.stars}
				<p class='{stars.wrap.class}' {stars.wrap.attrs}>
					<label class='{label.class}'>
						<span class='{label.span.class}'>{stars.label}</span> 
						{stars.markup}	
					</label>
				</p>
				{endif.stars}

				{if.honeypot}
				<p class='{honeypot.wrap.class}'>
					<label>
						<span>{honeypot.label}</span>
						<input type='text' name='{honeypot.input.name}' value='{honeypot.input.value}' size='3' />
					</label>
				</p>
				{endif.honeypot}
				
				<p class='{text.wrap.class}'>
					<label class='{label.class}'>
						<span class='{label.span.class}'>{text.label}</span> 
						<textarea name='text' class='{text.input.class}' required='required' rows='{text.input.rows}' cols='{text.input.cols}'>{text.input.value}</textarea>
					</label>
				</p>
				
				{if.notify}
				<p class='{notify.wrap.class}'>
					<label class='{notify.label.class}'>
						<span class='{notify.label.span.class}'>{notify.label}</span>
					</label> 
					<label class='{notify.input.label.class}'>
						<input class='{notify.input.class}' type='radio' name='{notify.input.name}' checked='checked' value='{notify.input.off.value}' {notify.input.off.checked}/> 
						{notify.input.off.label}
					</label>	
					
					{if.notify.replies}
					<label class='{notify.input.label.class}'>
						<input class='{notify.input.class}' type='radio' name='{notify.input.name}' value='{notify.input.replies.value}' {notify.input.replies.checked}/> 
						{notify.input.replies.label}
					</label>	
					{endif.notify.replies}
					
					{if.notify.all}
					<label class='{notify.input.label.class}'>
						<input class='{notify.input.class}' type='radio' name='{notify.input.name}' value='{notify.input.all.value}' {notify.input.all.checked}/> 
						{notify.input.all.label}
					</label>	
					{endif.notify.all}
				</p>	
				{endif.notify}
				
				<p class='{submit.wrap.class}'>
					<button type='submit' class='{submit.input.class}' name='{submit.input.name}' value='{submit.input.value}'>{submit.label}</button>
					{form.hidden.inputs}
				</p>
			</form>
		";

		return $out; 
	}
	
	/**
	 * Apply an {if.name} ... {endif.name} statement
	 *
	 * @param string $name Property name for if statement
	 * @param bool $value If true the contents of the {if...} statement will stay, if false it will be removed
	 * @param string $out
	 * @return mixed|string
	 *
	 */
	protected function applyIf($name, $value, $out) {

		$if = "{if.$name}";
		$endif = "{endif.$name}";

		if(strpos($out, $if) === false || strpos($out, $endif) === false) return $out;

		if($value) {
			$out = str_replace(array($if, $endif), '', $out);
		} else {
			list($before, $within) = explode($if, $out, 2);
			list(,$after) = explode($endif, $within, 2);
			$out = $before . $after;
		}

		return $out;
	}
	
	/**
	 * Custom markup form render
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

		$field = $this->commentsField;
		$useDepth = $this->options['depth'] > 0;
		$classes = $this->options['classes'];
		$classes['form'] = trim("$classes[form] $class " . ($useDepth ? 'CommentFormThread' : 'CommentFormNormal'));
		$labels = $this->options['labels'];

		foreach($classes as $key => $value) {
			$classes[$key] = trim(str_replace('{id}', $id, $value));
		}

		$labelClass = $classes['label'];
		$labelSpanClass = $classes['labelSpan'];

		$formHiddenInputs = "<input type='hidden' name='page_id' value='{$this->page->id}' />";
		if($useDepth) $formHiddenInputs .= "<input type='hidden' class='CommentFormParent' name='parent_id' value='0' />";

		$useStars = $field->useStars && $field->schemaVersion > 5;
		list($starsClass, $starsNote, $starsInput, $starsMarkup, $stars) = array($classes['stars'], '', '', '', ''); 
		if($useStars) {
			$starsInput = "<input type='number' name='stars' value='$inputValues[stars]' min='0' max='5' />";
			$commentStars = new CommentStars();
			$stars = $commentStars->render(0, true);
			$starsMarkup = $starsInput . $stars;
		}
		if($useStars && $field->useStars > 1) {
			$starsNote = $labels['starsRequired'];
			$starsClass .= " $classes[starsRequired]";
		}

		// do we need to show the honeypot field?
		$honeypot = $this->options['requireHoneypotField'];
		$useHoneypot = $honeypot ? true : false;
		$honeypotLabel = $useHoneypot && isset($labels[$honeypot]) ? $labels[$honeypot] : '';
		$honeypotValue = $useHoneypot && isset($inputValues[$honeypot]) ? $inputValues[$honeypot] : '';

		$placeholders = array(
			'{form.class}' => $classes['form'],
			'{form.action}' => $attrs['action'] . "#$id",
			'{form.method}' => $attrs['method'],
			'{form.attrs}' => $attrs['form'],
			'{form.hidden.inputs}' => $formHiddenInputs,
			'{label.class}' => $labelClass,
			'{label.span.class}' => $labelSpanClass,
			'{cite.wrap.class}' => $classes['cite'],
			'{cite.label}' => $labels['cite'],
			'{cite.input.name}' => 'cite',
			'{cite.input.class}' => $classes['citeInput'],
			'{cite.input.value}' => $inputValues['cite'],
			'{email.wrap.class}' => $classes['email'],
			'{email.label}' => $labels['email'],
			'{email.input.name}' => 'email',
			'{email.input.class}' => $classes['emailInput'],
			'{email.input.value}' => $inputValues['email'],
			'{website.wrap.class}' => $classes['website'],
			'{website.label}' => $labels['website'],
			'{website.input.name}' => 'website',
			'{website.input.class}' => $classes['websiteInput'],
			'{website.input.value}' => $inputValues['website'],
			'{stars.wrap.class}' => $starsClass,
			'{stars.wrap.attrs}' => "data-note='$starsNote'",
			'{stars.label}' => $labels['stars'],
			'{stars.input.name}' => 'stars',
			'{stars.input.class}' => '',
			'{stars.input.value}' => $inputValues['stars'],
			'{stars.stars}' => $stars, // just stars icons
			'{stars.input}' => $starsInput, // just stars <input>
			'{stars.markup}' => $starsMarkup, // both stars <input> and stars icons
			'{honeypot.wrap.class}' => $classes['honeypot'],
			'{honeypot.label}' => $honeypotLabel,
			'{honeypot.input.name}' => $honeypot,
			'{honeypot.input.class}' => '',
			'{honeypot.input.value}' => $honeypotValue,
			'{text.wrap.class}' => $classes['text'],
			'{text.label}' => $labels['text'],
			'{text.input.name}' => 'text',
			'{text.input.class}' => $classes['textInput'],
			'{text.input.rows}' => $attrs['rows'],
			'{text.input.cols}' => $attrs['cols'],
			'{text.input.value}' => $inputValues['text'],
			'{submit.wrap.class}' => $classes['submit'],
			'{submit.label}' => $labels['submit'],
			'{submit.input.name}' => "{$id}_submit",
			'{submit.input.class}' => $classes['submitButton'],
			'{submit.input.value}' => 1,
			'{notify.wrap.class}' => $this->options['classes']['notify'],
			'{notify.label}' => $labels['notify'],
			'{notify.label.class}' => $this->options['classes']['label'],
			'{notify.label.span.class}' => $this->options['classes']['labelSpan'],
			'{notify.input.label.class}' => $this->options['classes']['radioLabel'],
			'{notify.input.class}' => $this->options['classes']['radioInput'],
			'{notify.input.name}' => 'notify',
			'{notify.input.off.value}' => '0',
			'{notify.input.off.label}' => $labels['notifyOff'],
			'{notify.input.off.checked}' => ($this->options['notifyDefault'] == 0 ? 'checked ' : ''),
			'{notify.input.replies.value}' => '2',
			'{notify.input.replies.label}' => $labels['notifyReplies'],
			'{notify.input.replies.checked}' => ($this->options['notifyDefault'] == 2 ? 'checked ' : ''),
			'{notify.input.all.value}' => '4',
			'{notify.input.all.label}' => $labels['notifyAll'],
			'{notify.input.all.checked}' => ($this->options['notifyDefault'] == 4 ? 'checked ' : ''),
		);

		$ifs = array(
			'website' => $field->useWebsite,
			'stars' => $useStars,
			'honeypot' => $useHoneypot,
			'notify' => $field->useNotify,
			'notify.replies' => $field->depth > 0,
			'notify.all' => $field->useNotify == Comment::flagNotifyAll,
		);
		
		$out = $this->getFormMarkup();

		foreach($ifs as $name => $value) {
			$out = $this->applyIf($name, $value, $out);
		}

		$out = str_replace(array_keys($placeholders), array_values($placeholders), $out);

		return $out;
	}
}