if(typeof Sedo === 'undefined') var Sedo = {};

!function($, window, document, undefined)
{
	Sedo.Stats = 
	{
		init: function($charts)
		{
			var parent = Sedo.Stats, stack = [];

			if($.jqplot == undefined){
				console.debug('No jqplot');
				return false;
			}

      			/***
      			 * - Needed for preview form - reason: the xfActivate is executed too soon
      			 * - Still have some problems sometimes on the first preview - don't know why
      			 *   Solution: click again, it will work
      			 ***/			
			setTimeout(function(){
				parent.load($charts);
			}, 0);
		},
		load: function($charts)
		{			
			var parent = this, stack = [];

			$charts.attr('dir', 'ltr').each(function(){
				 var $chart = $(this),
					 id = $chart.attr('id'),
					 data = $chart.data('stats'),
					 config = $chart.data('config');

				if(id == undefined || data == undefined || config == undefined){
					console.debug('Bbm stats - missing params, id:', id);
					return false;
				}

				var defaultSeries = config.seriesDefaults;
			
				if(defaultSeries == undefined){
					return false;
				}

				/* Series renderer*/
				if(defaultSeries.renderer != 'none'){
					var defaultSeriesRenderer = parent.getRenderer(defaultSeries.renderer);

					if(!defaultSeriesRenderer){
						return false;
					}
				}else{
					delete defaultSeries.renderer;
				}

				defaultSeries.renderer = defaultSeriesRenderer;

				/* Function to format renderers */
				var keys = ['renderer', 'tickRenderer', 'labelRenderer'];
				
				var renderCheck = function(config, renderKey){
      					if(config != undefined && config[renderKey] != undefined){
      						var configRenderer = parent.getRenderer(config[renderKey]);
      						if(configRenderer){
      							config[renderKey] = configRenderer;
      						}else{
      							delete config[renderKey];
      						}
      					}				
				}

				/*Custom Series*/
				if(config.series != undefined){
					$.each(config.series, function(i, configSeries){
						$.each(keys, function (i, keyToModify){
							renderCheck(configSeries, keyToModify);
						});
					});
				}

				/*Axes*/
				if(config.axesDefaults != undefined){
					var axesDefaultsConfig = config.axesDefaults;

					$.each(keys, function (i, keyToModify){
						renderCheck(axesDefaultsConfig, keyToModify);						
					});					
				}
				
				if(config.axes != undefined){
					var axesConfig = config.axes;
					
					$.each([axesConfig.xaxis, axesConfig.x2axis, axesConfig.yaxis, axesConfig.y2axis], function(i, config){
						$.each(keys, function (i, keyToModify){
							renderCheck(config, keyToModify);						
						});
					});
				}

				/*Exec*/
				/*Debug - 1*/
				//console.log('data', data, 'config', config);

				var renderedPie = $.jqplot(id, data, config);
				$chart.data('renderConfig', renderedPie);
				$chart.attr('rendered', true);
		
				/*Replot XenAjax - don't do it on resize: it uses ressources for not that much*/
				$(document).bind('AutoValidationComplete', function(e){
					renderedPie.replot( { resetAxes:true } );
				});
				
				/*Debug - 2*/
				//console.log('data', data, 'config', config, 'render', $chart.data('renderConfig'));
			});
		},
		getRenderer:function(xRenderer)
		{
			if(xRenderer == undefined){
				return false;
			}
			
			if($.jqplot[xRenderer] !== undefined){
				return $.jqplot[xRenderer];
			}
			return false;		
		}
	}

	XenForo.register('.bbmStatsInit', 'Sedo.Stats.init');
}
(jQuery, this, document);