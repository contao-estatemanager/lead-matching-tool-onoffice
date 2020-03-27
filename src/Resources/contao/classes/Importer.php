<?php


namespace ContaoEstateManager\LeadMatchingToolOnOffice;

use Contao\Database;
use Oveleon\ContaoOnofficeApiBundle\OnOfficeRead;
use ContaoEstateManager\LeadMatchingTool\SearchcriteriaModel;
use ContaoEstateManager\RegionEntity\Region;
use ContaoEstateManager\RegionEntity\RegionModel;
use ContaoEstateManager\RegionEntity\RegionConnectionModel;
use ContaoEstateManager\ObjectTypeEntity\ObjectType;
use ContaoEstateManager\ObjectTypeEntity\ObjectTypeModel;
use ContaoEstateManager\ObjectTypeEntity\ObjectTypeConnectionModel;
use Symfony\Component\HttpFoundation\Response;

class Importer
{
    /**
     * Max results per call
     * @var int
     */
    public static $limit = 500;

    /**
     * Import from server cron job
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

        // get database instance
        $objDatabase = Database::getInstance();

        // truncate data
        $objDatabase->prepare('TRUNCATE TABLE tl_searchcriteria')->execute();
        $objDatabase->prepare('DELETE FROM tl_region_connection WHERE ptable="tl_searchcriteria"')->execute();
        $objDatabase->prepare('DELETE FROM tl_object_type_connection WHERE ptable="tl_searchcriteria"')->execute();

        // request new data
        $arrInquiriesBuy = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'kauf']], 0);
        $arrInquiriesRent = Importer::getSearchInquiries(['searchdata' => ['vermarktungsart' => 'miete']], 0);

        if(isset($arrInquiriesBuy))
        {
            for($k=0; $k <= $arrInquiriesBuy['data']['meta']['cntabsolute'];)
            {
                Importer::import('kauf', $k);
                $k = $k + static::$limit;
            }
        }

        if(isset($arrInquiriesRent))
        {
            for($m=0; $m <= $arrInquiriesRent['data']['meta']['cntabsolute'];)
            {
                Importer::import('miete', $k);
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
     *
     * @return string
     */
    public static function import($marketing, $offset)
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
                    $record->price_from = $arrData['kaufpreis__von'];
                    $record->price_to   = $arrData['kaufpreis__bis'];

                    // import region if regionaler_zusatz exists and write range fields into the record
                    $record->latitude    = $arrData['range_breitengrad'];
                    $record->longitude   = $arrData['range_laengengrad'];
                    $record->postalcode  = $arrData['range_plz'];
                    $record->city        = $arrData['range_ort'];
                    $record->country     = $arrData['range_land'];
                    $record->range       = $arrData['range'];

                    if(is_array($arrData['regionaler_zusatz']))
                    {
                        $arrRegions = [];

                        // ToDo: Perfocmance verbessern
                        // ToDo: DB Indexe setzen
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

                    if($arrData['objektart'])
                    {
                        if($objecttype = ObjectTypeModel::findOneBy('oid', $arrData['objektart']))
                        {
                            // delete previous connections
                            ObjectTypeConnectionModel::deleteByPidAndPtable($record->id, 'tl_searchcriteria');

                            // save new connections
                            ObjectType::saveConnectionRecord($objecttype->id, $record->id, 'tl_searchcriteria');

                            $record->objectTypes = serialize([$objecttype->id]);
                        }
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
}