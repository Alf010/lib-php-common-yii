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
namespace DreamFactory\Yii\Modules;

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Option;

/**
 * DreamWebModule
 * Provides extra functionality to the base Yii module functionality
 */
class DreamWebModule extends \CWebModule
{
    //*************************************************************************
    //* Private Members
    //*************************************************************************

    /**
     * @var string
     */
    protected $_configPath = null;
    /**
     * @var string
     */
    protected $_assetPath = null;
    /**
     * @var string
     */
    protected $_assetUrl = null;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $name
     *
     * @return \CDbConnection
     */
    public function getDb( $name = 'db' )
    {
        return Pii::db( $name );
    }

    /**
     * Initialize
     */
    public function init()
    {
        //	Phone home...
        parent::init();

        //	import the module-level models and components
        $this->setImport(
            array(
                $this->id . '.models.*',
                $this->id . '.components.*',
            )
        );

        //	Read private configuration...
        if ( !empty( $this->_configPath ) )
        {
            /** @noinspection PhpIncludeInspection */
            if ( false !== ( $_configuration = require( $this->basePath . $this->_configPath ) ) )
            {
                $this->configure( $_configuration );
            }
        }

        //	Get our asset manager going...
        $this->_setAssetPaths();

        //	Who doesn't need this???
        if ( !Option::get( Pii::clientScript()->scriptMap, 'jquery.js', false ) )
        {
            Pii::clientScript()->registerCoreScript( 'jquery' );
        }
    }

    /**
     * Initializes the asset manager for this module
     */
    protected function _setAssetPaths()
    {
        $_assetManager = Pii::app()->getAssetManager();

        if ( null === $this->_assetPath )
        {
            $this->_assetPath = $_assetManager->getBasePath() . DIRECTORY_SEPARATOR . $this->getId();
        }

        if ( !is_dir( $this->_assetPath ) )
        {
            @mkdir( $this->_assetPath );
        }

        $this->_assetUrl = Pii::publishAsset( $this->_assetPath, true );
    }
}