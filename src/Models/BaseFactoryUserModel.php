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
namespace DreamFactory\Yii\Models;

use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Log;

/**
 * BaseFactoryUserModel
 * Provides a base for user tables. Just add YourModel::tableName()
 *
 * @property int    $id
 * @property string $email_addr_text
 * @property string $password_text
 * @property string $last_login_date
 * @property string $create_date
 * @property string $lmod_date
 */
abstract class BaseFactoryUserModel extends BaseFactoryModel
{
    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * @return array validation rules for model attributes.
     */
    public function rules()
    {
        return array(
            array('id, email_addr_text, password_text', 'required'),
            array('id, active_ind', 'numerical', 'integerOnly' => true),
        );
    }

    /**
     * @return array customized attribute labels (name=>label)
     */
    public function attributeLabels()
    {
        return array(
            'id'              => 'ID',
            'email_addr_text' => 'Email Address',
            'password_text'   => 'Password',
            'last_login_date' => 'Last Login',
            'create_date'     => 'Create Date',
            'lmod_date'       => 'Modified Date',
        );
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     *
     * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
     */
    public function search()
    {
        $_criteria = new \CDbCriteria;

        $_criteria->compare( 'id', $this->id );
        $_criteria->compare( 'email_addr_text', $this->email_addr_text );
        $_criteria->compare( 'last_login_date', $this->last_login_date );
        $_criteria->compare( 'create_date', $this->create_date, true );

        return new \CActiveDataProvider(
            $this, array(
                'criteria' => $_criteria,
            )
        );
    }

    /**
     * @param int    $id
     * @param string $stateData
     *
     * @throws \CDbException
     * @return void
     */
    public static function timestamp( $id, $stateData = 'auth_info' )
    {
        /** @var $_user BaseFactoryUserModel */
        if ( null === ( $_user = static::model()->findByPk( $id ) ) )
        {
            throw new \CDbException( 'Something fishy is going on...' );
        }

        try
        {
            if ( null !== ( $_stateData = Pii::getState( $stateData ) ) )
            {
                //	Make sure all the columns are kosher.
                foreach ( $_stateData as $_key => $_value )
                {
                    if ( !$_user->hasAttribute( $_key ) )
                    {
                        unset( $_stateData[ $_key ] );
                    }
                }

                //	Timestamp the record
                if ( false === ( $_result = $_user->update( $_stateData ) ) )
                {
                    throw new \CDbException( $_user->getErrorsForLogging() );
                }
            }

            Log::info( 'TIMESTAMP', $_stateData );
        }
        catch ( \CDbException $_ex )
        {
            Log::error( 'Update error time-stamping user row: ', $_ex->getMessage() );
        }
    }
}
