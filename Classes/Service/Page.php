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
 * Page Service
 *
 * Service for interacting with Pages - gets content elements and page configuration
 * options.
 *
 * @package Fed
 * @subpackage Service
 */
class Tx_Fed_Service_Page implements t3lib_Singleton {

	/**
	 * @var Tx_Extbase_Object_ObjectManager
	 */
	protected $objectManager;

	/**
	 * @var Tx_Fed_Domain_Repository_ContentElementRepository
	 */
	protected $contentElementRepository;

	/**
	 * @var Tx_Extbase_Configuration_ConfigurationManagerInterface
	 */
	protected $configurationManager;

	/**
	 * @param Tx_Extbase_Object_ObjectManager $objectManager
	 */
	public function injectObjectManager(Tx_Extbase_Object_ObjectManager $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * @param Tx_Fed_Domain_Repository_ContentElementRepository $contentElementRepository
	 */
	public function injectContentElementRepository(Tx_Fed_Domain_Repository_ContentElementRepository $contentElementRepository) {
		$this->contentElementRepository = $contentElementRepository;
	}

	/**
	 * @param Tx_Extbase_Configuration_ConfigurationManagerInterface $configurationManager
	 */
	public function injectConfigurationManager(Tx_Extbase_Configuration_ConfigurationManagerInterface $configurationManager) {
		$this->configurationManager = $configurationManager;
	}

	/**
	 * Fetches ContentElement objects from $page where column position matches $columnPosition
	 *
	 * @param Tx_Fed_Domain_Model_Page $page
	 * @param integer $columnPosition
	 * @api
	 */
	public function getContentElementsByColumnPosition(Tx_Fed_Domain_Model_Page $page, $columnPosition) {
		$pid = $page->getUid();
		return $this->contentElementRepository->findAllByPidAndColPos($pid, $columnPosition);
	}

	/**
	 * Fetches ContentElement objects from $page where column name matches $coumnName
	 *
	 * @param Tx_Fed_Domain_Model_Page $page
	 * @param string $columnName
	 * @return Tx_Extbase_Persistence_QueryResultInterface
	 * @api
	 */
	public function getContentElementsByColumnName(Tx_Fed_Domain_Model_Page $page, $columnName) {
		$columns = $this->getColumnConfiguration($page);
		$pid = $page->getUid();
		foreach ($columns as $columnPosition => $column) {
			if ($column['name'] == $columnName) {
				return $this->contentElementRepository->findAllByPidAndColPos($pid, $columnPosition);
			}
		}
		return NULL;
	}

	/**
	 * Gets an array of the column definition in a BackendLayout object
	 *
	 * @param Tx_Fed_Domain_Model_Page $page
	 * @return array
	 * @api
	 */
	public function getColumnConfiguration(Tx_Fed_Domain_Model_Page $page) {
		$config = $page->getBackendLayout()->getConfig();
		$parser = $this->objectManager->get('t3lib_tsparser');
		$parser->parse($config);
		$array = $parser->setup;
		$columns = array();
		foreach ($array['rows'] as $row) {
			foreach ($row['columns'] as $column) {
				$columns[$column['colPos']] = $column['name'];
			}
		}
		return $columns;
	}

	/**
	 * Process RootLine to find first usable, configured Fluid Page Template.
	 * WARNING: do NOT use the output of this feature to overwrite $row - the
	 * record returned may or may not be the same recod as defined in $id.
	 *
	 * @param integer $pageUid
	 * @return array
	 * @api
	 */
	public function getPageTemplateConfiguration($pageUid) {
		if ($pageUid < 1) {
			return NULL;
		}
		$pageSelect = new t3lib_pageSelect();
		$page = $pageSelect->getPage($pageUid);
		if (strpos($page['tx_fed_page_controller_action'], '->')) {
			return $page;
		}
		do {
			$page = $this->getWorkspaceParentPage($page);
			$workspacePage = NULL;
			$workspacePage = $this->getWorkspacePage($page);
			if ($workspacePage) {
				$page = $workspacePage;
			}
		} while ($page && !strpos($page['tx_fed_page_controller_action_sub'], '->'));
		$page['tx_fed_page_controller_action'] = $page['tx_fed_page_controller_action_sub'];
		return $page;
	}

	/**
	 * Gets the workspace parent for a given page
	 *
	 * @param array $page
	 * @return array
	 */
	protected function getWorkspaceParentPage($page) {
		$page = $this->getPositionPlaceholder($page);
		$page = t3lib_BEfunc::getRecord('pages', $page['pid']);
		$page = $this->getPositionPlaceholder($page);
		return $page;
	}

	/**
	 * Gets the workspace version of a given page
	 *
	 * @param array $page
	 * @return array
	 */
	protected function getWorkspacePage($page) {
		if ($page) {
			$wsid = $GLOBALS['BE_USER']->workspace ?: 0;
			if ($wsid != 0 && $page['t3ver_wsid'] != $wsid) {
				$workspacePage = t3lib_BEfunc::getRecordRaw('pages', $where = sprintf('t3ver_oid=%d AND t3ver_wsid=%d', $page['uid'], $wsid), $fields = '*');
				if ($workspacePage !== NULL) {
					$page = $workspacePage;
				}
			}
		}
		return $page;
	}

	/**
	 * Gets a placeholder for a given page
	 *
	 * @param array $page
	 * @return array
	 */
	protected function getPositionPlaceholder($page) {
		if ($page['pid'] != -1) {
			// original, dont do anything
		} elseif ($page['t3ver_state'] == 0) {
			// page has changed, but not moved
			$page = t3lib_BEfunc::getRecord('pages', $page['t3ver_oid']);
		} elseif ($page['t3ver_state'] == 4) {
			// page has moved. get placeholder for new position
			$page = t3lib_BEfunc::getRecordRaw('pages', $where = sprintf('t3ver_move_id=%d AND t3ver_state=3', $page['t3ver_oid']), $fields = '*');
			//$page = t3lib_BEfunc::getRecord('pages', $page['t3ver_move_id']);
		}
		return $page;
	}

	/**
	 * Gets the fallback Fluid Page Template defined in TypoScript
	 *
	 * @param boolean $translatePath If FALSE, does not translate the TypoScript path
	 * @return string
	 */
	public function getFallbackPageTemplatePathAndFilename($translatePath = TRUE) {
		$settings = $this->configurationManager->getConfiguration(Tx_Extbase_Configuration_ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, 'fed', 'API');
		$fallbackTemplatePathAndFilename = $settings['defaults.']['templates.']['fallbackFluidPageTemplate'];
		if ($translatePath === TRUE) {
			$fallbackTemplatePathAndFilename = t3lib_div::getFileAbsFileName($fallbackTemplatePathAndFilename);
		}
		if (file_exists($fallbackTemplatePathAndFilename) || ($translatePath === FALSE)) {
			return $fallbackTemplatePathAndFilename;
		} else {
			return t3lib_extMgm::extPath('fed', 'Resources/Private/Templates/Page/Render.html');
		}
	}

	/**
	 * Get a usable page configuration flexform from rootline
	 *
	 * @param integer $pageUid
	 * @return string
	 * @api
	 */
	public function getPageFlexFormSource($pageUid) {
		if ($pageUid < 1) {
			return NULL;
		}
		$pageSelect = new t3lib_pageSelect();
		$page = $pageSelect->getPage($pageUid);
		$page = $this->getWorkspacePage($page);
		while ($page['uid'] != 0 && empty($page['tx_fed_page_flexform'])) {
			$page = $this->getWorkspaceParentPage($page);
			$workspacePage = NULL;
			$workspacePage = $this->getWorkspacePage($page);
			if ($workspacePage) {
				$page = $workspacePage;
			}
		};
		if (empty($page['tx_fed_page_flexform'])) {
			return NULL;
		}
		return $page['tx_fed_page_flexform'];
	}

}

