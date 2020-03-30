<?php


namespace ContaoEstateManager\LeadMatchingToolOnOffice;

use Contao\Database;
use Oveleon\ContaoOnofficeApiBundle\OnOfficeRead;
use ContaoEstateManager\LeadMatchingTool\SearchcriteriaModel;
use ContaoEstateManager\RegionEntity\Region;
use ContaoEstateManager\RegionEntity\RegionModel;
use ContaoEstateManager\RegionEntity\RegionConnectionModel;
use ContaoEstateManager\ObjectTypeEntity\ObjectTypeModel;
use Symfony\Component\HttpFoundation\Response;

class Importer
{
    /**
     * Max results per call
     * @var int
     */
    public static $limit = 500;

    /**
     * Create new searchcriteria
     */
    public static function cronCreate()
    {
        // get database instance
        $objDatabase = Database::getInstance();

        $objLastInquiry = $objDatabase->execute('SELECT MAX(oid) as oid FROM tl_searchcriteria');

        if($objLastInquiry)
        {
            $nextId = $objLastInquiry->oid + 2;
            $arrNewInquiry = Importer::getSearchInquiry($nextId);

            if(!isset($arrNewInquiry['data']['records'][0]['elements']))
            {
                $nextId = $objLastInquiry->oid + 1;
                $arrNewInquiry = Importer::getSearchInquiry($nextId);
            }

            if(!isset($arrNewInquiry['data']['records'][0]['elements']))
            {
                $nextId = $objLastInquiry->oid + 3;
                $arrNewInquiry = Importer::getSearchInquiry($nextId);
            }

            if(!isset($arrNewInquiry['data']['records'][0]['elements']))
            {
                return Response('Create Search Criteria: No new records found');
            }

            $arrInquiry = $arrNewInquiry['data']['records'][0]['elements'];

            // prepare object types
            $objObjectTypes = ObjectTypeModel::findAll();
            $arrObjectTypes = [];

            if($objObjectTypes !== null)
            {
                while($objObjectTypes->next())
                {
                    $arrObjectTypes[ $objObjectTypes->oid ] = $objObjectTypes->id;
                }
            }

            // create new search criteria
            $record = new SearchcriteriaModel();
            $record->id = $objDatabase->getNextId('tl_searchcriteria');
            $record->oid = $nextId;

            // geo
            if(isset($arrInquiry['Umkreis']))
            {
                $record->latitude    = $arrInquiry['Umkreis']['range_breitengrad'];
                $record->longitude   = $arrInquiry['Umkreis']['range_laengengrad'];
                $record->postalcode  = $arrInquiry['Umkreis']['range_plz'];
                $record->city        = $arrInquiry['Umkreis']['range_ort'];
                $record->country     = $arrInquiry['Umkreis']['range_land'];
                $record->range       = $arrInquiry['Umkreis']['range'];
            }

            // marketing
            if(isset($arrInquiry['vermarktungsart']))
            {
                $record->marketing  = $arrInquiry['vermarktungsart'][0];
            }

            // object types
            if(isset($arrInquiry['objektart']))
            {
                $record->objectType  = $arrObjectTypes[ $arrInquiry['objektart'][0] ];
            }

            // prices
            if(isset($arrInquiry['range_kaufpreis']))
            {
                $record->price_from = $arrInquiry['range_kaufpreis'][0];
                $record->price_to   = $arrInquiry['range_kaufpreis'][1];
            }

            if(isset($arrInquiry['range_kaltmiete']))
            {
                $record->price_from = $arrInquiry['range_kaltmiete'][0];
                $record->price_to   = $arrInquiry['range_kaltmiete'][1];
            }

            // area
            if(isset($arrInquiry['range_wohnflaeche']))
            {
                $record->area_from  = $arrInquiry['range_wohnflaeche'][0];
                $record->area_to    = $arrInquiry['range_wohnflaeche'][1];
            }

            // room
            if(isset($arrInquiry['range_anzahl_zimmer']))
            {
                $record->room_from  = $arrInquiry['range_anzahl_zimmer'][0];
                $record->room_to    = $arrInquiry['range_anzahl_zimmer'][1];
            }

            // meta data
            $record->adresse    = $arrInquiry['_meta']['internaladdressid'];
            $record->published  = 1;
            $record->tstamp     = time();

            // save
            $record->save();
        }

        return new Response('<p>Record with the ID ' . $nextId . ' was created:<p/><pre>' . json_encode($arrNewInquiry['data']['records'][0]) . '</pre>');
    }

    /**
     * Create new searchcriteria
     */
    public static function cronDelete()
    {


        return new Response('Delete Search Criteria: OK');
    }

    /**
     * Import from cron job
     */
    public static function cronImport()
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

        $importRegions = !!\Input::get('regions');
        $truncate = !!\Input::get('truncate');

        // get database instance
        $objDatabase = Database::getInstance();

        // truncate data
        if($truncate)
        {
            $objDatabase->prepare('TRUNCATE TABLE tl_searchcriteria')->execute();

            if($importRegions)
            {
                $objDatabase->prepare('DELETE FROM tl_region_connection WHERE ptable="tl_searchcriteria"')->execute();
            }
        }

