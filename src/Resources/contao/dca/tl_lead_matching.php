<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/lead-matching-tool
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

$GLOBALS['TL_DCA']['tl_lead_matching']['palettes']['onoffice'] = '{title_legend},title,type;{config_legend},marketingType;{mapping_legend},mapping_marketingType,mapping_objectTypes,mapping_regions,mapping_room,mapping_area,mapping_price_kauf,mapping_price_miete;{field_legend},marketingTypes,addBlankMarketingType,objectTypes,addBlankObjectType,regions,addBlankRegion;{searchcriteria_legend},listMetaFields,txtListHeadline,txtListDescription,numberOfItems,perPage,groupRelatedFields,countResults,listItemTemplate;{estate_form_legend},addEstateForm;{contact_form_legend},addContactForm;';

// Add field options
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['type']['options'][] = 'onoffice';

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['marketingType']['options_callback']  = array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getMarketingTypeFields');
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['marketingTypes']['options_callback'] = array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getMarketingTypeFields');
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['listMetaFields']['options_callback'] = array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaRangeFieldOptions');

// Add field callbacks
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['marketingTypes']['save_callback'][] = array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'saveMarketingTypes');
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['objectTypes']['save_callback'][]    = array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'saveObjectTypes');
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['regions']['save_callback'][]        = array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'saveRegions');

// Add fields
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_marketingType'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_marketingType'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50 clr'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_objectTypes'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_objectTypes'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_regions'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_regions'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_room'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_room'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_area'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_area'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

// ToDo: Create fields dynamic to map any kind of marketing type or use something like multicolumnwizard
$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_price_kauf'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_price_kauf'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mapping_price_miete'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['mapping_price_miete'],
    'inputType'        => 'select',
    'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFieldOptions'),
    'eval'             => array('mandatory'=>true, 'tl_class'=> 'w50'),
    'sql'              => "varchar(255) NOT NULL default ''"
);

$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['groupRelatedFields'] = array(
    'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['groupRelatedFields'],
    'inputType'        => 'checkbox',
    'eval'             => array('tl_class'=>'w50 m12'),
    'sql'              => "char(1) NOT NULL default '1'"
);

/*$GLOBALS['TL_DCA']['tl_lead_matching']['fields']['mappingFields'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_lead_matching']['mappingFields'],
    'inputType' => 'multiColumnWizard',
    'eval'      => [
        'tl_class'     => 'w50 clr',
        'dragAndDrop'  => false,
        'columnFields' => [
            'from' => [
                'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['from'],
                'inputType'        => 'select',
                'options_callback' => array('tl_lead_matching', 'getListMetaFields'),
                'reference'        => &$GLOBALS['TL_LANG']['tl_lead_matching_meta'],
                'eval'             => ['style' => 'width:100%']
            ],
            'to' => [
                'label'            => &$GLOBALS['TL_LANG']['tl_lead_matching']['to'],
                'inputType'        => 'select',
                'options_callback' => array('\\ContaoEstateManager\\LeadMatchingToolOnOffice\\LeadMatching', 'getSearchCriteriaFields'),
                'eval'             => ['style' => 'width:100%']
            ],
        ],
    ],
    'sql'       => 'blob NULL',
);*/
