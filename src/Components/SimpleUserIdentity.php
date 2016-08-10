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
namespace DreamFactory\Yii\Components;

use DreamFactory\Yii\Utility\Pii;

/**
 * SimpleUserIdentity
 * Provides a password-based login. The allowed users are retrieved from the configuration file in the 'params' section.
 * The array data should be in 'UserName' => 'password' format.
 */
class SimpleUserIdentity extends \CUserIdentity
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var int The user ID
     */
    protected $_userId;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Authenticates a user.
     *
     * @return boolean
     */
    public function authenticate()
    {
        return ( static::ERROR_NONE === ( $this->errorCode = static::_authenticate( $this->username, $this->password ) ) );
    }

    /**
     * @param string $userName
     * @param string $password
     *
     * @return int
     */
    protected static function _authenticate( $userName, $password )
    {
        $_checkUser = trim( strtolower( $userName ) );
        $_checkPassword = trim( $password );

        $_allowedUsers = Pii::getParam( 'app.auth.allowedUsers', array() );

        if ( !isset( $_allowedUsers[ $_checkUser ] ) )
        {
            return static::ERROR_USERNAME_INVALID;
        }

        if ( $_allowedUsers[ $_checkUser ] !== $_checkPassword && $_allowedUsers[ $_checkUser ] !== md5( $_checkPassword ) )
        {
            return static::ERROR_PASSWORD_INVALID;
        }

        return static::ERROR_NONE;
    }

    /**
     * @return int
     */
    public function getUserId()
    {
        return $this->_userId;
    }

    /**
     * Returns the user's ID instead of the name
     *
     * @return int|string
     */
    public function getId()
    {
        return $this->_userId;
    }

    /**
     * @param int $userId
     *
     * @return SimpleUserIdentity
     */
    public function setUserId( $userId )
    {
        $this->_userId = $userId;

        return $this;
    }
}