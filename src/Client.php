<?php
/**
 * Shapeways API Client
 * @copyright 2014 Shapeways <api@shapeways.com> (http://developers.shapeways.com)
 */

namespace Shapeways;

/**
 * Exception raised when method parameters are not valid
 */
class ParameterValidationException extends \Exception{}

/**
 * API Client for obtaining OAuth token/secret and making
 * API calls to api.shapeways.com
 */
class Client{

    /**
     * @var string $callbackUrl the oauth callback url
     */
    public $callbackUrl;

    /**
     * @var string $consumerKey the oauth consumer key
     * @var string $_consumerSecret the oauth consumer secret
     */
    public $consumerKey, $consumerSecret;

    /**
     * @var string $_client the \OAuth client instance
     */
    public $_client;

    /**
     * @var string $oauthToken the oauth token used for requests
     * @var string $oauthSecret the oauth secret used for requests
     */
    public $oauthToken, $oauthSecret;

    /**
     * @var string $baseUrl the api base url used to generate api urls
     */
    public $baseUrl = 'https://api.shapeways.com';

    /**
     * @var string $apiVersion the api version used to generate api urls
     */
    public $apiVersion = 'v1';

    /**
     * Create a new \Shapeways\Client
     *
     * @param string $consumerKey your app consumer key
     * @param string $consumerSecret your app consumer secret
     * @param string|null $callbackUrl your app callback url
     * @param string|null $oauthToken a users oauth token if it is already known
     * @param string|null $oauthSecret a users oauth secret if it is already known
     */
    public function __construct(
        $consumerKey, $consumerSecret,
        $callbackUrl = NULL, $oauthToken = NULL, $oauthSecret = NULL
    ){
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->callbackUrl = $callbackUrl;
        $this->oauthToken = $oauthToken;
        $this->oauthSecret = $oauthSecret;
        $this->_client = new \Oauth($this->consumerKey,
                                  $this->consumerSecret,
                                  OAUTH_SIG_METHOD_HMACSHA1,
                                  OAUTH_AUTH_TYPE_AUTHORIZATION);
        $this->_client->setToken($this->oauthToken, $this->oauthSecret);
    }

    /**
     * Get request token and authentication url to send user to
     *
     * @return string|bool the authentication url or false if it failed
     */
    public function connect(){
        $url = $this->url('/oauth1/request_token/');
        $response = $this->_client->getRequestToken($url, $this->callbackUrl);
        if(isset($response['authentication_url'])){
            $this->oauthSecret = $response['oauth_token_secret'];
            $this->_client->setToken($this->oauthToken, $this->oauthSecret);
            return $response['authentication_url'];
        }
        return false;
    }

    /**
     * Get an access token from the oauth authentication callback
     *
     * @param string $token the oauth_token in the auth callback query string
     * @param string $verifier the oauth_verifier in the auth callback query string
     * @return bool
     */
    public function verify($token, $verifier){
        $url = $this->url('/oauth1/access_token/');
        $this->oauthToken = $token;
        $this->_client->setToken($this->oauthToken, $this->oauthSecret);
        $response = $this->_client->getAccessToken($url, null, $verifier);
        if(isset($response['oauth_token']) && isset($response['oauth_token_secret'])){
            $this->oauthToken = $response['oauth_token'];
            $this->oauthSecret = $response['oauth_token_secret'];
            $this->_client->setToken($this->oauthToken, $this->oauthSecret);
            return true;
        }
        return false;
    }

    /**
     * Get an access token from the raw oauth authentication callback uri
     * this method will parse the oauth_token and oauth_verifier from the
     * query string for you.
     *
     * @param string $url the raw auth callback uri (e.g. $_SERVER['REQUEST_URI'])
     * @return bool
     */
    public function verifyUrl($url){
        $query= parse_url($url, PHP_URL_QUERY);
        $params = array();
        parse_str($query, $params);
        return $this->verify($params['oauth_token'], $params['oauth_verifier']);
    }

    /**
     * Generate a correct full api url from just the api part.
     *
     * @param string $path the api path (e.g. '/orders/cart/')
     * @return string
     */
    public function url($path){
        $baseUrl = trim($this->baseUrl, '/');
        $version = trim($this->apiVersion, '/');
        $path = trim($path, '/');

        return $baseUrl . '/' . $path . '/' . $version;
    }

    /**
     * Make a GET request to the api server
     *
     * @param string $url the api url to request
     * @param array $params the parameters to send with the request
     * @return array the json response from the api call
     */
    private function _get($url, $params = array()){
        try{
            $this->_client->fetch($url, $params, OAUTH_HTTP_METHOD_GET);
        } catch(\Exception $e){}
        return json_decode($this->_client->getLastResponse());
    }

