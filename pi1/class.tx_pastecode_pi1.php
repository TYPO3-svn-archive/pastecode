<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Steffen Kamper <info@sk-typo3.de>
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

require_once(PATH_tslib.'class.tslib_pibase.php');
require_once(t3lib_extMgm::siteRelPath('geshilib').'res/geshi.php');
if (t3lib_extMgm::isLoaded('ratings')) {
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
	var $prefixId      = 'tx_pastecode_pi1';		// Same as class name
	var $scriptRelPath = 'pi1/class.tx_pastecode_pi1.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'pastecode';	// The extension key.
	var $pi_checkCHash = true;
	var $languages = array('--div--;Common','php','typoscript','javascript','html4strict','sql','xml','diff','--div--;other','actionscript','ada','apache','applescript','asm','asp','bash','blitzbasic','c','c_mac','caddcl','cadlisp','cpp','csharp','css','d','delphi','div','dos','eiffel','freebasic','gml','ini','java','lisp','lua','matlab','mpasm','mysql','nsis','objc','ocaml','ocaml-brief','oobas',	'oracle8','pascal','perl','php-brief','python','qbasic','ruby','scheme','sqlbasic','smarty','vb','vbnet','vhdl','visualfoxpro');
	var $storagePid;
	var $pid;
	var $icon;


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


		$this->storagePid = intval($this->cObj->data['pages']);
		$this->pid = $GLOBALS['TSFE']->id;
		$this->type = $GLOBALS['TSFE']->type;

		$tmpl = $this->conf['templateFile'] ? $this->conf['templateFile'] : 'EXT:pastecode/res/template.html';
		$this->template = $this->cObj->fileResource($tmpl);

		$this->icon['ok'] = '<img class="icon" src="'. t3lib_extMgm::siteRelPath('pastecode') . 'res/ok.png" width="12" height="12" title="working snippet" alt="ok" />';
		$this->icon['problem'] = '<img class="icon" src="' . t3lib_extMgm::siteRelPath('pastecode') . 'res/bug.png" width="12" height="12" title="snippet has a problem" alt="bug" />';
		$this->icon['help'] = '<img class="icon" src="' . t3lib_extMgm::siteRelPath('pastecode') . 'res/help.gif" width="16" height="16" alt="help" />';
		$this->icon['rss'] = '<img class="icon" src="' . t3lib_extMgm::siteRelPath('pastecode') . 'res/rss.gif" width="16" height="16" alt="RSS-feed" />';
		$this->icon['edit'] = '<img class="icon" src="typo3/sysext/t3skin/icons/gfx/edit2.gif" width="16" height="16" alt="edit snippet" />';

		if (t3lib_extMgm::isLoaded('ratings')) {
			$this->ratings = t3lib_div::makeInstance('tx_ratings_api');
		}


		// RSS
		if ($this->type == 112) {
			$this->pid = intval($this->conf['pid']);
			$this->storagePid = intval($this->conf['storagePid']);
			return $this->rssView();
		}
		if (intval($this->conf['pageBrowser.']['results']) == 0) {
			$this->conf['pageBrowser.']['results'] = 30;
		}
		if ($this->piVars['search']) {
			$content = $this->searchView();
		} elseif ($this->piVars['code']) {
			$content = $this->singleView($this->piVars['code']);
		} elseif ($this->piVars['new'] || $this->piVars['edit']) {
			$content = $this->newCode();
		} elseif ($this->conf['authorlist']) {
			$content = $this->authorList();
		} else {
			$content = $this->overView();
		}


		return $this->pi_wrapInBaseClass($content);
	}

	function searchView() {

		$totalSubpart = $this->cObj->getSubpart($this->template, '###SEARCHSNIPPET###');
		$resultSubpart = $this->cObj->getSubpart($totalSubpart, '###RESULTS###');
		$rowSubpart = $this->cObj->getSubpart($resultSubpart, '###ROW###');

		$marker['###ACTION###'] = $this->pi_getPageLink($GLOBALS['TSFE']->id, '', array( $this->prefixId . '[search]' => 1));
		$subpart['###RESULTS###'] = '';
		$marker['###OVERVIEWLINK###'] = $this->pi_linkTP('&lt;&lt: back to overview',array(),1);

		$sword = addslashes(str_replace("'", '', $this->piVars['sword']));
		$marker['###V_SEARCH###'] = htmlspecialchars($this->piVars['sword']);

		if ($sword == '') {
			return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
		}
		$addWhere = '(title LIKE "%' . $GLOBALS['TYPO3_DB']->escapeStrForLike($sword,'tx_pastecode_code') . '%"';
		$addWhere .= ' OR description LIKE "%' . $GLOBALS['TYPO3_DB']->escapeStrForLike($sword,'tx_pastecode_code') . '%")';


		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'count(*)',
			'tx_pastecode_code',
			$addWhere . $this->cObj->enableFields('tx_pastecode_code'),
			'',
			'',
			''
		);

		$row=$GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		$count = $row[0];
		$marker['###PB_TOTAL###'] = $count;

		if ($count == 0) {
			return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
		}

			// prepare pagebrowser
		$pbConf = $this->conf['pageBrowser.'];
		$pbConf['maxPages'] = 10;

		$this->pi_alwaysPrev = $pbConf['alwaysPrev'];

		$this->internal['res_count'] = $count;
		$this->internal['results_at_a_time'] = $pbConf['results'];
		$this->internal['maxPages'] = 6;

		$wrapArrFields = explode(',', 'disabledLinkWrap,inactiveLinkWrap,activeLinkWrap,browseLinksWrap,showResultsWrap,showResultsNumbersWrap,browseBoxWrap');
		$wrapArr = array();
		foreach($wrapArrFields as $key) {
			if ($pbConf[$key]) {
				$wrapArr[$key] = $pbConf[$key];
			}
		}

		// render pagebrowser
		$marker['###BROWSE_LINKS###'] = $this->pi_list_browseresults(
								0,
								$pbConf['tableParams'],
								$wrapArr,
								'pointer',
								true);


		$marker['###PB_START###'] = intval($this->piVars['pointer']) * $pbConf['results'] + 1;
		$marker['###PB_END###'] = $marker['###PB_START'] + $pbConf['results'] < $count ? $marker['###PB_START'] + $pbConf['results'] : $count;


		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			$where_clause = $addWhere . $this->cObj->enableFields('tx_pastecode_code'),
			$groupBy='',
			$orderBy='',
			$limit = intval($this->piVars['pointer']) * $pbConf['results'] . ',' . $pbConf['results']
		);
		$rows = '';
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			$l = $this->pi_linkTP('',
				array(
					$this->prefixId.'[code]' => $row['uid'],
				), 1, $this->conf['snippetPid']
			);
			$marker['###HREF###'] = $this->cObj->lastTypoLinkUrl;

			$marker['###ICON###'] = $row['problem'] ? $this->icon['problem'] : $this->icon['ok'];
			$marker['###TITLE###'] = $row['title'];
			$marker['###POSTER###'] = $row['poster'];
			$marker['###DATE###'] = date('Y-m-d', $row['crdate']);
			$marker['###LANG###'] = $row['language'];
			$this->markerHook($marker, $row);
			$rows .= $this->cObj->substituteMarkerArrayCached($rowSubpart, $marker);
		}
		$subpart['###ROW###'] = $rows;
		$subpart['###RESULTS###'] = $this->cObj->substituteMarkerArrayCached($resultSubpart, $marker, $subpart);
		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}

	function singleView($id) {
		if (intval($id) == 0) {
			return '';
		}

		$totalSubpart = $this->cObj->getSubpart($this->template, '###SINGLESNIPPET###');


		$marker['###OVERVIEWLINK###'] = $this->pi_linkTP('&lt;&lt: back to overview',array(),1);
		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			$where_clause = 'uid=' . intval($id) . $this->cObj->enableFields('tx_pastecode_code'),
			$groupBy='',
			$orderBy='',
			$limit=''
		);

		if ($res) {
			$row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$marker['###ICON###'] = $row['problem'] ? $this->icon['problem'] : $this->icon['ok'];
			$marker['###CODE###'] = $this->highLight($row['code'], $row['language']);
			$marker['###CODE_PLAIN###'] = $row['code'];
			$marker['###POSTER###'] = htmlspecialchars($row['poster']);
			$marker['###TITLE###'] = htmlspecialchars($row['title']);
			$marker['###DESCRIPTION###'] = nl2br(htmlspecialchars($row['description']));
			$marker['###DATE###'] = date('Y-m-d', $row['crdate']);
			$GLOBALS['TSFE']->ATagParams = 'title = "edit snippet"';
			$marker['###EDIT###'] = $GLOBALS['TSFE']->fe_user->user['name'] == $row['poster'] ?
			$this->pi_linkTP($this->icon['edit'],array(
				$this->prefixId . '[edit]' => $row['uid']
			),false) :
			'';

			$GLOBALS['TSFE']->ATagParams = 'title="show all snippets of language ' . $row['language'] . '"';
			$marker['###LANGUAGE###'] = $this->pi_linkTP($row['language'], array(
						$this->prefixId.'[language]' => urlencode($row['language']),
					),1);
			$GLOBALS['TSFE']->ATagParams = '';
			$marker['###TAGS###'] = '';
			if($row['tags']) {
				$tags = t3lib_div::trimExplode(',', $row['tags']);
				foreach ($tags as $tag) {
					$t[] = $this->pi_linkTP(htmlspecialchars($tag), array(
						$this->prefixId.'[tag]' => urlencode($tag),
					),1);
				}
				$marker['###TAGS###'] = implode(', ', $t);
			}
			$marker['###LINKS###'] = '';
			$t=array();
			if($row['links']) {
				$links = t3lib_div::trimExplode(',', $row['links']);
				foreach ($links as $link) {
					$pre = substr($link,0,1);
					$number = intval(substr($link,1));
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
			$this->markerHook($marker, $row);
		}


		$marker['###TX_RATINGS###'] = $this->ratings ? $this->ratings->getRatingDisplay('tx_pastecode_pi1' . intval($id)) : '';

		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, array(), array());
	}

	function newCode() {


		$GLOBALS['TSFE']->additionalHeaderData[] = "
			<script type=\"text/javascript\">
			function addTag( p_string ) {
				t_tag_separator = ',';
				t_tag_string = document.getElementById('tags');
				t_tag_select = document.getElementById('seltags');
				if ( t_tag_string.value != '' ) {
					t_tag_string.value = t_tag_string.value + t_tag_separator + p_string;
				} else {
					t_tag_string.value = t_tag_string.value + p_string;
				}
				t_tag_select.selectedIndex=0;
			}
			</script>
		";
		$totalSubpart = $this->cObj->getSubpart($this->template, '###NEWSNIPPET###');
		$previewSubpart = $this->cObj->getSubpart($totalSubpart, '###PREVIEW###');
		$pid = $GLOBALS['TSFE']->id;

		$poster = $_COOKIE['snippetposter_' . $this->prefixId];
		$marker['###OVERVIEWLINK###'] = $this->pi_linkTP('&lt;&lt: back to overview',array(),1);
		$marker['###HIDDEN###'] = '';


		if($this->piVars['edit']) {
			$snippet = $this->pi_getRecord('tx_pastecode_code', intval($this->piVars['edit']));
			if ($GLOBALS['TSFE']->fe_user->user['name'] != $snippet['poster']) {
				return 'Access denied';
			}
			$marker['###HIDDEN###'] .= '<input type="hidden" name="tx_pastecode_pi1[edit]" value="' . intval($this->piVars['edit']) . '" />';
			if(!$this->piVars['title']) $this->piVars['title'] = $snippet['title'];
			if(!$this->piVars['description']) $this->piVars['description'] = $snippet['description'];
			if(!$this->piVars['snippet']) $this->piVars['snippet'] = $snippet['code'];
			if(!$this->piVars['links']) $this->piVars['links'] = $snippet['links'];
			if(!$this->piVars['tags']) $this->piVars['tags'] = $snippet['tags'];
			if(!$this->piVars['problem']) $this->piVars['problem'] = $snippet['problem'];

		}


		if ($GLOBALS['TSFE']->fe_user->user['uid']) {
			$subpart['###USERINFO###'] = '';
			$marker['###HIDDEN###'] .= '<input type="hidden" name="tx_pastecode_pi1[poster]" value="' . htmlspecialchars($GLOBALS['TSFE']->fe_user->user['name']) . '" />';
		}

		if($this->piVars['save']) {
			//validate
			$err = array();
			if (strlen($this->piVars['title']) < 4 || strlen($this->piVars['snippet']) < 30) {
				$err[] = 'Your Entries are too small!';
			}
			#captcha response
			if (t3lib_extMgm::isLoaded('captcha') && !$GLOBALS['TSFE']->fe_user->user['uid'])	{
				session_start();
				if ($this->piVars['captchaResponse'] != $_SESSION['tx_captcha_string']) {
				   $err[]=$this->pi_getLL('captcha_error');
				}
				$_SESSION['tx_captcha_string'] = '';
			}
			if (count($err) == 0) {
				//save snippet
				if ($this->piVars['poster'] == '') {
					$this->piVars['poster'] = 'anonymous';
				} else {
					SetCookie('snippetposter_' . $this->prefixId, $this->piVars['poster']);
				}
				$links = @implode(',',t3lib_div::trimExplode(',', $this->piVars['links']));

				$fields_values = array(
					'tstamp' => time(),
					'pid' => $this->storagePid,
					'title' => $this->piVars['title'],
					'description' => $this->piVars['description'],
					'language' => $this->piVars['language'],
					'problem' => intval($this->piVars['problem']),
					'code' => $this->piVars['snippet'],
					'tags' => implode(',', t3lib_div::trimexplode(',', $this->piVars['tags'],1)),
					'poster' => $this->piVars['poster'],
					'links' => $links,
				);

				if($this->piVars['edit']) {
					$res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_pastecode_code','uid=' . intval($this->piVars['edit']), $fields_values);
				} else {
					$fields_values['crdate'] = time();
					$res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_pastecode_code',$fields_values,$no_quote_fields=FALSE);
				}
					// clear cache
				$this->clearSpecificCache($GLOBALS['TSFE']->id);
				header('location:' . t3lib_div::locationHeaderUrl($this->pi_getPageLink($GLOBALS['TSFE']->id)));
			}
		}
		$marker['###LEGEND###'] = $this->piVars['edit'] ? 'Edit snippet' : 'New snippet';
		$marker['###MESSAGE###'] = count($err) ? '<p class="error">' . implode('<br />', $err) . '</p>' : '';
		$marker['###ACTION###'] = $this->piVars['edit'] ? $this->pi_getPageLink($pid,'',array($this->prefixId.'[edit]'=>1)) : $this->pi_getPageLink($pid,'',array($this->prefixId.'[new]'=>1));
		$marker['###TITLE###'] = htmlspecialchars($this->piVars['title']);
		$marker['###DESCRIPTION###'] = htmlspecialchars($this->piVars['description']);
		$marker['###POSTER###'] = $this->piVars['poster'] ? htmlspecialchars($this->piVars['poster']) : htmlspecialchars($poster);
		$marker['###SNIPPET###'] = htmlspecialchars($this->piVars['snippet']);
		$marker['###LINKS###'] = htmlspecialchars($this->piVars['links']);
		$marker['###TAGS###'] = htmlspecialchars($this->piVars['tags']);
		$marker['###PROBLEM###'] =intval($this->piVars['problem']) ? 'checked="checked"' : '';
		$marker['###LANGOPTIONS###'] = $this->languageSelect();
		$marker['###SELTAGS###'] = $this->getTags();

		$zw = '&#09;&#09;&#09;';
		$marker['###HELP###'] = '<a href="#" title="click for help" onclick="toggleHelp();return false;>' . $this->icon['help'] . '</a>';

		#captcha
		if (t3lib_extMgm::isLoaded('captcha') && !$GLOBALS['TSFE']->fe_user->user['uid'])	{
			$marker['###CAPTCHAINPUT###'] = '<input type="text" id="captcha" size="10" name="'.$this->prefixId.'[captchaResponse]" value="" />';
			$marker['###CAPTCHAPICTURE###'] = '<img src="'.t3lib_extMgm::siteRelPath('captcha').'captcha/captcha.php" alt="" />';
			$marker['###L_CAPTCHA###']=$this->pi_getLL('captcha');
		} else {
			$subpart['###CAPTCHA###'] = '';
		}


		if ($this->piVars['preview']) {
			$marker['###PREVIEWCODE###'] .= $this->highLight($this->piVars['snippet'], $this->piVars['language']);
			$marker['###LANG###'] = htmlspecialchars($this->piVars['language']);
			$subpart['###PREVIEW###'] = $this->cObj->substituteMarkerArrayCached($previewSubpart, $marker);
		} else {
			$subpart['###PREVIEW###'] = '';
		}
		$this->markerHook($marker, $this->piVars);
		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}

	function authorList() {
		if ($this->piVars['author']) {
			return $this->overview();
		}
		$totalSubpart = $this->cObj->getSubpart($this->template, '###AUTHORLIST###');
		$rowSubpart = $this->cObj->getSubpart($totalSubpart, '###ROW###');


		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'count(*) anz, poster',
			'tx_pastecode_code',
			$where_clause = 'pid=' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code'),
			$groupBy='poster',
			$orderBy='anz desc',
			$limit= ''
		);

		$rows = '';
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$marker['###AUTHOR###'] = $this->pi_linkTP(htmlspecialchars($row['poster']) . ' ['.$row['anz'].' snippets]', array(
				$this->prefixId . '[author]' => urlencode($row['poster'])
			), true);
			$rows .= $this->cObj->substituteMarkerArrayCached($rowSubpart, $marker);
		}

		$subpart['###ROW###'] = $rows;

		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);
	}

	function overView() {

		$totalSubpart = $this->cObj->getSubpart($this->template, '###LISTSNIPPETS###');
		$rowSubpart = $this->cObj->getSubpart($totalSubpart, '###ROW###');
		$rowOSubpart = $this->cObj->getSubpart($totalSubpart, '###OROW###');
		$pid = $GLOBALS['TSFE']->id;
		$order = 'title';

		if ($this->piVars['tag']) {
			$marker['###HEADER###'] = 'Snippets with tag "' . htmlspecialchars(urldecode($this->piVars['tag'])) . '"';
			$marker['###SHOWALL###'] = '<p>' . $this->pi_linkTP('show all scripts',array(),1). '</p>';
			$addWhere = ' and FIND_IN_SET("'. urldecode($this->piVars['tag']) . '", tags)>0';
		} elseif ($this->piVars['author']) {
			$marker['###HEADER###'] = 'Snippets from "' . htmlspecialchars(urldecode($this->piVars['author'])) . '"';
			$marker['###SHOWALL###'] = '<p>' . ($this->conf['authorlist'] ? $this->pi_linkTP('back to author list',array(),1) : $this->pi_linkTP('show all scripts',array(),1)). '</p>';
			$addWhere = ' and poster="'. urldecode($this->piVars['author']) . '"';
		} elseif ($this->piVars['language']) {
			$marker['###HEADER###'] = 'Snippets with language "' . htmlspecialchars(urldecode($this->piVars['language'])) . '"';
			$marker['###SHOWALL###'] = '<p>' . $this->pi_linkTP('show all scripts',array(),1). '</p>';
			$addWhere = ' and language="'. urldecode($this->piVars['language']) . '"';
		} elseif ($this->conf['top25'] || $this->piVars['top25']) {
			$marker['###HEADER###'] = 'Top 25 rated snippets';
			$marker['###SHOWALL###'] = '';
			$top25 = $this->getTop25();
			$this->conf['pageBrowser.']['results'] = 25;
			$order = 'FIELD(uid,' . $top25['idlist'] . ')';
			$addWhere = ' and uid IN('. $top25['idlist'] . ')';

		} elseif ($this->conf['my_snippets'] || ($this->piVars['my_snippets'] && $GLOBALS['TSFE']->fe_user->user['uid'])) {
			$marker['###HEADER###'] = 'My snippets';
			$marker['###SHOWALL###'] = '';

			$order = 'crdate';
			$addWhere = ' and poster="' . $GLOBALS['TSFE']->fe_user->user['name'] . '"';

		} else {
			$marker['###HEADER###'] = 'Snippets';
			$marker['###SHOWALL###'] = '';
			$addWhere = '';
		}

		//overview
		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'count(*)',
			'tx_pastecode_code',
			$where_clause = 'pid=' . $this->storagePid . $addWhere . $this->cObj->enableFields('tx_pastecode_code'),
			$groupBy='',
			$orderBy='title',
			$limit=''
		);

		$row=$GLOBALS['TYPO3_DB']->sql_fetch_row($res);
		$count = $row[0];
		$marker['###PB_TOTAL###'] = $count;


			// prepare pagebrowser
		$pbConf = $this->conf['pageBrowser.'];
		$pbConf['maxPages'] = 10;

		$this->pi_alwaysPrev = $pbConf['alwaysPrev'];

		$this->internal['res_count'] = $count;
		$this->internal['results_at_a_time'] = $pbConf['results'];
		$this->internal['maxPages'] = 6;

		$wrapArrFields = explode(',', 'disabledLinkWrap,inactiveLinkWrap,activeLinkWrap,browseLinksWrap,showResultsWrap,showResultsNumbersWrap,browseBoxWrap');
		$wrapArr = array();
		foreach($wrapArrFields as $key) {
			if ($pbConf[$key]) {
				$wrapArr[$key] = $pbConf[$key];
			}
		}

		// render pagebrowser
		$marker['###BROWSE_LINKS###'] = $this->pi_list_browseresults(
								0,
								$pbConf['tableParams'],
								$wrapArr,
								'pointer',
								true);



		$marker['###PB_START###'] = intval($this->piVars['pointer']) * $pbConf['results'] + 1;
		$marker['###PB_END###'] = intval($marker['###PB_START###'] + $pbConf['results']) < $count ? intval($marker['###PB_START###'] + $pbConf['results']) : $count;

		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			$where_clause = 'pid=' . $this->storagePid . $addWhere . $this->cObj->enableFields('tx_pastecode_code'),
			$groupBy='',
			$orderBy=$order,
			$limit= intval($this->piVars['pointer']) * $pbConf['results'] . ',' . $pbConf['results']
		);
		$rows = '';
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$l = $this->pi_linkTP('',
				array(
					$this->prefixId.'[code]' => $row['uid'],
				),1,  $this->conf['snippetPid']
			);
			$marker['###HREF###'] = $this->cObj->lastTypoLinkUrl;
			$marker['###ICON###'] = $row['problem'] ? $this->icon['problem'] : $this->icon['ok'];
			$marker['###TITLE###'] = $row['title'];
			$marker['###POSTER###'] = $row['poster'];
			$marker['###DATE###'] = date('Y-m-d', $row['crdate']);
			$marker['###LANG###'] = $row['language'];
			$rating =  $this->ratings ? $this->ratings->getRatingArray('tx_pastecode_pi1' . intval($row['uid'])) : array();
			$ratetxt = intval($rating['vote_count']) == 0 ? '-' : number_format($rating['rating'] / $rating['vote_count'],2);
			$marker['###TX_RATINGS###'] = $this->ratings ? $ratetxt : '';

			$this->markerHook($marker, $row);
			$rows .= $this->cObj->substituteMarkerArrayCached($rowOSubpart, $marker);
		}
		$subpart['###OROW###'] = $rows;

		// last 10
		$marker['###RSS###'] = $this->cObj->typolink('get the last snippets as RSS-feed'.$this->icon['rss'],array('parameter' => $this->pid . ',112'));
		$subpart['###ROW###'] = $this->lastSnippets(10, $rowSubpart);


		$marker['###NEW###'] = $this->pi_linkTP('new snippet',array($this->prefixId.'[new]'=>1),1);
		$marker['###SEARCH###'] = $this->pi_linkTP('search',array($this->prefixId.'[search]'=>1),1);
		$marker['###TAGS###'] = $this->tagCloud();
		$marker['###LANGUAGES###'] = $this->langCloud();

		return $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);


	}

	function rssView() {
		$totalSubpart = $this->cObj->getSubpart($this->template, '###RSSFEED###');
		$rowSubpart = $this->cObj->getSubpart($totalSubpart, '###CONTENT###');

		$marker['###SITE_TITLE###'] = 'Snippets on support.typo3.org';
		$marker['###SITE_LINK###'] = 'http://support.typo3.org/snippets/';
		$marker['###SITE_DESCRIPTION###'] = 'snippets';
		$marker['###NEWS_COPYRIGHT###'] = '';
		$marker['###NEWS_WEBMASTER###'] = 'Steffen Kamper';
		$marker['###NEWS_LASTBUILD###'] = date('Y-m-d');

		$subpart['###CONTENT###'] = $this->lastSnippets(20, $rowSubpart);
		$content = $this->cObj->substituteMarkerArrayCached($totalSubpart, $marker, $subpart);

		return $content;
	}

	function tagCloud() {
		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			'pid=' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code')
		);
		$max = 0;
		$t = array();
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['tags']) {
				$tags = t3lib_div::trimExplode(',', $row['tags']);
				foreach ($tags as $tag) {
					$t[$tag]++;
					$max = $t[$tag]>$max ? $t[$tag] : $max;
				}
			}
		}

		$q = floor($max/8);
		foreach ($t as $tag => $count) {
			if ($count > intval($this->conf['tagsMinCount'])) {
				$class = floor($count/$q);
				if ($class > 8) $class = 8;
				$taglinks[] = $this->pi_linkTP('<span class="tag-' . $class . '">' . htmlspecialchars($tag) . '</span>', array(
					$this->prefixId.'[tag]' => urlencode($tag),
				), 1,  $this->conf['snippetPid']);
			}
		}

		return implode(' ', $taglinks);
	}

	function lastSnippets($count, $subPart) {
		// last 10

		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			$where_clause = 'pid=' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code'),
			$groupBy='',
			$orderBy='crdate desc',
			$limit=intval($count)
		);


		$rows = '';
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$l = $this->pi_linkTP('',
				array(
					$this->prefixId.'[code]' => $row['uid'],
				), 1,  $this->conf['snippetPid']
			);
			$marker['###HREF###'] = $this->cObj->lastTypoLinkUrl;
			$marker['###HREFRSS###'] = 'snippets/c/'.$row['uid'].'/';
			$marker['###BASEURL###'] = $this->conf['baseURL'];

			$marker['###TITLE###'] = $row['title'];
			$marker['###POSTER###'] = $row['poster'];
			$marker['###DATE###'] = date('Y-m-d', $row['crdate']);
			$marker['###LANG###'] = $row['language'];
			$marker['###DESCRIPTION###'] = htmlspecialchars($row['description']);
			$this->markerHook($marker, $row);
			$rows .= $this->cObj->substituteMarkerArrayCached($subPart, $marker);
		}
		return $rows;
	}

	function getTags() {
		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			$where_clause = 'pid=' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code')
		);
		$max = 0;
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			if ($row['tags']) {
				$tags = t3lib_div::trimExplode(',', $row['tags']);
				foreach ($tags as $tag) {
					$t[] = $tag;
				}
			}
		}
		$t = array_unique($t);
		sort($t);
		$options[] = '<option value=""></option>';
		foreach ($t as $tag) {
			$options[] = '<option value="' . htmlspecialchars($tag) . '" onclick="addTag(\'' . htmlspecialchars($tag) . '\');">' . htmlspecialchars($tag) . '</option>';
		}

		return implode("", $options);
	}

	function langCloud() {
		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'tx_pastecode_code',
			$where_clause = 'pid=' . $this->storagePid . $this->cObj->enableFields('tx_pastecode_code')
		);
		$t = array();
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$t[$row['language']]++;
		}
		#sort($t);

		foreach ($t as $lang => $count) {
			$langlinks[] = '<li>' . $this->pi_linkTP(htmlspecialchars($lang) . ' (' . $count . ')', array(
				$this->prefixId . '[language]' => urlencode($lang),
			), 1,  $this->conf['snippetPid']) . '</li>';
		}
		return implode(' ', $langlinks);
	}


	function highLight($code, $language) {
		$geshi = new GeSHi($code,$language,'');
		$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS,2);
		$geshi->set_line_style('background: #fcfcfc;', 'background: #fdfdfd;');
		$geshi->enable_classes(true);
		$geshi->set_overall_id('pastecode-code-c') . $this->cObj->data['uid'];
		$GLOBALS['TSFE']->additionalCSS[] = $geshi->get_stylesheet();
		return $geshi->parse_code();
	}

	function languageSelect() {
		$optGroup = false;
		$options = '';
		foreach($this->languages as $lang) {
			if(substr($lang,0,7) == '--div--') {
				$og = explode(';', $lang);
				$options .= '<optgroup' . ($og[1] ? ' label="' . $og[1] . '"' : '') . '>';
				$optGroup = true;
			} else {
				$options .= '<option value="' . $lang . '"' . ($lang==$this->piVars['language'] ? ' selected="selected"' : '') . '>' . $lang . '</option>';
			}
		}
		if ($optGroup) {
			$options .= '</optgroup>';
		}
		return $options;
	}

	function getTop25() {
		$result = array();
		$res=$GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'rating, vote_count, (rating/vote_count) q, substring(reference,17) sid',
			'tx_ratings_data',
			$where_clause = 'vote_count>0 and left(reference,16) = "tx_pastecode_pi1"',
			'',
			'q desc',
			'25'
		);
		while($row=$GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
			$result['ids'][] = $row['sid'];
			$result['top'][] = $row;
		}
		$result['idlist'] = implode(',', $result['ids']);

		return $result;
	}

	function clearSpecificCache($pid, $cHash=false) {
		if(is_array($pid)) {
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_pages', 'page_id IN (' . implode(',', $pid) . ')');
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_pagesection', 'page_id IN (' . implode(',', $pid) .')');
		} else {
			$addWhere = $cHash ? ' and cHash = "' . $cHash . '"' : '';
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_pages', 'page_id = ' . $pid . $addWhere);
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('cache_pagesection', 'page_id = ' . $pid . $addWhere);
		}
	}

	function markerHook(&$marker, $row) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pastecode']['markerHook'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['pastecode']['markerHook'] as $_classRef) {
				$_procObj = & t3lib_div::getUserObj($_classRef);
				$marker = $_procObj->pastecodeMarkerProcessor($marker, $row, $this);
			}
		}
	}

}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pastecode/pi1/class.tx_pastecode_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pastecode/pi1/class.tx_pastecode_pi1.php']);
}

?>
