<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/lead-matching-tool
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

declare(strict_types=1);

namespace ContaoEstateManager\LeadMatchingToolOnOffice\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use ContaoEstateManager\LeadMatchingTool\EstateManagerLeadMatchingTool;
use ContaoEstateManager\LeadMatchingToolOnOffice\EstateManagerLeadMatchingToolOnOffice;

class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(EstateManagerLeadMatchingToolOnOffice::class)
                ->setLoadAfter([EstateManagerLeadMatchingTool::class])
                ->setReplace(['estatemanagerleadmatchingtoolonoffice']),
        ];
    }
}
