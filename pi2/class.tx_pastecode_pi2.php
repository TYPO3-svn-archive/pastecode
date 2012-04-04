<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Sven Burkert <bedienung@sbtheke.de>
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
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

require_once(PATH_tslib . 'class.tslib_pibase.php');
require_once(t3lib_extMgm::extPath('pastecode', 'pi1/class.tx_pastecode_pi1.php'));

/**
 * Plugin 'Snippets' for the 'pastecode' extension.
 *
 * @author	Sven Burkert <bedienung@sbtheke.de>
 * @package	TYPO3
 * @subpackage	tx_pastecode
 */
class tx_pastecode_pi2 extends tslib_pibase {
	var $prefixId = 'tx_pastecode_pi2'; // Same as class name
	var $scriptRelPath = 'pi2/class.tx_pastecode_pi2.php'; // Path to this script relative to the extension dir.
	var $extKey = 'pastecode'; // The extension key.
	var $pi_USER_INT_obj = true;
	var $storagePid;

	/**
	 * The main method of the PlugIn
	 *
	 * @param	string		$content: The PlugIn content
	 * @param	array		$conf: The PlugIn configuration
	 * @return	The content that is displayed on the website
	 */
	function main($content, $conf) {
		$this->conf = $conf;
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();

		$this->pastecodePi1 = t3lib_div::makeInstance('tx_pastecode_pi1');
		#$this->pastecodePi1->init();
		$this->storagePid = (int)$this->cObj->data['pages'];

		$this->template = $this->cObj->fileResource($this->conf['general.']['templateFile']);

		// user has to be logged in to add new snippets
		if(!$GLOBALS['TSFE']->loginUser) {
			return $this->cObj->stdWrap($this->pi_getLL('error_login'), $this->conf['general.']['notLoggedIn_stdWarp.']);
		} else {
			return $this->pi_wrapInBaseClass($this->newCode());
		}
	}


	function newCode() {
		$totalSubpart = $this->cObj->getSubpart($this->template, '###NEWSNIPPET###');
		$previewSubpart = $this->cObj->getSubpart($totalSubpart, '###PREVIEW###');

		$poster = $_COOKIE['snippetposter_' . $this->prefixId];

		// language keys
		$marker = $this->pastecodePi1->prepareLLArray('EXT:pastecode/pi2/locallang.xml', $this);

		$marker['###OVERVIEW_URL###'] = $this->pi_getPageLink($this->conf['general.']['snippetPid']);
		$marker['###HIDDEN###'] = '';

		if($this->piVars['edit']) {
			$snippet = $this->pi_getRecord('tx_pastecode_code', (int)$this->piVars['edit']);
			if ($GLOBALS['TSFE']->fe_user->user['name'] != $snippet['poster']) {
				return 'Access denied';
			}
			$marker['###HIDDEN###'] .= '<input type="hidden" name="tx_pastecode_pi1[edit]" value="' . (int)$this->piVars['edit'] . '" />';
			if(!$this->piVars['title']) $this->piVars['title'] = $snippet['title'];
			if(!$this->piVars['description']) $this->piVars['description'] = $snippet['description'];
			if(!$this->piVars['snippet']) $this->piVars['snippet'] = $snippet['code'];
			if(!$this->piVars['links']) $this->piVars['links'] = $snippet['links'];
			if(!$this->piVars['tags']) $this->piVars['tags'] = $snippet['tags'];
			if(!$this->piVars['problem']) $this->piVars['problem'] = $snippet['problem'];
		}

		if($GLOBALS['TSFE']->fe_user->user['uid']) {
			$subpart['###USERINFO###'] = '';
			$marker['###HIDDEN###'] .= '<input type="hidden" name="tx_pastecode_pi2[poster]" value="' . htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name']) . '" />';
		}

		if($this->piVars['save']) {
			// validate
			$err = array();
			if(strlen($this->piVars['title']) < 4 || strlen($this->piVars['snippet']) < 30) {
				$err[] = $this->pi_getLL('error_length');
			}
			#captcha response
			if(t3lib_extMgm::isLoaded('captcha') && !$GLOBALS['TSFE']->fe_user->user['uid']) {
				session_start();
				if ($this->piVars['captchaResponse'] != $_SESSION['tx_captcha_string']) {
					$err[] = $this->pi_getLL('error_captcha');
				}
				$_SESSION['tx_captcha_string'] = '';
			}
			if(count($err) == 0) {
				// save snippet
				if($this->piVars['poster'] == '') {
					$this->piVars['poster'] = 'anonymous';
				} else {
					setcookie('snippetposter_' . $this->prefixId, $this->piVars['poster']);
				}
				$links = implode(',', t3lib_div::trimExplode(',', $this->piVars['links']));

				$fields_values = array(
					'tstamp' => time(),
					'pid' => $this->storagePid,
					'title' => $this->piVars['title'],
					'description' => $this->piVars['description'],
					'language' => $this->piVars['language'],
					'problem' => (int)$this->piVars['problem'],
					'code' => $this->piVars['snippet'],
					'tags' => implode(',', t3lib_div::trimexplode(',', $this->piVars['tags'], 1)),
					'poster' => $this->piVars['poster'],
					'links' => $links,
				);

				if ($this->piVars['edit']) {
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
						'tx_pastecode_code',
						'uid=' . (int)$this->piVars['edit'],
						$fields_values,
						$no_quote_fields = FALSE
					);
				} else {
					$fields_values['crdate'] = time();
					$GLOBALS['TYPO3_DB']->exec_INSERTquery(
						'tx_pastecode_code',
						$fields_values,
						$no_quote_fields = FALSE
					);
				}
				// clear cache
				$GLOBALS['TSFE']->clearPageCacheContent_pidList($this->conf['general.']['snippetPid']);
				header('Location:' . t3lib_div::locationHeaderUrl($this->pi_getPageLink($this->conf['general.']['snippetPid'])));
			}
		}
		$marker['###HEADER###'] = $this->piVars['edit'] ? $this->pi_getLL('edit snippet') : $this->pi_getLL('new snippet');
		$marker['###MESSAGE###'] = count($err) ? $this->cObj->stdWrap(implode('<br /><br />', $err), $this->conf['general.']['error_stdWrap.']) : '';
		$marker['###ACTION###'] = $this->piVars['edit']
				? $this->pi_getPageLink($GLOBALS['TSFE']->id, '', array($this->prefixId . '[edit]' => 1))
				: $this->pi_getPageLink($GLOBALS['TSFE']->id, '', array($this->prefixId . '[new]' => 1));
		$marker['###TITLE###'] = htmlspecialchars($this->piVars['title']);
		$marker['###DESCRIPTION###'] = htmlspecialchars($this->piVars['description']);
		$marker['###POSTER###'] = $this->piVars['poster'] ? htmlspecialchars($this->piVars['poster'])
				: htmlspecialchars($poster);
		$marker['###SNIPPET###'] = htmlspecialchars($this->piVars['snippet']);
		$marker['###LINKS###'] = htmlspecialchars($this->piVars['links']);
		$marker['###TAGS###'] = htmlspecialchars($this->piVars['tags']);
		$marker['###PROBLEM_CHECKED###'] = (int)$this->piVars['problem'] ? ' checked="checked"' : '';
		$marker['###LANGOPTIONS###'] = $this->pastecodePi1->languageSelect($this);
		$marker['###SELTAGS###'] = $this->getTags();

