<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/lead-matching-tool
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

// Global operations
$GLOBALS['TL_DCA']['tl_searchcriteria']['list']['global_operations']['importSearchInquiries'] = array('href'=>'key=importSearchInquiries', 'class'=>'header_theme_import');

// Add fields
$GLOBALS['TL_DCA']['tl_searchcriteria']['fields']['oid'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_searchcriteria']['oid'],
    'exclude'          => true,
    'inputType'        => 'text',
    'eval'             => array('maxlength'=>255, 'tl_class'=>'w50'),
    'sql'              => "int(10) unsigned NOT NULL default 0"
);

$GLOBALS['TL_DCA']['tl_searchcriteria']['fields']['adresse'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_searchcriteria']['adresse'],
    'exclude'          => true,
    'inputType'        => 'text',
    'eval'             => array('maxlength'=>255, 'tl_class'=>'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_searchcriteria']['fields']['updated'] = array
(
    'sql'              => "int(10) unsigned NOT NULL default 0"
);