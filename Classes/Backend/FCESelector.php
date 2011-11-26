<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2011 Claus Due <claus@wildside.dk>, Wildside A/S
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Class that renders a selection field for Fluid FCE template selection
 *
 * @package	TYPO3
 * @subpackage	fed
 */
class Tx_Fed_Backend_FCESelector {

	/**
	 * @var Tx_Extbase_Object_ObjectManager
	 */
	protected $objectManager;

	/**
	 *
	 * @var Tx_Fed_Configuration_ConfigurationManager
	 */
	protected $configurationManager;

	/**
	 * @var Tx_Fed_Utility_FlexForm
	 */
	protected $flexform;

	/**
	 * CONSTRUCTOR
	 */
	public function __construct() {
		$this->objectManager = t3lib_div::makeInstance('Tx_Extbase_Object_ObjectManager');
		$this->configurationManager = $this->objectManager->get('Tx_Fed_Configuration_ConfigurationManager');
		$this->flexform = $this->objectManager->get('Tx_Fed_Utility_FlexForm');
	}

	/**
	 * Render a Flexible Content Element type selection field
	 *
	 * @param array $parameters
	 * @param mixed $pObj
	 * @return string
	 */
	public function renderField(&$parameters, &$pObj) {
		$allTemplatePaths = $this->configurationManager->getContentConfiguration();
		$name = $parameters['itemFormElName'];
		$value = $parameters['itemFormElValue'];
		$select = "<div><select name='{$name}'  class='formField select' onchange='if (confirm(TBE_EDITOR.labels.onChangeAlert) && TBE_EDITOR.checkSubmit(-1)){ TBE_EDITOR.submitForm() };'>" . LF;
		$select .= "<option value=''>(Select Fluid FCE type)</option>" . LF;
		foreach ($allTemplatePaths as $key=>$templatePathSet) {
			$templateRootPath = $templatePathSet['templateRootPath'];
			$templateRootPath = Tx_Fed_Utility_Path::translatePath($templateRootPath);
			$files = $this->getFiles($templateRootPath, TRUE);
			if (count($files) > 0) {
				$groupLabel = 'Group: ' . $key;
				$select .= "<optgroup label='{$groupLabel}'>" . LF;
				foreach ($files as $fileRelPath) {
					$templateFilename = $templateRootPath . DIRECTORY_SEPARATOR . $fileRelPath;
					$view = $this->objectManager->get('Tx_Fed_MVC_View_ExposedTemplateView');
					$view->setTemplatePathAndFilename($templateFilename);
					try {
						$config =  $view->getStoredVariable('Tx_Fed_ViewHelpers_FceViewHelper', 'storage', 'Configuration');
						$enabled = $config['enabled'];
						$label = $config['label'];
						if ($enabled !== FALSE) {
							$optionValue = $key . ':' . $fileRelPath;
							if (!$label) {
								$label = $optionValue;
							}
							$selected = ($optionValue == $value ? " selected='selected'" : "");
							$select .= "<option value='{$optionValue}'{$selected}>{$label}</option>" .LF;
						}
					} catch (Exception $e) {
						$select .= "<option value=''>INVALID: " . $fileRelPath . " (Exception # " . $e->getCode() . ")</option>" . LF;
					}
				}
				$select .= "</optgroup>" . LF;
			}
		}
		$select .= "</select></div>" . LF;
		return $select;

	}

	/**
	 * Get a list of files (recursively) located in and below $basePath
	 *
	 * @param string $basePath
	 * @param boolean $recursive
	 * @param string $appendBasePath
	 * @return array
	 */
	protected function getFiles($basePath, $recursive=FALSE, $appendBasePath=NULL) {
		$files = scandir($basePath . $appendBasePath);
		$addFiles = array();
		foreach ($files as $file) {
			if (substr($file, 0, 1) === '.') {
				continue;
			} else if (is_dir($basePath . $appendBasePath . $file) && $recursive) {
				foreach ($this->getFiles($basePath, $recursive, $appendBasePath . $file . DIRECTORY_SEPARATOR) as $addFile) {
					$addFiles[] = $appendBasePath . $addFile;
				}
			} else if (is_file($basePath . $appendBasePath . $file)) {
				$addFiles[] = $appendBasePath . $file;
			}
		}
		sort($addFiles);
		return (array) $addFiles;
	}

}

?>