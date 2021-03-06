<?php

namespace ContaoEstateManager\LeadMatchingToolOnOffice;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\Environment;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;

class ImportSearchInquiries extends Backend
{
    /**
     * Import onoffice search criteria
     */
    public function importInquiries()
    {
        // handle import
        if(Input::get('step') === 'call')
        {
            // increase session lifetime
            @ini_set('session.gc_maxlifetime',  36000);

            // disable execution time
            @ini_set('max_execution_time', 0);

            // Consider the suhosin.memory_limit (see #7035)
            if (\extension_loaded('suhosin'))
            {
                if (($limit = ini_get('suhosin.memory_limit')) !== '')
                {
                    @ini_set('memory_limit', $limit);
                }
            }
            else
            {
                @ini_set('memory_limit', -1);
            }

            return Importer::import(Input::get('marketing'), Input::get('offset'), !!Input::get('regions'));
        }

        // create backend template
        $objTemplate = new BackendTemplate('be_sync_onoffice_inquiries');

        // show queue
        if(Input::get('step') === 'start')
        {
            switch (Input::get('data'))
            {
                case 'kauf':
                    $arrInquiriesBuy = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'kauf']], 0);

                    if(Input::get('truncate'))
                    {
                        $this->Database->prepare('DELETE FROM tl_searchcriteria WHERE marketing="kauf"')->execute();
                    }
                    break;
                case 'miete':
                    $arrInquiriesRent = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'miete']], 0);

                    if(Input::get('truncate'))
                    {
                        $this->Database->prepare('DELETE FROM tl_searchcriteria WHERE marketing="miete"')->execute();
                    }
                    break;
                default:
                    $arrInquiriesBuy = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'kauf']], 0);
                    $arrInquiriesRent = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'miete']], 0);

                    if(Input::get('truncate'))
                    {
                        $this->Database->prepare('TRUNCATE TABLE tl_searchcriteria')->execute();
                    }
            }

            $importRegions = Input::get('regions');
            $strBuffer = '';

            // Buy queue
            if(isset($arrInquiriesBuy))
            {
                $strBuffer .= '<br/><h3>' . $GLOBALS['TL_LANG']['tl_searchcriteria']['buy'] . '</h3>';

                for($k=0; $k <= $arrInquiriesBuy['data']['meta']['cntabsolute'];)
                {
                    $url = str_replace('&step=start', '', Environment::get('uri')) . '&step=call&marketing=kauf&offset=' . $k . '&regions=' . $importRegions;
                    $strBuffer .= '<a href="' . $url . '" target="_blank" class="call" data-url="' . $url . '">Call [' . $k . ' - ' . ($k + Importer::$limit) . ']</a><br>';

                    $k = $k + Importer::$limit;
                }
            }

            // Rent queue
            if(isset($arrInquiriesRent))
            {
                $strBuffer .= '<br/><h3>' . $GLOBALS['TL_LANG']['tl_searchcriteria']['rent'] . '</h3>';

                for($m=0; $m <= $arrInquiriesRent['data']['meta']['cntabsolute'];)
                {
                    $url = str_replace('&step=start', '', Environment::get('uri')) . '&step=call&marketing=miete&offset=' . $m . '&regions=' . $importRegions;
                    $strBuffer .= '<a href="' . $url . '" target="_blank" class="call" data-url="' . $url . '">Call [' . $m . ' - ' . ($m + Importer::$limit) . ']</a><br>';

                    $m = $m + Importer::$limit;
                }
            }

            if(Input::get('truncate'))
            {
                if(!!$importRegions)
                {
                    $this->Database->prepare('DELETE FROM tl_region_connection WHERE ptable="tl_searchcriteria"')->execute();
                }

                Message::addConfirmation($GLOBALS['TL_LANG']['tl_searchcriteria']['deleteConfirm']);
            }

            $objTemplate->content = $strBuffer;
            $objTemplate->indexContinue = $GLOBALS['TL_LANG']['tl_searchcriteria']['indexContinue'];
            $objTemplate->isRunning = true;
        }

        // show overview
        else
        {
            Message::addInfo($GLOBALS['TL_LANG']['tl_searchcriteria']['importConfirm']);

            $objTemplate->indexSubmit = $GLOBALS['TL_LANG']['tl_searchcriteria']['importSearchInquiries'][0];
            $objTemplate->indexLabel = $GLOBALS['TL_LANG']['tl_searchcriteria']['data'];
            $objTemplate->indexTruncate = $GLOBALS['TL_LANG']['tl_searchcriteria']['truncate'][0];
            $objTemplate->indexTruncateDescription = $GLOBALS['TL_LANG']['tl_searchcriteria']['truncate'][1];
            $objTemplate->indexRegions = $GLOBALS['TL_LANG']['tl_searchcriteria']['importRegion'][0];
            $objTemplate->indexRegionsDescription = $GLOBALS['TL_LANG']['tl_searchcriteria']['importRegion'][1];

            $objTemplate->backTitle = StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']);
            $objTemplate->backName = $GLOBALS['TL_LANG']['MSC']['backBT'];
            $objTemplate->backLink = ampersand(str_replace('&key=importSearchInquiries', '', Environment::get('request')));

            $objTemplate->options = [
                ''      => $GLOBALS['TL_LANG']['tl_searchcriteria']['all'],
                'kauf'  => $GLOBALS['TL_LANG']['tl_searchcriteria']['buy'],
                'miete' => $GLOBALS['TL_LANG']['tl_searchcriteria']['rent'],
            ];
        }

        $objTemplate->loading = $GLOBALS['TL_LANG']['tl_searchcriteria']['indexLoading'];
        $objTemplate->complete = $GLOBALS['TL_LANG']['tl_searchcriteria']['indexComplete'];
        $objTemplate->message = Message::generate();
        $objTemplate->action = ampersand(Environment::get('request'));

        return $objTemplate->parse();
    }
}
