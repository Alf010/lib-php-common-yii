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

use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Scalar;

/**
 * DataFormatBehavior
 * If attached to a model, fields are formatted per your configuration. Also provides a default sort for a model.
 * Can be used to format 0s and 1s to FALSE and TRUE for instance...
 */
class DataFormatBehavior extends BaseModelBehavior
{
    //********************************************************************************
    //* Member
    //********************************************************************************

    /***
     * Holds the default/configured formats for use when populating fields
     *
     * array(
     *     'event' => array(                //    The event to apply format in
     *         'dataType' => <format>        //    The format for the display
     *         'method' => <function>        //    The function to call for formatting
     *     ),                                //        Send array(object,method) for class methods
     *     'event' => array(                //    The event to apply format in
     *         'dataType' => <format>        //    The format for the display
     *         'method' => <function>        //    The function to call for formatting
     *     ),                                //        Send array(object,method) for class methods
     *     ...
     *
     * @var array
     */
    protected $_dateFormat = array(
        'afterFind'     => array(
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
        ),
        'afterValidate' => array(
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
        ),
    );
    /**
     * @var string The default sort order
     */
    protected $_defaultSort;
    /***
     * @var bool If true, numeric types will be set to their PHP types
     */
    protected $_numericConversion = true;

    //*************************************************************************
    //* Handlers
    //*************************************************************************

    /**
     * Apply any formats
     *
     * @param \CModelEvent event parameter
     *
     * @return bool|void
     */
    public function beforeValidate( $event )
    {
        return $this->_handleEvent( __FUNCTION__, $event );
    }

    /**
     * Apply any formats
     *
     * @param \CEvent $event
     *
     * @return bool|void
     */
    public function afterValidate( $event )
    {
        return $this->_handleEvent( __FUNCTION__, $event );
    }

    /**
     * Apply any formats
     *
     * @param \CEvent $event
     *
     * @return bool|void
     */
    public function beforeFind( $event )
    {
        //	Is a default sort defined?
        if ( !empty( $this->_defaultSort ) )
        {
            //	Is a sort defined?
            /** @var \CDbCriteria $_criteria */
            $_criteria = $event->sender->getDbCriteria();

            //	No sort? Set the default
            if ( empty( $_criteria->order ) )
            {
                $_criteria->mergeWith( array('order' => $this->_defaultSort) );
            }
        }

        return $this->_handleEvent( __FUNCTION__, $event );
    }

    /**
     * Apply any formats
     *
     * @param \CEvent $event
     *
     * @return bool|void
     */
    public function afterFind( $event )
    {
        return $this->_handleEvent( __FUNCTION__, $event );
    }

    /**
     * Applies the requested format to the value and returns it.
     * Override this method to apply additional format types.
     *
     * @param \CDbColumnSchema $column
     * @param mixed            $value
     * @param string           $which
     *
     * @return mixed
     */
    protected function _applyFormat( $column, $value, $which = 'view' )
    {
        $_result = null;

        //	Apply formats
        switch ( $column->dbType )
        {
            case 'date':
            case 'datetime':
            case 'timestamp':
                //	Handle blanks
                if ( null != $value && $value != '0000-00-00' && $value != '0000-00-00 00:00:00' )
                {
                    $_result = date( $this->getFormat( $which, $column->dbType ), strtotime( $value ) );
                }
                break;

            default:
                $_result = $value;
                break;
        }

        return $_result;
    }

    /**
     * Process the data and apply formats
     *
     * @param string  $which
     * @param \CEvent $event
     *
     * @return bool
     */
    protected function _handleEvent( $which, \CEvent $event )
    {
        static $_schema;
        static $_schemaFor;

        $_model = $event->sender;

        //	Cache for multi event speed
        if ( $_schemaFor != get_class( $_model ) )
        {
            /** @var \CDbColumnSchema[] $_schema */
            $_schema = $_model->getMetaData()->columns;
            $_schemaFor = get_class( $_model );
        }

        //	Not for us? Pass it through...
        //	Is it safe?
        if ( !$_schema )
        {
            $_model->addError( null, 'Cannot read schema for data formatting' );

            return false;
        }

        //	Scoot through and update values...
        foreach ( $_schema as $_name => $_column )
        {
            if ( isset( $this->_dateFormat[ $which ] ) )
            {
                if ( !empty( $_name ) && $_model->hasAttribute( $_name ) && isset( $_schema[ $_name ], $this->_dateFormat[ $which ][ $_column->dbType ] ) )
                {
                    $_value = $this->_applyFormat( $_column, $_model->getAttribute( $_name ), $which );
                    $_model->setAttribute( $_name, $_value );
                }
            }

            //	Convert strings to numeric if desired
            if ( true === $this->_numericConversion && null !== ( $_value = $_model->getAttribute( $_name ) ) )
            {
                if ( 'after' == substr( $which, 0, 5 ) )
                {
                    switch ( $_model->determineGenericType( $_column ) )
                    {
                        case 'double':
                        case 'float':
                            $_model->setAttribute( $_name, doubleval( $_value ) );
                            break;

                        case 'integer':
                            $_model->setAttribute( $_name, intval( $_value ) );
                            break;

                        case 'boolean':
                            $_model->setAttribute( $_name, Scalar::boolval( $_value ) );
                            break;
                    }
                }
                else if ( 'before' == substr( $which, 0, 6 ) )
                {
                    switch ( $_model->determineGenericType( $_column ) )
                    {
                        case 'boolean':
                            $_model->setAttribute( $_name, $_value ? 1 : 0 );
                            break;
                    }
                }
            }
        }

        //	Papa don't preach...
        return parent::$which( $event );
    }

    /**
     * @param string $which
     * @param string $type
     *
     * @return string
     */
    public function getFormat( $which = 'afterFind', $type = 'date' )
    {
        return Option::getDeep( $this->_dateFormat, $which, $type, 'm/d/Y' );
    }

    /**
     * Sets a format
     *
     * @param string $which
     * @param string $type
     * @param string $format
     *
     * @return DataFormatBehavior
     */
    public function setFormat( $which = 'afterValidate', $type = 'date', $format = 'm/d/Y' )
    {
        Option::addTo( $this->_dateFormat, $which, $type, $format );

        return $this;
    }

    /**
     * @param string $defaultSort
     *
     * @return DataFormatBehavior
     */
    public function setDefaultSort( $defaultSort )
    {
        $this->_defaultSort = $defaultSort;

        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultSort()
    {
        return $this->_defaultSort;
    }
}