    /**
     * Make a PUT request to the api server
     *
     * @param string $url the api url to request
     * @param array $params the parameters to send with the request
     * @return array the json response from the api call
     */
    private function _put($url, $params = array()){
        try{
            $this->_client->fetch($url, json_encode($params), OAUTH_HTTP_METHOD_PUT);
        } catch(\Exception $e){}
        return json_decode($this->_client->getLastResponse());
    }

    /**
     * Make a POST request to the api server
     *
     * @param string $url the api url to request
     * @param array $params the parameters to send with the request
     * @return array the json response from the api call
     */
    private function _post($url, $params = array()){
        try{
            $this->_client->fetch($url, json_encode($params), OAUTH_HTTP_METHOD_POST);
        } catch(\Exception $e){}
        return json_decode($this->_client->getLastResponse());
    }

    /**
     * Make a DELETE request to the api server
     *
     * @param string $url the api url to request
     * @param array $params the parameters to send with the request
     * @return array the json response from the api call
     */
    private function _delete($url, $params = array()){
        try{
            $this->_client->fetch($url, $params, OAUTH_HTTP_METHOD_DELETE);
        } catch(\Exception $e){}
        return json_decode($this->_client->getLastResponse());
    }

    /**
     * Upload a model for the user
     *
     * https://developers.shapeways.com/docs#POST_-models-v1
     *
     * @param array $params the model data to set
     * @return array the json response from the api call
     */
    public function addModel($params){
        $required = array('file', 'fileName', 'hasRightsToModel', 'acceptTermsAndConditions');
        foreach($required as $key){
            if(!array_key_exists($key, $params)){
                throw new ParameterValidationException('Shapeways\Client::addModel missing required key: ' . $key);
            }
        }
        $params['file'] = urlencode(base64_encode($params['file']));
        return $this->_post($this->url('/models/'), $params);
    }

    /**
     * Add a new file for the model
     *
     * https://developers.shapeways.com/docs#POST_-models-modelId-files-v1
     *
     * @param int $modelId the modelId for the model
     * @param array $params the file data to upload
     * @return array the json response from the api call
     */
    public function addModelFile($modelId, $params){
        $required = array('file', 'fileName', 'hasRightsToModel', 'acceptTermsAndConditions');
        foreach($required as $key){
            if(!array_key_exists($key, $params)){
                throw new ParameterValidationException('Shapeways\Client::addModelFile missing required key: ' . $key);
            }
        }
        $params['file'] = urlencode(base64_encode($params['file']));
        return $this->_post($this->url('/models/' . $modelId . '/files/'), $params);
    }

    /**
     * Upload a new photo for the model
     *
     * https://developers.shapeways.com/docs#POST_-models-modelId-photos-v1
     *
     * @param int $modelId the modelId for the model to associate the photo with
     * @param array $params the photo data to upload
     * @return array the json response from the api call
     */
    public function addModelPhoto($modelId, $params){
        if(!isset($params['file'])){
            throw new ParameterValidationException('Shapeways\Client::addModelPhoto missing required key: file');
        }
        $params['file'] = urlencode(base64_encode($params['file']));
        return $this->_post($this->url('/models/' . $modelId . '/photos/'), $params);
    }

    /**
     * Add a model to the users shopping cart
     *
     * https://developers.shapeways.com/docs#POST_-orders-cart-v1
     *
     * @param array $params the model data for adding to the cart
     * @return array the json response from the api call
     */
    public function addToCart($params){
        if(!isset($params['modelId'])){
            throw new ParameterValidationException('Shapeways\Client::addToCart missing required key: modelId');
        }
        return $this->_post($this->url('/orders/cart/'), $params);
    }

    /**
     * Remove the users model with the given $modelId
     *
     * https://developers.shapeways.com/docs#DELETE_-models-modelId-v1
     *
     * @param int $modelId the modelId of the model to remove
     * @return array the json response from the api call
     */
    public function deleteModel($modelId){
        return $this->_delete($this->url('/models/' . $modelId . '/'));
    }