		// captcha
		if(t3lib_extMgm::isLoaded('captcha') && !$GLOBALS['TSFE']->fe_user->user['uid']) {
			$marker['###CAPTCHAPICTURE###'] = '<img src="' . t3lib_extMgm::siteRelPath('captcha') . 'captcha/captcha.php" alt="" />';
		} else {
			$subpart['###CAPTCHA###'] = '';
		}

		if($this->piVars['preview']) {
			$marker['###PREVIEWCODE###'] .= tx_pastecode_pi1::highLight($this->piVars['snippet'], $this->piVars['language']);
			$subpart['###PREVIEW###'] = $this->cObj->substituteMarkerArrayCached($previewSubpart, $marker);
		} else {
			$subpart['###PREVIEW###'] = '';
		}

		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}


	/**
	 * Selected tags as option-Tags
	 *
	 * @return string: HTML code
	 */
	function getTags() {
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code')
		);
		foreach($rows as $row) {
			if($row['tags']) {
				$tags = t3lib_div::trimExplode(',', $row['tags']);
				foreach ($tags as $tag) {
					$t[] = $tag;
				}
			}
		}
		$t = array_unique($t);
		sort($t);
		$options[] = '<option value=""></option>';
		foreach($t as $tag) {
			$options[] = '<option value="' . htmlspecialchars($tag) . '" onclick="addTag(\'' . htmlspecialchars($tag) . '\');">' . htmlspecialchars($tag) . '</option>';
		}
		return implode('', $options);
	}

	/**
	 * Clear cache of one page
	 *
	 * @param integer $pid: Page id
	 */
	/*public static function clearSpecificCache($pid) {
		t3lib_div::requireOnce(PATH_t3lib . 'class.t3lib_tcemain.php');
		$tce = t3lib_div::makeInstance('t3lib_TCEmain');
		/* @var $tce t3lib_TCEmain */
		/*$tce->clear_cacheCmd($pid);
	}*/
}

if(defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pastecode/pi2/class.tx_pastecode_pi2.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pastecode/pi2/class.tx_pastecode_pi2.php']);
}
?>