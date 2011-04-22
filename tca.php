<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

$TCA['tx_pastecode_code'] = array (
	'ctrl' => $TCA['tx_pastecode_code']['ctrl'],
	'interface' => array (
		'showRecordFieldList' => 'hidden,title,language,poster,description,code,tags'
	),
	'feInterface' => $TCA['tx_pastecode_code']['feInterface'],
	'columns' => array (
		'hidden' => array (
			'exclude' => 1,
			'label'   => 'LLL:EXT:lang/locallang_general.xml:LGL.hidden',
			'config'  => array (
				'type'    => 'check',
				'default' => '0'
			)
		),
		'title' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.title',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'language' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.language',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'poster' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.poster',
			'config' => array (
				'type' => 'input',
				'size' => '30',
			)
		),
		'description' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.description',
            'config' => array (
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            )
        ),
        'code' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.code',
			'config' => array (
				'type' => 'text',
				'cols' => '30',
				'rows' => '5',
			)
		),
		'tags' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.tags',
            'config' => array (
                'type' => 'text',
                'cols' => '30',
                'rows' => '5',
            )
        ),
        'problem' => array (
			'exclude' => 0,
			'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.problem',
			'config' => array (
				'type' => 'check',
			)
		),
		'links' => array (
            'exclude' => 0,
            'label' => 'LLL:EXT:pastecode/locallang_db.xml:tx_pastecode_code.links',
            'config' => array (
                'type' => 'input',
            )
        ),
	),
	'types' => array (
		'0' => array('showitem' => 'hidden;;1;;1-1-1, title;;;;2-2-2, language;;;;3-3-3, poster, description, links, problem, code, tags')
	),
	'palettes' => array (
		'1' => array('showitem' => '')
	)
);
?>
