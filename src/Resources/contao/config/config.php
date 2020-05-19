<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/lead-matching-tool
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

if(ContaoEstateManager\LeadMatchingTool\AddonManager::valid()) {
    // Hooks
    $GLOBALS['TL_HOOKS']['countLeadMatching'][]      = array('ContaoEstateManager\LeadMatchingToolOnOffice\LeadMatching', 'onLoadCount');
    $GLOBALS['TL_HOOKS']['fetchLeadMatching'][]      = array('ContaoEstateManager\LeadMatchingToolOnOffice\LeadMatching', 'fetch');
    $GLOBALS['TL_HOOKS']['parseLeadMatchingItems'][] = array('ContaoEstateManager\LeadMatchingToolOnOffice\LeadMatching', 'parseItems');
    $GLOBALS['TL_HOOKS']['readCountLeadMatching'][]  = array('ContaoEstateManager\LeadMatchingToolOnOffice\LeadMatching', 'onReadCount');

    // Back end modules
    $GLOBALS['BE_MOD']['real_estate']['searchcriteria']['importSearchInquiries'] = array('ContaoEstateManager\LeadMatchingToolOnOffice\ImportSearchInquiries', 'importInquiries');
}
