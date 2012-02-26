<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 Steffen Kamper <info@sk-typo3.de>
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
require_once(t3lib_extMgm::siteRelPath('geshilib') . 'res/geshi.php');
require_once(t3lib_extMgm::extPath('pastecode', 'pi2/class.tx_pastecode_pi2.php'));
if(t3lib_extMgm::isLoaded('ratings')) {
	require_once(t3lib_extMgm::extPath('ratings', 'class.tx_ratings_api.php'));
}

/**
 * Plugin 'Snippets' for the 'pastecode' extension.
 *
 * @author	Steffen Kamper <info@sk-typo3.de>
 * @package	TYPO3
 * @subpackage	tx_pastecode
 */
class tx_pastecode_pi1 extends tslib_pibase {
	var $prefixId = 'tx_pastecode_pi1'; // Same as class name
	var $scriptRelPath = 'pi1/class.tx_pastecode_pi1.php'; // Path to this script relative to the extension dir.
	var $extKey = 'pastecode'; // The extension key.
	var $storagePid;
	var $pid;

	/**
	 * Init function, mainly to be called from other extensions to use the functions from this extension
	 */
	/*function init() {
		$this->pi_setPiVarDefaults();
		$this->pi_loadLL();
	}*/

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

		if(!$this->piVars['search']) {
			$this->pi_checkCHash = true;
		}

