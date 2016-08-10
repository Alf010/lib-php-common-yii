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

use DreamFactory\Yii\Models\BaseFactoryModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Interfaces\AccessLevels;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * BaseFactoryController
 * A generic base class for controllers
 */
abstract class BaseFactoryController extends \CController implements AccessLevels
{
    //********************************************************************************
    //* Constants
    //********************************************************************************

    /**
     * @var integer The number of items to display per page
     */
    const DefaultPageSize = 25;

    //********************************************************************************
    //* Members
    //********************************************************************************

    /**
     * @var int
     */
    protected $_debugMode;
    /**
     * @var array context menu items. This property will be assigned to {@link CMenu::items}.
     */
    protected $_menu = array();
    /**
     * @var array the breadcrumbs of the current page.
     */
    protected $_breadcrumbs = array();
    /**
     * @var string An optional, additional page heading
     */
    protected $_pageHeading;
    /**
     * @var BaseFactoryModel The currently loaded data model instance.
     */
    protected $_model = null;
    /**
     * @var string The class of the model for this controller
     */
    protected $_modelClass = null;
    /**
     * @var string The id in the state of our current filter/search criteria
     */
    protected $_searchId = null;
    /**
     * @var array Stores the current search criteria
     */
    protected $_searchCriteria = null;
    /**
     * @var string the default layout for the controller view
     */
    protected $_pageLayout = 'main';
    /**
     * @var string the layout of the content portion of this controller
     */
    protected $_contentLayout = null;
    /**
     * @var boolean Try to find proper layout to use
     */
    protected $_autoLayout = true;
    /**
     * @var boolean Try to find missing action
     */
    protected $_autoMissing = true;
    /**
     * @var array An array of actions permitted by any user
     */
    protected $_userActions = array();
    /**
     * @var string
     */
    protected $_displayName;
    /**
     * @var bool
     */
    protected $_cleanTrail;
    /**
     * @var array $viewData The array of data passed to views
     */
    protected $_viewData = array();

    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * Initialize the controller
     *
     */
    public function init()
    {
        //	Phone home...
        parent::init();

        //	Find layout...
        if ( !Pii::cli() && $this->_autoLayout && Pii::app() instanceof \CConsoleApplication )
        {
            if ( file_exists( Pii::basePath() . '/views/layouts/' . $this->getId() . '.php' ) )
            {
                $this->_pageLayout = $this->getId();
            }
        }

        //	Allow errors
        $this->addUserAction( static::Any, 'error' );

        //	Pull any search criteria we've stored...
        if ( $this->_modelClass )
        {
            $this->_searchCriteria = Pii::getState( $this->_searchId );
        }

        //	Ensure conformity
        if ( !is_array( $this->_viewData ) )
        {
            $this->_viewData = array();
        }

        //	Add "currentUser" value to view data if not there
        Option::sins( $this->_viewData, 'current_user', Pii::user() );

        //	And some defaults...
        $this->_cleanTrail = $this->_displayName;
        $this->defaultAction = 'index';
    }

    /**
     * How about a default action that displays static pages? Huh? Huh?
     *
     * In your configuration file, configure the urlManager as follows:
     *
     *    'urlManager' => array(
     *        'urlFormat' => 'path',
     *        'showScriptName' => false,
     *        'rules' => array(
     *            ... all your rules should be first ...
     *            //    Add this as the last line in your rules.
     *            '<view:\w+>' => 'default/_static',
     *        ),
     *
     * The above assumes your default controller is DefaultController. If is different
     * simply change the route above (default/_static) to your default route.
     *
     * Finally, create a directory under your default controller's view path:
     *
     *        /path/to/your/app/protected/views/default/_static
     *
     * Place your static files in there, for example:
     *
     *        /path/to/your/app/protected/views/default/_static/aboutUs.php
     *        /path/to/your/app/protected/views/default/_static/contactUs.php
     *        /path/to/your/app/protected/views/default/_static/help.php
     *
     * @return array
     */
    public function actions()
    {
        return array_merge(
            array(
                '_static' => array(
                    'class'    => 'CViewAction',
                    'basePath' => '_static',
                ),
            ),
            parent::actions()
        );
    }

