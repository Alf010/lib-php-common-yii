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
namespace DreamFactory\Yii\Behaviors;

use DreamFactory\Yii\Models\BaseFactoryModel;
use Kisma\Core\Utility\Option;

/**
 * TimestampBehavior
 * Allows you to define time stamp fields in models and have them automatically updated.
 */
class TimestampBehavior extends BaseModelBehavior
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string The default date/time format
     */
    const DEFAULT_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    //********************************************************************************
    //* Members
    //********************************************************************************

    /**
     * @var string|array The optional name of the create date column
     */
    protected $_createdColumn = null;
    /**
     * @var string|array The optional name of the created by user id column
     */
    protected $_createdByColumn = null;
    /**
     * @var string|array The optional name of the last modified date column
     */
    protected $_lastModifiedColumn = null;
    /**
     * @var string|array The optional name of the last modified by user id column
     */
    protected $_lastModifiedByColumn = null;
    /**
     * @var string The optional name of the owner_id column
     */
    protected $_ownerIdColumn = null;
    /**
     * @var string The date/time format to use if not using $dateTimeFunction
     */
    protected $_dateTimeFormat = self::DEFAULT_DATE_TIME_FORMAT;
    /**
     * @var callback The date/time with which function to stamp records
     */
    protected $_dateTimeFunction = null;
    /**
     * @var int|callable
     */
    protected $_currentUserId = null;
    /**
     * @var string|callable The owner_id of the current user
     */
    protected $_currentOwnerId = null;

    //********************************************************************************
    //*  Methods
    //********************************************************************************

    /**
     * Timestamps row
     *
     * @param \CModelEvent $event
     */
    public function beforeValidate( $event )
    {
        $_model = $event->sender;
        $_timestamp = $this->_timestamp();
        $_userId = $this->getCurrentUserId();

        //	Handle lmod stamp
        $this->_stampRow(
            $this->_lastModifiedColumn,
            $_timestamp,
            $_model
        );

        $this->_stampRow(
            $this->_lastModifiedByColumn,
            $_userId,
            $_model
        );

        //	Handle created stamp
        if ( $event->sender->isNewRecord )
        {
            $this->_stampRow(
                $this->_createdColumn,
                $_timestamp,
                $_model
            );

            $this->_stampRow(
                $this->_createdByColumn,
                $_userId,
                $_model
            );

            $_ownerId = $this->getCurrentOwnerId();

            $this->_stampRow(
                $this->_ownerIdColumn,
                $_ownerId,
                $_model
            );
        }

        parent::beforeValidate( $event );
    }

    /**
     * @param int|callable $currentUserId
     *
     * @return $this
     */
    public function setCurrentUserId( $currentUserId )
    {
        $this->_currentUserId = $currentUserId;

        return $this;
    }

    /**
     * @return int|callable
     */
    public function getCurrentUserId()
    {
        if ( !empty( $this->_currentUserId ) )
        {
            return is_callable( $this->_currentUserId ) ? call_user_func( $this->_currentUserId, $this ) : $this->_currentUserId;
        }

        return null;
    }

    /**
     * Sets lmod date(s) and saves
     * Will optionally touch other columns. You can pass in a single column name or an array of columns.
     * This is useful for updating not only the lmod column but a last login date for example.
     * Only the columns that have been touched are updated. If no columns are updated, no database action is performed.
     *
     * @param mixed $additionalColumns The single column name or array of columns to touch in addition to configured lmod column
     * @param bool  $update            If true, the row will be updated
     *
     * @return boolean
     */
    public function touch( $additionalColumns = null, $update = false )
    {
        /** @var BaseFactoryModel $_model */
        $_model = $this->getOwner();

        //	Any other columns to touch?
        $_updated = $this->_stampRow( array_merge( Option::clean( $additionalColumns ), array($this->_lastModifiedColumn) ), $this->_timestamp(), $_model );

        //	Only update if and what we've touched or wanted...
        return false !== $update || !empty( $_updated ) ? $_model->update( $_updated ) : true;
    }

    /**
     * Stamps a column(s) with a value
     *
     * @param string|array   $columns The name, or an array, of possible column names
     * @param mixed          $value   The value to stamp
     * @param \CActiveRecord $model   The target model
     *
     * @return array
     */
    protected function _stampRow( $columns, $value, $model )
    {
        $_updated = array();

        if ( !empty( $columns ) )
        {
            foreach ( Option::clean( $columns ) as $_column )
            {
                if ( $model->hasAttribute( $_column ) && $model->setAttribute( $_column, $value ) )
                {
//					Log::debug( 'Stamped "' . $_column . '" with: ' . $value );
                    $_updated[] = $_column;
                }
//				else
//				{
//					Log::debug( 'No column named "' . $_column . '" to stamp with: ' . $value );
//				}
            }
        }

        return $_updated;
    }

    /**
     * @return bool|string
     */
    protected function _timestamp()
    {
        if ( is_callable( $this->_dateTimeFunction ) )
        {
            return call_user_func( $this->_dateTimeFunction, $this->_dateTimeFormat );
        }

        return date( $this->_dateTimeFormat ?: static::DEFAULT_DATE_TIME_FORMAT );
    }

    /**
     * @param string|array $createdByColumn
     *
     * @return TimestampBehavior
     */
    public function setCreatedByColumn( $createdByColumn )
    {
        $this->_createdByColumn = $createdByColumn;

        return $this;
    }

    /**
     * @return string|array
     */
    public function getCreatedByColumn()
    {
        return $this->_createdByColumn;
    }

    /**
     * @param string|array $createdColumn
     *
     * @return TimestampBehavior
     */
    public function setCreatedColumn( $createdColumn )
    {
        $this->_createdColumn = $createdColumn;

        return $this;
    }

    /**
     * @return string|array
     */
    public function getCreatedColumn()
    {
        return $this->_createdColumn;
    }

    /**
     * @param callback $dateTimeFunction
     *
     * @throws \InvalidArgumentException
     * @return TimestampBehavior
     */
    public function setDateTimeFunction( $dateTimeFunction )
    {
        if ( !is_callable( $dateTimeFunction ) )
        {
            throw new \InvalidArgumentException( 'The "dateTimeFunction" you specified is not "callable".' );
        }

        $this->_dateTimeFunction = $dateTimeFunction;

        return $this;
    }

    /**
     * @return callback
     */
    public function getDateTimeFunction()
    {
        return $this->_dateTimeFunction;
    }

    /**
     * @param string|array $lastModifiedByColumn
     *
     * @return TimestampBehavior
     */
    public function setLastModifiedByColumn( $lastModifiedByColumn )
    {
        $this->_lastModifiedByColumn = $lastModifiedByColumn;

        return $this;
    }

    /**
     * @return string|array
     */
    public function getLastModifiedByColumn()
    {
        return $this->_lastModifiedByColumn;
    }

    /**
     * @param string|array $lastModifiedColumn
     *
     * @return TimestampBehavior
     */
    public function setLastModifiedColumn( $lastModifiedColumn )
    {
        $this->_lastModifiedColumn = $lastModifiedColumn;

        return $this;
    }

    /**
     * @return string|array
     */
    public function getLastModifiedColumn()
    {
        return $this->_lastModifiedColumn;
    }

    /**
     * @param string $dateTimeFormat
     *
     * @return TimestampBehavior
     */
    public function setDateTimeFormat( $dateTimeFormat )
    {
        $this->_dateTimeFormat = $dateTimeFormat;

        return $this;
    }

    /**
     * @return string
     */
    public function getDateTimeFormat()
    {
        return $this->_dateTimeFormat;
    }

    /**
     * @param callable|string $currentOwnerId
     *
     * @return TimestampBehavior
     */
    public function setCurrentOwnerId( $currentOwnerId )
    {
        $this->_currentOwnerId = $currentOwnerId;

        return $this;
    }

    /**
     * @return string
     */
    public function getCurrentOwnerId()
    {
        return is_callable( $this->_currentOwnerId ) ? call_user_func( $this->_currentOwnerId, $this ) : $this->_currentOwnerId;
    }

    /**
     * @param string $ownerIdColumn
     *
     * @return TimestampBehavior
     */
    public function setOwnerIdColumn( $ownerIdColumn )
    {
        $this->_ownerIdColumn = $ownerIdColumn;

        return $this;
    }

    /**
     * @return string
     */
    public function getOwnerIdColumn()
    {
        return $this->_ownerIdColumn;
    }

}