		// Flexform config
		$this->pi_initPIflexForm();
		// enables flexform values in $conf
		if(is_array($this->cObj->data['pi_flexform']['data'])) { // if there are flexform values
			foreach($this->cObj->data['pi_flexform']['data'] as $key => $value) { // every flexform category
				if(count($this->cObj->data['pi_flexform']['data'][$key]['lDEF']) > 0) { // if there are flexform values
					foreach($this->cObj->data['pi_flexform']['data'][$key]['lDEF'] as $key2 => $value2) { // every flexform option
						if($this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key2, $key)) { // if value exists in flexform
							$this->conf[$key . '.'][$key2] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], $key2, $key); // overwrite $this->conf
						}
					}
				}
			}
		}

		$this->pastecodePi2 = t3lib_div::makeInstance('tx_pastecode_pi2');
		$this->storagePid = intval($this->cObj->data['pages']);
		$this->pid = $GLOBALS['TSFE']->id;
		$this->type = $GLOBALS['TSFE']->type;

		$this->template = $this->cObj->fileResource($this->conf['general.']['templateFile']);

		if (t3lib_extMgm::isLoaded('ratings')) {
			$this->ratings = t3lib_div::makeInstance('tx_ratings_api');
		}

		// RSS
		if ($this->type == 112) {
			$this->pid = intval($this->conf['pid']);
			$this->storagePid = intval($this->conf['storagePid']);
			return $this->rssView();
		}
		switch($this->conf['general.']['displayMode']) {
			case 'snippets':
			default:
				if($this->piVars['code']) {
					$content = $this->singleView($this->piVars['code']);
				} elseif($this->conf['authorlist']) {
					$content = $this->authorList();
				} else {
					$content = $this->overView();
				}
			break;
			case 'tagcloud':
				$content = $this->tagCloud();
			break;
			case 'languages':
				$content = $this->langCloud();
			break;
			case 'last':

			break;
			case 'search':
				$content = $this->searchView();
			break;
		}
		return $this->pi_wrapInBaseClass($content);
	}

	/**
	 * Search form
	 *
	 * @return string: HTML code
	 */
	function searchView() {
		$totalSubpart = $this->cObj->getSubpart($this->template, '###SEARCHSNIPPET###');
		$resultSubpart = $this->cObj->getSubpart($totalSubpart, '###RESULTS###');
		$rowSubpart = $this->cObj->getSubpart($resultSubpart, '###ROW###');

		$marker['###ACTION###'] = $this->pi_getPageLink($this->conf['general.']['snippetPid']);
		$marker['###LANGOPTIONS###'] = $this->languageSelect($this);

		$sword = addslashes(str_replace("'", '', $this->piVars['sword']));
		$marker['###V_SEARCH###'] = htmlspecialchars($this->piVars['sword']);

		// language keys
		$marker += $this->prepareLLArray('EXT:pastecode/pi1/locallang.xml', $this);

		$this->markerHook($marker);
		return $this->cObj->substituteMarkerArray($totalSubpart, $marker);
	}

	function singleView($id) {
		if(intval($id) == 0) {
			return '';
		}

		$totalSubpart = $this->cObj->getSubpart($this->template, '###SINGLESNIPPET###');

		$marker['###OVERVIEW_URL###'] = $this->pi_getPageLink($this->conf['general.']['snippetPid']);
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			'uid = ' . intval($id) . $this->cObj->enableFields('tx_pastecode_code')
		);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);

		// set browser title
		$GLOBALS['TSFE']->page['title'] = $this->cObj->stdWrap($row['title'], $this->conf['general.']['browsertitle_stdWrap.']);

		$marker['###CLASS###'] = $row['problem'] ? 'snippet-problem' : 'snippet-ok';
		$marker['###CODE###'] = $this->highLight($row['code'], $row['language']);
		$marker['###CODE_PLAIN###'] = htmlspecialchars($row['code']);
		$marker['###COPY_TO_CLIPBOARD###'] = '';

		$marker['###POSTER###'] = htmlspecialchars($row['poster']);

		$marker['###COUNT_SNIPPETS###'] = sprintf($this->pi_getLL('author_snippets_count'), $this->getSnippetCountOfUser($row['poster']));
		$subpartArray['###LINK_AUTHOR_SNIPPETS###'] = explode('explode-here', $this->pi_linkTP('explode-here', array($this->prefixId . '[author]' => urlencode($row['poster'])), 1));

		$marker['###TITLE###'] = htmlspecialchars($row['title']);
		$marker['###DESCRIPTION###'] = nl2br(htmlspecialchars($row['description']));
		$marker['###DATE###'] = strftime($GLOBALS['TSFE']->tmpl->setup['languagesetting.']['dateFormat'], $row['crdate']);
		$GLOBALS['TSFE']->ATagParams = 'title = "edit snippet"';
		if($GLOBALS['TSFE']->fe_user->user['name'] == $row['poster']) {
			$marker['###EDIT###'] = $this->cObj->stdWrap($this->pi_linkToPage($this->pi_getLL('edit snippet'), $this->conf['general.']['newsnippetPid'], '', array($this->pastecodePi2->prefixId . '[edit]' => $row['uid'])), $this->conf['general.']['editsnippet_stdWrap.']);
		} else {
			$marker['###EDIT###'] = '';
		}

		$GLOBALS['TSFE']->ATagParams = '';
		$marker['###LANGUAGE###'] = $this->getLanguageLink($row['language']);
		$marker['###TAGS###'] = '';
		if($row['tags']) {
			$tags = t3lib_div::trimExplode(',', $row['tags']);
			foreach($tags as $tag) {
				$t[] = $this->pi_linkTP(htmlspecialchars($tag), array(
					$this->prefixId . '[tag]' => urlencode($tag),
				), 1);
			}
			$marker['###TAGS###'] = implode(', ', $t);
		}
		$marker['###LINKS###'] = '';
		$t = array();
		if($row['links']) {
			$links = t3lib_div::trimExplode(',', $row['links']);
			foreach ($links as $link) {
				$pre = substr($link, 0, 1);
				$number = intval(substr($link, 1));
				switch ($pre) {
					case 'n' :
						#$nntpAPI = t3lib_div::makeInstance('tx_nntpreader_api');
						#$t[] = $nntpAPI->getPostingLink($number);
					break;
					case 'b':
						$t[] = '<a href="http://bugs.typo3.org/view.php?id=' . $number . '" target="_blank">Mantis Bug #' . $number . '</a>';
					break;
					case 'i':
						$t[] = '<a href="http://forge.typo3.org/issues/show/' . $number . '" target="_blank">Forge Issue #' . $number . '</a>';
					break;
				}
			}
			$marker['###LINKS###'] = implode(', ', $t);
		}

		$marker['###TX_RATINGS###'] = $this->ratings ? $this->ratings->getRatingDisplay('tx_pastecode_pi1' . intval($id)) : '';
		// language keys
		$marker += $this->prepareLLArray('EXT:pastecode/pi1/locallang.xml', $this);

		$subpart['###CLIPBOARD###'] = '';
		if($this->conf['single.']['pathToZeroClipboardJS'] && $this->conf['single.']['pathToZeroClipboardJS']) {
			$clipboardSubpart = $this->cObj->getSubpart($totalSubpart, '###CLIPBOARD###');
			$marker['###CLIPBOARD_PATH_JS###'] = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['single.']['pathToZeroClipboardJS']);
			$marker['###CLIPBOARD_PATH_SWF###'] = $GLOBALS['TSFE']->tmpl->getFileName($this->conf['single.']['pathToZeroClipboardSWF']);
			$marker['###CODE_CLIPBOARD###'] = str_replace(array(PHP_EOL, "'"), array('\n', "\'"), $row['code']);
			$marker['###CODE_CLIPBOARD###'] = htmlspecialchars($row['code']);
			$subpart['###CLIPBOARD###'] = $this->cObj->substituteMarkerArray($clipboardSubpart, $marker);
		}

		$this->markerHook($marker, $row);
		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart, $subpartArray);
	}

	function authorList() {
		if($this->piVars['author']) {
			return $this->overview();
		}
		$totalSubpart = $this->cObj->getSubpart($this->template, '###AUTHORLIST###');
		$rowSubpart = $this->cObj->getSubpart($totalSubpart, '###ROW###');

		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'count(*) anz, poster',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code'),
			'poster',
			'anz desc'
		);

		$subpart['###ROW###'] = '';
		foreach($rows as $row) {
			$marker['###AUTHOR###'] = $this->pi_linkTP(htmlspecialchars($row['poster']) . ' [' . $row['anz'] . ' snippets]', array(
				$this->prefixId . '[author]' => urlencode($row['poster'])
			), true);
			$subpart['###ROW###'] .= $this->cObj->substituteMarkerArrayCached($rowSubpart, $marker);
		}
		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}

	function overView() {
		$totalSubpart = $this->cObj->getSubpart($this->template, '###LISTSNIPPETS###');
		$rowSubpart = $this->cObj->getSubpart($totalSubpart, '###ROW###');
		$rowOSubpart = $this->cObj->getSubpart($totalSubpart, '###OROW###');
		$pid = $GLOBALS['TSFE']->id;
		$order = 'title';
		$addWhere = '';

		$marker = array();
		$marker['###SHOWALL###'] = '';

		if($this->piVars['tag']) {
			$marker['###HEADER###'] = sprintf($this->pi_getLL('header_snippets_with_tag'), htmlspecialchars(urldecode($this->piVars['tag'])));
			$marker['###SHOWALL###'] = $this->pi_getLL('clear filters');
			$addWhere .= ' AND FIND_IN_SET("' . $GLOBALS['TYPO3_DB']->quoteStr(urldecode($this->piVars['tag']), 'tx_pastecode_code') . '", tags) > 0';
		} elseif($this->piVars['author']) {
			$marker['###HEADER###'] = sprintf($this->pi_getLL('header_snippets_from_author'), htmlspecialchars(urldecode($this->piVars['author'])));
			$marker['###SHOWALL###'] = ($this->conf['authorlist'] ? $this->pi_getLL('back to author list') : $this->pi_getLL('clear filters'));
			$addWhere .= ' AND poster="' . $GLOBALS['TYPO3_DB']->quoteStr(urldecode($this->piVars['author']), 'tx_pastecode_code') . '"';
		} elseif($this->piVars['sword'] || $this->piVars['language']) {
			if(!$this->piVars['sword']) {
				$marker['###HEADER###'] = sprintf($this->pi_getLL('header_snippets_language'), htmlspecialchars(urldecode($this->piVars['language'])));
			} elseif(!$this->piVars['language']) {
				$marker['###HEADER###'] = sprintf($this->pi_getLL('header_snippets_search_for'), htmlspecialchars(urldecode($this->piVars['sword'])));
			} else {
				$marker['###HEADER###'] = sprintf($this->pi_getLL('header_snippets_search_and_language'), htmlspecialchars(urldecode($this->piVars['sword'])), htmlspecialchars(urldecode($this->piVars['language'])));
			}
			$marker['###SHOWALL###'] = $this->pi_getLL('clear filters');

			if($this->piVars['language']) {
				$addWhere .= ' AND language="' . $GLOBALS['TYPO3_DB']->quoteStr(urldecode($this->piVars['language']), 'tx_pastecode_code') . '"';
			}
			$sword = addslashes(str_replace("'", '', $this->piVars['sword']));
			if($sword) {
				$addWhere .= ' AND (title LIKE "%' . $GLOBALS['TYPO3_DB']->escapeStrForLike($sword, 'tx_pastecode_code') . '%"';
				$addWhere .= ' OR description LIKE "%' . $GLOBALS['TYPO3_DB']->escapeStrForLike($sword, 'tx_pastecode_code') . '%")';
			}
		} elseif ($this->conf['top25'] || $this->piVars['top25']) {
			/* TODO: easier? pagebrowser does not exist any more */
			$marker['###HEADER###'] = 'Top 25 rated snippets';
			$top25 = $this->getTop25();
			$this->conf['snippets.']['limit'] = 25;
			$order = 'FIELD(uid,' . $top25['idlist'] . ')';
			$addWhere .= ' AND uid IN(' . $top25['idlist'] . ')';
		} elseif ($this->conf['my_snippets'] || ($this->piVars['my_snippets'] && $GLOBALS['TSFE']->fe_user->user['uid'])) {
			$marker['###HEADER###'] = 'My snippets';
			$order = 'crdate';
			$addWhere .= ' AND poster="' . $GLOBALS['TSFE']->fe_user->user['name'] . '"';
		} else {
			$marker['###HEADER###'] = 'Snippets';
		}
		if($marker['###SHOWALL###']) {
			$marker['###SHOWALL###'] = $this->cObj->stdWrap($this->pi_linkTP($marker['###SHOWALL###'], array(), 1), $this->conf['general.']['showall_stdWrap.']);
		}

		// overview
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'count(*)',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $addWhere . $this->cObj->enableFields('tx_pastecode_code'),
			'',
			'title'
		);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		$count = $row[0];

		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $addWhere . $this->cObj->enableFields('tx_pastecode_code'),
			'',
			$order,
			intval($this->piVars['page']) * $this->conf['snippets.']['limit'] . ',' . $this->conf['snippets.']['limit']
		);

		// render pagebrowser
		$marker['###BROWSE_LINKS###'] = $this->getListGetPageBrowser(ceil($count/$this->conf['snippets.']['limit']));

		$marker['###PB_TOTAL###'] = $count;
		$marker['###PB_START###'] = intval($this->piVars['page']) * $this->conf['snippets.']['limit'] + 1;
		$marker['###PB_END###'] = intval($marker['###PB_START###'] + $this->conf['snippets.']['limit']) < $count
				? intval($marker['###PB_START###'] + $this->conf['snippets.']['limit']) : $count;

		$subpart['###OROW###'] = '';
		foreach($rows as $row) {
			$subpartArray['###LINK_ITEM###'] = explode('explode-here', $this->pi_linkTP('explode-here', array($this->prefixId . '[code]' => $row['uid']), 1));
			$marker['###CLASS###'] = $row['problem'] ? 'snippet-problem' : 'snippet-ok';
			$marker['###TITLE###'] = htmlspecialchars($row['title']);
			$marker['###POSTER###'] = htmlspecialchars($row['poster']);
			$marker['###DATE###'] = strftime($GLOBALS['TSFE']->tmpl->setup['languagesetting.']['dateFormat'], $row['crdate']);

			$marker['###LANGUAGE###'] = $this->getLanguageLink($row['language']);
			$rating = $this->ratings ? $this->ratings->getRatingArray('tx_pastecode_pi1' . intval($row['uid'])) : array();
			$ratetxt = intval($rating['vote_count']) == 0 ? '-' : number_format($rating['rating'] / $rating['vote_count'], 2);
			$marker['###TX_RATINGS###'] = $this->ratings ? $ratetxt : '';

			$this->markerHook($marker, $row);
			$subpart['###OROW###'] .= $this->cObj->substituteMarkerArrayCached($rowOSubpart, $marker, array(), $subpartArray);
		}

		// last 10
		$marker['###RSS###'] = $this->cObj->typolink('get the last snippets as RSS-feed', array('parameter' => $this->pid . ',112'));
		$subpart['###ROW###'] = $this->lastSnippets(10, $rowSubpart);

		$marker['###NEW###'] = $this->cObj->stdWrap($this->pi_linkToPage($this->pi_getLL('new snippet'), $this->conf['general.']['newsnippetPid']), $this->conf['general.']['newsnippet_stdWrap.']);
		$marker['###SEARCH###'] = $this->searchView();
		$marker['###TAGCLOUD###'] = $this->tagCloud();
		$marker['###LANGUAGES###'] = $this->langCloud();

		// language keys
		$marker += $this->prepareLLArray('EXT:pastecode/pi1/locallang.xml', $this);

		$this->markerHook($marker);
		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}

	function rssView() {
		$totalSubpart = $this->cObj->getSubpart($this->template, '###RSSFEED###');
		$rowSubpart = $this->cObj->getSubpart($totalSubpart, '###CONTENT###');

		$marker = array();
		$marker['###SITE_TITLE###'] = 'Snippets on support.typo3.org';
		$marker['###SITE_LINK###'] = 'http://support.typo3.org/snippets/';
		$marker['###SITE_DESCRIPTION###'] = 'snippets';
		$marker['###NEWS_COPYRIGHT###'] = '';
		$marker['###NEWS_WEBMASTER###'] = 'Steffen Kamper';
		$marker['###NEWS_LASTBUILD###'] = date('Y-m-d');

		$subpart['###CONTENT###'] = $this->lastSnippets(20, $rowSubpart);

		$this->markerHook($marker);
		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}

	/**
	 * Generate tag cloud
	 *
	 * @return string: HTML code
	 */
	function tagCloud() {
		$template = $this->cObj->getSubpart($this->template, '###TEMPLATE_TAGCLOUD###');
		$templateTag = $this->cObj->getSubpart($template, '###TAGCLOUD_TAG###');

		$marker = array();

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code')
		);
		$t = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if($row['tags']) {
				$tags = t3lib_div::trimExplode(',', $row['tags']);
				foreach($tags as $tag) {
					// do not allow numbers
					if(!(int)$tag || strlen($tag) != strlen((int)$tag)) {
						$t[$tag]++;
					}
				}
			}
		}

		$maxCountTag = max($t);
		$minCountTag = min($t);
		$q = ceil(($maxCountTag - $minCountTag) / $this->conf['tagcloud.']['maxSize']);

		// output tag only, if it is used more than x times
		foreach($t as $tag => $count) {
			if($count < intval($this->conf['tagcloud.']['tagsMinCount'])) {
				unset($t[$tag]);
			}
		}

		// do not output all tags
		if($this->conf['tagcloud.']['tagsMax'] && count($t) > $this->conf['tagcloud.']['tagsMax']) {
			arsort($t, SORT_NUMERIC);
			$t = array_slice($t, 0, $this->conf['tagcloud.']['tagsMax'], true);
		}

		// shuffle tags (with preserving of keys)
		$keys = array_keys($t);
		shuffle($keys);
		$t = array_merge(array_flip($keys), $t);

		// build links for tags
		$subpart['###TAGCLOUD_TAG###'] = '';
		foreach($t as $tag => $count) {
			$class = ceil(($count - $minCountTag + 1) / $q);
			if($class > $this->conf['tagcloud.']['maxSize']) {
				$class = $this->conf['tagcloud.']['maxSize'];
			}
			if($this->conf['tagcloud.']['sizeReverse']) {
				$class = $this->conf['tagcloud.']['maxSize'] + 1 - $class;
			}
			// currently choosed tag
			if($this->piVars['tag'] == $tag) {
				$marker['###TAG###'] = '<b class="size-' . $class . '">' . htmlspecialchars($tag) . '</b>';
			} else {
				$marker['###TAG###'] = $this->pi_linkTP('<span class="size-' . $class . '">' . htmlspecialchars($tag) . '</span>', array(
					$this->prefixId . '[tag]' => urlencode($tag),
				), 1, $this->conf['general.']['snippetPid']);
			}
			$subpart['###TAGCLOUD_TAG###'] .= $this->cObj->substituteMarkerArray($templateTag, $marker);
		}
		return $this->cObj->substituteMarkerArrayCached($template, array(), $subpart);
	}

	function lastSnippets($count, $subPart) {
		// last 10
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code'),
			'',
			'crdate desc',
			intval($count)
		);

		$rows = '';
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$marker['###HREF###'] = $this->cObj->lastTypoLinkUrl;
			$marker['###HREFRSS###'] = 'snippets/c/' . $row['uid'] . '/';
			$marker['###BASEURL###'] = $this->conf['baseURL'];

			$marker['###TITLE###'] = htmlspecialchars($row['title']);
			$marker['###POSTER###'] = htmlspecialchars($row['poster']);
			$marker['###DATE###'] = strftime($GLOBALS['TSFE']->tmpl->setup['languagesetting.']['dateFormat'], $row['crdate']);
			$marker['###LANGUAGE###'] = $this->getLanguageLink($row['language']);
			$marker['###DESCRIPTION###'] = htmlspecialchars($row['description']);
			$this->markerHook($marker, $row);
			$rows .= $this->cObj->substituteMarkerArrayCached($subPart, $marker);
		}
		return $rows;
	}

	function langCloud() {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code')
		);
		$t = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$t[$row['language']]++;
		}
		#sort($t);

		foreach($t as $lang => $count) {
			$langlinks[] = '<li>' . $this->pi_linkTP(htmlspecialchars($lang) . ' (' . $count . ')', array(
				$this->prefixId . '[language]' => urlencode($lang),
			), 1, $this->conf['general.']['snippetPid']) . '</li>';
		}
		return implode(' ', $langlinks);
	}

	/**
	 * Highlight code with GeSHi
	 *
	 * @param string $code: Code to highlight
	 * @param string $language: Type of code, e.g. "php", "typoscript", "html4strict", ...
	 */
	public function highLight($code, $language) {
		$geshi = new GeSHi($code, $language, '');
		$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS, 2);
		$geshi->set_line_style('background: #fcfcfc;', 'background: #fdfdfd;');
		$geshi->enable_classes(true);
		$geshi->set_overall_id('pastecode-code-c');
		$GLOBALS['TSFE']->additionalCSS[] = $geshi->get_stylesheet();
		return $geshi->parse_code();
	}

	function getTop25() {
		$result = array();
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'rating, vote_count, (rating/vote_count) q, substring(reference,17) sid',
			'tx_ratings_data',
			'vote_count > 0 and left(reference,16) = "tx_pastecode_pi1"',
			'',
			'q desc',
			'25'
		);
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$result['ids'][] = $row['sid'];
			$result['top'][] = $row;
		}
		$result['idlist'] = implode(',', $result['ids']);

		return $result;
	}

	function markerHook(&$marker, $row = NULL) {
		if(is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pastecode']['markerHook'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pastecode']['markerHook'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$marker = $_procObj->pastecodeMarkerProcessor($marker, $row, $this);
			}
		}
	}

	public function languageSelect(&$piObj) {
		$options = '';
		foreach(t3lib_div::trimExplode(',', $GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_pastecode_pi1.']['general.']['languages']) as $lang) {
			$options .= '<option value="' . $lang . '"' . ($lang == $piObj->piVars['language']
				? ' selected="selected"' : '') . '>' . $lang . '</option>';
		}
		return $options;
	}

	/**
	 * Render page browser with ext. page
	 *
	 * @param integer $numberOfPages: Amount of pages to render
	 * @return string: HTML code
	 */
	protected function getListGetPageBrowser($numberOfPages) {
		// Get default configuration
		$conf = $this->conf['snippets.']['pagebrowse.'];
		// Modify this configuration
		$conf['pageParameterName'] = $this->prefixId . '|page';
		$conf['numberOfPages'] = $numberOfPages;
		if($this->piVars['sword']) {
			$conf['extraQueryString'] = '&' . $this->prefixId . '[sword]=' . $this->piVars['sword'];
		}
		if($this->piVars['language']) {
			$conf['extraQueryString'] = '&' . $this->prefixId . '[language]=' . $this->piVars['language'];
		}
		if($this->piVars['tag']) {
			$conf['extraQueryString'] = '&' . $this->prefixId . '[tag]=' . $this->piVars['tag'];
		}
		// Get page browser
		return $this->cObj->cObjGetSingle('USER', $conf);
	}

	/**
	 * Generate template markers for language keys
	 *
	 * @param string $langFile: Language file
	 * @return array
	 */
	public function prepareLLArray($langFile, &$piObj){
		$returnArray = array();
		// Read in language file
		$langAll = t3lib_div::readLLfile($langFile, 'default');

		// Create language markers
		foreach($langAll['default'] as $key => $value) {
			$returnArray['###LL_' . strtoupper(str_replace(' ', '_', $key)) . '###'] = $piObj->pi_getLL($key);
		}
		return $returnArray;
	}

	/**
	 * Get amount of snippets a user has created
	 *
	 * @param string $user: Name of user
	 * @return integer: Count of snippets
	 */
	public function getSnippetCountOfUser($user) {
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'count(*)',
			'tx_pastecode_code',
			'pid = ' . $this->storagePid . ' AND poster = "' . $user . '"' . $this->cObj->enableFields('tx_pastecode_code')
		);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		return $row[0];
	}

	/**
	 * Link category to list and filter for it
	 *
	 * @param string $language
	 * @return string: HTML code (link)
	 */
	protected function getLanguageLink($language) {
		$GLOBALS['TSFE']->ATagParams = 'title="show all snippets of language ' . htmlspecialchars($language) . '"';
		if($this->conf['snippets.']['linkLanguage']) {
			$link = $this->pi_linkTP(
				htmlspecialchars($language),
				array($this->prefixId . '[language]' => urlencode($language)),
				1
			);
		} else {
			$link = htmlspecialchars($language);
		}
		$GLOBALS['TSFE']->ATagParams = '';
		return $link;
	}
}

if(defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pastecode/pi1/class.tx_pastecode_pi1.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pastecode/pi1/class.tx_pastecode_pi1.php']);
}
?>