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
namespace DreamFactory\Yii\Actions;

use Kisma\Core\Enums\HttpMethod;

/**
 * RestAction
 * Represents a REST action that is defined as a BaseRestController method.
 * The method name is like 'getXYZ' where 'XYZ' stands for the action name.
 */
class RestAction extends \CAction
{
	//********************************************************************************
	//* Members
	//********************************************************************************

	/**
	 * @var mixed The inbound payload for non-GET/POST requests
	 */
	protected $_payload;
	/**
	 * @var string The request method
	 */
	protected $_method;

	//********************************************************************************
	//* Methods
	//********************************************************************************

	/**
	 * @param \CController $controller
	 * @param string       $id
	 * @param string       $method
	 */
	public function __construct( $controller, $id, $method = 'GET' )
	{
		parent::__construct( $controller, $id );

		$this->_method = strtoupper( $method );

		if ( HttpMethod::Get != $this->_method && HttpMethod::Post != $this->_method )
		{
			//	Get the payload...
			$this->_payload = @file_get_contents( 'php://input' );
		}
	}

	/**
	 * Runs the REST action.
	 *
	 * @throws CHttpException
	 */
	public function run()
	{
		$_controller = $this->getController();

		if ( !( $_controller instanceof BaseRestController ) )
		{
			$_controller->missingAction( $this->getId() );

			return;
		}

		//	Call the controllers dispatch method...
		$_controller->dispatchRequest( $this );
	}

	//*************************************************************************
	//* Properties
	//*************************************************************************

	/**
	 * @param mixed $payload
	 *
	 * @return \DreamFactory\Yii\Actions\RestAction
	 */
	public function setPayload( $payload )
	{
		$this->_payload = $payload;

		return $this;
	}

	/**
	 * @return mixed
	 */
	public function getPayload()
	{
		return $this->_payload;
	}

}