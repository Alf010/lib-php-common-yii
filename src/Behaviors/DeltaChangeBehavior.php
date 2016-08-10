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

/**
 * Provides reference between saves for changed columns
 *
 * @author        Jerry Ablan <jerryablan@gmail.com>
 *
 * @property array   $priorValues     The attributes when the model was fresh
 * @property boolean $caseInsensitive Changes are compared in a case-insensitive manner if true. Defaults to true.
 * @property boolean $isDirty         True if we're dirty
 */
class DeltaChangeBehavior extends \CActiveRecordBehavior
{
    //********************************************************************************
    //* Member
    //********************************************************************************

    /**
     * @var array Access to prior data after a save
     */
    protected $_priorValues = array();
    /**
     * @var boolean If true, comparisons will be done in a case-insensitive manner. Defaults to true.
     */
    protected $_caseInsensitive = true;
    /**
     * @var boolean Caches change state.
     */
    protected $_isDirty = false;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @return array
     */
    public function getPriorValues()
    {
        return $this->_priorValues;
    }

    /**
     * @param $which
     *
     * @return mixed
     */
    public function getPriorValue( $which )
    {
        return Option::get( $this->_priorValues, $which );
    }

    /**
     * @return bool
     */
    public function getCaseInsensitive()
    {
        return $this->_caseInsensitive;
    }

    /**
     * @param bool $value
     */
    public function setCaseInsensitive( $value = true )
    {
        $this->_caseInsensitive = $value;
    }

    //********************************************************************************
    //*  Event Handlers
    //********************************************************************************

    /**
     * After a row is pulled from the database...
     *
     * @param \CModelEvent $event
     */
    public function afterFind( $event )
    {
        //	Get fresh values
        $this->_priorValues = $event->sender->getAttributes();
        $this->_isDirty = false;

        //	Let parents have a go...
        parent::afterFind( $event );
    }

    /**
     * After a row is saved to the database...
     *
     * @param \CModelEvent $event
     */
    public function afterSave( $event )
    {
        //	Get fresh values
        $this->_priorValues = $event->sender->getAttributes();
        $this->_isDirty = false;

        //	Let parents have a go...
        parent::afterSave( $event );
    }

    //********************************************************************************
    //* Public Methods
    //********************************************************************************

    /**
     * Reverts a model back to its state before changes were made.
     *
     * @return void
     */
    public function revert()
    {
        $this->owner->setAttributes( $this->_priorValues );
    }

    /**
     * Returns an array of changed attributes since last save.
     *
     * @param array $attributes
     * @param bool  $returnChanges
     *
     * @return array The changed set of attributes or an empty array.
     * @see didChange
     */
    public function getChangedSet( $attributes = array(), $returnChanges = false )
    {
        $_result = array();

        if ( $this->_isDirty )
        {
            foreach ( $this->_priorValues as $_key => $_value )
            {
                //	Only return asked for attributes
                if ( !empty( $attributes ) && !in_array( $_key, $attributes ) )
                {
                    continue;
                }

                //	This value changed...
                if ( null !== ( $_temp = $this->checkAttributeChange( $_key, $returnChanges ) ) )
                {
                    $_result = array_merge( $_result, $_temp );
                }
            }
        }

        return $_result;
    }

    /**
     * Returns true if the attribute(s) changed since save
     *
     * @param string|array $attributes You may pass in a single attribute or an array of attributes to check
     *
     * @return boolean
     * @see getChangedSet
     */
    public function didChange( $attributes )
    {
        if ( !$this->_isDirty )
        {
            $_check = Option::clean( $attributes );

            if ( !is_array( $_check ) )
            {
                $_check = array($_check);
            }

            foreach ( $_check as $_key => $_value )
            {
                if ( $this->checkAttributeChange( $_key ) )
                {
                    $this->_isDirty = true;
                    break;
                }
            }
        }

        //	Return
        return $this->_isDirty;
    }

    /**
     * If attribute has changed, returns array of old/new values.
     *
     * @param string $attribute
     * @param bool   $returnChanges
     * @param bool   $quickCheck
     *
     * @return array
     */
    protected function checkAttributeChange( $attribute, $returnChanges = false, $quickCheck = false )
    {
        /** @var $_schema \CDbColumnSchema[] */
        static $_schema;
        static $_owner;

        if ( empty( $_owner ) )
        {
            $_owner = $this->owner;
            $_schema = $_owner->getMetaData()->columns;
        }

        $_result = array();

        //	Get old and new values
        $_newValue = $_owner->getAttribute( $attribute ) ?: 'NULL';
        $_oldValue = $this->getPriorValue( $attribute ) ?: 'NULL';

        //	Make dates look the same for string comparison
        if ( isset( $_schema[ $attribute ] ) )
        {
            if ( 'date' == $_schema[ $attribute ]->dbType || 'datetime' == $_schema[ $attribute ]->dbType )
            {
                $_didChange = ( strtotime( $_oldValue ) != strtotime( $_newValue ) );
            }
            else
            {
                $_didChange = ( $this->_caseInsensitive ) ? ( 0 != strcasecmp( $_oldValue, $_newValue ) ) : ( 0 != strcmp( $_oldValue, $_newValue ) );
            }

            //	Record the change...
            if ( $_didChange )
            {
                //	Set our global dirty flag
                $this->_isDirty = true;

                //	Just wanna know?
                if ( $quickCheck )
                {
                    return true;
                }

                //	Store info
                $_result[ $attribute ] = $returnChanges ? array($_oldValue, $_newValue) : $_oldValue;
            }
        }

        //	Return
        return empty( $_result ) ? ( $quickCheck ? false : null ) : $_result;
    }
}