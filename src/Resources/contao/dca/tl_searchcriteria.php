<?php

$GLOBALS['TL_DCA']['tl_searchcriteria']['list']['global_operations']['importSearchInquiries'] = array(
    'href'                => 'key=importSearchInquiries',
    'class'               => 'header_theme_import'
);

$GLOBALS['TL_DCA']['tl_searchcriteria']['fields']['oid'] = array
(
    'inputType'               => 'text',
    'eval'                    => array('maxlength'=>255, 'tl_class'=>'w50'),
    'sql'                     => "int(10) unsigned NOT NULL default 0"
);

$GLOBALS['TL_DCA']['tl_searchcriteria']['fields']['adresse'] = array
(
    'inputType'               => 'text',
    'eval'                    => array('maxlength'=>255, 'tl_class'=>'w50'),
    'sql'                     => "varchar(255) NOT NULL default ''"
);