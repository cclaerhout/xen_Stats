<?php

class Sedo_Stats_Listener_Pie
{
	/***
	 * PIE RENDERER 
	 ***/
	public static function pieRenderer(&$content, array &$options, &$templateName, &$fallBack, array $rendererStates, $parentClass, $bbCodeIdentifier)
	{
		if(!empty($rendererStates['bbmPreCacheInit']))
		{
			//Need to modify the BBM to avoid this
			return false;
		}
		
		/* XenForo 1.1.x compatible code */
		if($bbCodeIdentifier != 'sedo_stats_pie')
		{
			return false;
		}

		if(empty($content))
		{
			return false;
		}

		/* Default variables */
		$inlineCss = array();
		
		$title = '';
		$hasTitle = false;
		$titleAlign = 'center';

		list($legendShow, $legendPos, $legendOutside) = Sedo_Stats_Helper_BbCodes::getLegendPosition();
		$legendOpt = false;
		
		$width = null;
		$height = null;
		list($minWidth, $maxWidth, $minHeight, $maxHeight) = Sedo_Stats_Helper_BbCodes::getMinMaxWidthHeight();
		
		$sizeRegex = '#^(\d{1,3}|@)(px)?x(\d{1,3}|@)(px)?$#'; //only pixels - no percent
		$sizeOpt = false;
		
		$blockClass = 'bleft';
		$floatClass = '';
		$blockOpt = false;

		$fillToZero = false;
		$fillToZeroOpt = false;
		
		$forcedRenderer = null;

		/* Get options and read them */
		foreach($options as $i => $option)
		{
			if($i > 20) { break; }
			$cleanOption = BBM_Helper_BbCodes::cleanOption($option, true);
			
			if(!$legendOpt && strpos($cleanOption, 'legend:') === 0)
			{
				$legendOpt = true;
				$legendOption = substr(str_replace(' ', '', $cleanOption), 7);
				list($legendShow, $legendPos, $legendOutside) = Sedo_Stats_Helper_BbCodes::getLegendPosition($legendOption);
				unset($options[$i]);
			}
			elseif(!$forcedRenderer && in_array($cleanOption, array('donut')) )
			{
				 //no need to add 'pie' ; only compatible with 1 serie of data
				$forcedRenderer = $cleanOption;
				unset($options[$i]);
			}
			elseif( !$sizeOpt && preg_match($sizeRegex, $cleanOption, $match) )
			{
				$sizeOpt = true;
				$width = $match[1];
				$height = $match[3];

				if($width != '@')
				{
					$width = ($width > $maxWidth) ? $maxWidth : ($width < $minWidth) ? $minWidth : $width;
					array_push($inlineCss, "width:{$width}px");
				}

				if($height != '@')
				{
					$height = ($height > $maxHeight) ? $maxHeight : ($height < $minHeight) ? $minHeight : $height;
					array_push($inlineCss, "height:{$height}px");
				}
				
				unset($options[$i]);
			}
			elseif ( !$blockOpt && in_array($cleanOption, array('bleft', 'bcenter', 'bright', 'fleft', 'fright')) )
			{
				$blockOpt = true;
				$blockClass = $cleanOption;
				
				if(in_array($cleanOption, array('fleft', 'fright')))
				{
					$blockClass = '';
					$floatClass = $cleanOption;
				}
				
				unset($options[$i]);
			}
		}

		list(  	$dataLabelsShow,
			$dataLabelsType,
			$dataLabelsFill,
			$dataLabelsMinValueToShow,
			$dataLabelsPosFactor,
			$dataStartAngle,
			$dataSliceMargin,
			$defaultRendererOptions) = self::manageSeriesOptions($options);

		/* Get special tags and read code */
		$stack = array();
		$data = array();
		$maxData = 2;
		$customSeries = array();

		$specialTagsInfo = BBM_Helper_BbCodes::getSpecialTags($content, array('title', 'data'));

		foreach($specialTagsInfo as $i => $info)
		{
			if($i > 8) { break; }

			$speTag = $info['tag'];
			$speOption = $info['option'];
			$speContent = $info['content'];

			$stack[$speTag] = !isset($stack[$speTag]) ? 1 : $stack[$speTag]+1;

			if( 	($speTag == 'title' && $stack[$speTag] != 1)
				||
				($speTag == 'data' && $stack[$speTag] > $maxData) 
			)
			{
				continue;
			}

			//Title tag
			if($speTag == 'title' && !empty($speContent))
			{
				$title = BBM_Helper_BbCodes::stripBbCodes($speContent, true);
				$hasTitle = true;
				$speOpts = explode('|', $speOption);
				
				foreach($speOpts as $n => $speOpt)
				{
					if($n > 5) { break; }
					
					$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
					
					if(in_array($speCleanOpt, array('left', 'center', 'right')))
					{
						$titleAlign = $speCleanOpt;
					}
				}
			}

			//Data tag
			if($speTag == 'data' && !empty($speContent))
			{
				//Options
				$speOpts = explode('|', $speOption);

				$customSerie = self::manageSeriesOptions($speOpts, true, $defaultRendererOptions, $stack[$speTag]);
				array_push($customSeries, $customSerie);
				
				//Content
				$speContent = str_replace(array('<br />', '&nbsp;'), array('[]', ' '), $speContent);
				$dataItems = array_filter(array_map('trim', explode('[]', $speContent)));
				$dataToPush = array();

				foreach($dataItems as $dataItem)
				{
					$dataItem = explode('|', $dataItem);
					if(!empty($dataItem[0]) && isset($dataItem[1]))
					{
						$dataName = BBM_Helper_BbCodes::stripBbCodes($dataItem[0], true);
						$dataVal = floatval($dataItem[1]);
						
						array_push($dataToPush, array($dataName, $dataVal));
					}
				}

				array_push($data, $dataToPush);
			}	
		}

		/* Data management */
		if(!isset($stack['data']))
		{
			return;
		}

		$iData = $stack['data'];

		switch($forcedRenderer)
		{
			case 'donut': $renderer = 'DonutRenderer'; break;
			default: $renderer = ($iData == 1) ? 'PieRenderer' : 'DonutRenderer';
		}

		/***
		 * Config mangement
		 * http://www.jqplot.com/docs/files/plugins/jqplot-pieRenderer-js.html#$.jqplot.PieRenderer.dataLabels
		 **/
		$config = array(
			'seriesColors' => Sedo_Stats_Helper_BbCodes::getStatColors(),
			'title' => array(
				'text' => $title,
				'show' => $hasTitle,
				'textAlign' => $titleAlign
			),		
			'seriesDefaults' => array(
				'renderer' => $renderer,
				'rendererOptions' => array(
					'dataLabels' => $dataLabelsType,
					'showDataLabels' => $dataLabelsShow,
					'dataLabelThreshold' => $dataLabelsMinValueToShow, //in percentage
					'dataLabelPositionFactor' => $dataLabelsPosFactor,
					'fill' => $dataLabelsFill,
					'startAngle' => $dataStartAngle,
					'sliceMargin' => $dataSliceMargin,
					'highlightMouseOver' => false,
					'highlightMouseDown' => true
				)
			),
			'series' => array($customSeries),
			'legend' => array(
				'show' => $legendShow,
				'location' => $legendPos
			),
			'grid' => Sedo_Stats_Helper_BbCodes::getGridConfig()
		);

		/*Legend Manager*/
		if($legendOutside)
		{
			$config['legend']['placement'] = 'outsideGrid';
		}

		$options['data'] = $data;
		$options['config'] = $config;
		$options['uniqid'] = uniqid('pie_');
		$options['renderer'] = $renderer;
		$options['inlineCss'] = implode('; ', $inlineCss);
		$options['blockClass'] = $blockClass;
		$options['floatClass'] = $floatClass;

		/* Responsive Management */
		$useResponsiveMode = BBM_Helper_BbCodes::useResponsiveMode();
		$options['responsiveMode'] = $useResponsiveMode;
		
		if($useResponsiveMode)
		{
			$options['inlineCss'] = '';
			$options['blockAlign'] = 'bcenter';
		}
		//Zend_Debug::dump($config);
	}

