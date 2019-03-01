<?php
/**
 * Vebra Alto Wrapper plugin for Craft CMS 3.x
 *
 * Integration with the estate agency software vebraalto.com
 *
 * @link      https://github.com/Jegard
 * @copyright Copyright (c) 2018 Luca Jegard
 */

namespace jegardvebra\vebraaltowrapper\services;

use jegardvebra\vebraaltowrapper\VebraAltoWrapper;

use Craft;
use craft\base\Component;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use craft\elements\Category;
use craft\helpers\StringHelper;
use jegardvebra\vebraaltowrapper\models\LinkModel;
use jegardvebra\vebraaltowrapper\records\VebraAltoWrapperRecord;
use craft\elements\db\CategoryQuery;

/**
 * VebraAltoWrapperService Service
 *
 * All of your plugin’s business logic should go in services, including saving data,
 * retrieving data, etc. They provide APIs that your controllers, template variables,
 * and other plugins can interact with.
 *
 * https://craftcms.com/docs/plugins/services
 *
 * @author    Luca Jegard
 * @package   VebraAltoWrapper
 * @since     1.0.0
 */
class VebraAltoWrapperService extends Component
{
    // Public Methods
    // =========================================================================

    /**
     * This function can literally be anything you want, and you can have as many service
     * functions as you want
     *
     * From any other plugin file, call it like this:
     *
     *     VebraAltoWrapper::$plugin->vebraAltoWrapperService->exampleService()
     *
     * @return mixed
     */

    private $url;
    private $dataFeedID;
    private $vebraUsername;
    private $vebraPassword;
    private $_folder;

    public function __construct($url = null, $dataFeedID = null, $vebraUsername = null, $vebraPassword = null)
    {
        //Intention: get all of the settings from the settings section of Vebra within the Plugin section of Craft
        $this->url = "http://webservices.vebra.com/export/";

        $this->dataFeedID = $dataFeedID;
        if( is_null( $dataFeedID ) ){
            $this->dataFeedID = VebraAltoWrapper::$plugin->getSettings()->dataFeedID;
        }
        
        if( is_null( $vebraUsername ) ){
            $this->vebraUsername = VebraAltoWrapper::$plugin->getSettings()->vebraUsername;
        }
        if( is_null( $vebraPassword ) ){
            $this->vebraPassword = VebraAltoWrapper::$plugin->getSettings()->vebraPassword;
        }

    }

    public function exampleService()
    {
        $result = 'something';
        // Check our Plugin's settings for `someAttribute`
        // if (VebraAltoWrapper::$plugin->getSettings()->someAttribute) {
        // }

        return $result;
    }
    public function getAllLinkModels ()
    {
        //get record from DB
        return VebraAltoWrapperRecord::find()
            ->all();
    }
    public function getLinkModel ($sectionId)
    {
        return VebraAltoWrapperRecord::find()
            ->where(['sectionId' => $sectionId])
            ->one();
    }
    public function deleteLinkModel( $sectionId )
    {
        //get record from DB
        $linkModelRecord = VebraAltoWrapperRecord::find()
            ->where(['sectionId' => $sectionId])
            ->one();
        return $linkModelRecord->delete();
    }
    public function updateLinkModel( $sectionId, $branch )
    {
        //get record from DB
        $linkModelRecord = VebraAltoWrapperRecord::find()
            ->where(['sectionId' => $sectionId])
            ->one();
        
        //\Kint::dump( $linkModelRecord );
        
        if( $linkModelRecord ){
            $linkModelRecord->setAttribute('branch', $branch);
        }

        else {
            $linkModelRecord = new VebraAltoWrapperRecord;
            $linkModelRecord->setAttribute('sectionId', $sectionId);
            $linkModelRecord->setAttribute('branch', $branch);
        }
        
        return $linkModelRecord->save();
    }
    public function getFieldMapping( $sectionId )
    {
        return $linkModelRecord = VebraAltoWrapperRecord::find()
            ->where(['sectionId' => $sectionId])
            ->one();
    }
    public function updateFieldMapping ( $sectionId, $fieldMapping )
    {
        $linkModelRecord = VebraAltoWrapperRecord::find()
            ->where(['sectionId' => $sectionId])
            ->one();
        if( $linkModelRecord ){
            $linkModelRecord->setAttribute('fieldMapping', $fieldMapping);
            return $linkModelRecord->save();
        }else{
            return false;
        }
    }
    public function getNewToken()
    {
        //DataFeedID is set in the function __construct where it is retrieved from the 
        //Settings section of this plugin within Craft CMS
        $url = "http://webservices.vebra.com/export/".$this->dataFeedID."/v10/branch";
        //Start curl session
        $ch = curl_init($url);
        //Define Basic HTTP Authentication method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);//$this->vebraPassword
        //Provide Username and Password Details
        curl_setopt($ch, CURLOPT_USERPWD, "$this->vebraUsername:$this->vebraPassword");
        //Show headers in returned data but not body as we are only using this curl session to aquire and store the token
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        //Get the response upon executing the statement
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //Set the execution of the statement to a variable ($response)
        $response = curl_exec($ch);
        //Unsure why getting the info as this does NOT seem to be used..? Retained due to uncertainty to remove
        $info = curl_getinfo($ch);
        //Close the curl session
        curl_close($ch);
        $headers = $this->get_headers_from_curl_response( $response )[0];
        
        if( array_key_exists('Token', $headers) ){
            file_put_contents( __DIR__.'/token.txt', base64_encode( $headers['Token'] ) );
            return $headers['Token'];
        }else{
            return false;
        }

    }