        // request new data
        $arrInquiriesBuy = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'kauf']], 0);
        $arrInquiriesRent = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'miete']], 0);

        if(isset($arrInquiriesBuy))
        {
            for($k=0; $k <= $arrInquiriesBuy['data']['meta']['cntabsolute'];)
            {
                Importer::import('kauf', $k, !!\Input::get('regions'));
                $k = $k + static::$limit;
            }
        }

        if(isset($arrInquiriesRent))
        {
            for($m=0; $m <= $arrInquiriesRent['data']['meta']['cntabsolute'];)
            {
                Importer::import('miete', $k, !!\Input::get('regions'));
                $m = $m + static::$limit;
            }
        }

        return new Response('SearchCriteria import: OK');
    }

    /**
     * Import part of onoffice data
     *
     * @param $marketing
     * @param $offset
     * @param bool $importRegionConnections
     *
     * @return string
     */
    public static function import($marketing, $offset, $importRegionConnections=false)
    {
        // get database instance
        $objDatabase = Database::getInstance();

        // start time tracking for the onoffice request
        $onOfficeTimeStart = time();

        // request onoffice api
        $arrInquiries = static::getSearchInquiries([
            'searchdata' => [
                'vermarktungsart' => $marketing
            ],
            'outputall'  => 1
        ], static::$limit, $offset);

        // parse time tracking for the onoffice request
        $onOfficeTimeDiff = time() - $onOfficeTimeStart;
        $onOfficeLog      = floor($onOfficeTimeDiff / 60) . ':' . $onOfficeTimeDiff % 60 . ' min.';

        // start time tracking for parsing of the data and saving into database
        $importTimeStart  = time();

        // import data
        if(count($arrInquiries['data']['records']))
        {
            // prepare object types
            $objObjectTypes = ObjectTypeModel::findAll();
            $arrObjectTypes = [];

            if($objObjectTypes !== null)
            {
                while($objObjectTypes->next())
                {
                    $arrObjectTypes[ $objObjectTypes->oid ] = $objObjectTypes->id;
                }
            }

            // begin database transaction
            $objDatabase->beginTransaction();

            foreach ($arrInquiries['data']['records'] as $inquiry)
            {
                if(!$record = SearchcriteriaModel::findOneBy('oid', $inquiry['id']))
                {
                    $record = new SearchcriteriaModel();
                    $record->id = $objDatabase->getNextId('tl_searchcriteria');
                    $record->oid = $inquiry['id'];
                }

                if(is_array($inquiry['elements']) && count($inquiry['elements']))
                {
                    $arrData = $inquiry['elements'];

                    $record->adresse    = $arrData['adresse'];
                    $record->marketing  = $arrData['vermarktungsart'];
                    $record->area_from  = $arrData['wohnflaeche__von'];
                    $record->area_to    = $arrData['wohnflaeche__bis'];
                    $record->room_from  = $arrData['anzahl_zimmer__von'];
                    $record->room_to    = $arrData['anzahl_zimmer__bis'];

                    if($marketing === 'miete')
                    {
                        $record->price_from = $arrData['kaltmiete__von'];
                        $record->price_to   = $arrData['kaltmiete__bis'];
                    }else{
                        $record->price_from = $arrData['kaufpreis__von'];
                        $record->price_to   = $arrData['kaufpreis__bis'];
                    }

                    // import region if regionaler_zusatz exists and write range fields into the record
                    $record->latitude    = $arrData['range_breitengrad'];
                    $record->longitude   = $arrData['range_laengengrad'];
                    $record->postalcode  = $arrData['range_plz'];
                    $record->city        = $arrData['range_ort'];
                    $record->country     = $arrData['range_land'];
                    $record->range       = $arrData['range'];

                    // set region connection
                    if($importRegionConnections && is_array($arrData['regionaler_zusatz']))
                    {
                        $arrRegions = [];

                        foreach ($arrData['regionaler_zusatz'] as $regionKey)
                        {
                            if($region = RegionModel::findOneBy('oid', $regionKey))
                            {
                                // delete previous connections
                                RegionConnectionModel::deleteByPidAndPtable($record->id, 'tl_searchcriteria');

                                // save new connections
                                Region::saveConnectionRecord($region->id, $record->id, 'tl_searchcriteria');

                                // push id to array for region picker
                                $arrRegions[] = $region->id;
                            }
                        }

                        $record->regions = serialize($arrRegions);
                    }

                    // set object type
                    if(isset($arrObjectTypes[ $arrData['objektart'] ]))
                    {
                        $record->objectType = $arrObjectTypes[ $arrData['objektart'] ];
                    }

                }

                $record->published = 1;
                $record->tstamp = time();
                $record->save();
            }

            $objDatabase->commitTransaction();
        }

        // parse import time tracking
        $importTimeDiff = time() - $importTimeStart;
        $importLog      = floor($importTimeDiff / 60) . ':' . $importTimeDiff % 60 . ' min.';

        // return status and time
        return sprintf("Request onOffice: %s<br/>Parse and import data: %s", $onOfficeLog, $importLog);
    }

    /**
     * Call onOffice API and return data
     *
     * @param array $param
     * @param int $limit
     * @param int $offset
     *
     * @return mixed
     */
    public static function getSearchInquiries($param, $limit, $offset=0)
    {
        $param['limit'] = $limit;

        if($offset)
        {
            $param['offset'] = $offset;
        }

        $controller = new OnOfficeRead();
        return $controller->run('search', 'searchcriteria', null, $param, true);
    }

    /**
     * Call onOffice API and return data

     * @param $id
     *
     * @return mixed
     */
    public static function getSearchInquiry($id)
    {
        $controller = new OnOfficeRead();
        return $controller->run('searchcriterias', null, null, ['mode' => 'searchcriteria', 'ids' => [$id]], true);
    }
}