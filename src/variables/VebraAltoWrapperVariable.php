<?php

/**
 * @copyright Copyright (c) PutYourLightsOn
 */

namespace jegardvebra\vebraaltowrapper\variables;

use Craft;
use jegardvebra\vebraaltowrapper\VebraAltoWrapper;

use craft\elements\db\EntryQuery;

use jegardvebra\vebraaltowrapper\models\LinkModel;

/**
 * Entry Count Variable
 */
class VebraAltoWrapperVariable
{
    public function getAllLinkModels()
    {
        return VebraAltoWrapper::getInstance()->vebraAlto->getAllLinkModels();
    }
    public function getLinkByField($sectionId, $fieldHandle)
    {
        $linkModel = VebraAltoWrapper::getInstance()->vebraAlto->getFieldMapping($sectionId);
        $fieldMapping = (array) json_decode($linkModel->fieldMapping);
        if (array_key_exists($fieldHandle, $fieldMapping)) {
            return $fieldMapping[$fieldHandle];
        } else {
            return '';
        }
    }
    public function getSchema()
    {
        return array(
            '' => 'Dont import',
            //'reference,agents' => 'reference,agents',

            'LetOrSale(category)' => 'LetOrSale(category)',
            'images' => 'images',

            'pdf' => 'pdf',

            'parish' => 'parish',

            'measurements' => 'measurements',
            'paragraphs' => 'paragraphs',

            'reference,software' => 'reference,software',
            'address,name' => 'address,name',
            'address,street' => 'address,street',
            'address,locality' => 'address,locality',
            'address,town' => 'address,town',
            'address,county' => 'address,county',
            'address,postcode' => 'address,postcode',
            'address,custom_location' => 'address,custom_location',
            'address,display' => 'address,display',
            'price' => 'price',
            'rentalfees' => 'rentalfees',
            'rm_qualifier' => 'rm_qualifier',
            'available' => 'available',
            'uploaded' => 'uploaded',
            'longitude' => 'longitude',
            'latitude' => 'latitude',

            'easting' => 'easting',
            'northing' => 'northing',
            'web_status' => 'web_status',
            'custom_status' => 'custom_status',
            'comm_rent' => 'comm_rent',
            'premium' => 'premium',
            'service_charge' => 'service_charge',

            'type,0' => 'type,0',
            'type,1' => 'type,1',

            'furnished' => 'furnished',
            'rm_type' => 'rm_type',
            'let_bond' => 'let_bond',
            'rm_let_type_id' => 'rm_let_type_id',
            'bedrooms' => 'bedrooms',
            'receptions' => 'receptions',
            'bathrooms' => 'bathrooms',
            'userfield1' => 'userfield1',
            'Parking -> userfield2' => 'userfield2',
            'solddate' => 'solddate',
            'leaseend' => 'leaseend',
            'instructed' => 'instructed',
            'soldprice' => 'soldprice',

            'garden' => 'garden',
            'parking (Do Not Use)' => 'parking',
            'newbuild' => 'newbuild',
            'groundrent' => 'groundrent',
            'commission' => 'commission',

            'description' => 'description',

            'bullets,bullet' => 'bullets,bullet',
            'brochure' => 'brochure'

        );
    }
    public function getAllBranches()
    {
        $token = VebraAltoWrapper::getInstance()->vebraAlto->getToken();
        $branches = VebraAltoWrapper::getInstance()->vebraAlto->getBranch();
        $options = [];

        if (gettype($branches) !== 'NULL') {
            foreach ($branches as $branch) {
                if ((string) $branch->name == '') {
                    $options[(int) $branch->branchid . '-noname'] = $branch->branchid;
                } else {
                    $options[(string) $branch->name] = $branch->name;
                }
            }
        } else {
            echo '<script>window.location = "/admin";</script>';
            Craft::$app->session->setFlash('error', "Cannot connect to Vebra please try again.");
        }

        return $options;
    }
}