	public static function manageSeriesOptions(array $options, $customDataMode = false, array $defaultRendererOptions = array(), $dataId = 1,  $loopLimit = 8)
	{
		$dataLabelsShow = true;
		$dataLabelsType = 'percent';  // ‘label’, ‘value’, ‘percent’ 
		$dataLabelsFill = true;
		$dataLabelsMinValueToShow = 3;
		$dataLabelsPosFactor = '0.52';
		$dataStartAngle = 0;
		$dataSliceMargin = 0;

		$customRendererOptions = array();
		
		foreach($options as $n => $option)
      		{
      			if($n > $loopLimit) { break; }
      			
      			$cleanOption = BBM_Helper_BbCodes::cleanOption($option);

      			if(strpos($cleanOption, 'label:') === 0)
      			{
      				$labelOption = substr(str_replace(' ', '', $cleanOption), 6);
      
      				switch($labelOption)
      				{
      					case 'no': 
      						$customRendererOptions['showDataLabels'] = $dataLabelsShow = false;
      						break;
      					case 'percent': case 'value': case 'label':
      						$customRendererOptions['dataLabels'] = $dataLabelsType = $labelOption;
      						break;
      				}
      			}
      			elseif($cleanOption == 'nofill')
      			{
      				$customRendererOptions['fill'] = $dataLabelsFill = false;
      			}
			elseif(strpos($cleanOption, 'label-min:') === 0)
      			{
      				$minLabel = floatval(substr(str_replace(' ', '', $cleanOption), 10));
      				if($minLabel >= 0 && $minLabel <= 100)
      				{
      					$customRendererOptions['dataLabelThreshold'] = $dataLabelsMinValueToShow = $minLabel;
      				}
      			}
			elseif(strpos($cleanOption, 'label-pos:') === 0)
      			{
      				$labelFactor = floatval(substr(str_replace(' ', '', $cleanOption), 10));

      				if($labelFactor >= 0 && $labelFactor <= 1)
      				{
      					$customRendererOptions['dataLabelPositionFactor'] = $dataLabelsPosFactor = "$labelFactor";
      				}
      			}
			elseif(strpos($cleanOption, 'start-angle:') === 0)
      			{
      				$startAngle = intval(substr(str_replace(' ', '', $cleanOption), 12));

      				if($startAngle >= -180 && $startAngle <= 180)
      				{
      					$customRendererOptions['startAngle'] = $dataStartAngle = $startAngle;
      				}
      			}
			elseif(strpos($cleanOption, 'slice-margin:') === 0)
      			{
      				$sliceMargin = intval(substr(str_replace(' ', '', $cleanOption), 13));

      				if($sliceMargin >= 0 && $sliceMargin <= 20)
      				{
      					$customRendererOptions['sliceMargin'] = $dataSliceMargin = $sliceMargin;
      				}
      			}
      		}
      		
      		if(!$customDataMode)
      		{
	      		return array(
	      			$dataLabelsShow,
				$dataLabelsType,
				$dataLabelsFill,
				$dataLabelsMinValueToShow,
				$dataLabelsPosFactor,
				$dataStartAngle,
				$dataSliceMargin,
				array(
					'dataLabels' => $dataLabelsType,
					'showDataLabels' => $dataLabelsShow,
					'dataLabelThreshold' => $dataLabelsMinValueToShow,
					'dataLabelPositionFactor' => $dataLabelsPosFactor,
					'fill' => $dataLabelsFill,
					'startAngle' => $dataStartAngle,
					'sliceMargin' => $dataSliceMargin				
				)
	      		);
	      	}

		$customSerieData = array(
			'rendererOptions' => $customRendererOptions+$defaultRendererOptions
		);

		return $customSerieData;
	}	
}