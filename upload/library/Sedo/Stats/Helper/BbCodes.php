<?php

class Sedo_Stats_Helper_BbCodes
{
	protected static $_statColors = array();

	public static function filterString($string, $deleteSpace = false)
	{
		$string = trim($string);
		$string = strip_tags($string);
		
		if($deleteSpace === true)
		{
			$string = str_replace(' ', '', $string);
		}
		
		return $string;
	}

	public static function filterFloat($float)
	{
		$float = self::filterString($float);
		$float = floatval($float);
		
		return $float;
	}

	public static function getStatColors()
	{
		$colors = self::$_statColors;
		
		if(!empty($colors))
		{
			return $colors;
		}

		for ($i = 1; $i <= 12; $i++) {
			array_push(
				$colors, 
				BBM_Helper_BbCodes::getHexaColor(
					XenForo_Template_Helper_Core::styleProperty("bbm_stats_color_{$i}")
				)
			);
		}

		return $colors;
	}

	public static function getLegendPosition($code = null)
	{
		if($code == null)
		{
			$code = XenForo_Application::get('options')->get('sedo_stats_default_legend_pos');
		}

		$legendShow = true;
		$legendPos = 'e';
		$legendOutside = false;

		switch($code)
      		{
      			case 'no': 
      				$legendShow = false; break;
      			case 'nw': case 'n': case 'ne': case 'e':
      			case 'se': case 's': case 'sw': case 'w': 
      				$legendPos = $code; break;
      			case 'nw-outside': case 'n-outside': case 'ne-outside': case 'e-outside':
      			case 'se-outside': case 's-outside': case 'sw-outside': case 'w-outside': 
      				$legendPos = str_replace('-outside', '', $code); 
      				$legendOutside = true;
      				break;
      			case 'outside': $legendOutside = true; break;
      		}
      		
      		return array($legendShow, $legendPos, $legendOutside);
	}

	public static function gridLineManager(array &$config, array $globalGridSettings, array $customGridSettings = array(), $dualAxeMode = false)
	{
		$globalGridMode = false;
	
		if( empty($globalGridSettings['x']) && empty($globalGridSettings['y']) )
		{
			self::_disableGridLine($config, 'axesDefaults');
			$globalGridMode = 'full';
		}
		elseif( empty($globalGridSettings['x']) )
		{
			self::_disableGridLine($config['axes'], 'yaxis'); //not sure why the config is inverted
			
			if($dualAxeMode)
			{
				self::_disableGridLine($config['axes'], 'y2axis');
			}

			$globalGridMode = 'x';			
		}
		elseif( empty($globalGridSettings['y']) )
		{
			self::_disableGridLine($config['axes'], 'xaxis');  //not sure why the config is inverted

			if($dualAxeMode)
			{
				self::_disableGridLine($config['axes'], 'x2axis');
			}
			
			$globalGridMode = 'y';
		}

		//ignore below at the moment
		if($dualAxeMode && !$globalGridMode)
		{
			$nData = 1;
			foreach($customGridSettings as $axe)
			{
				if($nData == 1)
				{
					$nData = '';
				}
				
				if( empty($axe['x']) && empty($axe['y']) )
				{
					self::_disableGridLine($config['axes'], "x{$nData}axis");
					self::_disableGridLine($config['axes'], "y{$nData}axis");
				}
				elseif( empty($axe['x']) )
				{
					self::_disableGridLine($config['axes'], "y{$nData}axis");
					
				}
				elseif( empty($axe['y']) )
				{
					self::_disableGridLine($config['axes'], "x{$nData}axis");
				}
				
				$nData++;
			}
		}			
	}

	protected static function _disableGridLine(&$src, $key)
	{
		$disableGridLine = array(
			'tickOptions' => array(
				'showGridline' => false
			)
		);
		
		$src[$key]['tickOptions']['showGridline'] = false;
	}

	public static function getMinMaxWidthHeight()
	{
		return array(
			intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_bbcode_minwidth")),
			intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_bbcode_maxwidth")),
			intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_bbcode_minheight")),
			intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_bbcode_maxheight"))
		);
	}
	
	public static function getDefaultWidthHeight()
	{
		return array(
			intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_defaultwidth")),
			intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_defaultheight")),		
		);
	}
	
	public static function getGridConfig($drawGridLines = true)
	{
		return array(
			'drawGridLines' => $drawGridLines, // not very usefull when some formatters are enabled
			'gridLineColor' => BBM_Helper_BbCodes::getHexaColor(XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_gridline_color")),
			'background'  => BBM_Helper_BbCodes::getHexaColor(XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_bgcolor")),
			'borderColor' => BBM_Helper_BbCodes::getHexaColor(XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_bordercolor")),
			'borderWidth' => floatval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_borderwidth")),
			'shadow' => intval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_shadow")),
			'shadowWidth' => floatval(XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_shadow_width")),
			'shadowAlpha' => XenForo_Template_Helper_Core::styleProperty("bbm_stats_grid_shadow_alpha")
		);
	}
	
	public static function getConfigItem($config, $k1, $k2 = null)
	{
		if(!isset($config[$k1]))
		{
			return null;
		}

		if($k2 == null)
		{
			return $config[$k1];
		}
	
	}
}
//Zend_Debug::dump($abc);