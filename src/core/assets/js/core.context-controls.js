$(function(){
	//$('.controls-hover').each(function(){
	$('.controls').each(function(){
		var $original = $(this),
			$clone = $original.clone(),
			$wrapper = $('<div style="position:relative;" class="controls-hover-wrapper"/>'),
			controlcount = $clone.find('li').length;

		$original.addClass('controls-hover').after($wrapper);

		// Now, I can move them both into the wrapper.
		$original.appendTo($wrapper);
		$clone.appendTo($wrapper);

		// I'm going to do modifications to the original, since that may have events already bound to it.
		$original.addClass('context-menu');

		// This helps fine-tune the styles a little.
		if(controlcount == 1){
			$original.addClass('context-menu-one-link');
		}
		else{
			$original.addClass('context-menu-many-links');
		}

		// Ensure the clone width is correct.
		$original.css('min-width', $wrapper.width());

		// And hide it!  (it'll be shown on hover)
		$original.hide();

		// If there are more than 3 options, hide the rest.
		if(controlcount > 3){
			$clone.html('<li><i class="icon-cog"></i> Controls</li>');
		}

		$wrapper
			.mouseover(function(){
				$original.show();
			})
			.mouseout(function(){
				$original.hide();
			});
	});
});