    /**
     * Calculate the price from the provided parameters
     *
     * https://developers.shapeways.com/docs#POST_-price-v1
     *
     * @param array $params the parameters used to calculate the price
     * @return array the json response from the api call
     */
    public function getPrice($params){
        $required = array('area', 'volume', 'xBoundMin', 'xBoundMax',
                          'yBoundMin', 'yBoundMax', 'zBoundMin', 'zBoundMax');
        foreach($required as $key){
            if(!array_key_exists($key, $params)){
                throw new ParameterValidationException('Shapeways\Client::getPrice missing required key: ' . $key);
            }
        }
        return $this->_post($this->url('/price/'), $params);
    }

    /**
     * Get the current api info
     *
     * https://developers.shapeways.com/docs#GET_-api-v1-
     *
     * @return array the json response from the api call
     */
    public function getApiInfo(){
        return $this->_get($this->url('/api/'));
    }

    /**
     * Get the users shopping cart
     *
     * https://developers.shapeways.com/docs#GET_-orders-cart-v1
     *
     * @return array the json response from the api call
     */
    public function getCart(){
        return $this->_get($this->url('/orders/cart/'));
    }

    /**
     * Get a list of all categories
     *
     * https://developers.shapeways.com/docs#GET_-categories-v1
     *
     * @return array the json response from the api call
     */
    public function getCategories(){
        return $this->_get($this->url('/categories/'));
    }

    /**
     * Get information for the provided categoryId
     *
     * https://developers.shapeways.com/docs#GET_-categories-categoryId-v1
     *
     * @param int $catId the categoryId to get the information for
     * @return array the json response from the api call
     */
    public function getCategory($catId){
        return $this->_get($this->url('/categories/' . $catId . '/'));
    }

    /**
     * Get information for the provided materialId
     *
     * https://developers.shapeways.com/docs#GET_-materials-materialId-v1
     *
     * @param int $materialId the materialId to get information for
     * @return array the json response from the api call
     */
    public function getMaterial($materialId){
        return $this->_get($this->url('/materials/' . $materialId . '/'));
    }

    /**
     * Get a list of materials
     *
     * https://developers.shapeways.com/docs#GET_-materials-v1
     *
     * @return array the json response from the api call
     */
    public function getMaterials(){
        return $this->_get($this->url('/materials/'));
    }

    /**
     * Get a model
     *
     * https://developers.shapeways.com/docs#GET_-models-modelId-v1
     *
     * @param int $modelId the modelId
     * @return array the json response from the api call
     */
    public function getModel($modelId){
        return $this->_get($this->url('/models/' . $modelId . '/'));
    }

    /**
     * Get a model's file
     *
     * https://developers.shapeways.com/docs#GET_-models-modelId-files-fileVersion-v1
     *
     * @param int $modelId the modelId which the file belongs to
     * @param int $fileVersion the file version
     * @param bool $includeFile whether or not to include the raw file in the response (default: False)
     * @return array the json response from the api call
     */
    public function getModelFile($modelId, $fileVersion, $includeFile = FALSE){
        $url = $this->url('/models/' . $modelId . '/files/' . $fileVersion . '/');
        return $this->_get($url, array('file' => (int)$includeFile));
    }

    /**
     * Get information for the provided modelId
     *
     * https://developers.shapeways.com/docs#GET_-models-modelId-info-v1
     *
     * @param int $modelId the modelId for the model to retreive
     * @return array the json response from the api call
     */
    public function getModelInfo($modelId){
        return $this->_get($this->url('/models/' . $modelId . '/info/'));
    }

    /**
     * Get a list of models
     *
     * https://developers.shapeways.com/docs#GET_-models-v1
     *
     * @param int $page the page of models to get (default: 1)
     * @return array the json response from the api call
     */
    public function getModels($page = 1){
        return $this->_get($this->url('/models/'), array('page' => $page));
    }

    /**
     * Get the information for the provided printerId
     *
     * https://developers.shapeways.com/docs#GET_-printers-printerId-v1
     *
     * @param int $printerId the printerId for the printer you want to get info for
     * @return array the json response from the api call
     */
    public function getPrinter($printerId){
        return $this->_get($this->url('/printers/' . $printerId . '/'));
    }

    /**
     * Get the full list of printers available
     *
     * https://developers.shapeways.com/docs#GET_-printers-v1
     *
     * @return array the json response from the api call
     */
    public function getPrinters(){
        return $this->_get($this->url('/printers/'));
    }

    /**
     * Update a models information
     *
     * https://developers.shapeways.com/docs#PUT_-models-modelId-info-v1
     *
     * @param int $modelId the modelId of the model to update
     * @param array $params the values to update
     * @return array the json response from the api call
     */
    public function updateModelInfo($modelId, $params){
        return $this->_put($this->url('/models/' . $modelId . '/'), $params);
    }
}