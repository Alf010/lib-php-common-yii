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
namespace DreamFactory\Yii\Models\Forms;

use DreamFactory\Yii\Components\SimpleUserIdentity;
use DreamFactory\Yii\Utility\Pii;

/**
 * SimpleLoginForm
 * Provides a standard simple login form
 */
class SimpleLoginForm extends \CFormModel
{
    //********************************************************************************
    //* Members
    //********************************************************************************

    /**
     * @var string
     */
    public $username;
    /**
     * @var string
     */
    public $password;
    /**
     * @var boolean
     */
    public $rememberMe;
    /**
     * @var SimpleUserIdentity Our user identity
     */
    protected $_identity;

    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * Declares the validation rules.
     * The rules state that username and password are required,
     * and password needs to be authenticated.
     *
     * @return array
     */
    public function rules()
    {
        return array(
            array('username, password', 'required'),
            array('rememberMe', 'boolean'),
            array('password', 'authenticate', 'skipOnError' => true),
        );
    }

    /**
     * Declares attribute labels.
     *
     * @return array
     */
    public function attributeLabels()
    {
        return array(
            'username'   => 'Email Address',
            'password'   => 'Password',
            'rememberMe' => 'Remember Me',
        );
    }

    /**
     * Authenticates the password.
     * This is the 'authenticate' validator as declared in rules().
     *
     * @param string $attribute
     * @param array  $params
     *
     * @return bool
     */
    public function authenticate( $attribute, $params )
    {
        $this->_identity = new SimpleUserIdentity( $this->username, $this->password );

        if ( !$this->_identity->authenticate() )
        {
            $this->addError( 'password', 'Incorrect username or password.' );

            return false;
        }

        return true;
    }

    /**
     * Logs in the user using the given username and password in the model.
     *
     * @return boolean whether login is successful
     */
    public function login()
    {
        if ( null === $this->_identity )
        {
            $this->authenticate( null, null );
        }

        if ( SimpleUserIdentity::ERROR_NONE !== $this->_identity->errorCode )
        {
            return false;
        }

        Pii::user()->login( $this->_identity );

        return true;
    }

    /**
     * @param string $password
     *
     * @return SimpleLoginForm
     */
    public function setPassword( $password )
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param boolean $rememberMe
     *
     * @return SimpleLoginForm
     */
    public function setRememberMe( $rememberMe )
    {
        $this->rememberMe = $rememberMe;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getRememberMe()
    {
        return $this->rememberMe;
    }

    /**
     * @param string $username
     *
     * @return SimpleLoginForm
     */
    public function setUsername( $username )
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return SimpleUserIdentity
     */
    public function getIdentity()
    {
        return $this->_identity;
    }

    /**
     * @param SimpleUserIdentity $identity
     *
     * @return SimpleLoginForm
     */
    protected function _setIdentity( $identity )
    {
        $this->_identity = $identity;

        return $this;
    }
}
