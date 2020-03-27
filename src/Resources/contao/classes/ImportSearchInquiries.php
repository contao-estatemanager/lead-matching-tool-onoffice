<?php

namespace ContaoEstateManager\LeadMatchingToolOnOffice;

class ImportSearchInquiries extends \Backend
{
    /**
     * Import onoffice search criteria
     */
    public function importInquiries()
    {
        // handle import
        if(\Input::get('step') === 'call')
        {
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

            return Importer::import(\Input::get('marketing'), \Input::get('offset'));
        }

        // create backend template
        $objTemplate = new \BackendTemplate('be_sync_onoffice_inquiries');

        // show queue
        if(\Input::get('step') === 'start')
        {
            switch (\Input::get('data'))
            {
                case 'kauf':
                    $arrInquiriesBuy = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'kauf']], 0);
                    break;
                case 'miete':
                    $arrInquiriesRent = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'miete']], 0);
                    break;
                default:
                    $arrInquiriesBuy = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'kauf']], 0);
                    $arrInquiriesRent = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'miete']], 0);
            }

            $strBuffer = '';

            // Buy queue
            if(isset($arrInquiriesBuy))
            {
                $strBuffer .= '<br/><h3>' . $GLOBALS['TL_LANG']['tl_searchcriteria']['buy'] . '</h3>';

                for($k=0; $k <= $arrInquiriesBuy['data']['meta']['cntabsolute'];)
                {
                    $strBuffer .= '<span class="call" data-url="' . str_replace('&step=start', '', \Environment::get('uri')) . '&step=call&marketing=kauf&offset=' . $k . '">Call [' . $k . ' - ' . ($k + Importer::$limit) . ']</span><br>';

                    $k = $k + Importer::$limit;
                }
            }

            // Rent queue
            if(isset($arrInquiriesRent))
            {
                $strBuffer .= '<br/><h3>' . $GLOBALS['TL_LANG']['tl_searchcriteria']['rent'] . '</h3>';

                for($m=0; $m <= $arrInquiriesRent['data']['meta']['cntabsolute'];)
                {
                    $strBuffer .= '<span class="call" data-url="' . str_replace('&step=start', '', \Environment::get('uri')) . '&step=call&marketing=miete&offset=' . $m . '">Call [' . $m . ' - ' . ($m + Importer::$limit) . ']</span><br>';

                    $m = $m + Importer::$limit;
                }
            }

            if(\Input::get('truncate'))
            {
                $this->Database->prepare('TRUNCATE TABLE tl_searchcriteria')->execute();
                $this->Database->prepare('DELETE FROM tl_region_connection WHERE ptable="tl_searchcriteria"')->execute();
                $this->Database->prepare('DELETE FROM tl_object_type_connection WHERE ptable="tl_searchcriteria"')->execute();

                \Message::addConfirmation($GLOBALS['TL_LANG']['tl_searchcriteria']['deleteConfirm']);
            }

            $objTemplate->content = $strBuffer;
            $objTemplate->indexContinue = $GLOBALS['TL_LANG']['tl_searchcriteria']['indexContinue'];
            $objTemplate->isRunning = true;
        }

        // show overview
        else
        {
            \Message::addInfo($GLOBALS['TL_LANG']['tl_searchcriteria']['importConfirm']);

            $objTemplate->indexSubmit = $GLOBALS['TL_LANG']['tl_searchcriteria']['importSearchInquiries'][0];
            $objTemplate->indexLabel = $GLOBALS['TL_LANG']['tl_searchcriteria']['data'];
            $objTemplate->indexTruncate = $GLOBALS['TL_LANG']['tl_searchcriteria']['truncate'][0];
            $objTemplate->indexTruncateDescription = $GLOBALS['TL_LANG']['tl_searchcriteria']['truncate'][1];

            $objTemplate->backTitle = \StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']);
            $objTemplate->backName = $GLOBALS['TL_LANG']['MSC']['backBT'];
            $objTemplate->backLink = ampersand(str_replace('&key=importSearchInquiries', '', \Environment::get('request')));

            $objTemplate->options = [
                ''      => $GLOBALS['TL_LANG']['tl_searchcriteria']['all'],
                'kauf'  => $GLOBALS['TL_LANG']['tl_searchcriteria']['buy'],
                'miete' => $GLOBALS['TL_LANG']['tl_searchcriteria']['rent'],
            ];
        }

        $objTemplate->loading = $GLOBALS['TL_LANG']['tl_searchcriteria']['indexLoading'];
        $objTemplate->complete = $GLOBALS['TL_LANG']['tl_searchcriteria']['indexComplete'];
        $objTemplate->message = \Message::generate();
        $objTemplate->action = ampersand(\Environment::get('request'));

        return $objTemplate->parse();
    }
}