    /**
     * A generic action that renders a page and passes in the model
     *
     * @param string                                            $actionId
     * @param \CModel|\DreamFactory\Yii\Models\BaseFactoryModel $model
     * @param array                                             $extraParameters
     * @param string                                            $modelVariableName
     * @param string                                            $flashKey
     * @param string                                            $flashValue
     * @param string                                            $flashDefaultValue
     *
     * @return void
     */
    public function genericAction( $actionId, $model = null, $extraParameters = array(), $modelVariableName = 'model', $flashKey = null, $flashValue = null, $flashDefaultValue = null )
    {
        if ( $flashKey )
        {
            Pii::setFlash( $flashKey, $flashValue, $flashDefaultValue );
        }

        $this->render(
            $actionId,
            array_merge(
                $extraParameters,
                array(
                    $modelVariableName => ( $model ?: $this->loadModel() )
                )
            )
        );
    }

    /**
     * Returns the data model based on the primary key given in the GET variable.
     * If the data model is not found, an HTTP exception will be raised.
     *
     * @param null $id
     *
     * @throws \CHttpException
     * @return \CActiveRecord|null
     *
     * @internal param \the $integer primary key value. Defaults to null, meaning using the 'id' GET variable
     */
    public function loadModel( $id = null )
    {
        if ( null === $this->_model )
        {
            $_id = FilterInput::get( INPUT_GET, 'id', $id, FILTER_SANITIZE_STRING );

            if ( empty( $_id ) )
            {
                throw new \CHttpException( 500, 'Invalid ID' );
            }

            //	No data? bug out
            if ( null === ( $this->_model = $this->_load( $_id ) ) )
            {
                $this->redirect( array($this->defaultAction) );
            }

            //	Get the name of this model...
            $this->_setModelClass( get_class( $this->_model ) );
        }

        //	Return our model...
        return $this->_model;
    }

    /**
     * Provide automatic missing action mapping...
     * Also handles a theme change request from any portlets
     *
     * @param string $actionId
     */
    public function missingAction( $actionId = null )
    {
        if ( false !== $this->_autoMissing )
        {
            if ( empty( $actionId ) )
            {
                $actionId = $this->defaultAction;
            }

            if ( $this->getViewFile( $actionId ) )
            {
                /** @var $controller BaseFactoryController */
                $controller = $this;
                $view = $actionId;
                $viewData = array();

                $this->_runMissing(
                    $actionId,
                    function () use ( $controller, $view, $viewData )
                    {
                        $controller->render( $view, $viewData );
                    }
                );

                return;
            }
        }

        parent::missingAction( $actionId );
    }

    /**
     * @param string   $actionId
     * @param callable $handler
     *
     * @throws \CException
     * @return void
     */
    protected function _runMissing( $actionId, $handler )
    {
        if ( !is_callable( $handler ) )
        {
            throw new \CException( 'The handler specified is invalid.' );
        }

        $_priorAction = $this->getAction();
        $this->setAction( $_action = new \CInlineAction( $this, $actionId ) );

        if ( $this->beforeAction( $_action ) )
        {
            $_args = func_get_args();
            array_shift( $_args );
            array_shift( $_args );

            call_user_func_array( $handler, $_args );
            $this->afterAction( $_action );
        }

        $this->setAction( $_priorAction );
    }

    /**
     * Our error handler...
     */
    public function actionError()
    {
        if ( null === ( $_error = Pii::currentError() ) )
        {
            if ( !Pii::ajaxRequest() )
            {
                echo Option::get( $_error, 'message' );
            }
        }

        if ( !$this->hasView( 'error' ) )
        {
            throw new \CHttpException( 404, 'Page not found.' );
        }

        $this->render(
            'error',
            array(
                'error' => $_error
            )
        );
    }

    /** {@InheritDoc} */
    public function render( $view, $data = null, $return = false, $layoutData = null )
    {
        //	Allow passing second array in third parameter
        if ( !is_bool( $return ) )
        {
            $_return = $layoutData ?: false;
            $layoutData = $return;
            $return = $_return;
        }

        if ( $this->beforeRender( $view ) )
        {
            $_output = $this->renderPartial( $view, $data, true );

            if ( false !== ( $layoutFile = $this->getLayoutFile( $this->layout ) ) )
            {
                $_layoutData = array_merge(
                    array(
                        'content' => $_output
                    ),
                    Option::clean( $layoutData )
                );

                $_output = $this->renderFile( $layoutFile, $_layoutData, true );
            }

            $this->afterRender( $view, $_output );

            $_output = $this->processOutput( $_output );

            if ( $return )
            {
                return $_output;
            }

            echo $_output;
        }
    }

