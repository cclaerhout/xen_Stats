<?php

class Sedo_Stats_Listener_Bar
{
	/***
	 * BAR RENDERER 
	 ***/
	protected static $mbStringSupport;
	 
	public static function barRenderer(&$content, array &$options, &$templateName, &$fallBack, array $rendererStates, $parentClass, $bbCodeIdentifier)
	{
		if(!empty($rendererStates['bbmPreCacheInit']))
		{
			//Need to modify the BBM to avoid this
			return false;
		}

		/* XenForo 1.1.x compatible code */
		if($bbCodeIdentifier != 'sedo_stats_bar')
		{
			return false;
		}

		if(empty($content))
		{
			return false;
		}

		$xenOptions = XenForo_Application::get('options');		
		self::$mbStringSupport = $parentClass->getTagExtra('mbstring');
		
		if(self::$mbStringSupport == null)
		{
			self::$mbStringSupport = extension_loaded('mbstring');
			$parentClass->addTagExtra('mbstring', self::$mbStringSupport);
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
		
		$dualAxeMode = false; // only compatible with 2 data tags
		$dualAxeModeOpt = false;
		
		$zoom = $xenOptions->sedo_stats_default_zoom;
		$zoomOpt = false;

		$rendererTicks = 'CanvasAxisTickRenderer';
		$rendererTicksOpt = false;
		
		$stackSeries = false;
		$stackSeriesOpt = false;
		
		$globalPad = null;
		$globalPadOpt = false;

		$highlighter = false;
		$highlighterOpt = false;
				
		/* Get options and read them */
		foreach($options as $i => $option)
		{
			if($i > 25) { break; }
			$cleanOption = BBM_Helper_BbCodes::cleanOption($option, true);
			
			if(!$legendOpt && strpos($cleanOption, 'legend:') === 0)
			{
				$legendOpt = true;
				$legendOption = substr(str_replace(' ', '', $cleanOption), 7);
				list($legendShow, $legendPos, $legendOutside) = Sedo_Stats_Helper_BbCodes::getLegendPosition($legendOption);
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
					array_push($inlineCss, "height:{$width}px");
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
			elseif(!$dualAxeModeOpt && $cleanOption == 'dual-axe')
			{
				//shortcut for dual axe
				$dualAxeMode = 'dualAxeLine';
				$dualAxeModeOpt = true;				
			}
			elseif(!$dualAxeModeOpt && strpos($cleanOption, 'dual-axe:') === 0)
      			{
      				$dualAxeModeVal = (substr(str_replace(' ', '', $cleanOption), 9));

				switch($dualAxeModeVal)
				{
					case 'line': 
						$dualAxeMode = 'dualAxeLine';
						$dualAxeModeOpt = true;
						break;
					case 'bar':
						$dualAxeMode = 'dualAxeBar';
						$dualAxeModeOpt = true;
						break;
				}
      			}
      			elseif(!$zoomOpt && $cleanOption == 'zoom')
      			{
				$zoomOpt = true;
      				$zoom = true;
      			}		
      			elseif(!$zoomOpt && $cleanOption == 'no-zoom')
      			{
				$zoomOpt = true;
      				$zoom = false;
      			}
			elseif(!$rendererTicksOpt && $cleanOption == 'no-tick')
			{
				$rendererTicks = false;
				$rendererTicksOpt = true;
			}
			elseif(!$stackSeriesOpt && $cleanOption == 'stack-data')
			{
				$stackSeries = true;
				$stackSeriesOpt = true;
			}
			elseif(!$globalPadOpt && strpos($cleanOption, 'pad:') === 0)
			{
				$globalPadOpt = true;
				$globalPad = floatval(substr(str_replace(' ', '', $cleanOption), 4));
			}
			elseif(!$highlighterOpt && $cleanOption == 'highlighter')
			{
				$highlighter = true;
				$highlighterOpt = true;
			}
		}

		list(  	$tickAngle,
			$globalGrid,
			$loadPointLabelsJs,
			$globalModAxis,
			$animate,
			$barDirection,
			$rootDefaults,
			$seriesDefaults) = self::manageSeriesOptions($options);

		/* Get special tags and read code */
		$stack = array();
		$data = array();
		$maxData = 150;
		$customSeries = array();
		$customSeriesGrid = array();
		
		$specialTagsInfo = BBM_Helper_BbCodes::getSpecialTags(
			$content,
			array('title', 'data', 'ticks', 'xaxis', 'yaxis', 'x2axis', 'y2axis', 'points', 'zoom', 'hl')
		);
		
		$onlyDataMode = null;
		$xDataEntryMode = null;
		$xDataEntryModeLabel = null;

		$manualTicks = array();
		$manualTicksTarget = 'xaxis';
		
		$pointLabels = array();
		
		$noRendererMode = null;

		$zoomTooltip = false;
		$zoomTooltipLocation = null;
		$zoomShowVerticalLine = false;
		$zoomShowHonrizontalLine = false;
		$zoomLoose = false;
		$zoomConstrainTo = null;
		$zoomFollowMouse = false;
		$zoomCursorLegend = false;
		$zoomCursorLegendFormat = null;
		
		$hlShowMarker = true;
		$hlShowTooltip = true;		
		$hlTooltipLocation = null;
		$hlTooltipAxes = null;
		$hlTooltipFormatString = null;
		$hlFormatString = null;
		$hlSizeAdjust = null;
		
		$xaxisLabel = null;
		$xaxisMin = null;
		$xaxisMax = null;
		$xaxisStringFormatter = null;
		$xaxisTickAngle = null;
		$xaxisRenderer = null;
		$xaxisRenderingTicks = null;
		$xaxisNoGrid = null;
		$xaxisNoTickMark = null;
		$xaxisTickInterval = null;
		$xaxisAlignTicks = null;
		$xaxisForceTickAt0 = null;
		$xaxisPad = null;
		
		$yaxisLabel = null;
		$yaxisMin = null;
		$yaxisMax = null;
		$yaxisStringFormatter = null;
		$yaxisTickAngle = null;
		$yaxisRenderer = null;
		$yaxisRenderingTicks = null;
		$yaxisNoGrid = null;
		$yaxisNoTickMark = null;
		$yaxisTickInterval = null;
		$yaxisAlignTicks = null;
		$yaxisForceTickAt0 = null;
		$yaxisPad = null;
		
		$x2axisLabel = null;
		$x2axisMin = null;
		$x2axisMax = null;
		$x2axisStringFormatter = null;
		$x2axisTickAngle = null;
		$x2axisRenderer = null;		
		$x2axisRenderingTicks = null;
		$x2axisNoGrid = null;
		$x2axisNoTickMark = null;
		$x2axisTickInterval = null;
		$x2axisAlignTicks = null;
		$x2axisForceTickAt0 = null;
		$x2axisPad = null;
		
		$y2axisLabel = null;
		$y2axisMin = null;
		$y2axisMax = null;
		$y2axisStringFormatter = null;
		$y2axisTickAngle = null;
		$y2axisRenderer = null;
		$y2axisRenderingTicks = null;
		$y2axisNoGrid = null;
		$y2axisNoTickMark = null;			
		$y2axisTickInterval = null;
		$y2axisAlignTicks = null;
		$y2axisForceTickAt0 = null;
		$y2axisPad = null;
		
		$xenStringFormatter = $xenOptions->get('sedo_stats_sprintf');

		foreach($specialTagsInfo as $i => $info)
		{
			if($i > 50) { break; }

			$speTag = strtolower($info['tag']);
			$speOption = $info['option'];
			$speContent = $info['content'];

			$stack[$speTag] = !isset($stack[$speTag]) ? 1 : $stack[$speTag]+1;

			if( 	($speTag == 'title' && $stack[$speTag] != 1)
				||
				($speTag == 'xaxis' && $stack[$speTag] != 1)
				||
				($speTag == 'yaxis' && $stack[$speTag] != 1)
				||
				($speTag == 'x2axis' && $stack[$speTag] != 1)
				||
				($speTag == 'y2axis' && $stack[$speTag] != 1)
				||
				($speTag == 'ticks' && $stack[$speTag] != 1)
				||
				($speTag == 'points' && $stack[$speTag] != 1)
				||
				($speTag == 'zoom' && $stack[$speTag] != 1)
				||
				($speTag == 'hl' && $stack[$speTag] != 1)
				||
				($speTag == 'data' && $stack[$speTag] > $maxData) 
			)
			{
				continue;
			}

			switch($speTag)
			{
				case 'title':
					if(empty($speContent))	{ break; }
					
					$title = Sedo_Stats_Helper_BbCodes::filterString($speContent);
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
				break;
	
				case 'xaxis':
					if(!empty($speContent))
					{
						$xaxisLabel = Sedo_Stats_Helper_BbCodes::filterString($speContent);
					}
					
					$speOpts = explode('|', $speOption);

					foreach($speOpts as $n => $speOpt)
					{
						if($n > 30) { break; }
						
						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));

						if(strpos($speCleanOpt, 'min:') === 0)
						{
							$xaxisMin = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));
						}
						elseif(strpos($speCleanOpt, 'max:') === 0)
						{
							$xaxisMax = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'pad:') === 0)
						{
							$xaxisPad = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}					
						elseif(strpos($speCleanOpt, 'string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 7);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$xaxisStringFormatter = $xenStringFormatter[$stringFormatterKey];
							}
						}
						elseif(strpos($speCleanOpt, 'tick-angle:') === 0)
			      			{
			      				$axisTickAngle = intval(substr(str_replace(' ', '', $speCleanOpt), 11));

			      				if($axisTickAngle >= -180 && $axisTickAngle <= 180)
			      				{
			      					$xaxisTickAngle = $axisTickAngle;
			      				}
			      			}
			      			elseif($speCleanOpt == 'renderer')
			      			{
			      				$xaxisRenderer = true;
			      			}
			      			elseif($speCleanOpt == 'no-renderer')
			      			{
			      				$xaxisRenderer = false;			      			
			      			}
			      			elseif($speCleanOpt == 'renderer-ticks')
			      			{
				      			$xaxisRenderingTicks = true;
			      			}
			      			elseif($speCleanOpt == 'no-tick')
			      			{
				      			$xaxisRenderingTicks = false;
			      			}
			      			elseif($speCleanOpt == 'no-grid')
						{
							$xaxisNoGrid = true;
						}
						elseif($speCleanOpt == 'no-tick-mark')
						{
							$xaxisNoTickMark = true;
						}
						elseif(strpos($speCleanOpt, 'tick-interval:') === 0)
						{
							$tickIntervalVal = substr(str_replace(' ', '', $speCleanOpt), 14);
							$xaxisTickInterval = Sedo_Stats_Helper_BbCodes::filterFloat($tickIntervalVal);
						}
						elseif($speCleanOpt == 'tick-align')
						{
							$xaxisAlignTicks = true;
						}
						elseif($speCleanOpt == 'tick-zero')
						{
							$xaxisForceTickAt0  = true;
						}						
					}		
				break;

