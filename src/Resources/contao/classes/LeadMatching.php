<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/lead-matching-tool
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

namespace ContaoEstateManager\LeadMatchingToolOnOffice;

use ContaoEstateManager\ObjectTypeEntity\ObjectTypeModel;
use ContaoEstateManager\RegionEntity\RegionModel;
use Oveleon\ContaoOnofficeApiBundle\OnOfficeRead;
use Oveleon\ContaoOnofficeApiBundle\Fieldset;

class LeadMatching extends \Backend
{
    /**
     * Array with onoffice translation values
     *
     * @var array
     */
    protected $arrTranslations = array();

    /**
     * Return number of items
     *
     * @return int
     */
    public function count()
    {
        $_GET['limit'] = 0;

        $data = $this->call('search', 'searchcriteria');

        if(!$data['status']['errorcode'])
        {
            $intNumber = $data['data']['meta']['cntabsolute'] ?: 0;

            // save in session
            $_SESSION['LEAD_MATCHING']['previousCount'] = $intNumber;

            // return count
            return $intNumber;
        }

        return 0;
    }

    /**
     * Fetch items
     *
     * @param $config
     * @param $limit
     * @param $offset
     * @param $objModule
     *
     * @return array
     */
    public function fetch($config, $limit, $offset, $objModule)
    {
        // prepare parameters
        $_GET['searchdata']   = $this->buildFilterQuery($config, 'session');
        $_GET['limit']        = $limit;
        $_GET['offset']       = $offset;

        $outputFields = \StringUtil::deserialize($config->listMetaFields);

        if($outputFields !== null)
        {
            // add marketing type to determine the correct price
            if(!in_array('vermarktungsart', $outputFields))
            {
                $outputFields[] = 'vermarktungsart';
            }

            $_GET['outputfields'] = $outputFields;
        }
        else
        {
            $_GET['outputall'] = true;
        }

        $data = $this->call('search', 'searchcriteria');

        if(!$data['status']['errorcode'])
        {
            // return records
            return $data['data'];
        }
    }

    /**
     * Parse items
     *
     * @param $arrItems
     * @param $config
     * @param $objModule
     *
     * @return array
     */
    public function parseItems($config, $arrItems, $objModule)
    {
        // get translation values
        $this->arrTranslations = $this->getSearchCriteriaFieldTranslations();

        $limit = $arrItems['meta']['cntabsolute'];

        if ($limit < 1)
        {
            return array();
        }

        $count = 0;
        $arrItemCollection = array();

        foreach ($arrItems['records'] as $item)
        {
            $arrItemCollection[] = $this->parseItem($config, $item,((++$count == 1) ? ' first' : '') . (($count == $limit) ? ' last' : '') . ((($count % 2) == 0) ? ' odd' : ' even'), $count, $objModule);
        }

        return $arrItemCollection;
    }

