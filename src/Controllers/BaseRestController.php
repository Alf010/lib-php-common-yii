<?php
/**
 * This file is part of the DreamFactory Yii Connector Library
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * DreamFactory DreamFactory Yii Connector Library <http://github.com/dreamfactorysoftware/lib-php-common-yii>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Yii\Controllers;

use DreamFactory\Common\Enums\GelfLevels;
use DreamFactory\Common\Interfaces\Graylog;
use DreamFactory\Common\Interfaces\ServiceProviderLike;
use DreamFactory\Common\Services\Graylog\GelfLogger;
use DreamFactory\Yii\Actions\RestAction;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Enums\OutputFormat;
use Kisma\Core\Interfaces\ConsumerLike;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * BaseRestController
 */
class BaseRestController extends \CController implements Graylog, ConsumerLike, ServiceProviderLike
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const RESPONSE_FORMAT_V1 = 1;
    /**
     * @var int
     */
    const RESPONSE_FORMAT_V2 = 2;
    /**
     * @var int
     */
    const DEFAULT_HTTP_RESPONSE_CODE = HttpResponse::Ok;

    //**************************************************************************
    //* Members
    //**************************************************************************

    /**
     * @var string The inbound content type
     */
    protected $_contentType = 'application/json';
    /**
     * @var int The requested output format. Defaults to JSON
     */
    protected $_outputFormat = OutputFormat::JSON;
    /**
     * @var bool If true (the default), all inbound request parameters will be passed to the action as a hash instead of individual arguments.
     */
    protected $_singleParameterActions = true;
    /**
     * @var bool If true, route will be included in the $urlParameters (this is current behavior that I don't like)
     */
    protected $_includeRouteInParameters = true;
    /**
     * @var int
     */
    protected $_responseFormat = self::RESPONSE_FORMAT_V2;
    /**
     * @var float The microtime of the request
     */
    protected $_timestamp = null;
    /**
     * @var float The time taken to complete this request
     */
    protected $_elapsed = null;
    /**
     * @var int The status code to send back with the response
     */
    protected $_statusCode = self::DEFAULT_HTTP_RESPONSE_CODE;
    /**
     * @var array The raw url parameters.
     * - URL = '/controller/route/a/b/c/' => urlParameters = array('a','b','c');
     */
    protected $_urlParameters = array();
    /**
     * @var array
     * - URL = '/controller/route/a/b/c/' => uriPath = array('a','b','c');
     */
    protected $_uriPath = array();
    /**
     * @var string The full URL of this request
     */
    protected $_requestedUrl;
    /**
     * @var string The requested URL parsed via parse_url()
     */
    protected $_parsedUrl;
    /**
     * @var bool If true, extra error data is returned
     */
    protected $_debugMode = false;

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * @InheritDoc
     */
    public function init()
    {
        parent::init();

        //	Catch exceptions and return a proper json version
        Pii::app()->attachEventHandler( 'onException', array($this, '_errorHandler') );

        //	No layouts needed
        $this->layout = false;
    }

    /**
     * @param \CExceptionEvent|\CErrorEvent $event
     */
    protected function _errorHandler( $event )
    {
        if ( $event->exception instanceof \CHTTPException )
        {
            $event->handled = true;
            $this->_sendResponse( $event->exception, $this->_createErrorResponse( $event ) );
        }
    }

    /**
     * Runs the action after passing through all filters.
     * This method is invoked by {@link runActionWithFilters} after all
     * possible filters have been executed and the action starts to run.
     *
     * @param \CAction $action Action to run
     *
     * @throws \Exception
     */
    public function runAction( $action )
    {
        try
        {
            $_oldAction = $this->getAction();

            $this->setAction( $action );

            //	Parse out the request payload
            $this->_urlParameters = $this->_parseRequest();

            if ( $this->beforeAction( $action ) )
            {
                $this->_dispatchRequest( $action );
                $this->afterAction( $action );
            }

            $this->setAction( $_oldAction );
        }
        catch ( \Exception $_ex )
        {
            $this->_createErrorResponse( $_ex );
        }
    }

    /**
     * Creates the action instance based on the action name.
     * The action can be either an inline action or an object.
     * The latter is created by looking up the action map specified in {@link actions}.
     *
     * @param string $actionId
     *
     * @return \CAction|\DreamFactory\Yii\Actions\RestAction
     */
    public function createAction( $actionId )
    {
        return new RestAction(
            $this, $actionId ?: $this->defaultAction, strtoupper(
                Pii::request()->getRequestType()
            )
        );
    }

    /**
     * @param int    $statusCode
     * @param mixed  $response
     * @param string $contentType
     * @param bool   $endApp
     */
    protected function _sendResponse( $statusCode = self::DEFAULT_HTTP_RESPONSE_CODE, $response = null, $contentType = null, $endApp = true )
    {
        $_message = null;
        $_contentType = $contentType ?: ( $this->_outputFormat == OutputFormat::JSON ? 'application/json' : 'text/html' );

        if ( $statusCode instanceof \CHttpException )
        {
            $_statusCode = $statusCode->statusCode;
            $_message = $statusCode->getMessage();
        }
        elseif ( $statusCode instanceOf \Exception )
        {
            $_statusCode = $statusCode->getCode();
            $_message = $statusCode->getMessage();
        }
        else
        {
            $_statusCode = $statusCode;
        }

        //	Build the response header
        $_header[] = 'HTTP/1.1 ' . $_statusCode . ' ' . $_message;
        $_header[] = 'Content-type: ' . $_contentType;

        array_walk(
            $_header,
            function ( $value, $key )
            {
                foreach ( explode( PHP_EOL, $value ) as $_header )
                {
                    header( $_header );
                }
            }
        );

        // pages with body are easy
        if ( !empty( $response ) )
        {
            if ( OutputFormat::JSON == $this->_outputFormat && !is_string( $response ) )
            {
                $response = json_encode( $response );
            }
        }

        echo $response;

        if ( true !== $endApp )
        {
            return;
        }

        Pii::end();
    }

    /**
     * Runs the named REST action.
     * Filters specified via {@link filters()} will be applied.
     *
     * @param \CAction|\DreamFactory\Yii\Actions\RestAction $action
     *
     * @return mixed
     */
    protected function _dispatchRequest( \CAction $action )
    {
        $_callResults = null;
        $_actionId = $action->getId();

        //	Is it a valid request?
        $_httpMethod = strtoupper( Pii::request()->getRequestType() );

        switch ( $_httpMethod )
        {
            case HttpMethod::Post:
                foreach ( $_POST as $_key => $_value )
                {
                    if ( !is_array( $_value ) )
                    {
                        $this->_urlParameters[ $_key ] = $_value;
                    }
                    else
                    {
                        foreach ( $_value as $_subKey => $_subValue )
                        {
                            $this->_urlParameters[ $_subKey ] = $_subValue;
                        }
                    }
                }
                break;

            case HttpMethod::Get:
                break;
        }

        /**
         * If the determined target method is the same as the request method, shift the action into
         * the first parameter position.
         *
         * GET /controller/resource/<resourceId> becomes $object->get(<resourceId>[,<payload>])
         */
        if ( $_httpMethod === strtoupper( ( $_targetMethod = $this->_determineMethod( $_httpMethod, $_actionId ) ) ) )
        {
            array_unshift( $this->_urlParameters, $_actionId );
        }

        try
        {
            //	Get the additional data ready
            $_logInfo = array(
                'short_message' => $_httpMethod . ' ' . '/' . $this->id . ( $_actionId && '/' != $_actionId ? '/' . $this->action->id : null ),
                'level'         => GelfLevels::Info,
                'facility'      => Graylog::DefaultFacility . '/' . $this->id,
                'source'        => $_SERVER['REMOTE_ADDR'],
                'payload'       => $this->_urlParameters,
                'php_server'    => $_SERVER,
            );

            $this->_elapsed = null;
            $this->_timestamp = microtime( true );

            $_callResults = call_user_func_array(
                array(
                    $this,
                    $_targetMethod
                ),
                //	Pass in parameters collected as a single array or individual values
                $this->_singleParameterActions ? array($this->_urlParameters) : array_values( $this->_urlParameters )
            );

            $this->_elapsed = ( microtime( true ) - $this->_timestamp );

            $_response = $this->_createResponse( $_callResults );

            //	Format and echo the results
            $_logInfo['success'] = true;
            $_logInfo['elapsed'] = $this->_elapsed;
            $_logInfo['response'] = $_response;

            GelfLogger::logMessage( $_logInfo );

            $_output = $this->_formatOutput( $_response );

            $this->_sendResponse( $this->_statusCode ?: static::DEFAULT_HTTP_RESPONSE_CODE, $_output );
        }
        catch ( \Exception $_ex )
        {
            $this->_elapsed = microtime( true ) - $this->_timestamp;
            $_response = $this->_createErrorResponse( $_ex, $_ex->getCode(), $_callResults );

            //	Format and echo the results
            $_logInfo['success'] = false;
            $_logInfo['elapsed'] = $this->_elapsed;
            $_logInfo['response'] = $_response;
            $_logInfo['level'] = GelfLevels::Error;

            GelfLogger::logMessage( $_logInfo );

            $_output = $this->_formatOutput( $_response );
            Log::error(
                'Complete (!)< ' . $_targetMethod . ' < ERROR result : ' . PHP_EOL . ( is_scalar( $_output ) ? $_output : print_r( $_output, true ) )
            );
            $this->_sendResponse( $_ex, $_output );
        }
    }

    /**
     * Parses the request and returns a KVP array of parameters
     *
     * @return array
     */
    protected function _parseRequest()
    {
        $_urlParameters = $_options = array();

        $this->_contentType = Option::server( 'CONTENT_TYPE' );
        $_uri = Option::server( 'REQUEST_URI' );

        //	Parse url
        $this->_parsedUrl = \parse_url(
            $this->_requestedUrl = 'http' . ( 'on' == Option::server( 'HTTPS' ) ? 's' : null ) . '://' . Option::server( 'SERVER_NAME' ) . $_uri
        );

        //	Remove route....
        $_path = $this->_parsedUrl['path.original'] = $this->_parsedUrl['path'];

        if ( !empty( $_path ) )
        {
            $_path = str_ireplace( '/' . $this->id . '/' . $this->action->id, null, rtrim( $_path, '/' ) );

//			Log::debug( 'Parsed path: ' . $_path . ' from [' . '/' . $this->id . '/' . $this->action->id . ']' );
            $this->_parsedUrl['path'] = $_path;
        }

        //	Parse the path...
        if ( isset( $this->_parsedUrl['path'] ) )
        {
            $this->_uriPath = explode( '/', trim( $this->_parsedUrl['path'], '/' ) );

            foreach ( $this->_uriPath as $_key => $_value )
            {
                if ( null === $_value )
                {
                    continue;
                }

                if ( false !== strpos( $_value, '=' ) )
                {
                    if ( null != ( $_list = explode( '=', $_value ) ) )
                    {
                        $_options[ $_list[0] ] = $_list[1];
                    }

                    unset( $_options[ $_key ] );
                }
                elseif ( $this->_includeRouteInParameters )
                {
                    $_options[ $_key ] = $_value;
                }
            }
        }

        //	Any query string? (?x=y&...)
        if ( isset( $this->_parsedUrl['query'] ) )
        {
            $_queryOptions = array();

            \parse_str( $this->_parsedUrl['query'], $_queryOptions );

            $_options = \array_merge( $_queryOptions, $_options );

            //	Remove Yii route variable
            if ( isset( $_options['r'] ) )
            {
                unset( $_options['r'] );
            }
        }

        //	load into url params
        foreach ( $_options as $_key => $_value )
        {
            if ( !isset( $_urlParameters[ $_key ] ) )
            {
                if ( $this->_singleParameterActions )
                {
                    $_urlParameters[ $_key ] = $_value;
                }
                else
                {
                    $_urlParameters[] = $_value;
                }
            }
        }

        //	If the inbound request is JSON data, convert to an array and merge with params
        if ( false !== stripos( $this->_contentType, 'application/json' ) && isset( $GLOBALS, $GLOBALS['HTTP_RAW_POST_DATA'] ) )
        {
            //	Merging madness!
            $_urlParameters = array_merge(
                $this->_urlParameters,
                json_decode( $GLOBALS['HTTP_RAW_POST_DATA'], true )
            );
        }

        //	Clean up relayed parameters
        $_params = array();
        foreach ( $_urlParameters as $_key => $_value )
        {
            if ( is_numeric( $_key ) && false !== strpos( $_value, '=' ) )
            {
                $_parts = explode( '=', $_value );

                if ( 2 != sizeof( $_parts ) )
                {
                    continue;
                }

                $_params[ $_parts[0] ] = urldecode( $_parts[1] );
                unset( $_urlParameters[ $_key ] );
            }
        }

        return array_merge( $_params, $_urlParameters );
    }

    /**
     * @param string $httpMethod
     * @param string $actionId
     *
     * @return string
     */
    protected function _determineMethod( $httpMethod, $actionId )
    {
        static $_methodCache = null;

        if ( null === $_methodCache )
        {
            $_mirror = new \ReflectionClass( get_class( $this ) );
            foreach ( $_mirror->getMethods() as $_method )
            {
                $_methodCache[] = $_method->getName();
            }

            unset( $_mirror );
        }

        $_pattern = array();

        $httpMethod = strtolower( $httpMethod );
        $actionId = lcFirst( $actionId );

        //.........................................................................
        //. Step 1: Check for "<method>[ActionId]" (i.e. getCar() or postOffice() )
        //.			Also check for underscored methods (i.e. get_car())
        //.........................................................................

        $_pattern[] = $httpMethod . $actionId;
        $_pattern[] = $httpMethod . '_' . $actionId;

        //.........................................................................
        //. Step 2: Check for catch-all "request[ActionId]" (i.e. requestCar() or requestOffice() )
        //.			Also check for underscored methods (i.e. request_car())
        //.........................................................................

        $_pattern[] = 'request' . ucFirst( $actionId );
        $_pattern[] = 'request' . '_' . $actionId;

        //.........................................................................
        //. Step 3: Check for single purpose controllers (i.e. get(), post(), put(), delete())
        //.........................................................................

        $_pattern[] = $httpMethod;

        $_matched = preg_grep( "/^(" . implode( '|', $_pattern ) . ")$/i", $_methodCache );

        if ( !empty( $_matched ) )
        {
            return current( $_matched );
        }

        //.........................................................................
        //. Step 4: Let the missingAction() method take care of the request...
        //.........................................................................

        //	No clue what it is, so must be bogus. Hand off to missing action...
        $this->missingAction( $actionId );
    }

    /**
     * Converts the given argument to the proper format for
     * return the consumer application.
     *
     * @param mixed $output
     *
     * @return mixed
     */
    protected function _formatOutput( $output )
    {
        //	Transform output
        switch ( $this->_outputFormat )
        {
            case OutputFormat::JSON:
                @header( 'Content-type: application/json' );

                //	Are we already in JSON?
                if ( null !== @json_decode( $output ) )
                {
                    break;
                }

                /**
                 * Chose NOT to overwrite in the case of an error while
                 * formatting into json via builtin.
                 */
                if ( false !== ( $_response = json_encode( $output ) ) )
                {
                    $output = $_response;
                }
                break;

            case OutputFormat::XML:
                //	Set appropriate content type
                if ( stristr( $_SERVER['HTTP_ACCEPT'], 'application/xhtml+xml' ) )
                {
                    header( 'Content-type: application/xhtml+xml;charset=utf-8' );
                }
                else
                {
                    header( 'Content-type: text/xml;charset=utf-8' );
                }
                break;

            case OutputFormat::Raw:
                //	Nothing to do...
                break;

            default:
                if ( !is_array( $output ) )
                {
                    $output = array($output);
                }
                break;
        }

        //	And return the formatted (or not as the case may be) output
        return $output;
    }

    /**
     * Creates a JSON encoded array (as a string) with a standard REST response. Override to provide
     * a different response format.
     *
     * @param array   $resultList
     * @param boolean $isError
     * @param string  $errorMessage
     * @param integer $errorCode
     * @param array   $additionalInfo
     *
     * @return string JSON encoded array
     */
    protected function _createResponse( $resultList = array(), $isError = false, $errorMessage = 'failure', $errorCode = 0, $additionalInfo = array() )
    {
        if ( static::RESPONSE_FORMAT_V1 == $this->_responseFormat )
        {
            return $this->_buildLegacyResponse( $resultList = array(), $isError = false, $errorMessage = 'failure', $errorCode = 0, $additionalInfo = array() );
        }

        $this->setOutputFormat( OutputFormat::JSON );

        if ( false !== $isError )
        {
            $_response = $resultList;

            if ( empty( $_response ) )
            {
                $_response = array();
            }

            if ( !empty( $additionalInfo ) )
            {
                $_response = array_merge( $additionalInfo, $_response );
            }

            return $this->_buildErrorContainer( $errorMessage, $errorCode, $_response );
        }

        return $this->_buildContainer( true, $resultList );
    }

    /**
     * Builds a v1 style response container
     *
     * @param array  $resultList
     * @param bool   $isError
     * @param string $errorMessage
     * @param int    $errorCode
     * @param array  $additionalInfo
     *
     * @return array
     * @deprecated Please start using v2 of the API
     */
    protected function _buildLegacyResponse( $resultList = array(), $isError = false, $errorMessage = 'failure', $errorCode = 0, $additionalInfo = array() )
    {
        if ( $isError )
        {
            $_response = array(
                'result'       => 'failure',
                'errorMessage' => $errorMessage,
                'errorCode'    => $errorCode,
            );

            if ( $resultList )
            {
                $_response['resultData'] = $resultList;
            }
        }
        else
        {
            $_response = array(
                'result' => 'success',
            );

            if ( $resultList )
            {
                $_response['resultData'] = $resultList;
            }
        }

        //	Add in any additional info...
        if ( is_array( $additionalInfo ) && !empty( $additionalInfo ) )
        {
            $_response = array_merge(
                $additionalInfo,
                $_response
            );
        }

        return $_response;
    }

    /**
     * Creates a JSON encoded array (as a string) with a standard REST response. Override to provide
     * a different response format.
     *
     * @param string|\Exception $errorMessage
     * @param integer          $errorCode
     * @param mixed            $details
     *
     * @return string JSON encoded array
     */
    protected function _createErrorResponse( $errorMessage = 'failure', $errorCode = 0, $details = null )
    {
        if ( static::RESPONSE_FORMAT_V1 != $this->_responseFormat )
        {
            return $this->_buildErrorContainer( $errorMessage, $errorCode, $details );
        }

        //	Version 1
        $_additionalInfo = null;

        if ( $errorMessage instanceof \Exception )
        {
            $_ex = $errorMessage;

            $errorMessage = $_ex->getMessage();
            $details = ( 0 !== $errorCode ? $errorCode : null );
            $errorCode = ( $_ex instanceof \CHttpException ? $_ex->statusCode : $_ex->getCode() );
            $_previous = $_ex->getPrevious();

            if ( HttpResponse::TemporaryRedirect == $errorCode && method_exists( $_ex, 'getRedirectUri' ) )
            {
                $details['location'] = $_ex->getRedirectUri();
            }

            //	In debug mode, we output more information
            if ( $this->_debugMode )
            {
                $_additionalInfo = array(
                    'errorType'  => 'Exception',
                    'errorClass' => get_class( $_ex ),
                    'errorFile'  => $_ex->getFile(),
                    'errorLine'  => $_ex->getLine(),
                    'stackTrace' => $_ex->getTrace(),
                    'previous'   => ( $_previous ? $this->_createErrorResponse( $_previous ) : null ),
                );
            }
        }

        if ( $details && !is_array( $details ) )
        {
            $details = array($details);
        }

        if ( empty( $_additionalInfo ) )
        {
            $_additionalInfo = array();
        }

        $_fullDetails = array_merge( $_additionalInfo, empty( $details ) ? array() : $details );
        if ( empty( $_fullDetails ) )
        {
            $_fullDetails = null;
        }

        //	Set some error headers
        header( 'Pragma: no-cache' );
        header( 'Cache-Control: no-store, no-cache, max-age=0, must-revalidate' );

        return $this->_createResponse(
            array(),
            true,
            $errorMessage,
            $errorCode,
            $_fullDetails
        );
    }

    /**
     * Builds a v2 error container
     *
     * @param string $message
     * @param int    $code
     * @param mixed  $details Additional error details
     *
     * @return array
     */
    protected function _buildErrorContainer( $message = 'failure', $code = 0, $details = null )
    {
        if ( empty( $details ) )
        {
            $details = array();
        }

        if ( $message instanceof \Exception )
        {
            $_ex = $message;

            $message = $_ex->getMessage();
            $code = ( $_ex instanceof \CHttpException ? $_ex->statusCode : $_ex->getCode() );
            $_previous = $_ex->getPrevious();

            //	In debug mode, we output more information
            if ( $this->_debugMode )
            {
                $details = array_merge(
                    $details,
                    array(
                        'errorType'  => 'Exception',
                        'errorClass' => get_class( $_ex ),
                        'errorFile'  => $_ex->getFile(),
                        'errorLine'  => $_ex->getLine(),
                        'stackTrace' => $_ex->getTrace(),
                        'previous'   => ( $_previous ? $this->_createErrorResponse( $_previous ) : null ),
                    )
                );
            }

            if ( HttpResponse::TemporaryRedirect == $code && method_exists( $_ex, 'getRedirectUri' ) )
            {
                $details['location'] = $_ex->getRedirectUri();
            }
        }

        $details['message'] = $message;
        $details['code'] = $code;

        return $this->_buildContainer( false, $details );
    }

    /**
     * Builds a v2 response container
     *
     * @param bool  $success
     * @param mixed $details Additional details/data/payload
     *
     * @return array
     */
    protected function _buildContainer( $success = true, $details = null )
    {
        $_actionId = $this->action->id;
        $_id = sha1( $_SERVER['REQUEST_TIME'] . $_SERVER['HTTP_HOST'] . $_SERVER['REMOTE_ADDR'] );
        $_resource = '/' . $this->id . ( $_actionId && '/' != $_actionId ? '/' . $this->action->id : null );
        $_uri = str_replace( $_resource, null, Pii::baseUrl( true ) . Pii::url( $this->route ) );

        $_container = array(
            'success' => $success,
            'details' => $details,
            'request' => array(
                'id'        => $_id,
                'timestamp' => date( 'c', $_SERVER['REQUEST_TIME'] ),
                'elapsed'   => (float)number_format( $this->_elapsed, 4 ),
                'verb'      => $_SERVER['REQUEST_METHOD'],
                'uri'       => $_uri . $_resource,
                'signature' => base64_encode( hash_hmac( 'sha256', $_id, $_id, true ) ),
            ),
        );

        return $_container;
    }

    /***
     * Translates errors from normal model attribute names to REST map names
     *
     * @param \CActiveRecord|BaseRestController $model
     *
     * @return array
     */
    protected function _translateErrors( \CActiveRecord $model )
    {
        if ( !Pii::isEmpty( $_errorList = $model->getErrors() ) )
        {
            if ( method_exists( $model, 'attributeRestMap' ) )
            {
                /** @noinspection PhpUndefinedMethodInspection */
                $_restMap = $model->attributeRestMap();
                $_resultList = array();

                foreach ( $_errorList as $_key => $_value )
                {
                    if ( in_array( $_key, array_keys( $_restMap ) ) )
                    {
                        $_resultList[ $_restMap[ $_key ] ] = $_value;
                    }
                }

                $_errorList = $_resultList;
            }
        }

        return $_errorList;
    }

    /**
     * @param string $contentType
     *
     * @return BaseRestController
     */
    public function setContentType( $contentType )
    {
        $this->_contentType = $contentType;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentType()
    {
        return $this->_contentType;
    }

    /**
     * @param float $elapsed
     *
     * @return BaseRestController
     */
    public function setElapsed( $elapsed )
    {
        $this->_elapsed = $elapsed;

        return $this;
    }

    /**
     * @return float
     */
    public function getElapsed()
    {
        return $this->_elapsed;
    }

    /**
     * @param int $outputFormat
     *
     * @return BaseRestController
     */
    public function setOutputFormat( $outputFormat )
    {
        $this->_outputFormat = $outputFormat;

        return $this;
    }

    /**
     * @return int
     */
    public function getOutputFormat()
    {
        return $this->_outputFormat;
    }

    /**
     * @param int $responseFormat
     *
     * @return BaseRestController
     */
    public function setResponseFormat( $responseFormat )
    {
        $this->_responseFormat = $responseFormat;

        return $this;
    }

    /**
     * @return int
     */
    public function getResponseFormat()
    {
        return $this->_responseFormat;
    }

    /**
     * @param boolean $singleParameterActions
     *
     * @return BaseRestController
     */
    public function setSingleParameterActions( $singleParameterActions )
    {
        $this->_singleParameterActions = $singleParameterActions;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getSingleParameterActions()
    {
        return $this->_singleParameterActions;
    }

    /**
     * @param int $statusCode
     *
     * @return BaseRestController
     */
    public function setStatusCode( $statusCode )
    {
        $this->_statusCode = $statusCode;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->_statusCode;
    }

    /**
     * @param float $timestamp
     *
     * @return BaseRestController
     */
    public function setTimestamp( $timestamp )
    {
        $this->_timestamp = $timestamp;

        return $this;
    }

    /**
     * @return float
     */
    public function getTimestamp()
    {
        return $this->_timestamp;
    }

    /**
     * @param array $urlParameters
     *
     * @return BaseRestController
     */
    public function setUrlParameters( $urlParameters )
    {
        $this->_urlParameters = $urlParameters;

        return $this;
    }

    /**
     * @return array
     */
    public function getUrlParameters()
    {
        return $this->_urlParameters;
    }

    /**
     * @param boolean $debugMode
     *
     * @return BaseRestController
     */
    public function setDebugMode( $debugMode )
    {
        $this->_debugMode = $debugMode;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getDebugMode()
    {
        return $this->_debugMode;
    }

    /**
     * @param boolean $includeRouteInParameters
     *
     * @return BaseRestController
     */
    public function setIncludeRouteInParameters( $includeRouteInParameters )
    {
        $this->_includeRouteInParameters = $includeRouteInParameters;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getIncludeRouteInParameters()
    {
        return $this->_includeRouteInParameters;
    }

    /**
     * @return array
     */
    public function getUriPath()
    {
        return $this->_uriPath;
    }

    /**
     * @return string
     */
    public function getRequestedUrl()
    {
        return $this->_requestedUrl;
    }

    /**
     * @param string $parsedUrl
     *
     * @return BaseRestController
     */
    public function setParsedUrl( $parsedUrl )
    {
        $this->_parsedUrl = $parsedUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getParsedUrl()
    {
        return $this->_parsedUrl;
    }
}
