{**
 * The main template for most form elements.  This was copied to the default theme because the description is moved to
 * after the input in this theme.
 *}

<div class="{$element->getClass()} {$element->get('id')}">
	<div class="formelement-labelinputgroup">
		{if $element->get('title')}
			<label for="{$element->get('id')}">{$element->get('title')|escape}</label>
		{/if}

		<input type="{$type}"{$element->getInputAttributes()}>

			<div class="clear"></div>
	</div>
	<div class="clear"></div>

	{if $element->get('description')}
		<p class="formdescription">{$element->get('description')}</p>
	{/if}

</div>