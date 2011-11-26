<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Claus Due <claus@wildside.dk>, Wildside A/S
*
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
 * Configuration Manager subclass. Contains additional configuration fetching
 * methods used in FED's features.
 *
 * @version $Id$
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 * @package Fed
 * @subpackage Configuration
 */
class Tx_Fed_Configuration_ConfigurationManager extends Tx_Extbase_Configuration_ConfigurationManager implements Tx_Extbase_Configuration_ConfigurationManagerInterface {

	/**
	 * Get definitions of paths for FCEs defined in TypoScript
	 *
	 * @param string $extensionName Optional extension name to get only that extension
	 * @return array
	 */
	public function getContentConfiguration($extensionName=NULL) {
		$typoscript = $this->getConfiguration(Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
		$paths = $typoscript['plugin.']['tx_fed.']['fce.'];
		if (is_array($paths) === FALSE) {
			return array();
		}
		$paths = Tx_Extbase_Utility_TypoScript::convertTypoScriptArrayToPlainArray($paths);
		if ($extensionName) {
			return $paths[$extensionName];
		} else {
			return $paths;
		}
	}

	/**
	 * Get definitions of paths for Page Templates defined in TypoScript
	 *
	 * @param string $extensionName
	 * @return array
	 */
	public function getPageConfiguration($extensionName=NULL) {
		$config = $this->getConfiguration(Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
		$config = $config['plugin.']['tx_fed.']['page.'];
		if (is_array($config) === FALSE) {
			return array();
		}
		$config = Tx_Extbase_Utility_TypoScript::convertTypoScriptArrayToPlainArray($config);
		if ($extensionName) {
			return $config[$extensionName];
		} else {
			return $config;
		}
	}

	/**
	 * Gets a human-readable label from a Fluid Page template file
	 *
	 * @param string $extensionName
	 * @param string $templateFile
	 * @return string
	 */
	public function getPageTemplateLabel($extensionName, $templateFile) {
		$config = $this->getPageConfiguration($extensionName);
		$templateRootPath = $config['templateRootPath'];
		$templatePathAndFilename = $templateRootPath . 'Page/' . $templateFile . '.html';
		$templatePathAndFilename = Tx_Fed_Utility_Path::translatePath($templatePathAndFilename);
		#var_dump($extensionName);
		#var_dump($config);
		#var_dump($templatePathAndFilename);
		$layoutRootPath = Tx_Fed_Utility_Path::translatePath($config['layoutRootPath']);
		$partialRootPath = Tx_Fed_Utility_Path::translatePath($config['partialRootPath']);
		$exposedView = $this->objectManager->get('Tx_Fed_MVC_View_ExposedTemplateView');
		$exposedView->setTemplatePathAndFilename($templatePathAndFilename);
		$exposedView->setLayoutRootPath($layoutRootPath);
		$exposedView->setPartialRootPath($partialRootPath);
		#$exposedView->render();
		#$exposedView->setPartialRootPath($config['partialRootPath']);
		#$exposedView->setLayoutRootPath($config['layoutRootPath']);
		#$exposedView->setTemplateRootPath($config['templateRootPath']);
		$page = $exposedView->getStoredVariable('Tx_Fed_ViewHelpers_FceViewHelper', 'storage', 'Configuration');
		return $page['label'] ? $page['label'] : $extensionName . ': ' . $templateFile;
		#var_dump($page);
		#exit();
	}

	/**
	 * Gets a list of usable Page Templates from defined page template TypoScript
	 *
	 * @param string $format
	 * @return array
	 */
	public function getAvailablePageTemplateFiles($format='html') {
		$typoScript = $this->getPageConfiguration();
		$output = array();
		if (is_array($typoScript) === FALSE) {
			return $output;
		}
		foreach ($typoScript as $extensionName=>$group) {
			$path = $group['templateRootPath'] . 'Page' . DIRECTORY_SEPARATOR;
			$path = Tx_Fed_Utility_Path::translatePath($path);
			$files = scandir($path);
			$output[$extensionName] = array();
			foreach ($files as $k=>$file) {
				$pathinfo = pathinfo($path . $file);
				$extension = $pathinfo['extension'];
				if (substr($file, 0, 1) === '.') {
					unset($files[$k]);
				} else if (strtolower($extension) != strtolower($format)) {
					unset($files[$k]);
				} else {
					$output[$extensionName][] = $pathinfo['filename'];
				}
			}
		}
		return $output;
	}

}

?>
