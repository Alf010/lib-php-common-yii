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

use DreamFactory\Common\Exceptions\RestException;
use DreamFactory\Common\Interfaces\SecureServiceLike;
use DreamFactory\Fabric\Yii\Models\Auth\User;
use DreamFactory\Fabric\Yii\Models\Deploy\Instance;
use DreamFactory\Yii\Models\BaseModel;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpResponse;
use Kisma\Core\Utility\Log;

/**
 * BaseResourceController
 * A generic resource controller
 */
class BaseResourceController extends BaseRestController implements SecureServiceLike
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var BaseModel
     */
    protected $_resource = null;
    /**
     * @var string
     */
    protected $_resourceClass = null;
    /**
     * @var User
     */
    protected $_resourceUser = null;
    /**
     * @var bool
     */
    protected $_adminView = false;

    //*************************************************************************
    //* Public Actions
    //*************************************************************************

    /**
     * @{InheritDoc}
     */
    public function init()
    {
        //	We want individual parameters
        $this->setSingleParameterActions( false );

        parent::init();
    }

    /**
     * Retrieve an instance
     *
     * @param string|int $id
     *
     * @return array
     */
    public function get( $id )
    {
        return $this->validateRequest( $id )->getRestAttributes();
    }

    /**
     * @param string|int $id
     * @param array      $payload
     *
     * @throws RestException
     * @return array|null
     */
    public function put( $id, $payload )
    {
        return $this->post( $id, $payload );
    }

    /**
     * Delete a resource
     *
     * @param string|int $id
     *
     * @return bool
     * @throws \CDbException
     * @throws \DreamFactory\Common\Exceptions\RestException
     */
    public function delete( $id )
    {
        return $this->validateRequest( $id )->delete();
    }

    /**
     * Create/update a resource
     *
     * @param string|int $id
     * @param array      $payload
     *
     * @return array|null
     * @throws \DreamFactory\Common\Exceptions\RestException
     */
    public function post( $id, $payload = null )
    {
        if ( empty( $this->_resourceClass ) )
        {
            throw new RestException( HttpResponse::NotImplemented );
        }

        if ( is_array( $id ) )
        {
            //	new
            $_resource = new $this->_resourceClass;
            $payload = $id;
            unset( $payload['id'] );
        }
        else
        {
            $_resource = $this->validateRequest( $id, $payload );
        }

        unset( $payload['createDate'], $payload['lastModifiedDate'], $payload['userId'] );

        try
        {
            $_resource->setRestAttributes( $payload );
            $payload['user_id'] = Pii::user()->id;

            $_resource->save();

            return $_resource->getRestAttributes();
        }
        catch ( \CDbException $_ex )
        {
            Log::error( 'Exception saving resource "' . $this->_resourceClass . '::' . $_resource->id . '": ' . $_ex->getMessage() );
            throw new RestException( HttpResponse::InternalServerError );
        }
    }

    /**
     * @param int|string $id
     * @param array      $payload
     *
     * @throws \DreamFactory\Common\Exceptions\RestException
     * @return Instance
     */
    public function validateRequest( $id, $payload = null )
    {
        if ( empty( $id ) )
        {
            throw new RestException( HttpResponse::BadRequest );
        }

        throw new RestException( HttpResponse::NotImplemented );
    }

    /**
     * @param User $resourceUser
     *
     * @return $this
     */
    public function setResourceUser( $resourceUser )
    {
        $this->_resourceUser = $resourceUser;

        return $this;
    }

    /**
     * @return \DreamFactory\Fabric\Yii\Models\Auth\User
     */
    public function getResourceUser()
    {
        return $this->_resourceUser;
    }

    /**
     * @param \DreamFactory\Yii\Models\BaseModel $resource
     *
     * @return BaseResourceController
     */
    public function setResource( $resource )
    {
        $this->_resource = $resource;

        return $this;
    }

    /**
     * @return \DreamFactory\Yii\Models\BaseModel
     */
    public function getResource()
    {
        return $this->_resource;
    }

    /**
     * @param string $resourceClass
     *
     * @return $this
     */
    public function setResourceClass( $resourceClass )
    {
        $this->_resourceClass = $resourceClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getResourceClass()
    {
        return $this->_resourceClass;
    }

    /**
     * @param boolean $adminView
     *
     * @return AuthBaseResourceController
     */
    public function setAdminView( $adminView )
    {
        $this->_adminView = $adminView;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getAdminView()
    {
        return $this->_adminView;
    }
}