				case 'yaxis':
					if(!empty($speContent))
					{				
						$yaxisLabel = Sedo_Stats_Helper_BbCodes::filterString($speContent);
					}

					$speOpts = explode('|', $speOption);
					
					foreach($speOpts as $n => $speOpt)
					{
						if($n > 30) { break; }
						
						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
						
						if(strpos($speCleanOpt, 'min:') === 0)
						{
							$yaxisMin = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));
						}
						elseif(strpos($speCleanOpt, 'max:') === 0)
						{
							$yaxisMax = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'pad:') === 0)
						{
							$yaxisPad = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 7);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$yaxisStringFormatter = $xenStringFormatter[$stringFormatterKey];
							}
						}
						elseif(strpos($speCleanOpt, 'tick-angle:') === 0)
			      			{
			      				$axisTickAngle = intval(substr(str_replace(' ', '', $speCleanOpt), 11));

			      				if($axisTickAngle >= -180 && $axisTickAngle <= 180)
			      				{
			      					$yaxisTickAngle = $axisTickAngle;
			      				}
			      			}
			      			elseif($speCleanOpt == 'renderer')
			      			{
			      				$yaxisRenderer = true;
			      			}
			      			elseif($speCleanOpt == 'no-renderer')
			      			{
			      				$yaxisRenderer = false;			      			
			      			}
			      			elseif($speCleanOpt == 'renderer-ticks')
			      			{
				      			$yaxisRenderingTicks = true;
			      			}
			      			elseif($speCleanOpt == 'no-tick')
			      			{
				      			$yaxisRenderingTicks = false;
			      			}
			      			elseif($speCleanOpt == 'no-grid')
						{
							$yaxisNoGrid = true;
						}
						elseif($speCleanOpt == 'no-tick-mark')
						{
							$yaxisNoTickMark = true;
						}
						elseif(strpos($speCleanOpt, 'tick-interval:') === 0)
						{
							$tickIntervalVal = substr(str_replace(' ', '', $speCleanOpt), 14);
							$yaxisTickInterval = Sedo_Stats_Helper_BbCodes::filterFloat($tickIntervalVal);
						}
						elseif($speCleanOpt == 'tick-align')
						{
							$yaxisAlignTicks = true;
						}
						elseif($speCleanOpt == 'tick-zero')
						{
							$yaxisForceTickAt0  = true;
						}		      			
					}
				break;

				case 'x2axis':
					if(!empty($speContent))
					{
						$x2axisLabel = Sedo_Stats_Helper_BbCodes::filterString($speContent);
					}
					
					$speOpts = explode('|', $speOption);
					
					foreach($speOpts as $n => $speOpt)
					{
						if($n > 30) { break; }
						
						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
						
						if(strpos($speCleanOpt, 'min:') === 0)
						{
							$x2axisMin = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));
						}
						elseif(strpos($speCleanOpt, 'max:') === 0)
						{
							$x2axisMax = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'pad:') === 0)
						{
							$x2axisPad = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 7);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$x2axisStringFormatter = $xenStringFormatter[$stringFormatterKey];
							}
						}
						elseif(strpos($speCleanOpt, 'tick-angle:') === 0)
			      			{
			      				$axisTickAngle = intval(substr(str_replace(' ', '', $speCleanOpt), 11));

			      				if($axisTickAngle >= -180 && $axisTickAngle <= 180)
			      				{
			      					$x2axisTickAngle = $axisTickAngle;
			      				}
			      			}
			      			elseif($speCleanOpt == 'renderer')
			      			{
			      				$x2axisRenderer = true;
			      			}
			      			elseif($speCleanOpt == 'no-renderer')
			      			{
			      				$x2axisRenderer = false;			      			
			      			}
			      			elseif($speCleanOpt == 'renderer-ticks')
			      			{
				      			$x2axisRenderingTicks = true;
			      			}
			      			elseif($speCleanOpt == 'no-tick')
			      			{
				      			$x2axisRenderingTicks = false;
			      			}
			      			elseif($speCleanOpt == 'no-grid')
						{
							$x2axisNoGrid = true;
						}
						elseif($speCleanOpt == 'no-tick-mark')
						{
							$x2axisNoTickMark = true;
						}
						elseif(strpos($speCleanOpt, 'tick-interval:') === 0)
						{
							$tickIntervalVal = substr(str_replace(' ', '', $speCleanOpt), 14);
							$x2axisTickInterval = Sedo_Stats_Helper_BbCodes::filterFloat($tickIntervalVal);
						}
						elseif($speCleanOpt == 'tick-align')
						{
							$x2axisAlignTicks = true;
						}
						elseif($speCleanOpt == 'tick-zero')
						{
							$x2axisForceTickAt0  = true;
						}		      			
					}					
				break;

				case 'y2axis':
					if(!empty($speContent))
					{				
						$y2axisLabel = Sedo_Stats_Helper_BbCodes::filterString($speContent);
					}

					$speOpts = explode('|', $speOption);
					
					foreach($speOpts as $n => $speOpt)
					{
						if($n > 30) { break; }
						
						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
						
						if(strpos($speCleanOpt, 'min:') === 0)
						{
							$y2axisMin = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));
						}
						elseif(strpos($speCleanOpt, 'max:') === 0)
						{
							$y2axisMax = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'pad:') === 0)
						{
							$y2axisPad = floatval(substr(str_replace(' ', '', $speCleanOpt), 4));						
						}
						elseif(strpos($speCleanOpt, 'string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 7);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$y2axisStringFormatter = $xenStringFormatter[$stringFormatterKey];
							}
						}
						elseif(strpos($speCleanOpt, 'tick-angle:') === 0)
			      			{
			      				$axisTickAngle = intval(substr(str_replace(' ', '', $speCleanOpt), 11));

			      				if($axisTickAngle >= -180 && $axisTickAngle <= 180)
			      				{
			      					$y2axisTickAngle = $axisTickAngle;
			      				}
			      			}
			      			elseif($speCleanOpt == 'renderer')
			      			{
			      				$y2axisRenderer = true;
			      			}
			      			elseif($speCleanOpt == 'no-renderer')
			      			{
			      				$y2axisRenderer = false;			      			
			      			}
			      			elseif($speCleanOpt == 'renderer-ticks')
			      			{
				      			$y2axisRenderingTicks = true;
			      			}
			      			elseif($speCleanOpt == 'no-tick')
			      			{
				      			$y2axisRenderingTicks = false;
			      			}
			      			elseif($speCleanOpt == 'no-grid')
						{
							$y2axisNoGrid = true;
						}
						elseif($speCleanOpt == 'no-tick-mark')
						{
							$y2axisNoTickMark = true;
						}
						elseif(strpos($speCleanOpt, 'tick-interval:') === 0)
						{
							$tickIntervalVal = substr(str_replace(' ', '', $speCleanOpt), 14);
							$y2axisTickInterval = Sedo_Stats_Helper_BbCodes::filterFloat($tickIntervalVal);
						}
						elseif($speCleanOpt == 'tick-align')
						{
							$y2axisAlignTicks = true;
						}
						elseif($speCleanOpt == 'tick-zero')
						{
							$y2axisForceTickAt0  = true;
						}											
					}
				break;
				
				case 'ticks':
					if(empty($speContent))	{ break; }
					$speContent = str_replace(array('<br />', '&nbsp;'), array('|', ' '), $speContent);
					$manualTicks = explode('|', $speContent);

					$speOpts = explode('|', $speOption);
					
					foreach($speOpts as $n => $speOpt)
					{
						if($n > 5) { break; }
						
						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
						
						if(in_array($speCleanOpt, array('xaxis', 'yaxis', 'x2axis', 'y2axis')))
						{
							$manualTicksTarget = $speCleanOpt;
						}
					}					
				break;
				
				case 'data':
					if(empty($speContent))	{ break; }

					//Options
					$speOpts = explode('|', $speOption);
	
					list(	$customSerieGrid, 
						$customSerie,
						$CustomAnimate,
						$customLoadPointLabelsJs,
						$customNoRendererMode
						) = self::manageSeriesOptions($speOpts, true, $seriesDefaults, $stack[$speTag]);
					
					if($customNoRendererMode)
					{
						 $noRendererMode = true;
					}
					
					if($customLoadPointLabelsJs)
					{
						$loadPointLabelsJs = true;
					}
					
					if($CustomAnimate)
					{
						$animate = true;
					}
					
					//Content
					$dataToPush = array();
					$speContent = str_replace(array('<br />', '&nbsp;'), array('[]', ' '), $speContent);
					$dataItems = array_filter(array_map('trim', explode('[]', $speContent)));					
										
					if(	(count($dataItems) == 1 && strpos($speContent, ';') === false)
						 &&
						 preg_match('#^(\d+)x(\d+)(?:x)?([\S ]+)?$#u', $speContent, $speContentItemMatch)
					)
      					{
						//Make this shortcut to allow multi data with the -x-x- format (will not be exaclty the same than -x-x-;-x-x-;-x-x-)
						$xDataEntryMode = true;

      						$nestedItemsToPush = array(
      							floatval($speContentItemMatch[1]),
      							floatval($speContentItemMatch[2])
      						);
      						
      						if(isset($speContentItemMatch[3]) && isset($seriesDefaults['pointLabels']))
      						{
      							$newItem = Sedo_Stats_Helper_BbCodes::filterString($speContentItemMatch[3]);
							$xDataEntryModeLabel = true;
      							
      							if($newItem == 'null')
      							{
      								//Not really usefull
      								$newItem = null;
      							}
      							
      							array_push($nestedItemsToPush, $newItem);
      						}
      						
      						array_push($dataToPush, $nestedItemsToPush);
      					}
      					else
      					{
						foreach($dataItems as $dataItem)
						{
							$dataItem = explode('|', $dataItem);
							if(isset($dataItem[1]) && $onlyDataMode !== true)
							{
								$onlyDataMode = false;
								
								$dataName = Sedo_Stats_Helper_BbCodes::filterString($dataItem[0]);
								$dataVal = $dataItem[1];
								
								$dataValTest = explode(';', $dataVal);
							
								if(isset($dataValTest[1]))
								{
									$dataVal = array_map(array('Sedo_Stats_Helper_BbCodes', 'filterFloat'), $dataValTest);
	
									foreach($dataVal as $dataValItem)
									{
										if(preg_match('#^(\d+)x(\d+)(?:x)?([\S ]+)?$#u', $dataValItem, $dataValItemMatch))
										{
											$xDataEntryMode = true;
											
											$nestedItemsToPush = array(
												floatval($dataValItemMatch[1]),
												floatval($dataValItemMatch[2])
											);
											
											if(isset($dataValItemMatch[3]) && isset($seriesDefaults['pointLabels']))
											{
												$xDataEntryModeLabel = true;
												$newItem = Sedo_Stats_Helper_BbCodes::filterString($dataValItemMatch[3]);
												
												if($newItem == 'null')
												{
													$newItem = null;
												}
												
												array_push($nestedItemsToPush, $newItem);
											}
											
											array_push($dataToPush, $nestedItemsToPush);
										}
										else
										{
											array_push($dataToPush, floatval($dataValItem));
										}
									}
									continue;
								}
								else
								{
									$dataVal = Sedo_Stats_Helper_BbCodes::filterFloat($dataVal);
									array_push($dataToPush, array($dataName, $dataVal));
								}						
							}
							elseif($onlyDataMode !== false)
							{
								$onlyDataMode = true;
								$dataVal = (isset($dataItem[1])) ? $dataItem[1] : $dataItem[0]; //auto patch
								$dataVal = trim($dataVal);
	
								$dataValTest = explode(';', $dataVal);
					
								if(isset($dataValTest[1]))
								{
									//$dataVal = array_map(array('Sedo_Stats_Helper_BbCodes', 'filterFloat'), $dataValTest);
									$dataVal =  $dataValTest;
	
									foreach($dataVal as $dataValItem)
									{
										if(preg_match('#^(\d+)x(\d+)(?:x)?([\S ]+)?$#u', $dataValItem, $dataValItemMatch))
										{
											$xDataEntryMode = true;
											
											$nestedItemsToPush = array(
												floatval($dataValItemMatch[1]),
												floatval($dataValItemMatch[2])
											);
											
											if(isset($dataValItemMatch[3]) && isset($seriesDefaults['pointLabels']))
											{
												$xDataEntryModeLabel = true;
												$newItem = Sedo_Stats_Helper_BbCodes::filterString($dataValItemMatch[3]);
												
												if($newItem == 'null')
												{
													$newItem = null;
												}
												
												array_push($nestedItemsToPush, $newItem);
											}
											
											array_push($dataToPush, $nestedItemsToPush);
										}
										else
										{
											array_push($dataToPush, floatval($dataValItem));
										}
									}
									continue;								
								}
								else
								{
									$dataVal = Sedo_Stats_Helper_BbCodes::filterFloat($dataVal);
								}
	
								array_push($dataToPush, $dataVal);
							}
						}
					}

					array_push($customSeries, $customSerie);
					array_push($customSeriesGrid, $customSerieGrid);	
					array_push($data, $dataToPush);	
				break;
				
				case 'points':
					if(empty($speContent))	{ break; }
					
					$speContent = str_replace(array('<br />', '&nbsp;'), array('|', ' '), $speContent);
					$pointLabels = explode('|', $speContent);
					//might need to make a loop and check for null string (not really useful thus)
				break;

				case 'zoom':
					$zoom = true;
					
					if(!empty($speContent))
					{
						//not use at the moment
						$zoomContent = Sedo_Stats_Helper_BbCodes::filterString($speContent);
					}
					
					$speOpts = explode('|', $speOption);
					
					foreach($speOpts as $n => $speOpt)
					{
						if($n > 15) { break; }

						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
						
						if($speCleanOpt == 'tooltip')
						{
							$zoomTooltip = true;
						}
						elseif(strpos($speCleanOpt, 'tooltip:') === 0)
						{
							$zoomTooltipVal = substr(str_replace(' ', '', $speCleanOpt), 8);
							$zoomTooltipVal = Sedo_Stats_Helper_BbCodes::filterString($zoomTooltipVal);
							
							if(in_array($zoomTooltipVal, array('nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w')))
							{
								$zoomTooltip = true;
								$zoomTooltipLocation = $zoomTooltipVal;
							
							}
							elseif($zoomTooltipVal == 'no')
							{
								$zoomTooltip = false;
							}
						}
						elseif($speCleanOpt == 'no-tooltip')
						{
							$zoomTooltip = false;
						}
						elseif($speCleanOpt == 'v-line')
						{
							$zoomShowVerticalLine = true;
						}
						elseif($speCleanOpt == 'h-line')
						{
							$zoomShowHonrizontalLine = true;
						}
						elseif($speCleanOpt == 'loose')
						{
							$zoomLoose = true;
						}
						elseif(strpos($speCleanOpt, 'constrain:') === 0)
						{
							$zoomConstrainToVal = substr(str_replace(' ', '', $speCleanOpt), 10);
							$zoomConstrainToVal = Sedo_Stats_Helper_BbCodes::filterString($zoomConstrainToVal);

							if(in_array($zoomConstrainToVal, array('x', 'y')))
							{
								$zoomConstrainTo = $zoomConstrainToVal;
							}
						}
						elseif($speCleanOpt == 'follow-mouse')
						{
							$zoomFollowMouse = true;
						}
						elseif($speCleanOpt == 'legend')
						{
							$zoomCursorLegend = true;
						}
						elseif(strpos($speCleanOpt, 'legend-string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 14);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$zoomCursorLegendFormat = $xenStringFormatter[$stringFormatterKey];
							}
						}						
					}
				break;		

				case 'hl':
					$highlighter = true;
					
					if(!empty($speContent))
					{
						//not use at the moment
						$zoomContent = Sedo_Stats_Helper_BbCodes::filterString($speContent);
					}
					
					$speOpts = explode('|', $speOption);
					
					foreach($speOpts as $n => $speOpt)
					{
						if($n > 15) { break; }

						$speCleanOpt = trim(BBM_Helper_BbCodes::cleanOption($speOpt, true));
						
						if($speCleanOpt == 'no-marker')
						{
							$hlShowMarker = false;
						}
						elseif($speCleanOpt == 'no-tooltip')
						{
							$hlShowTooltip = false;
						}
						elseif(strpos($speCleanOpt, 'tooltip:') === 0)
						{
							$hlTooltipVal = substr(str_replace(' ', '', $speCleanOpt), 8);
							$hlTooltipVal = Sedo_Stats_Helper_BbCodes::filterString($hlTooltipVal);
							
							if(in_array($hlTooltipVal, array('nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w')))
							{
								$hlShowTooltip = true;
								$hlTooltipLocation = $hlTooltipVal;
							
							}
							elseif($zoomTooltipVal == 'no')
							{
								$hlShowTooltip = false;
							}
						}
						elseif(strpos($speCleanOpt, 'tooltip-axis:') === 0)
						{
							$hlTooltipAxesVal = substr(str_replace(' ', '', $speCleanOpt), 13);
							$hlTooltipAxesVal = Sedo_Stats_Helper_BbCodes::filterString($hlTooltipAxesVal);
							
							if(in_array($hlTooltipAxesVal, array('x', 'y', 'xy', 'yx')))
							{
								$hlTooltipAxes = $hlTooltipAxesVal;
							}
						}
						elseif(strpos($speCleanOpt, 'tooltip-string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 15);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$hlTooltipFormatString = $xenStringFormatter[$stringFormatterKey];
							}
						}
						elseif(strpos($speCleanOpt, 'string:') === 0)
						{
							$stringFormatterKey = substr(str_replace(' ', '', $speCleanOpt), 7);
							if(isset($xenStringFormatter[$stringFormatterKey]))
							{
								$hlFormatString = $xenStringFormatter[$stringFormatterKey];
							}
						}
						elseif(strpos($speCleanOpt, 'size-adjust:') === 0)
						{
							$hlSizeAdjustVal = substr(str_replace(' ', '', $speCleanOpt), 7);
							$hlSizeAdjustVal = Sedo_Stats_Helper_BbCodes::filterFloat($hlSizeAdjustVal);
							
							if($hlSizeAdjustVal > 0 && $hlSizeAdjustVal < 30)
							{
								$hlSizeAdjust = $hlSizeAdjustVal;
							}
						}
					}
				break;
			}
		}

		/* Data management */
		if(!isset($stack['data']))
		{
			return;
		}

		$iData = $stack['data'];

		if($iData != 2)
		{
			$dualAxeMode = false;
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
			'seriesDefaults' => $seriesDefaults,
			'series' => $customSeries,
			'legend' => array(
				'show' => $legendShow,
				'location' => $legendPos
			),
			'axes' => array(
				'xaxis' => array(
					'renderer' => 'CategoryAxisRenderer'
				)
			),			
			'grid' => Sedo_Stats_Helper_BbCodes::getGridConfig()
		);

		$config = array_merge_recursive($config, $rootDefaults);

      		/* Ticks Manager - default axes */
      		$axisNoRenderingTicks = false;
      		$ticksOptions = array(
      			'angle' => $tickAngle,
      			'fontSize' => '10pt'
      		);

      		if($xenOptions->sedo_stats_tick_disable_font_support)
      		{
      			$ticksOptions['enableFontSupport'] = false;	
      		}
      		else
      		{
      			$ticksOptions['fontFamily'] = 'Georgia, Arial, Helvetica, sans-serif';	
      		}

      		if($xaxisRenderingTicks === false || $x2axisRenderingTicks === false || $yaxisRenderingTicks === false || $y2axisRenderingTicks === false)
      		{
      			$axisNoRenderingTicks = true;	
      		}
      		elseif($rendererTicks)
      		{
			//http://www.jqplot.com/docs/files/plugins/jqplot-canvasAxisTickRenderer-js.html
			$config['axesDefaults'] = array(
      				'tickRenderer' => $rendererTicks,
      				'tickOptions' => $ticksOptions
      			);
      		}

		/*Legend position Manager*/
		if($legendOutside)
		{
			$config['legend']['placement'] = 'outsideGrid';
		}

		/*Animation Manager*/
		if($animate)
		{
			$config['animate'] = true;
		}

		/*Zoom Manager*/
		if($zoom)
		{
			$config['cursor'] = array(
				'show' => true,
				'zoom' => true,
				'showTooltip' => $zoomTooltip
			);
			
			if($zoomTooltipLocation !== null)
			{
				$config['cursor']['tooltipLocation'] = $zoomTooltipLocation;
			}
			
			if($zoomShowVerticalLine)
			{
				$config['cursor']['showVerticalLine'] = true;
			}

			if($zoomShowVerticalLine)
			{
				$config['cursor']['showHorizontalLine'] = true;
			}
			
			if($zoomLoose)
			{
				$config['cursor']['looseZoom'] = true;
			}
			
			if($zoomConstrainTo !== null)
			{
				$config['cursor']['constrainZoomTo'] = $zoomConstrainTo;
			}
			
			if($zoomFollowMouse)
			{
				$config['cursor']['followMouse'] = true;
			}
			
			if($zoomCursorLegend)
			{
				$config['cursor']['showCursorLegend'] = true;
			}
			
			if($zoomCursorLegendFormat !== null)
			{
				$config['cursor']['cursorLegendFormatString'] = $zoomCursorLegendFormat;
			}
		}

		/*Highlighter Manager*/
		if($highlighter)
		{
			$config['highlighter'] = array(
				'show' => true
			);
			
			if($hlShowMarker === false)
			{
				$config['highlighter']['showMarker'] = false;
			}

			if($hlShowTooltip === false)
			{
				$config['highlighter']['showTooltip'] = false;		
			}

			if($hlTooltipLocation !== null)
			{
				$config['highlighter']['tooltipLocation'] = $hlTooltipLocation;			
			}

			if($hlTooltipAxes !== null)
			{
				$config['highlighter']['tooltipAxes'] = $hlTooltipAxes;			
			}	

			if($hlTooltipFormatString !== null)
			{
				$config['highlighter']['tooltipFormatString'] = $hlTooltipFormatString;			
			}

			if($hlFormatString !== null)
			{
				$config['highlighter']['formatString'] = $hlFormatString;			
			}

			if($hlSizeAdjust !== null)
			{
				$config['highlighter']['sizeAdjust'] = $hlSizeAdjust;			
			}			
		}

		/*Stack Series Manager*/
		if($stackSeries)
		{
			$config['stackSeries'] = true;
		}

		/*Dual Axe Mode Manager*/
		if($dualAxeMode)
		{
			$dualAxeModeConfig = array(
				'series' => array(
					array(
						'renderer' => 'BarRenderer'
					),
					array(
						'xaxis' => 'x2axis',
						'yaxis' => 'y2axis'
					)
				),
				'axes' => array(
					'xaxis' => array(
						'renderer' => 'CategoryAxisRenderer'
					),
					'x2axis' => array(
						'renderer' => 'CategoryAxisRenderer'
					),
					'yaxis' => array(
						'autoscale' => true

					), 
					'y2axis'=> array(
						'autoscale' => true
					)
				)
			);

			if($dualAxeMode == 'dualAxeLine')
			{
				$dualAxeModeConfig['seriesDefaults'] = array(
					'renderer' => 'none'
				);
			}

			$config = array_replace_recursive($config, $dualAxeModeConfig);
		}
		elseif($noRendererMode)
		{
			$config['seriesDefaults']['renderer'] = 'none';

			foreach($config['series'] as $k => $s)
			{
				if(isset($s['renderer']) && $s['renderer'] == false)
				{
					unset($config['series'][$k]['renderer']);
				}
				else
				{
					$config['series'][$k]['renderer'] = 'BarRenderer';
				}
			}
		}

		/*Point Labels Manager*/
      		if(isset($config['pointLabels']) && !empty($pointLabels))
      		{
			if($xDataEntryMode && !$xDataEntryModeLabel)
			{
				//Let's make the point special tag works with the xDataEntryMode modes
				foreach($pointLabels as $k => $label)
				{
					$data_n = count($data);
					
					if($data_n == 1)
					{
						//For xDataEntryMode with ';'
						if(!isset($data[0], $data[0][$k]))
						{
							continue;
						}

						array_push($data[0][$k], $label);
					}
					else
					{
						//For xDataEntryMode with several 'data' special tags
						if(!isset($data[$k], $data[$k][0]))
						{
							continue;
						}

						array_push($data[$k][0], $label);
					}
				}
			}
			else
			{
				$config['pointLabels']['labels'] = $pointLabels;
			}
      		}

		/*Axes manager*/

			//Manual axis mod manager
			if(!empty($globalModAxis))
			{
				foreach($globalModAxis as $from => $to)
				{
					$customSerieData[$from] = $to;
				}
			}

			//Global pad
			if($globalPad !== null)
			{
				$config['axesDefaults']['pad'] = $globalPad;
			}			
			
			//xaxis
			if(($xaxisLabel !== null || $xaxisMin !== null || $xaxisMax !== null)
				&&
				!isset($config['axes']['xaxis'])
			)
			{
				$config['axes']['xaxis'] = array();
			}
	
				if($xaxisLabel !== null)
				{
					$xaxisLabelConfig = array(
						'label' => $xaxisLabel,
						'labelRenderer' => 'CanvasAxisLabelRenderer',
						//'renderer' => 'CategoryAxisRenderer'
					);
					
					$config['axes']['xaxis'] += $xaxisLabelConfig;
				}
	
				if($xaxisMin !== null)
				{
					$config['axes']['xaxis'] += array(
						'min' => $xaxisMin
					);
				}
	
				if($xaxisMax !== null)
				{
					$config['axes']['xaxis'] += array(
						'max' => $xaxisMax
					);
				}

				if($xaxisPad !== null)
				{
					$config['axes']['xaxis'] += array(
						'pad' => $xaxisPad
					);
				}
				
				if($xaxisStringFormatter !== null)
				{
					$config['axes']['xaxis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['xaxis']['tickOptions']['formatString'] = $xaxisStringFormatter;
				}
				
				if($xaxisTickAngle !== null)
				{
					$config['axes']['xaxis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['xaxis']['tickOptions']['angle'] = $xaxisTickAngle;
				}
				
				if($xaxisRenderer !== null)
				{
					switch($xaxisRenderer)
					{
						case true: $config['axes']['xaxis']['renderer'] = 'CategoryAxisRenderer'; break;
						case false: unset($config['axes']['xaxis']['renderer']); break;
					}
				}

				if($xaxisNoGrid)
				{
					$config['axes']['xaxis']['drawMajorGridlines'] = false;
				}

				if($xaxisNoTickMark)
				{
					$config['axes']['xaxis']['drawMajorTickMarks'] = false;
				}

				if($xaxisTickInterval !== null)
				{
					//$config['axes']['xaxis']['tickInterval'] = $xaxisTickInterval;
				}

				if($xaxisAlignTicks)
				{
					$config['axes']['xaxis']['rendererOptions']['alignTicks'] = true;
				}
				
				if($xaxisForceTickAt0)
				{
					$config['axes']['xaxis']['rendererOptions']['forceTickAt0'] = true;				
				}
				
				if($xaxisRenderingTicks === true || ($axisNoRenderingTicks && $xaxisRenderingTicks === null) )
				{
					$config['axes']['xaxis']['tickRenderer'] = 'CanvasAxisTickRenderer';

					if(isset($config['axes']['xaxis']['tickOptions']))
					{
						$config['axes']['xaxis']['tickOptions'] += $ticksOptions;
					}
					else
					{
						$config['axes']['xaxis']['tickOptions'] = $ticksOptions;
					}
				}
				elseif($xaxisRenderingTicks === false && isset($config['axes']['xaxis']['tickRenderer']))
				{
					unset($config['axes']['xaxis']['tickRenderer']);
				}
	
			//yaxis
			if(($yaxisLabel !== null || $yaxisMin !== null || $yaxisMax !== null)
				&&
				!isset($config['axes']['yaxis'])
			)
			{
				$config['axes']['yaxis'] = array();
			}		
			
				if($yaxisLabel !== null)
				{
					$yaxisLabelConfig = array(
						'label' => $yaxisLabel,
						'labelRenderer' => 'CanvasAxisLabelRenderer',
						//'renderer' => 'CategoryAxisRenderer'
					);
					
					$config['axes']['yaxis'] += $yaxisLabelConfig;
				}
	
				if($yaxisMin !== null)
				{
					$config['axes']['yaxis'] += array(
						'min' => $yaxisMin
					);
				}
	
				if($yaxisMax !== null)
				{
					$config['axes']['yaxis'] += array(
						'max' => $yaxisMax
					);
				}

				if($yaxisPad !== null)
				{
					$config['axes']['yaxis'] += array(
						'pad' => $yaxisPad
					);
				}

				if($yaxisStringFormatter !== null)
				{
					$config['axes']['yaxis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['yaxis']['tickOptions']['formatString'] = $yaxisStringFormatter;
				}
	
				if($yaxisTickAngle !== null)
				{
					$config['axes']['yaxis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['yaxis']['tickOptions']['angle'] = $yaxisTickAngle;
				}						
	
				if($yaxisRenderer !== null)
				{
					switch($yaxisRenderer)
					{
						case true: $config['axes']['yaxis']['renderer'] = 'CategoryAxisRenderer'; break;
						case false: unset($config['axes']['yaxis']['renderer']); break;
					}
				}

				if($yaxisNoGrid)
				{
					$config['axes']['yaxis']['drawMajorGridlines'] = false;
				}

				if($yaxisNoTickMark)
				{
					$config['axes']['yaxis']['drawMajorTickMarks'] = false;
				}

				if($yaxisTickInterval !== null)
				{
					//$config['axes']['yaxis']['tickInterval'] = $yaxisTickInterval;
				}

				if($yaxisAlignTicks)
				{
					$config['axes']['yaxis']['rendererOptions']['alignTicks'] = true;
				}
				
				if($yaxisForceTickAt0)
				{
					$config['axes']['yaxis']['rendererOptions']['forceTickAt0'] = true;				
				}

				if($yaxisRenderingTicks === true || ($axisNoRenderingTicks && $yaxisRenderingTicks === null) )
				{
					$config['axes']['yaxis']['tickRenderer'] = 'CanvasAxisTickRenderer';

					if(isset($config['axes']['yaxis']['tickOptions']))
					{
						$config['axes']['yaxis']['tickOptions'] += $ticksOptions;
					}
					else
					{
						$config['axes']['yaxis']['tickOptions'] = $ticksOptions;
					}					
				}
				elseif($xaxisRenderingTicks === false && isset($config['axes']['yaxis']['tickRenderer']))
				{
					unset($config['axes']['yaxis']['tickRenderer']);
				}							
	
			//x2axis
			if(($x2axisLabel !== null || $x2axisMin !== null || $x2axisMax !== null)
				&&
				!isset($config['axes']['x2axis'])
			)
			{
				$config['axes']['x2axis'] = array();
			}
			
				if($x2axisLabel !== null)
				{
					$x2axisLabelConfig = array(
						'label' => $x2axisLabel,
						'labelRenderer' => 'CanvasAxisLabelRenderer',
						//'renderer' => 'CategoryAxisRenderer'
					);
					
					$config['axes']['x2axis'] += $x2axisLabelConfig;
				}
				
				if($x2axisMin !== null)
				{
					$config['axes']['x2axis'] += array(
						'min' => $x2axisMin
					);
				}
	
				if($x2axisMax !== null)
				{
					$config['axes']['x2axis'] += array(
						'max' => $x2axisMax
					);
				}				

				if($x2axisPad !== null)
				{
					$config['axes']['x2axis'] += array(
						'pad' => $x2axisPad
					);
				}
	
				if($x2axisStringFormatter !== null)
				{
					$config['axes']['x2axis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['x2axis']['tickOptions']['formatString'] = $x2axisStringFormatter;
				}
	
				if($x2axisTickAngle !== null)
				{
					$config['axes']['x2axis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['x2axis']['tickOptions']['angle'] = $x2axisTickAngle;
				}
	
				if($x2axisRenderer !== null)
				{
					switch($x2axisRenderer)
					{
						case true: $config['axes']['x2axis']['renderer'] = 'CategoryAxisRenderer'; break;
						case false: unset($config['axes']['x2axis']['renderer']); break;
					}
				}

				if($x2axisNoGrid)
				{
					$config['axes']['x2axis']['drawMajorGridlines'] = false;
				}

				if($x2axisNoTickMark)
				{
					$config['axes']['x2axis']['drawMajorTickMarks'] = false;
				}

				if($x2axisTickInterval !== null)
				{
					//$config['axes']['x2axis']['tickInterval'] = $x2axisTickInterval;
				}

				if($x2axisAlignTicks)
				{
					$config['axes']['x2axis']['rendererOptions']['alignTicks'] = true;
				}
				
				if($x2axisForceTickAt0)
				{
					$config['axes']['x2axis']['rendererOptions']['forceTickAt0'] = true;				
				}

				if($x2axisRenderingTicks === true || ($axisNoRenderingTicks && $x2axisRenderingTicks === null) )
				{
					$config['axes']['x2axis']['tickRenderer'] = 'CanvasAxisTickRenderer';

					if(isset($config['axes']['x2axis']['tickOptions']))
					{
						$config['axes']['x2axis']['tickOptions'] += $ticksOptions;
					}
					else
					{
						$config['axes']['x2axis']['tickOptions'] = $ticksOptions;
					}					
				}
				elseif($xaxisRenderingTicks === false && isset($config['axes']['x2axis']['tickRenderer']))
				{
					unset($config['axes']['x2axis']['tickRenderer']);
				}				
	
			//Y2axis
			if(($y2axisLabel !== null || $y2axisMin !== null || $y2axisMax !== null)
				&&
				!isset($config['axes']['y2axis'])
			)
			{
				$config['axes']['y2axis'] = array();
			}
					
				if($y2axisLabel !== null)
				{
					$y2axisLabelConfig = array(
						'label' => $y2axisLabel,
						'labelRenderer' => 'CanvasAxisLabelRenderer',
						//'renderer' => 'CategoryAxisRenderer'
					);
					
					$config['axes']['y2axis'] += $y2axisLabelConfig;		
				}
	
				if($y2axisMin !== null)
				{
					$config['axes']['y2axis'] += array(
						'min' => $y2axisMin
					);
				}
	
				if($y2axisMax !== null)
				{
					$config['axes']['y2axis'] += array(
						'max' => $y2axisMax
					);
				}

				if($y2axisPad !== null)
				{
					$config['axes']['y2axis'] += array(
						'pad' => $y2axisPad
					);
				}

				if($y2axisStringFormatter !== null)
				{
					$config['axes']['y2axis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['y2axis']['tickOptions']['formatString'] = $y2axisStringFormatter;
				}
	
				if($y2axisTickAngle !== null)
				{
					$config['axes']['y2axis']['tickRenderer'] = 'CanvasAxisTickRenderer';
					$config['axes']['y2axis']['tickOptions']['angle'] = $y2axisTickAngle;
				}
	
				if($y2axisRenderer !== null)
				{
					switch($y2axisRenderer)
					{
						case true: $config['axes']['y2axis']['renderer'] = 'CategoryAxisRenderer'; break;
						case false: unset($config['axes']['y2axis']['renderer']); break;
					}
				}

				if($y2axisAlignTicks)
				{
					$config['axes']['y2axis']['rendererOptions']['alignTicks'] = true;
				}
				
				if($y2axisForceTickAt0)
				{
					$config['axes']['y2axis']['rendererOptions']['forceTickAt0'] = true;				
				}

				if($y2axisNoGrid)
				{
					$config['axes']['y2axis']['drawMajorGridlines'] = false;
				}

				if($y2axisNoTickMark)
				{
					$config['axes']['y2axis']['drawMajorTickMarks'] = false;
				}

				if($y2axisTickInterval !== null)
				{
					//$config['axes']['y2axis']['tickInterval'] = $y2axisTickInterval;
				}

				if($y2axisRenderingTicks === true || ($axisNoRenderingTicks && $y2axisRenderingTicks === null) )
				{
					$config['axes']['y2axis']['tickRenderer'] = 'CanvasAxisTickRenderer';

					if(isset($config['axes']['y2axis']['tickOptions']))
					{
						$config['axes']['y2axis']['tickOptions'] += $ticksOptions;
					}
					else
					{
						$config['axes']['y2axis']['tickOptions'] = $ticksOptions;
					}					
				}
				elseif($xaxisRenderingTicks === false && isset($config['axes']['x2axis']['tickRenderer']))
				{
					unset($config['axes']['y2axis']['tickRenderer']);
				}
	
			//Axis auto invert config with horizontal layout
			if($xaxisRenderer === null && $yaxisRenderer === null && $barDirection == 'horizontal')
			{
				unset($config['axes']['xaxis']['renderer']);
				$config['axes']['yaxis']['renderer'] = 'CategoryAxisRenderer';
			}

		/*Axe ticks manager*/
		if(!empty($manualTicks))
		{
			if(!isset($config['axes'][$manualTicksTarget]))
			{
				$config['axes'][$manualTicksTarget] = array();
			}
	
			$config['axes'][$manualTicksTarget] += array(
				'ticks'  => $manualTicks,
				'tickRenderer' => 'CanvasAxisTickRenderer',
				'renderer' => 'CategoryAxisRenderer'
			);
		}

		/* Grid Management - config passed as reference */
		Sedo_Stats_Helper_BbCodes::gridLineManager($config, $globalGrid, $customSeriesGrid, $dualAxeMode);

		$options['data'] = $data;
		$options['config'] = $config;
		$options['uniqid'] = uniqid('bar_');
		$options['inlineCss'] = implode('; ', $inlineCss);
		$options['blockClass'] = $blockClass;
		$options['floatClass'] = $floatClass;
		$options['zoom'] = $zoom;
		$options['highlighter'] = (isset($config['highlighter']) && !empty($config['highlighter']['show']) );
		$options['pointLabels'] = $loadPointLabelsJs;

		/* Responsive Management */
		$useResponsiveMode = BBM_Helper_BbCodes::useResponsiveMode();
		$options['responsiveMode'] = $useResponsiveMode;
		
		if($useResponsiveMode)
		{
			$options['inlineCss'] = '';
			$options['blockAlign'] = 'bcenter';
		}

		//Zend_Debug::dump($config);
		//Zend_Debug::dump($data);
	}

	public static function manageSeriesOptions(array $options, $customDataMode = false, 
		array $seriesDefaults = array(), $dataId = 1, $loopLimit = 50
	)
	{
		$xenOptions = XenForo_Application::get('options');
		
		//Permanent renderer options (default)
		$dataLabelsShow = true;
		$dataLabelsMinValueToShow = 3;
		$dataLabelsPosFactor = '0.52';
		
		$tickAngle = $xenOptions->sedo_stats_default_tick_angle;
		$dataLabels = array();
		$dataGrid = array('x' => true, 'y' => true);
		$dataPointLabels = false;
		$customPointLabels = false;
		$loadPointLabelsJs = false;
		$noRenderer = false;

		//Custom options (default)
		$animate = false;
		$animateOpt = false;
		$animateSpeed = false;

		$barPadding = null;
		$barPaddingOpt = false;		

		$barMargin = null;
		$barMarginOpt = false;
		
		$barWidth = null;
		$barWidthOpt = false;

		$barDirection = null;
		$barDirectionOpt = false;	

		$shadowAngle = null;
		$shadowAngleOpt = false;

		$shadowDepth = null;
		$shadowDepthOpt = false;

		$multiColor = false;
		$multiColorOpt = false;

		$stackValues = false;
		$stackValuesOpt = false;

		$fillToZero = false;
		$fillToZeroOpt = false;

		$modAxis = array();

		$customRendererOptions = array();

		$labelMaxLength = 20;

      		foreach($options as $n => $option)
      		{
      			if($n > $loopLimit) { break; }
      			
      			$cleanOption = BBM_Helper_BbCodes::cleanOption($option);
      			$hasLabel = null;

      			if(strpos($cleanOption, 'label:') === 0)
      			{
      				$labelOption = substr(str_replace(' ', '', $cleanOption), 6);
      
      				if($labelOption == 'no')
      				{
					$hasLabel = false;
					$customRendererOptions['showDataLabels'] = $dataLabelsShow = false;
      				}
      				else
      				{
					$labelOption = 	substr($cleanOption, 6);
					
					if(self::$mbStringSupport && mb_strlen($labelOption, 'UTF-8') > $labelMaxLength)
					{
						$labelOption = mb_substr($labelOption, 0, $labelMaxLength, 'UTF-8') . '...';
					}
					elseif(!self::$mbStringSupport && strlen($labelOption) > $labelMaxLength)
					{
						$labelOption = substr($labelOption, 0, $labelMaxLength) . '...';					
					}

      					$label = $labelOption;
					$hasLabel = true;
      				}
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
			elseif(strpos($cleanOption, 'tick-angle:') === 0)
      			{
      				$tickAngleVal = intval(substr(str_replace(' ', '', $cleanOption), 11));

      				if($tickAngleVal >= -180 && $tickAngleVal <= 180)
      				{
      					$tickAngle = $tickAngleVal;
      				}
      			}
      			elseif(strpos($cleanOption, 'no-grid:') === 0)
      			{
      				$noGridValue = substr(str_replace(' ', '', $cleanOption), 8);
      				
      				switch($noGridValue)
      				{
      					case 'x': 
	      					$dataGrid = array('x' => false, 'y' => true);
      						break;
      					case 'y':
	      					$dataGrid = array('x' => true, 'y' => false);
      						break;
					case 'xy': case 'yx':
	      					$dataGrid = array('x' => false, 'y' => false);					
						break;
      				}
      			}
      			elseif($cleanOption == 'no-grid')
      			{
				$dataGrid = array('x' => false, 'y' => false);      			
      			}
      			elseif($cleanOption == 'point-labels')
      			{
				$dataPointLabels = '@noLocation';
				$loadPointLabelsJs = true;
      				
      				$customPointLabels = array(
					'show' => true
				);      			
      			}
      			elseif(strpos($cleanOption, 'point-labels:') === 0)
      			{
				$dataPointLabels = 'e';
				$loadPointLabelsJs = true;
      				
      				$pointLabelsVal = substr(str_replace(' ', '', $cleanOption), 13);
      				if(in_array($pointLabelsVal, array('nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w')))
      				{
      					$dataPointLabels = $pointLabelsVal;
      				}

      				$customPointLabels = array(
					'show' => true,
					'location' => $dataPointLabels
				);
      			}      			
      			elseif(strpos($cleanOption, 'mod-axis:') === 0)
      			{
      				$modAxisVal = explode(',', substr(str_replace(' ', '', $cleanOption), 9));
      				
				$modAxisTasks = array();
				
				foreach($modAxisVal as $axisMod)
				{
					$axisModConfig = explode('-', $axisMod);
					
					if(count($axisModConfig) == 2)
					{
						$modAxisError = null;
						
						foreach($axisModConfig as &$v)
						{
							switch($v)
							{
								case 'x': $v = 'xaxis'; break;
								case 'y': $v = 'yaxis'; break;
								case 'x2': $v = 'x2axis'; break;
								case 'y2': $v = 'y2axis'; break;
								default: $modAxisError = true;
							}
						}
						
						if($modAxisError === null)
						{
							$modAxis[$axisModConfig[0]] = $axisModConfig[1];
						}
					}
				}
      			}
      			elseif($cleanOption == 'no-renderer')
      			{
      				$noRenderer = true;
      			}
      			elseif(!$barPaddingOpt && strpos($cleanOption, 'bar-padding:') === 0)
      			{
	      			$barPaddingVal = intval(substr(str_replace(' ', '', $cleanOption), 12));
	      			$barPaddingOpt = true;
	      			
	      			if($barPaddingVal >= -90 && $barPaddingVal < 90)
	      			{
	      				$barPadding = $barPaddingVal;
	      			}
	      		}
      			elseif(!$barMarginOpt && strpos($cleanOption, 'bar-margin:') === 0)
      			{
	      			$barMarginVal = intval(substr(str_replace(' ', '', $cleanOption), 11));
	      			$barMarginOpt = true;
	      			
	      			if($barMarginVal >= 0 && $barMarginVal < 90)
	      			{
	      				$barMargin = $barMarginVal;
	      			}
	      		}
      			elseif(!$barWidthOpt && strpos($cleanOption, 'bar-width:') === 0)
      			{
	      			$barWidthVal = intval(substr(str_replace(' ', '', $cleanOption), 11));
	      			$barWidthOpt = true;
	      			
	      			if($barWidthVal >= -500 && $barWidthVal < 500)
	      			{
	      				$barWidth = $barWidthVal;
	      			}
	      		}	      		
      			elseif(!$shadowAngleOpt && strpos($cleanOption, 'shadow-angle:') === 0)
      			{
	      			$shadowAngleVal = intval(substr(str_replace(' ', '', $cleanOption), 13));
	      			$shadowAngleOpt = true;
	      			
	      			if($shadowAngleVal >= -180 && $shadowAngleVal <= 180)
	      			{
	      				$shadowAngle = $shadowAngleVal;
	      			}
	      		}	      		
      			elseif(!$shadowDepthOpt && strpos($cleanOption, 'shadow-depth:') === 0)
      			{
	      			$shadowDepthVal = intval(substr(str_replace(' ', '', $cleanOption), 13));
	      			$shadowDepthOpt = true;
	      			
	      			if($shadowDepthVal >= 0 && $shadowDepthVal <= 15)
	      			{
	      				$shadowDepth = $shadowDepthVal;
	      			}
	      		}
	      		elseif(!$barDirectionOpt && ($cleanOption == 'bar-horizontal' || $cleanOption == 'bar-h'))
	      		{
				$barDirection = 'horizontal';
	      			$barDirectionOpt = true;
	      		}
	      		elseif(!$barDirectionOpt && ($cleanOption == 'bar-vertical' || $cleanOption == 'bar-v'))
	      		{
				$barDirection = 'vertical';
	      			$barDirectionOpt = true;
	      		}
      			elseif(!$animateOpt && $cleanOption == 'animate')
      			{
      				$animate = true;
      				$animateOpt = true;
      			}
      			elseif(!$animateOpt && strpos($cleanOption, 'animate:') === 0)
      			{
      				$animateSpeedVal = intval(substr(str_replace(' ', '', $cleanOption), 8));
      				$animate = true;
      				$animateOpt = true;
      				
      				if($animateSpeedVal > 0 && $animateSpeedVal < 20000)
      				{
	      				$animateSpeed = $animateSpeedVal;
	      			}
      			}
			elseif(!$multiColorOpt && $cleanOption == 'multi-color')
			{
				$multiColor = true;
			}
			elseif(	!$stackValuesOpt && $cleanOption == 'stack-values')
			{
				$stackValues = true;
				$stackValuesOpt = true;
			}
			elseif(!$fillToZeroOpt && $cleanOption == 'axis-zero')
			{
				$fillToZero = true;
				$fillToZeroOpt = true;
			}
      			elseif(!empty($cleanOption) && (strpos($cleanOption, ':') === false || preg_match('#^&quot;(.+?)&quot;$#u', $cleanOption, $quoteEscape)) )
      			{
	      			if($hasLabel !== false)
	      			{
		      			$hasLabel = true;
		      			$label = (!isset($quoteEscape[1])) ? $option : $quoteMode[1];
		      		}
      			}
      		}

		$rootDefaults = array();
		$genericDefaults = array(
			'rendererOptions' => array()	
		);
		
      		if($multiColor)
      		{
      			$genericDefaults['rendererOptions']['varyBarColor'] = true;
      		}
      
      		if($animateSpeed)
      		{
      			$genericDefaults['rendererOptions']['animation'] = array(
      				'speed' => $animateSpeed
      			);
      		}
      
      		if($barDirection)
      		{
      			$genericDefaults['rendererOptions'] += array(
      				'barDirection' => $barDirection
      			);
      			
      			if(!$shadowAngle)
      			{
      				$shadowAngle = 135;
      			}
      		}
      		
      		if($barMargin)
      		{
      			$genericDefaults['rendererOptions'] += array(
      				'barMargin' => $barMargin
      			);
      		}

		if($barWidth)
		{
      			$genericDefaults['rendererOptions'] += array(
      				'barWidth' => $barWidth
      			);		
		}
      		
      		if($barPadding)
      		{
      			$genericDefaults['rendererOptions'] += array(
      				'barPadding' => $barPadding
      			);		
      		}
      
      		if($shadowAngle)
      		{
      			$genericDefaults['rendererOptions'] += array(
      				'shadowAngle' => $shadowAngle
      			);		
      		}
      
      		if($shadowDepth)
      		{
      			$genericDefaults['rendererOptions'] += array(
      				'shadowDepth' => $shadowDepth
      			);		
      		}
      
      		if($dataPointLabels)
      		{
      			$rootDefaults['pointLabels']= array(
      				'show' => true
      			);
      			
      			$genericDefaults['pointLabels'] = array(
      				'show' => true
      			);
      				
      			if($dataPointLabels != '@noLocation')
      			{
      				$genericDefaults['pointLabels']['location'] = $dataPointLabels;
      			}
      			
      			if($stackValues)
      			{
      				$genericDefaults['pointLabels']['stackedValue'] = true;
      			}
      		}

		/* Default Series Management */
      		if(!$customDataMode)
      		{
	      		$seriesDefaults = array(
	      			'renderer' => ($noRenderer) ? 'none' : 'BarRenderer',
	      			'rendererOptions' => array(
	      				'fillToZero' => $fillToZero,
	      				'showDataLabels' => $dataLabelsShow,
	      				'dataLabelPositionFactor' => $dataLabelsPosFactor,
	      				'dataLabelThreshold' => $dataLabelsMinValueToShow,
	      				'highlightMouseOver' => false,
	      				'highlightMouseDown' => true
	      			)
	      		); 
	      		
	      		$seriesDefaults = array_merge_recursive($seriesDefaults, $genericDefaults);

	      		return array(
				$tickAngle,
				$dataGrid,
				$loadPointLabelsJs,
				$modAxis,
				$animate,
				$barDirection,
				$rootDefaults,
				$seriesDefaults
	      		);
	      	}

		/* Custom Serie Management */
		$defaultRendererOptions = (isset($seriesDefaults['rendererOptions'])) ? $seriesDefaults['rendererOptions'] : array();

		$customSerieData = array(
			'label' => (!$hasLabel) ? new XenForo_Phrase('sedo_stats_series') . " $dataId" : $label,
			'rendererOptions' => $customRendererOptions+$defaultRendererOptions
		);
		
		if(empty($genericDefaults['rendererOptions']))
		{
			unset($genericDefaults['rendererOptions']);
		}

		$customSerieData = array_replace_recursive($customSerieData, $genericDefaults);
		
		if($customPointLabels)
		{
			$customSerieData['pointLabels'] = $customPointLabels;
		}

		if(!empty($modAxis))
		{
			foreach($modAxis as $from => $to)
			{
				$customSerieData[$from] = $to;
			}
		}
		
		if($noRenderer)
		{
			$customSerieData['renderer'] = false;
		}
		
		return array($dataGrid, $customSerieData, $animate, $loadPointLabelsJs, $noRenderer);
	}
}
//Zend_Debug::dump($abc);