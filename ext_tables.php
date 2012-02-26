<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

// TypoScript config
t3lib_extMgm::addStaticFile($_EXTKEY, 'pi1/static/', 'Paste Code (Plugin 1)');
t3lib_extMgm::addStaticFile($_EXTKEY, 'pi2/static/', 'Paste Code (Plugin 2)');

// Flexform
$TCA['tt_content']['types']['list']['subtypes_addlist'][$_EXTKEY . '_pi1'] = 'pi_flexform';
t3lib_extMgm::addPiFlexFormValue($_EXTKEY . '_pi1', 'FILE:EXT:' . $_EXTKEY . '/pi1/flexform_ds.xml');

t3lib_extMgm::allowTableOnStandardPages('tx_pastecode_code');

t3lib_extMgm::addToInsertRecords('tx_pastecode_code');

$TCA['tx_pastecode_code'] = array (
	'ctrl' => array (
		'title'     => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code',
		'label'     => 'title',
		'label_alt' => 'language,poster',
		'label_alt_force' => TRUE,
		'tstamp'    => 'tstamp',
		'crdate'    => 'crdate',
		'cruser_id' => 'cruser_id',
		'default_sortby' => "ORDER BY crdate",
		'delete' => 'deleted',
		'enablecolumns' => array (
			'disabled' => 'hidden',
		),
		'dynamicConfigFile' => t3lib_extMgm::extPath($_EXTKEY).'tca.php',
		'iconfile'          => t3lib_extMgm::extRelPath($_EXTKEY).'icon_tx_pastecode_code.gif',
	),
);


t3lib_div::loadTCA('tt_content');
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi1'] = 'layout,select_key,recursive';
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$_EXTKEY.'_pi2'] = 'layout,select_key,recursive';

t3lib_extMgm::addPlugin(array('LLL:EXT:pastecode/locallang_db.xml:tt_content.list_type_pi1', $_EXTKEY . '_pi1'), 'list_type');
t3lib_extMgm::addPlugin(array('LLL:EXT:pastecode/locallang_db.xml:tt_content.list_type_pi2', $_EXTKEY . '_pi2'), 'list_type');
?>