    /**
     * Renders a view.
     *
     * @param string $view
     * @param mixed  $data
     * @param bool   $return
     * @param bool   $processOutput
     *
     * @throws \InvalidArgumentException
     * @return string
     */
    public function renderPartial( $view, $data = null, $return = false, $processOutput = false )
    {
        if ( false === ( $_viewFile = $this->getViewFile( $view ) ) )
        {
            throw new \InvalidArgumentException( 'The view "' . $view . '" cannot be found.' );
        }

        if ( empty( $data ) )
        {
            $data = array();
        }

        $_output = $this->renderFile(
            $_viewFile,
            array_merge(
                $this->_viewData,
                $data
            ),
            true
        );

        if ( false !== $processOutput )
        {
            $_output = $this->processOutput( $_output );
        }

        if ( false !== $return )
        {
            return $_output;
        }

        echo $_output;
    }

    /**
     * Sets the content type for this page to the specified MIME type
     *
     * @param string $contentType
     * @param bool   $noLayout If true, the layout for this page is set to false
     *
     * @return \DreamFactory\Yii\Controllers\BaseFactoryController
     */
    public function setContentType( $contentType, $noLayout = true )
    {
        if ( true === $noLayout )
        {
            $this->layout = false;
        }

        header( 'Content-Type: ' . $contentType );

        return $this;
    }

    /**
     * Checks to see if a view is available
     *
     * @param string $viewName
     *
     * @return bool
     */
    public function hasView( $viewName )
    {
        return ( false !== $this->getViewFile( $viewName ) );
    }

    /**
     * {@InheritDoc}
     */
    public function filters()
    {
        if ( 'localhost' == Option::server( 'HTTP_HOST', gethostname() ) )
        {
            return array();
        }

        //	Default access control
        return array(
            'accessControl',
        );
    }

    /**
     * {@InheritDoc}
     */
    public function accessRules()
    {
        //	Console apps can bypass this...
        if ( Pii::app() instanceof \CConsoleApplication )
        {
            return array();
        }

        //	Build access rule array...
        static $_rules = array();

        foreach ( $this->_userActions as $_type => $_actions )
        {
            $_rules[] = array(
                'allow',
                'actions' => Option::clean( $_actions ),
                'users'   => Option::clean( $_type ),
            );
        }

        //	Add in a deny-all
        $_rules[] = array('deny');

        //	Return the rules...
        return $_rules;
    }

    /**
     * @param int    $accessLevel
     * @param string $roleName
     * @param string $action
     *
     * @return \DreamFactory\Yii\Controllers\BaseFactoryController
     */
    public function addUserActionRole( $accessLevel, $roleName, $action )
    {
        $this->_userActions[$accessLevel]['roles'][$roleName] = $action;

        return $this;
    }

    /**
     * @param int    $accessLevel
     * @param string $action
     *
     * @return mixed The value before removing
     */
    public function removeUserAction( $accessLevel, $action )
    {
        $this->_userActions[$accessLevel] = Option::clean( $this->_userActions[$accessLevel] );

        return Option::remove( $this->_userActions[$accessLevel], $action );
    }

    /**
     * @param string $accessLevel
     * @param string $action
     *
     * @return \DreamFactory\Yii\Controllers\BaseFactoryController
     */
    public function addUserAction( $accessLevel, $action )
    {
        $this->_userActions[$accessLevel] = Option::get( $this->_userActions, $accessLevel, array() );

        if ( !in_array( $action, $this->_userActions[$accessLevel] ) )
        {
            $this->_userActions[$accessLevel][] = $action;
        }

        return $this;
    }

    /**
     * @param int   $accessLevel
     * @param array $actions
     *
     * @return \DreamFactory\Yii\Controllers\BaseFactoryController
     */
    public function addUserActions( $accessLevel, $actions = array() )
    {
        foreach ( $actions as $_action )
        {
            $this->addUserAction( $accessLevel, $_action );
        }

        return $this;
    }