    /**
     * Parse item
     *
     * @param $config
     * @param $arrItem
     * @param string $strClass
     * @param int $intCount
     * @param $objModule
     *
     * @return string
     */
    private function parseItem($config, $arrItem, $strClass, $intCount, $objModule)
    {
        $objTemplate = new \FrontendTemplate($config->listItemTemplate);
        $objTemplate->setData($arrItem);
        $objTemplate->class = $strClass;

        $arrGroups = array();
        $arrFields = array();
        $listFields = \StringUtil::deserialize($config->listMetaFields);

        foreach ($listFields as $field)
        {
            $suffix   = '';

            $varLabel = $this->translate($field);
            $varValue = $arrItem['elements'][ $field ] ?: null;

            // add label supplement (from / to)
            if(!$config->groupRelatedFields)
            {
                if(strpos($field, '__von') !== false)
                {
                    $varLabel .= ' ' . $GLOBALS['TL_LANG']['tl_lead_matching_meta']['from'];
                }
                elseif(strpos($field, '__bis') !== false)
                {
                    $varLabel .= ' ' . $GLOBALS['TL_LANG']['tl_lead_matching_meta']['to'];
                }
            }

            // skip prices from wrong marketing type
            $marketingType = $arrItem['elements']['vermarktungsart'] ?: $config->marketingType;

            if(
                (strpos($field, 'kaufpreis') === 0 && $marketingType !== 'kauf') ||
                (strpos($field, 'kaltmiete') === 0 && $marketingType !== 'miete')
            )
            {
                continue;
            }

            // prepare values
            if($varValue)
            {
                switch($field)
                {
                    case 'regionaler_zusatz':
                        // ToDo: Get related regions
                        $varValue = $_SESSION['LEAD_MATCHING']['estate']['regions'];
                        break;

                    case 'kaufpreis__von':
                    case 'kaufpreis__bis':
                    case 'kaltmiete__von':
                    case 'kaltmiete__bis':
                        $varValue = number_format($varValue, 0, ',', '.');
                        $suffix   = $GLOBALS['TL_LANG']['tl_lead_matching_meta']['suffix_currency'];
                        break;

                    case 'wohnflaeche__von':
                    case 'wohnflaeche__bis':
                        $varValue = number_format($varValue, 2, ',', '.');
                        $suffix   = $GLOBALS['TL_LANG']['tl_lead_matching_meta']['suffix_area'];
                        break;

                    case 'anzahl_zimmer__von':
                    case 'anzahl_zimmer__bis':
                        $varValue = number_format($varValue, 0, ',', '.');
                        $suffix   = $GLOBALS['TL_LANG']['tl_lead_matching_meta']['suffix_room'];
                        break;

                    default:
                        $varValue = $this->translate($field, $varValue,false);
                }
            }

            if(is_array($varValue))
            {
                // array to readable list
                $varValue = implode(", ", $varValue);
            }

            // group related fields
            $strGroup = !!$config->groupRelatedFields ? str_replace(array('__von', '__bis'),'', $field) : $field;

            // add field
            $arrGroups[ $strGroup ][ $field ] = array(
                'label'  => $varLabel,
                'value'  => $varValue,
                'suffix' => $suffix
            );
        }

        // combine group field
        foreach ($arrGroups as $group => $fields)
        {
            $value = null;
            $first = reset($fields);

            if(count($fields) == 2)
            {
                $from = $this->parseValue($fields[ $group . '__von' ]['value']);
                $to   = $this->parseValue($fields[ $group . '__bis' ]['value']);

                if($from && $to && $from !== $to)
                {
                    $value = sprintf($GLOBALS['TL_LANG']['tl_lead_matching_meta']['from_to_value'], $from, $to);
                }
                elseif($to)
                {
                    $value = sprintf($GLOBALS['TL_LANG']['tl_lead_matching_meta']['to_value'], $to);
                }
                elseif($from)
                {
                    $value = sprintf($GLOBALS['TL_LANG']['tl_lead_matching_meta']['from_value'], $from);
                }
            }
            else
            {
                $value = $this->parseValue($first['value']);
            }

            if(!$value)
            {
                $value = $GLOBALS['TL_LANG']['tl_lead_matching_meta']['emptyField'];
            }
            elseif($first['suffix'])
            {
                $value .= ' ' . $first['suffix'];
            }

            $arrFields[] = array(
                'label'  => $first['label'],
                'value'  => $value,
                'class'  => $group
            );
        }

        $objTemplate->fields = $arrFields;

        return $objTemplate->parse();
    }

    /**
     * Parse and return value
     *
     * @param $varValue
     *
     * @return mixed
     */
    private function parseValue($varValue)
    {
        return $varValue && $varValue !== '0.00' && $varValue !== '0,00' ? $varValue : null;
    }

    /**
     * Translate and return the field name or its values
     *
     * @param $field
     * @param mixed $varValue
     * @param bool $label
     *
     * @return mixed
     */
    private function translate($field, $varValue=null, $label=true)
    {
        $arrTranslation = $this->arrTranslations[ $field ];

        if($this->arrTranslations !== null && isset($arrTranslation) && is_array($arrTranslation))
        {
            // translate field label
            if($label)
            {
                return $arrTranslation['name'];
            }

            // translate all field values
            if(is_array($varValue) && is_array($arrTranslation['values']))
            {
                $arrValues = array();

                foreach ($varValue as $value)
                {
                    if($newValue = $arrTranslation['values'][ $value ])
                    {
                        $arrValues[] = $newValue;
                    }
                    else
                    {
                        $arrValues[] = $GLOBALS['TL_LANG']['tl_lead_matching_meta'][ $value ] ?: $value;
                    }
                }

                return $arrValues;
            }

            // translate field value
            if(is_string($varValue) && is_array($arrTranslation['values']))
            {
                foreach($arrTranslation['values'] as $key => $value)
                {
                    if($key === $varValue)
                    {
                        return $value;
                    }
                }
            }
        }

        // get system translations as fallback
        return $label ? $field : $varValue;
    }

