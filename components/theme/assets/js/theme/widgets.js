$(function(){
	var counter = 0;

	// Widget sources are draggable, but only to the droppable widgetareas.
	$('.widget-dragsource').draggable({
		helper: 'clone',
		connectToSortable: '.widget-dragtarget',
		opacity: 0.5,
		revert: 'invalid',
		appendTo: 'body',
		start: function(e, ui){
			$('.widget-dragtarget').addClass('highlightdroptarget');
		},
		stop: function (e, ui){
			$('.widget-dragtarget').removeClass('highlightdroptarget');
		}
	});

	$('.widget-dragtarget').sortable({
		stop: function(e, ui){
			var $el = ui.item,
				$parent,
				parent,
				instanceid;
			
			// No instance ID, this widget needs to be setup!
			if(!$el.attr('attr:instanceid')){
				instanceid = 'new-' + (++counter);
				$parent = $el.closest('.widgetarea');
				parent = $parent.attr('attr:area');
				
				$el.find('input.baseurl').attr('name', 'widgetarea[' + instanceid + '][baseurl]');
				// Update the widget's widgetarea.  This is critical to know where it should be rendered at.
				$el.find('input.widgetarea').attr('name', 'widgetarea[' + instanceid + '][widgetarea]').val(parent);
				
				$el.attr('attr:instanceid', instanceid);
			}
			
			// Update the classes anyhow.
			$el.removeClass('widget-dragsource').addClass('widget-dragdropped');
		},
		helper: 'original',
		revert: true
	});
	
	// All deletes over here need to do something.
	$('.widget-bucket-destination').delegate('a.control-delete', 'click', function(){
		var $this = $(this),
			$el = $this.closest('div.widget-dragdropped'),
			instance = $el.attr('attr:instanceid');
			
		console.log(instance);
		// It didn't exist in the first place, feel free to delete it.
		if(instance.indexOf('new-') === 0){
			$el.remove();
		}
		else{
			$el.attr('attr:instanceid', 'del-' + instance).hide();
			$el.find(':input').each(function(){
				var $this = $(this),
					n = $this.attr('name');
				$this.attr('name', n.replace('widgetarea[' + instance + ']', 'widgetarea[del-' + instance + ']'));
			});
		}
		
		return false;
	});

});