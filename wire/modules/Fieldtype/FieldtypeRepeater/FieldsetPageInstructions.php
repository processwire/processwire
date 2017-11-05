<?php namespace ProcessWire;

function FieldsetPageInstructions(Field $field) {
	
	$exampleName = 'title';
	$exampleText = __('This is some example text');
	$repeaterFields = $field->get('repeaterFields');
	
	if(is_array($repeaterFields)) foreach($repeaterFields as $fid) {
		$f = $field->wire('fields')->get((int) $fid);
		if($f && $f->type instanceof FieldtypeText) {
			$exampleName = $f->name;
			break;
		}
	}

	$f = $field->wire('modules')->get('InputfieldMarkup');
	$f->attr('name', '_instructions');
	$f->label = __('Instructions on how to use this field');
	$f->collapsed = Inputfield::collapsedYes;
	$f->icon = 'life-ring';
	$f->value =
		"<p class='description'>" .
		__('This type of fieldset uses a separate page (behind the scenes) to store values for the fields you select above.') . ' ' .
		__('A benefit is that you can re-use fields that might already be present on your page, outside of the fieldset.') . ' ' .
		__('For example, you could have a “title” field on your page, and another in your fieldset. Likewise for any other fields.') . ' ' .
		__('This is possible because fields in the fieldset are in their own namespace—another page—separate from the main page.') . ' ' .
		__('Below are several examples on how to use this field in your template files and from the API.') .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" . __('Getting a value:') . "</span><br />" .
		"<code>\$$exampleName = \$page->{$field->name}->$exampleName;</code>" .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" . __('Outputting a value:') . "</span><br />" .
		"<code>echo \$page->{$field->name}->$exampleName;</code>" .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" . __('Outputting a value when in markup:') . "</span><br />" .
		"<code>&lt;div class='example'&gt;</code><br />" .
		"<code>&nbsp; &lt;?=\$page->{$field->name}->$exampleName?&gt;</code><br />" .
		"<code>&lt;/div&gt;</code>" .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" . __('Setting a value:') . "</span><br />" .
		"<code>\$page->{$field->name}->$exampleName = '$exampleText';</code>"  .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" . __('Setting and saving a value:') . "</span><br />" .
		"<code>\$page->of(false); <span class='detail'>// " . __('this turns off output formatting, when necessary') .
		"</span></code><br />" .
		"<code>\$page->{$field->name}->$exampleName = '$exampleText';</code><br />" .
		"<code>\$page->save();</code>" .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" . __('Assigning fieldset to another (shorter) variable and outputting a value:') . "</span><br />" .
		"<code>\$p = \$page->{$field->name};</code><br />" .
		"<code>echo \$p->$exampleName;</code>" .
		"</p>" . 
		
		"<p>" .
		"<span class='notes'>" .
		sprintf(__('Finding pages having fieldset with “%s” field containing text “example”:'), $exampleName) .
		"</span><br />" .
		"<code>\$items = \$pages->find('$field->name.$exampleName%=example');</code>" .
		"</p>";
	
	return $f;
}