    /**
     * Return an array of marketing types
     *
     * @param $dc
     *
     * @return array
     */
    public function getMarketingTypeFields($dc=null)
    {
        if($dc !== null && $dc->activeRecord->type !== 'onoffice')
        {
           return $this->returnDefaultOptions($dc);
        }

        $return = array();

        $fields = Fieldset::getInstance()->get('searchcriteriafields');

        if($fields !== null)
        {
            foreach ($fields as $index => $fieldset)
            {
                if(isset($fieldset['elements']['name']) && strtolower($fieldset['elements']['name']) == 'kategorie')
                {
                    if(isset($fieldset['elements']['fields']))
                    {
                        foreach ($fieldset['elements']['fields'] as $i => $data)
                        {
                            if($data['id'] === 'vermarktungsart')
                            {
                                $return = $data['values'];
                                break 2;
                            }
                        }
                    }

                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Return an array of search criteria
     *
     * @param null $dc
     * @param bool $range
     * @param bool $returnData
     *
     * @return array
     */
    public function getSearchCriteriaFields($dc=null, $range=true, $returnData=false)
    {
        if($dc !== null && $dc->activeRecord->type !== 'onoffice')
        {
            return $this->returnDefaultOptions($dc, array('tl_lead_matching', 'getListMetaFields'));
        }

        $return = array();

        if(!$returnData)
        {
            $return = array('Id' => 'ID');
        }

        $fields = Fieldset::getInstance()->get('searchcriteriafields');

        if($fields !== null)
        {
            foreach ($fields as $index => $fieldset)
            {
                if(isset($fieldset['elements']['fields']))
                {
                    foreach ($fieldset['elements']['fields'] as $i => $data)
                    {
                        if(isset($data['rangefield']) && $range)
                        {
                            $arrRage = array(
                                '__von' => ' (>)',
                                '__bis' => ' (<)'
                            );

                            foreach ($arrRage as $suffix => $label)
                            {
                                $return[ $data['id'] . $suffix ] = $returnData ? $data : $data['name'] . $label;
                            }
                        }
                        else
                        {
                            $return[ $data['id'] ] = $returnData ? $data : $data['name'];
                        }
                    }
                }
            }
        }

        return $return;
    }

    /**
     * Return an array of search criteria options
     *
     * @param null $dc
     *
     * @return array
     */
    public function getSearchCriteriaFieldOptions($dc=null)
    {
        return $this->getSearchCriteriaFields($dc, false);
    }

    /**
     * Return an array of search criteria range options
     *
     * @param null $dc
     *
     * @return array
     */
    public function getSearchCriteriaRangeFieldOptions($dc=null)
    {
        return $this->getSearchCriteriaFields($dc);
    }

    /**
     * Return an array of search criteria field translations
     *
     * @return array
     */
    private function getSearchCriteriaFieldTranslations()
    {
        return $this->getSearchCriteriaFields(null, true, true);
    }

    /**
     * Save key value set of object types
     *
     * @param $varValue
     * @param $dc
     *
     * @return string
     */
    public function saveObjectTypes($varValue, $dc){
        if(!$varValue || $dc->activeRecord->type !== 'onoffice')
        {
            return $varValue;
        }

        $arrChoosedTypes = \StringUtil::deserialize($varValue);

        if($arrChoosedTypes === null)
        {
            return $varValue;
        }

        $arrColumns = array("id IN('" . implode("','", $arrChoosedTypes) . "')");

        $objObjectTypes = ObjectTypeModel::findBy($arrColumns, array());

        if($objObjectTypes !== null)
        {
            $arrOptions = array();

            while($objObjectTypes->next())
            {
                $arrOptions[ $objObjectTypes->oid ] = $objObjectTypes->title;
            }

            // Store the new object type data
            \Database::getInstance()->prepare("UPDATE tl_lead_matching SET objectTypesData=? WHERE id=?")
                ->execute(serialize($arrOptions), $dc->id);
        }

        return $varValue;
    }

    /**
     * Save key value set of regions
     *
     * @param $varValue
     * @param $dc
     *
     * @return string
     */
    public function saveRegions($varValue, $dc){
        if(!$varValue || $dc->activeRecord->type !== 'onoffice')
        {
            return $varValue;
        }

        $arrChoosedTypes = \StringUtil::deserialize($varValue);

        if($arrChoosedTypes === null)
        {
            return $varValue;
        }

        $arrColumns = array("id IN('" . implode("','", $arrChoosedTypes) . "')");

        $objRegions = RegionModel::findBy($arrColumns, array());

        if($objRegions !== null)
        {
            $arrOptions = array();

            while($objRegions->next())
            {
                $arrOptions[ $objRegions->oid ] = $objRegions->title;
            }

            // Store the new object type data
            \Database::getInstance()->prepare("UPDATE tl_lead_matching SET regionsData=? WHERE id=?")
                ->execute(serialize($arrOptions), $dc->id);
        }

        return $varValue;
    }

    /**
     * Save key value set of marketing types
     *
     * @param $varValue
     * @param $dc
     *
     * @return string
     */
    public function saveMarketingTypes($varValue, $dc){
        if(!$varValue || $dc->activeRecord->type !== 'onoffice')
        {
            return $varValue;
        }

        $arrMarketingTypes = $this->getMarketingTypeFields();
        $arrChoosedTypes   = \StringUtil::deserialize($varValue);

        if($arrChoosedTypes === null)
        {
            return $varValue;
        }

        $arrOptions = array();

        foreach ($arrChoosedTypes as $value)
        {
            $arrOptions[ $value ] = $arrMarketingTypes[ $value ];
        }

        // Store the new object type data
        \Database::getInstance()->prepare("UPDATE tl_lead_matching SET marketingTypesData=? WHERE id=?")
            ->execute(serialize($arrOptions), $dc->id);

        return $varValue;
    }

    /**
     * Set and prepare filter parameter
     *
     * @param $config
     * @param string $method
     *
     * @return array
     */
    private function buildFilterQuery($config, $method='get')
    {
        $return = array();
        $arrMappings = array(
            'objectTypes' => 'mapping_objectTypes',
            'regions'     => 'mapping_regions',
            'room'        => 'mapping_room',
            'area'        => 'mapping_area'
        );

        if(!$config->marketingType)
        {
            $arrMappings = array('marketingType' => 'mapping_marketingType') + $arrMappings;

            if($choosedMarketingType = $this->getFromMethod('marketingType', $method))
            {
                $arrMappings = array('price' => 'mapping_price_' . $choosedMarketingType) + $arrMappings;
            }
        }
        else
        {
            $return[ $config->mapping_marketingType ] = $config->marketingType;
            $arrMappings = array('price' => 'mapping_price_' . $config->marketingType) + $arrMappings;
        }

        foreach ($arrMappings as $from => $to)
        {
            $varValue = $this->getFromMethod($from, $method);

            if($varValue)
            {
                $return[ $config->{$to} ] =  $varValue;
            }
        }

        return $return;
    }

    /**
     * Get value from given method
     *
     * @param $field
     * @param $method
     * @return mixed
     */
    private function getFromMethod($field, $method)
    {
        switch ($method)
        {
            case 'get':
            case 'post':
                return \Input::$method($field);
            case 'session':
                return $_SESSION['LEAD_MATCHING']['estate'][$field];
        }
    }

    /**
     * Return number of items on load
     *
     * @param $config
     * @param $objModule
     *
     * @return int
     */
    public function onLoadCount($config, $objModule)
    {
        // return value from session on form submit
        if (\Input::post('FORM_SUBMIT') == 'form_estate_' . $objModule->id && isset($_SESSION['LEAD_MATCHING']['previousCount']))
        {
            return $_SESSION['LEAD_MATCHING']['previousCount'];
        }

        $_GET['searchdata'] = $this->buildFilterQuery($config, 'session');

        return $this->count();
    }

    /**
     * Return number of items from read controller
     *
     * @param $config
     * @param $currParam
     * @param $objController
     *
     * @return int
     */
    public function onReadCount($config, $currParam, $objController)
    {
        $_GET['searchdata'] = $this->buildFilterQuery($config);

        return $this->count();
    }

    /**
     * Call onOffice API and return data
     *
     * @param $module
     * @param $id
     *
     * @return mixed
     */
    private function call($module, $id)
    {
        $controller = new OnOfficeRead();
        return $controller->run($module, $id, null, array(), true);
    }

    /**
     * Return the default options
     *
     * @param $dc
     * @param null $callback
     *
     * @return array|string
     */
    private function returnDefaultOptions($dc, $callback=null){
        $field = $GLOBALS['TL_DCA'][ $dc->table ]['fields'][ $dc->field ];

        if(isset($field['options']))
        {
            return $field['options'];
        }
        elseif($callback !== null)
        {
            $this->import($callback[0]);
            return $this->{$callback[0]}->{$callback[1]}($dc);
        }

        return array();
    }
}
