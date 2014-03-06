<?php
class Sedo_Stats_Option_Factory
{
      	public static function render_sprintf(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
      	{
      		$optionValue = array();

		if(is_array($preparedOption['option_value']))
		{
	     		$i = 1;
      			foreach($preparedOption['option_value'] as $sprintfKey => $sprintfCode)
      			{
      				$optionValue[$i] = array(
      					'key' => $sprintfKey,
      					'code' => $sprintfCode
      				);
      				
	      			$i++;
      			}
      		}

      		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
      			'preparedOption' => $preparedOption,
      			'canEditOptionDefinition' => $canEdit
      		));

      		return $view->createTemplateObject('option_sedo_stats_sprintf', array(
      			'fieldPrefix' => $fieldPrefix,
      			'listedFieldName' => $fieldPrefix . '_listed[]',
      			'preparedOption' => $preparedOption,
      			'formatParams' => $preparedOption['formatParams'],
      			'editLink' => $editLink,
      			'configs' => $optionValue,
      			'nextCounter' => count($preparedOption['option_value']) + 1
      		));
      	}
      	
      	public static function verify_sprintf(array &$configs, XenForo_DataWriter $dw, $fieldName)
      	{
		$data = array();
		
		foreach($configs as $key => $config)
		{
			if( empty($config['key']) || empty($config['code']))
			{
				unset($configs[$key]);
				continue;
			}
			
			if(!preg_match('#^[a-z]+$#i', $config['key']))
			{
				unset($configs[$key]);
				continue;
			}
			
			
			$sprintfKey = strtolower($config['key']);
			$data[$sprintfKey] = $config['code'];
		}

		$configs = $data;

		//Zend_Debug::dump($configs);break;
		return true;
      	}				
}
//Zend_Debug::dump($abc);