    public function get_headers_from_curl_response($headerContent)
    {
        //Used to get the token from the response of Vebra.
        $headers = array();

        // Split the string on every "double" new line.
        $arrRequests = explode("\r\n\r\n", $headerContent);

        // Loop of response headers. The "count() -1" is to 
        //avoid an empty row for the extra line break before the body of the response.
        for ($index = 0; $index < count($arrRequests) -1; $index++) {

            foreach (explode("\r\n", $arrRequests[$index]) as $i => $line)
            {
                if ($i === 0)
                    $headers[$index]['http_code'] = $line;
                else
                {
                    list ($key, $value) = explode(': ', $line);
                    $headers[$index][$key] = $value;
                }
            }
        }
        return $headers;
    }
    public function getToken()
    {
        $file = __DIR__ . "/token.txt";
        $tokenAge = 3600;
        if( !file_exists($file) ){
            $token = $this->getNewToken();
        }else{
            // if the tokens file is more then an hour old
            // if( date('U' , filectime($file)) <= time() - 3600 ) {
            // //if( filemtime($file) < time() -  (60 * 50) ) {
            //     $token = $this->getNewToken();
            // }else{
            //     $token = trim( file_get_contents( $file ) );
            // }
            
            // $token = $this->getNewToken();
            // if( !$token ){
            //     $token = trim( file_get_contents( $file ) );
            // }

            if (time()-filemtime($file) > $tokenAge) {
                file_put_contents( __DIR__.'/tokenAge.txt', 'Token older than '.$tokenAge. ' seconds old. The tokens age is '. (time()-filemtime($file)).' seconds old' );
                $token = $this->getNewToken();
            } else {
                file_put_contents( __DIR__.'/tokenAge.txt', 'Token NOT older than '.$tokenAge. ' seconds old. The tokens age is '. (time()-filemtime($file)).' seconds old' );
                $token = trim( file_get_contents( $file ) );
            }
        }
        return $token;
    }
    public function connect($url = "")
    {
        $token = $this->getToken();
        //$token = $this->getToken();
        if(!$token) {
            return false;
        }
        if( strlen($url) == 0 ){
            $url = "http://webservices.vebra.com/export/" . $this->dataFeedID . "/v10/branch";
        }
        $ch = curl_init( $url );
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic ' . $token ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        file_put_contents( __DIR__. '/info.json' , json_encode( $info ) );

        return array(
            'response' => (array)simplexml_load_string($response),
            'info' => $info
        );
    }
    public function getBranch()
    {
        $branches = $this->connect()['response'];
        return $branches;
    }
    public function searchCategoriesByTitle($title)
    {
        $query = new CategoryQuery(Category::class);

        //$title = 'St. Peter';

        $title = str_replace(array('.', ','), '' , $title);
        $title = trim( $title );
        $query->title = $title;
        return $query->all();

        //\Kint::dump( $title );
    }