    /** MOVED TO PlatformWebApplication **/
//
//    /**
//     * Overridden to log API requests to local graylog server
//     *
//     * @param \CAction $action
//     *
//     * @return bool
//     */
//    protected function beforeAction( $action )
//    {
//        if ( Pii::getParam( 'dsp.fabric_hosted', false ) )
//        {
//            $_host = Option::server( 'HTTP_HOST', gethostname() );
//
//            //	Get the additional data ready
//            $_logInfo = array(
//                'short_message' => strtoupper( $action->id ) . ' /' . $this->route,
//                'full_message'  => 'Inbound API request from "' . $_host . '": ' . $action->id,
//                'level'         => GelfLevels::Info,
//                'facility'      => 'platform/api',
//                '_dsp_name'     => $_host,
//                '_source'       => Option::server( 'REMOTE_ADDR', gethostbyname( $_host ) ),
//                '_payload'      => $_REQUEST,
//                '_path'         => Option::get( $_REQUEST, 'path' ),
//                '_app_name'     => Option::get( $_REQUEST, 'app_name' ),
//                '_method'       => Option::server( 'REQUEST_METHOD', 'GET' ),
//            );
//
//            GelfLogger::logMessage( $_logInfo );
//        }
//
//        return parent::beforeAction( $action );
//    }

    /**
     * Creates a static model
     *
     * @param string $class
     *
     * @return BaseFactoryModel
     */
    protected function _staticModel( $class = null )
    {
        $_class = $class ?: $this->_modelClass;

        if ( empty( $_class ) )
        {
            return null;
        }

        return call_user_func( array($_class, 'model') );
    }

