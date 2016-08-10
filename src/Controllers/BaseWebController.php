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
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Interfaces\ConsumerLike;
use Kisma\Core\Interfaces\HttpResponse;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Hasher;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * BaseWebController
 * A basic Yii compatible base controller with some junk added
 */
class BaseWebController extends BaseFactoryController implements ConsumerLike, HttpResponse
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string Salt used for ID hashes
     */
    const SaltyGoodness = '%]3,]~&t,EOxL30[wKw3auju:[+L>eYEVWEP,@3n79Qy';

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_customLoginView = null;
    /**
     * @var string
     */
    protected $_bootstrapHeader = null;
    /**
     * @var string
     */
    protected $_loginFormClass;
    /**
     * @var boolean $singleViewMode If true, only the 'update' view is called for create and update.
     */
    protected $_singleViewMode = false;
    /**
     * @var array
     */
    protected $_formOptions = array();

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * Initialize the controller
     */
    public function init()
    {
        parent::init();

        $this->defaultAction = 'admin';
        $this->setLoginFormClass( 'DreamFactory\\Yii\Models\\Forms\\DreamLoginForm' );
        $this->addUserActions( static::Authenticated, array('logout', 'admin', 'create', 'delete', 'update', 'index', 'view') );
        $this->addUserActions( static::Guest, array('login') );
    }

    /**
     * Home page
     */
    public function actionIndex()
    {
        $this->render( 'index' );
    }

    /**
     * Displays the login page
     *
     * @Route   ("/login",name="_app_login")
     * @Template()
     *
     * @throws \CHttpException
     * @return void
     */
    public function actionLogin()
    {
        /** @var $_model \LoginForm */
        $_model = new $this->_loginFormClass;
        $_loginPost = $_loginSuccess = false;
        $_postClass = basename( str_replace( '\\', '/', $this->_loginFormClass ) );

        //	If it is ajax validation request
        if ( isset( $_POST['ajax'] ) && 'login-form' === $_POST['ajax'] )
        {
            echo \CActiveForm::validate( $_model );
            Pii::end();
        }

        //	Collect user input data
        if ( isset( $_POST[ $_postClass ] ) )
        {
            $_loginPost = true;
            $_model->setAttributes( $_POST[ $_postClass ], false );

            //	Validate user input and redirect to the previous page if valid
            if ( $_model->validate() )
            {
                if ( $_model->login( !empty( $_model->remember_ind ) ) )
                {
                    if ( null === ( $_returnUrl = Pii::user()->getReturnUrl() ) )
                    {
                        $_returnUrl = Pii::url( '/' );
                    }

                    $this->redirect( $_returnUrl );

                    return;
                }
            }
        }

        //	Display the login form
        $this->render(
            $this->_customLoginView ?: 'login',
            array(
                'modelName' => $this->_loginFormClass,
                'model'     => $_model,
                'loginPost' => $_loginPost,
                'success'   => $_loginSuccess,
                'header'    => $this->_bootstrapHeader,
            )
        );
    }

    /**
     * Logs out the current user and redirect to homepage.
     */
    public function actionLogout( $token = null )
    {
        if ( null !== $token && $token != Pii::request()->getCsrfToken() )
        {
            throw new \CHttpException( 'Bad request.', static::BadRequest );
        }

        Pii::user()->logout();

        $this->redirect( Pii::app()->getHomeUrl() );
    }

    /**
     * Creates a new model.
     * If creation is successful, the browser will be redirected to the 'show' page.
     *
     * @param array If specified, also passed to the view.
     */
    public function actionCreate( $options = array() )
    {
        $this->actionUpdate( $options, true );
    }

    /**
     * Update the model
     */
    public function actionUpdate( $options = array(), $fromCreate = false )
    {
        //	Handle singleViewMode...
        $_model = ( $fromCreate ? new $this->_modelName : $this->loadModel() );
        $_viewName = ( $fromCreate ? ( $this->_singleViewMode ? 'update' : 'create' ) : 'update' );

        if ( Pii::postRequest() )
        {
            $this->_saveModel(
                $_model,
                $_POST,
                array(
                    'redirect' => 'update'
                )
            );
        }

        $options['update'] = !$fromCreate;

        $this->genericAction(
            $_viewName,
            $_model,
            array_merge(
                $this->_formOptions,
                $options
            )
        );
    }

    /**
     * View the model
     *
     */
    public function actionView( $options = array() )
    {
        $_model = $this->loadModel();
        $this->genericAction(
            'view',
            $_model,
            array_merge(
                $this->_formOptions,
                $options
            )
        );
    }

    /**
     * Deletes a particular model.
     * Only allowed via POST
     *
     * @param string $redirect
     *
     * @throws \CHttpException
     */
    public function actionDelete( $redirect = 'admin' )
    {
        if ( null === ( $_id = FilterInput::request( 'id' ) ) )
        {
            throw new \CHttpException( static::BadRequest, 'No "id" specified for delete.' );
        }

        if ( Pii::postRequest() )
        {
            if ( null !== ( $_model = $this->loadModel() ) )
            {
                $_model->delete();
                $this->redirect( array($redirect) );
            }
            else
            {
                throw new \CHttpException( static::NotFound );
            }
        }

        throw new \CHttpException( static::BadRequest );
    }

    /**
     * Admin page for use with a grid/table
     *
     * @param array $options
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     *
     * @return void
     */
    public function actionAdmin( $options = array() )
    {
        if ( null === $this->_modelClass )
        {
            throw new \RuntimeException( 'No model class set, unable to render "admin" page.' );
        }

        /** @var $_model BaseFactoryModel */
        $_model = $this->_staticModel();

        if ( $_model->hasAttribute( 'user_id' ) && !$_model->getMetaData()->tableSchema->columns['user_id']->allowNull )
        {
            $_models = $_model->userOwned()->findAll();
        }
        else
        {
            $_models = $_model->findAll();
        }

        $_view = 'admin';

        if ( false === $this->getViewFile( 'admin' ) )
        {
            if ( empty( $options ) && empty( $this->_formOptions ) )
            {
                throw new \InvalidArgumentException( 'You must pass the necessary options in order to use the default view.' );
            }

            $_view = 'DreamFactory.Yii.Views.Generic.admin';
        }

        $this->render(
            $_view,
            array_merge(
                $this->_formOptions,
                $options,
                array(
                    'model'  => $_model,
                    'models' => $_models,
                )
            )
        );
    }

    /**
     * @param bool $includeModules
     *
     * @return mixed
     * @return array
     */
    public function findControllers( $includeModules = true )
    {
        $_flags = GlobFlags::GLOB_RECURSE | GlobFlags::GLOB_NODOTS;

        $_classList = array();

        if ( true === $includeModules )
        {
            $_modulesPath = Pii::alias( 'application.modules' );

            $_moduleList = preg_replace(
                array('/Module\.php/i', '/\.php/i'),
                null,
                FileSystem::glob( $_modulesPath . '/*', $_flags )
            );

            foreach ( $_moduleList as $_module )
            {
                if ( false !== ( $_files = FileSystem::glob( $_modulesPath . DIRECTORY_SEPARATOR . $_module . '/controllers/*.php', $_flags ) ) )
                {
                    foreach ( $_files as $_file )
                    {
                        $_path = str_replace( dirname( dirname( $_modulesPath ) ), null, $_modulesPath ) . '/' . $_module . '/controllers/' . $_file;
                        $_classList[ $_path ] = preg_replace( array('/Controller\.php/i', '/\.php/i'), null, $_file );
                    }
                }
            }
        }

        $_controllerPath = Pii::alias( 'application.controllers' );
        $_controllerList = FileSystem::glob( $_controllerPath . '/*.php', $_flags );

        foreach ( $_controllerList as $_controller )
        {
            $_path = str_replace( dirname( dirname( $_controllerPath ) ), null, $_controllerPath ) . '/controllers/' . $_controller;
            $_classList[ $_path ] = preg_replace( array('/Controller\.php/i', '/\.php/i'), null, $_controller );
        }

        asort( $_classList );

        return $_classList;
    }

    /**
     * Post-login event which can be overridden
     *
     * @param \CFormModel $model
     *
     * @return bool
     */
    protected function _afterLogin( $model )
    {
        return true;
    }

    /**
     * @param array $seeds
     *
     * @return array
     */
    protected function _buildAdminOptions( array $seeds = array() )
    {
        /** @var $_model BaseFactoryModel */
        $_model = Option::get( $seeds, 'model', array() );
        $_shortName = ucwords( Option::get( $seeds, 'short_name', 'Item' ) );
        $_source = Option::get( $seeds, 'columns', array() );

        //	If your short name is different from the route, be sure to send it in...
        $_route = ucwords( Option::get( $seeds, 'route', $_shortName ) );

        $_columns = array();

        foreach ( $_source as $_attribute )
        {
            $_label = $_model->getAttributeLabel( $_attribute );
            $_columns[ $_label ] = $_attribute;
        }

        if ( null !== ( $_module = Pii::controller()->getModule() ) && $_module != Pii::app() )
        {
            $_module = $_module->id . '/';
        }
        else
        {
            $_module = null;
        }

        $_options = array(
            'span_size'     => Option::get( $seeds, 'span_size', 6 ),
            'save_to_state' => Option::get( $seeds, 'save_to_state', false ),
            'header'        => Option::get( $seeds, 'name' ),
            'subheader'     => 'Your ' . Inflector::pluralize( $_shortName ),
            'breadcrumbs'   => array(
                '<i class="icon-home icon-white" style="margin-right: 4px;"></i>Home' => '/',
                $_shortName . ' Manager'                                              => false,
            ),
            'tableId'       => str_replace( ' ', '-', strtolower( $_shortName ) ) . '-table',
            'clickUrl'      => Pii::url( $_module . lcfirst( $_route ) . '/update/id' ),
            'addUrl'        => Pii::url( $_module . lcfirst( $_route ) . '/create/' ),
            'shortName'     => $_shortName,
            'columns'       => $_columns,
        );

        if ( null !== ( $_ajaxData = Option::get( $seeds, 'ajaxData' ) ) )
        {
            $_options['ajaxData'] = $_ajaxData;
        }

        return $_options;
    }

    /**
     * Get a hashed id suitable for framing
     *
     * @param string $valueToHash
     *
     * @return string
     */
    public static function hashId( $valueToHash )
    {
        if ( null === $valueToHash )
        {
            return null;
        }

        return Hasher::hash( static::SaltyGoodness . $valueToHash );
    }

    /**
     * @param string $bootstrapHeader
     *
     * @return BaseWebController
     */
    public function setBootstrapHeader( $bootstrapHeader )
    {
        $this->_bootstrapHeader = $bootstrapHeader;

        return $this;
    }

    /**
     * @return string
     */
    public function getBootstrapHeader()
    {
        return $this->_bootstrapHeader;
    }

    /**
     * @param string $customLoginView
     *
     * @return BaseWebController
     */
    public function setCustomLoginView( $customLoginView )
    {
        $this->_customLoginView = $customLoginView;

        return $this;
    }

    /**
     * @return string
     */
    public function getCustomLoginView()
    {
        return $this->_customLoginView;
    }

    /**
     * @param array $formOptions
     *
     * @return BaseWebController
     */
    public function setFormOptions( $formOptions )
    {
        $this->_formOptions = $formOptions;

        return $this;
    }

    /**
     * @return array
     */
    public function getFormOptions()
    {
        return $this->_formOptions;
    }

    /**
     * @param string $loginFormClass
     *
     * @return BaseWebController
     */
    public function setLoginFormClass( $loginFormClass )
    {
        $this->_loginFormClass = $loginFormClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getLoginFormClass()
    {
        return $this->_loginFormClass;
    }

    /**
     * @param boolean $singleViewMode
     *
     * @return BaseWebController
     */
    public function setSingleViewMode( $singleViewMode )
    {
        $this->_singleViewMode = $singleViewMode;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getSingleViewMode()
    {
        return $this->_singleViewMode;
    }
}