    public function getPropertyList()
    {
        $propertyList = [];
        $branches = $this->getBranch();
        if( !$branches ){
            return false;
        }
        foreach($branches as $branch){
            $props = $this->connect( $branch->url . '/property' )['response'];
            $propertyList = array_merge( $propertyList, $props );
        }
        return $propertyList['property'];
    }

    public function getArrayValueByCsv($string, $array)
    {
        $keys = explode( ',' , $string );
        foreach($keys as $key){
            if( !isset($value) ){
                $value = $array[$key];
            }else{
                
                $value = $value[ is_numeric ( $key ) ? (int)$key : ($key) ];
            }
        }
        
        //return !is_array( $value ) ? $value : '';
        return $value;
    }
    public function populateSection($sectionId, $branchName)
    {
        $linkModel = $this->getLinkModel($sectionId);
        $fieldMapping = json_decode( $linkModel->fieldMapping );
        $branches = $this->getBranch();
        
        foreach( $branches as $_branch ){
            if( $_branch->name == $branchName ){
                $branch = $_branch;
            }
        }
        
        $propertyList = $this->connect( $branch->url . '/property' )['response']['property'];
        //file_put_contents(__DIR__.'/props.json', json_encode($propertyList));

        $allProps = [];
        foreach( $propertyList as $property ){
            $property = $this->connect( $property->url )['response'];
            $property = json_decode(json_encode( $property ), TRUE);

            $allProps = array_merge( $allProps, [ $property ] );
            // die();

            $title = $property['address']['display'];
            $ref = $property['reference']['software'];


            $fields = array(
                'title' => $title,
                'reference' => $ref,
            );

            //var_dump( $property['files']['file'] );

            $value = $this->getArrayValueByCsv( 'bullets,bullet', $property );
            //d( $value );

            foreach($fieldMapping as $craftField => $vebraField){
                //d( $vebraField );

                switch($vebraField){
                    case 'parish':
                        $ids = [];
                        $cats = $this->searchCategoriesByTitle( (string)$property['address']['town'] );
                        foreach($cats as $cat){
                            $ids [] = $cat->id;
                        }
                        //$fields[$craftField] = $ids;
                        $fields[$craftField] = $ids;
                        break;
                    case 'LetOrSale(category)':
                        if( (int)$property['web_status'] > 99 ){
                            //letting
                            $cat = Category::find()
                                ->title('For Let')
                                ->all();
                        }else{
                            //sales
                            $cat = Category::find()
                                ->title('For Sale')
                                ->all();
                        }
                        if( count($cat) > 0 ){
                            $fields[$craftField] = [ $cat[0]->id ];
                        }
                        break;
                    case 'pdf':
                            $pdfs = $this->getPdfs( $property['files'] );
                            $fields[$craftField] = $pdfs;
                        break;
                    case 'measurements':
                            $measure = [];

                            // d( $property['paragraphs'] );
                            if( $this->findKey( $property['paragraphs'], 'paragraph' ) ){
                                $paragraphs = $property['paragraphs']['paragraph'];

                                foreach($paragraphs as $paragraph){

                                    if( gettype( $paragraph ) == 'array' ){
                                        

                                        if( $this->findKey( $paragraph, 'metric' ) && $this->findKey( $paragraph, 'name' ) && $this->findKey( $paragraph, 'text' ) ){
                                            $name = $paragraph['name'];
                                            $dimensions = $paragraph['dimensions']['metric'];
                                            $text = $paragraph['text'];

                                            if( gettype( $name ) != 'array' && gettype( $dimensions ) != 'array' && gettype( $text ) != 'array' ){
                                                $measure [] = $paragraph['name'] . ' | ' . $paragraph['dimensions']['metric'] . ' | ' . $paragraph['text'];
                                            }
                                            
                                        }
                                    }
                                }
                                $fields[$craftField] = join('@', $measure);
                            }
                            
                        break;
                    case 'images':
                            $images = $this->getImages( $property['files'] , $title );
                            $fields[$craftField] = $images;
                        break;
                    default:
                        if( strlen( $vebraField ) > 0 ){
                            $value = $this->getArrayValueByCsv( $vebraField, $property );
                            if( strpos($vebraField, ',') !== false ){
                                // \Kint::dump(  $this->getArrayValueByCsv( $vebraField, $property ) );
                                $value = $this->getArrayValueByCsv( $vebraField, $property );

                                
                            }else{
                                $value = $property[$vebraField];
                            }
                            
                            $fields[$craftField] = is_array( $value ) ? join('|', $value) : $value;
                        }
                }
            }

            $entry = Entry::find()
                ->sectionId($sectionId)
                //->title( $title )
                ->reference( $ref )
                ->status(null)
                ->all();
            
            if( empty( $entry ) )
            {
                $this->saveNewEntry( 1 , $fields );
            }else{
                $this->updateEntry( $entry[0] , $fields );
            }
        }
        file_put_contents(__DIR__.'/props.json', json_encode($allProps));
        // d($allProps[0]['paragraphs']['paragraph']  );
        $allEntries = Entry::find()
            ->sectionId($sectionId)
            //->title( $title )
            ->limit(null)
            ->status(null)
            ->all();
        foreach($allEntries as $entry){
            $isOnVebra = false;
            foreach( $allProps as $property ){
                if( (string)$entry->reference == (string)$property['reference']['software'] ){
                    $isOnVebra = true;
                }
            }
            if( !$isOnVebra ){
                $entry->enabled = false;
                Craft::$app->elements->saveElement($entry);
            }else{
                $entry->enabled = true;
                Craft::$app->elements->saveElement($entry);
            }
        }
        
        return true;
    }
    public function findKey($array, $keySearch)
    {
        foreach ($array as $key => $item) {
            if ($key == $keySearch) {
                return true;
            } elseif (is_array($item) && $this->findKey($item, $keySearch)) {
                return true;
            }
        }
        return false;
    }
    public function populateProperties($propertyList)
    {
        foreach($propertyList as $property){
            $property = $this->connect( $property->url )['response'];

            $propArray = json_decode(json_encode( $property ), TRUE);
            
            \Kint::dump( $propArray );
            //file_put_contents( __DIR__ . '/prop.json' , json_encode( $property ) );

            $title = (string)$property['address']->display;
            $ref = (string)$property['reference']->software;
            $description = (string)$property['description'];

            $images = $this->getImages( $property['files'] );

            if( (int)$property['web_status'] > 99 ){
                //letting
                $cat = Category::find()
                    ->title('For Let')
                    ->all();
            }else{
                //sales
                $cat = Category::find()
                    ->title('For Sale')
                    ->all();
            }


            $fields = array(
                'reference' => $ref,
                'title' => $title,
                'slug' => $title,
                'price' => floatval( $property['price'] ),
                'description' => $description,
                'propertyImage' => $images,
                'bedrooms' => $property['bedrooms'],
                'receptions' => $property['receptions'],
                'bathrooms' => $property['bathrooms']
            );

            if( count($cat) > 0 ){
                $fields['propertyType'] = [ $cat[0]->id ];
            }

            $entry = Entry::find()
                ->section('property')
                //->title( $title )
                ->status(null)
                ->reference( $ref )
                ->all();

            //\Kint::dump( $property );
            if( empty( $entry ) )
            {
                $this->saveNewEntry( $sectionId , $fields );
            }else{
                $this->updateEntry( $entry[0] , $fields );
            }
        }
    }
    public function getPdfs( $pdfs )
    {


        $ids = [];
        //\Kint::dump( $images['file'][0]['name'] );

        foreach( $pdfs['file'] as $pdf ){
            
            //\Kint::dump( $pdf );

            $url = $pdf['url'];

            $name = $pdf['name'];

            
            if( gettype($url) == 'string' ){

                //$url = strtolower( $url );


                if( strpos( $url , 'pdf') !== false || strpos( $url , 'PDF') !== false ){

                    //$name = StringHelper::toKebabCase( $name );
                    $name = explode( '.' , $name )[0];
                    $name = StringHelper::toKebabCase( $name ) . '.pdf';

                    $assets = Asset::Find()
                        ->filename( $name )
                        ->all();
                    // d( $assets );
                    //\kint::dump( $url );
                    if( count( $assets ) == 0 ){
                        $ids [] = (string)$this->createAssetFromUrl( $name, $url );
                    }else{
                        $ids [] = (string)$assets[0]->id;
                    }
                }
            }
        }
        //\Kint::dump( $ids );
        return $ids;
    }
    public function getImages( $images, $title = '' )
    {


        $ids = [];
        //\Kint::dump( $images['file'][0]['name'] );

        foreach( $images['file'] as $image ){
            
            
            $url = $image['url'];

            $name = $image['name'];


            if( gettype($name) == 'array' ){
                $name = md5($url);
            }else{
                $name = md5($url) . $name;
            }
            
            if( gettype($name) == 'string' ){

                $name = strtolower( $name );
                
                if( strpos( strtolower( $url ) , 'jpg') !== false || strpos( strtolower( $url ) , 'png') ){

                    //$name = StringHelper::toKebabCase( $name );
                    $name = explode( '.' , $name )[0];
                    $name = StringHelper::toKebabCase( $name ) . '.jpg';

                    $assets = Asset::Find()
                        ->filename( $name )
                        ->all();
                    // d( $assets );
                    if( count( $assets ) == 0 ){
                        $ids [] = (string)$this->createAssetFromUrl( $name, $url );
                    }else{
                        $ids [] = (string)$assets[0]->id;
                    }
                }
            }
        }
        //\Kint::dump( $ids );
        return $ids;
    }
    public function createAssetFromUrl($sFilename,$url)
    {

        $img = file_get_contents($url);
        

        $path = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . $sFilename;
        //file_put_contents($path, $img);


        FileHelper::writeToFile($path, $img);


        // file_put_contents(__DIR__ . '/' . $sFilename , $img);
        $asset = new Asset();
        $asset->tempFilePath = $path;
        $asset->setScenario(Asset::SCENARIO_CREATE);
        $asset->filename = $sFilename;

        $asset->avoidFilenameConflicts = true;
        $asset->setScenario(\craft\elements\Asset::SCENARIO_CREATE);

        $folder = $this->getFolder(1);
        //\Kint::dump( $asset );
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;

        if(!$result = Craft::$app->getElements()->saveElement($asset)){
            Craft::error('[API CALLER] Could not store image ' . Json::encode($asset->getErrors()));
            //\Kint::dump($asset->getErrors());
        }

        return $asset->id;
    }
    public function getFolder($id){
        if($this->_folder === null){
            $this->_folder = Craft::$app->getAssets()->findFolder(['id' => $id]);
        }
        return $this->_folder;
    }
    public function updateEntry( $entry, $fields )
    {
        if(isset($fields['title'])) {
            $entry->title = $fields['title'];
            unset($fields['title']);
        }
    
        if(isset($fields['slug'])) {
            $entry->slug = $fields['slug'];
            unset($fields['slug']);
        }
        $entry->setFieldValues($fields);
        //$entry->expiryDate = new \DateTime();
        //$entry->expiryDate = null;

        if(Craft::$app->elements->saveElement($entry)) {
            return $entry;
        } else {
            throw new \Exception("Couldn't save new entry " . print_r($entry->getErrors(), true));
        }
    }
    public function saveNewEntry( $sectionId, $fields)
    {
        //$entryType = EntryType::find()->where(['handle' => $handle])->one();
    
        $entry = new Entry();
        $entry->sectionId = $sectionId;

        //$entry->typeId = $entryType->getAttribute('id');
        $entry->typeId = 1;
        //$entry->fieldLayoutId = $entryType->getAttribute('fieldLayoutId');
        $entry->authorId = 1;
    
        if(isset($fields['title'])) {
            $entry->title = $fields['title'];
            unset($fields['title']);
        }
    
        if(isset($fields['slug'])) {
            $entry->slug = $fields['slug'];
            unset($fields['slug']);
        }
    
        $entry->setFieldValues($fields);
    
        if(Craft::$app->elements->saveElement($entry)) {
            return $entry;
        } else {
            throw new \Exception("Couldn't save new entry " . print_r($entry->getErrors(), true));
        }
    }
}
