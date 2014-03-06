if(typeof Sedo === 'undefined') var Sedo = {};

!function($, window, document, undefined)
{
	Sedo.StatsAdmin = {};
	Sedo.StatsAdmin.sprintfOpt = function($element){ this.__construct($element); },
	Sedo.StatsAdmin.sprintfOpt.prototype = {
      		__construct: function($element)
      		{
      			$element.one('keypress', $.context(this, 'createChoice'));
      			$element.find('input[type=text]').val('');
      
      			this.$element = $element;
      			if (!this.$base){
      				this.$base = $element.clone();
      			}
      		},
      		createChoice: function()
      		{
      			var $new = this.$base.clone(),
      				nextCounter = this.$element.parent().children().length + 1; //Add 1
      
      			$new.find('input[name], select[name]').each(function(){
      				var $this = $(this);
      				$this.attr('name', $this.attr('name').replace(/\[(\d+)\]/, '[' + nextCounter + ']'));
      			});
      
      			$new.find('*[id]').each(function(){
      				var $this = $(this);
      				$this.removeAttr('id');
      				
      				var uniqId = XenForo.uniqueId($this);
      				$this.parent().attr('for', uniqId);
      
      				if (XenForo.formCtrl){
      					XenForo.formCtrl.clean($this);
      				}
      			});
      
      			$new.xfInsert('insertAfter', this.$element);
      			this.__construct($new);
      		}
	}

	XenForo.register('.BbmStatsSprintfOption', 'Sedo.StatsAdmin.sprintfOpt');
}
(jQuery, this, document);