    /**
     * Saves the data in the model
     *
     * @param \CActiveRecord|\DreamFactory\Yii\Models\BaseFactoryModel $model            The model to save
     * @param array                                                    $payload          The array of data to merge with the model
     * @param array                                                    $options          The options for saving. These can be:
     *                                                                                   -    redirect        The view to redirect to after a save
     *                                                                                   -    attributesSet   If true, attributes will NOT be set
     *                                                                                   from $payload
     *                                                                                   -    modelClass       The name of the model
     *                                                                                   -    success         The "success" flash message to show
     *                                                                                   after success
     *                                                                                   -    commit          If true, and this is a transaction, a
     *                                                                                   commit will be performed
     *                                                                                   -    safeOnly        If true, only saves "safe" attributes
     *
     * @return bool
     */
    protected function _saveModel( &$model, $payload = array(), array $options = array() )
    {
        $_redirect = Option::get( $options, 'redirect', 'update' );
        $_attributesSet = Option::get( $options, 'attributes_set', false );
        $_modelClass = Option::get( $options, 'model_class', get_class( $model ) );
        $_success = Option::get( $options, 'success', 'Your changes have been saved.' );
        $_commit = Option::get( $options, 'commit', true );
        $_safeOnly = Option::get( $options, 'safe_only', false );
        $_attributes = Option::get( $payload, $_modelClass );

        if ( !empty( $_attributes ) )
        {
            if ( false === $_attributesSet )
            {
                $model->setAttributes( $payload[$_modelClass], $_safeOnly );
            }

            if ( $model->save() )
            {
                if ( $_commit && ( $model instanceof BaseFactoryModel ) )
                {
                    $model->commit();
                }

                Pii::setFlash( 'success', $_success );

                if ( null !== $_redirect )
                {
                    $this->redirect(
                        array(
                            $_redirect,
                            'id' => $model->id
                        )
                    );
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Loads a page of models
     *
     * @param bool               $sort
     * @param \CDbCriteria|array $criteria
     *
     * @return array Element 0 is the results of the find. Element 1 is the pagination object
     */
    protected function _loadPaged( $sort = false, $criteria = null )
    {
        $_sort = $_pagination = null;
        $criteria = $criteria ?: new \CDbCriteria();

        //	And the paginator
        $_pagination = new \CPagination( $this->_loadCount( $criteria ) );
        $_pagination->pageSize = FilterInput::request( 'per_page', static::DefaultPageSize, FILTER_SANITIZE_NUMBER_INT );

        if ( null !== ( $_page = FilterInput::request( 'page', 0, FILTER_SANITIZE_NUMBER_INT ) ) )
        {
            $_pagination->setCurrentPage( intval( $_page ) - 1 );
        }

        $_pagination->applyLimit( $criteria );

        //	Sort...
        if ( $sort )
        {
            $_sort = new \CSort( $this->_modelClass );
            $_sort->applyOrder( $criteria );
        }

        //	Return an array of what we've build...
        return array($this->_loadAll( $criteria ), $criteria, $_pagination, $_sort);
    }

    /**
     * This method reads the data from the database and returns the row.
     * Must override in subclasses.
     *
     * @var int $id The primary key to look up
     * @return \CActiveRecord
     */
    protected function _load( $id = null )
    {
        //	we get a string key, assume hash...
        if ( !is_numeric( $id ) )
        {
            if ( null === ( $_staticModel = $this->_staticModel() ) )
            {
                return null;
            }

            if ( $_staticModel->hasAttribute( 'user_id' ) && !$_staticModel->getMetaData()->columns['user_id']->allowNull )
            {
                $_staticModel->userOwned();
            }

            $_staticModel->unhashId( $id, true );

            return $_staticModel->find();
        }

        return $this->_staticModel()->findByPk( $id );
    }

    /**
     * Loads all data using supplied criteria
     *
     * @param \CDbCriteria $criteria
     *
     * @return array
     */
    protected function _loadAll( &$criteria = null )
    {
        return $this->_staticModel()->findAll( $criteria );
    }

    /**
     * Returns the count of rows that match the supplied criteria
     *
     * @param \CDbCriteria $criteria
     *
     * @return integer The number of rows
     */
    protected function _loadCount( &$criteria = null )
    {
        return $this->_staticModel()->count( $criteria );
    }

    /**
     * Pushes a variable onto the view data stack
     *
     * @param string $variableName
     * @param mixed  $variableData
     */
    protected function _addViewData( $variableName, $variableData = null )
    {
        $this->_viewData[$variableName] = $variableData;
    }

    /**
     * Clears the current search criteria
     */
    protected function _clearSearchCriteria()
    {
        $this->_searchCriteria = null;
        Pii::clearState( $this->_searchId );
    }

    /**
     * Turns off the layout, echos the JSON encoded version of data and returns. Optionally encoding HTML characters.
     *
     * @param array|bool $payload      The response data
     * @param boolean    $encode       If true, response is run through htmlspecialchars()
     * @param bool       $returnString If true, response is returned instead of being echo'd
     * @param int        $encodeOptions
     *
     * @return void
     */
    protected function _ajaxReturn( $payload = false, $encode = false, $returnString = false, $encodeOptions = ENT_NOQUOTES )
    {
        $this->layout = false;

        if ( is_bool( $payload ) )
        {
            $payload = ( $payload ? '1' : '0' );
        }

        $_result = json_encode( $payload );

        if ( $encode )
        {
            $_result = htmlspecialchars( $_result, $encodeOptions );
        }

        if ( false !== $returnString )
        {
            return $_result;
        }

        echo $_result;
    }

    /**
     * @param int    $accessLevel
     * @param string $value
     *
     * @return \DreamFactory\Yii\Controllers\BaseFactoryController
     */
    protected function _setUserAction( $accessLevel, $value )
    {
        $this->_userActions[$accessLevel] = null;
        $this->addUserAction( $accessLevel, $value );

        return $this;
    }

    /**
     * Sets the class of the model and resets the search ID
     *
     * @param string $value
     *
     * @return \DreamFactory\Yii\Controllers\BaseFactoryController
     */
    protected function _setModelClass( $value )
    {
        $this->_modelClass = $value;
        $this->_searchId = 'dfyii.' . Inflector::tag( $value, true ) . '.search';
        $this->_searchCriteria = Pii::getState( $this->_searchId );

        return $this;
    }

    /**
     * @param bool $includeRoute
     *
     * @return array|null
     */
    protected function _getUrlValues( $includeRoute = false )
    {
        $_parts = explode( '/', str_replace( '/' . $this->getRoute() . '/', null, $_SERVER['REQUEST_URI'] ) );

        if ( !empty( $_parts ) )
        {
            foreach ( $_parts as $_part )
            {
                $_part = trim( $_part, ' /' );

                if ( !empty( $_part ) )
                {
                    $_result[] = $_part;
                }
            }

            if ( false === $includeRoute && !empty( $_result ) )
            {
                array_shift( $_result );
            }
        }

        return empty( $_result ) ? null : $_result;
    }

    /**
     * @param boolean $autoLayout
     *
     * @return BaseFactoryController
     */
    public function setAutoLayout( $autoLayout )
    {
        $this->_autoLayout = $autoLayout;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoLayout()
    {
        return $this->_autoLayout;
    }

    /**
     * @param boolean $autoMissing
     *
     * @return BaseFactoryController
     */
    public function setAutoMissing( $autoMissing )
    {
        $this->_autoMissing = $autoMissing;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAutoMissing()
    {
        return $this->_autoMissing;
    }

    /**
     * @param array $breadcrumbs
     *
     * @return BaseFactoryController
     */
    public function setBreadcrumbs( $breadcrumbs )
    {
        $this->_breadcrumbs = $breadcrumbs;

        return $this;
    }

    /**
     * @return array
     */
    public function getBreadcrumbs()
    {
        return $this->_breadcrumbs;
    }

    /**
     * @param boolean $cleanTrail
     *
     * @return BaseFactoryController
     */
    public function setCleanTrail( $cleanTrail )
    {
        $this->_cleanTrail = $cleanTrail;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getCleanTrail()
    {
        return $this->_cleanTrail;
    }

    /**
     * @param string $contentLayout
     *
     * @return BaseFactoryController
     */
    public function setContentLayout( $contentLayout )
    {
        $this->_contentLayout = $contentLayout;

        return $this;
    }

    /**
     * @return string
     */
    public function getContentLayout()
    {
        return $this->_contentLayout;
    }

    /**
     * @param int $debugMode
     *
     * @return BaseFactoryController
     */
    public function setDebugMode( $debugMode )
    {
        $this->_debugMode = $debugMode;

        return $this;
    }

    /**
     * @return int
     */
    public function getDebugMode()
    {
        return $this->_debugMode;
    }

    /**
     * @param string $displayName
     *
     * @return BaseFactoryController
     */
    public function setDisplayName( $displayName )
    {
        $this->_displayName = $displayName;

        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->_displayName;
    }

    /**
     * @param array $menu
     *
     * @return BaseFactoryController
     */
    public function setMenu( $menu )
    {
        $this->_menu = $menu;

        return $this;
    }

    /**
     * @return array
     */
    public function getMenu()
    {
        return $this->_menu;
    }

    /**
     * @param \DreamFactory\Yii\Models\BaseFactoryModel $model
     *
     * @return BaseFactoryController
     */
    public function setModel( $model )
    {
        $this->_model = $model;

        return $this;
    }

    /**
     * @return \DreamFactory\Yii\Models\BaseFactoryModel
     */
    public function getModel()
    {
        return $this->_model;
    }

    /**
     * @param string $modelClass
     *
     * @return BaseFactoryController
     */
    public function setModelClass( $modelClass )
    {
        $this->_modelClass = $modelClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getModelClass()
    {
        return $this->_modelClass;
    }

    /**
     * @param string $pageHeading
     *
     * @return BaseFactoryController
     */
    public function setPageHeading( $pageHeading )
    {
        $this->_pageHeading = $pageHeading;

        return $this;
    }

    /**
     * @return string
     */
    public function getPageHeading()
    {
        return $this->_pageHeading;
    }

    /**
     * @param string $pageLayout
     *
     * @return BaseFactoryController
     */
    public function setPageLayout( $pageLayout )
    {
        $this->_pageLayout = $pageLayout;

        return $this;
    }

    /**
     * @return string
     */
    public function getPageLayout()
    {
        return $this->_pageLayout;
    }

    /**
     * @param array $searchCriteria
     *
     * @return BaseFactoryController
     */
    public function setSearchCriteria( $searchCriteria )
    {
        $this->_searchCriteria = $searchCriteria;

        return $this;
    }

    /**
     * @return array
     */
    public function getSearchCriteria()
    {
        return $this->_searchCriteria;
    }

    /**
     * @param string $searchId
     *
     * @return BaseFactoryController
     */
    public function setSearchId( $searchId )
    {
        $this->_searchId = $searchId;

        return $this;
    }

    /**
     * @return string
     */
    public function getSearchId()
    {
        return $this->_searchId;
    }

    /**
     * @param array $userActions
     *
     * @return BaseFactoryController
     */
    public function setUserActions( $userActions )
    {
        $this->_userActions = $userActions;

        return $this;
    }

    /**
     * @return array
     */
    public function getUserActions()
    {
        return $this->_userActions;
    }

    /**
     * @param array $viewData
     *
     * @return BaseFactoryController
     */
    public function setViewData( $viewData )
    {
        $this->_viewData = $viewData;

        return $this;
    }

    /**
     * @return array
     */
    public function getViewData()
    {
        return $this->_viewData;
    }
}