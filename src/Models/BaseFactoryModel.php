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
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * BaseFactoryModel
 * The base class for all models. Defines two "built-in" behaviors: DataFormat and TimeStamp
 *  - DataFormat automatically formats date/time values for the target database platform (MySQL, Oracle, etc.)
 *  - TimeStamp automatically updates create_date and lmod_date columns in tables upon save.
 */
class BaseFactoryModel extends \CActiveRecord
{
    //*******************************************************************************
    //* Members
    //*******************************************************************************

    /**
     * @var array Our column schema, cached for speed
     */
    protected $_schema;
    /**
     * @var string The name of the model class
     */
    protected $_modelClass = null;
    /**
     * @var \CDbTransaction The current transaction
     */
    protected $_transaction = null;
    /**
     * @var bool If true,save() and delete() will throw an exception on failure
     */
    protected $_throwOnError = true;

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * Init
     */
    public function init()
    {
        $this->_modelClass = get_class( $this );

        parent::init();
    }

    /**
     * Returns the static model of the specified AR class.
     *
     * @param string $className
     *
     * @return $this
     */
    public static function model( $className = null )
    {
        return parent::model( $className ?: \get_called_class() );
    }

    /**
     * Returns this model's schema
     *
     * @return array()
     */
    public function getSchema()
    {
        return $this->_schema ?: $this->_schema = $this->getMetaData()->columns;
    }

    /**
     * Returns an array of all attribute labels.
     *
     * @param array $additionalLabels
     *
     * @return array
     */
    public function attributeLabels( $additionalLabels = array() )
    {
        static $_cache;

        if ( null !== $_cache )
        {
            return $_cache;
        }

        //	Merge all the labels together
        return $_cache = array_merge(
        //	Mine
            array(
                'id'                 => 'ID',
                'create_date'        => 'Created Date',
                'created_date'       => 'Created Date',
                'last_modified_date' => 'Last Modified Date',
                'lmod_date'          => 'Last Modified Date',
            ),
            //	Subclass
            $additionalLabels
        );
    }

    /**
     * Retrieves a single attribute label
     *
     * @param string $attribute
     *
     * @return array
     */
    public function attributeLabel( $attribute )
    {
        return Option::get( $this->attributeLabels(), $attribute );
    }

    /**
     * PHP sleep magic method.
     * Take opportunity to flush schema cache...
     *
     * @return array
     */
    public function __sleep()
    {
        //	Clean up and phone home...
        $this->_schema = null;

        return parent::__sleep();
    }

    /**
     * Override of CModel::setAttributes
     * Populates member variables as well.
     *
     * @param array $attributes
     * @param bool  $safeOnly
     *
     * @return void
     */
    public function setAttributes( $attributes, $safeOnly = true )
    {
        if ( !is_array( $attributes ) )
        {
            return;
        }

        $_attributes = array_flip( $safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames() );

        foreach ( $attributes as $_column => $_value )
        {
            if ( isset( $_attributes[ $_column ] ) )
            {
                $this->setAttribute( $_column, $_value );
            }
            else
            {
                $_column = Inflector::deneutralize( $_column );

                if ( method_exists( $this, 'set' . $_column ) )
                {
                    $this->{'set' . $_column}( $_value );
                }
            }
        }
    }

    /**
     * Sets our default behaviors
     *
     * @return array
     */
    public function behaviors()
    {
        return array_merge(
            parent::behaviors(),
            array(
                //	Data formatter
                'base_platform_model.data_format_behavior' => array(
                    'class' => '\\DreamFactory\\Yii\\Behaviors\\DataFormatBehavior',
                ),
            )
        );
    }

    /**
     * Returns the errors on this model in a single string suitable for logging.
     *
     * @param string $attribute Attribute name. Use null to retrieve errors for all attributes.
     *
     * @return string
     */
    public function getErrorsForLogging( $attribute = null )
    {
        $_result = null;
        $_i = 1;

        $_errors = $this->getErrors( $attribute );

        if ( !empty( $_errors ) )
        {
            foreach ( $_errors as $_attribute => $_error )
            {
                $_result .= $_i++ . '. [' . $_attribute . '] : ' . implode( '|', $_error ) . PHP_EOL;
            }
        }

        return $_result;
    }

    /**
     * Forces an exception on failed save
     *
     * @param bool  $runValidation
     * @param array $attributes
     *
     * @throws \CDbException
     * @return bool
     */
    public function save( $runValidation = true, $attributes = null )
    {
        if ( !parent::save( $runValidation, $attributes ) )
        {
            if ( $this->_throwOnError )
            {
                throw new \CDbException( $this->getErrorsForLogging() );
            }

            return false;
        }

        return true;
    }

    /**
     * @param array $findBy     The attributes to find the row by
     * @param array $attributes The attributes to set in the new/update row
     *
     * @return bool
     */
    public function upsert( $findBy = array(), $attributes = array() )
    {
        $_condition = $_params = array();

        foreach ( $findBy as $_key => $_value )
        {
            $_condition[] = $_key . ' = :' . $_key;
            $_params[ ':' . $_key ] = $_value;
        }

        /** @var BaseFactoryModel $_model */
        $_model = static::model()->find(
            array(
                'condition' => implode( ' AND ', $_condition ),
                'params'    => $_params,
            )
        );

        if ( null === $_model )
        {
            $_model = new static();
        }

        $_model->setAttributes( empty( $attributes ) ? $findBy : $attributes );

        return $_model->save();
    }

    /**
     * A mo-betta CActiveRecord update method. Pass in array( column => value, ... ) to update.
     *
     * Simply, this method updates each attribute with the passed value, then calls parent::update();
     *
     * NB: validation is not performed in this method. You may call {@link validate} to perform the validation.
     *
     * @param array $attributes list of attributes and values that need to be saved. Defaults to null, meaning do a full update.
     *
     * @return bool whether the update is successful
     * @throws \CException if the record is new
     */
    public function update( $attributes = null )
    {
        if ( empty( $attributes ) )
        {
            return parent::update();
        }

        $_columns = array();

        foreach ( $attributes as $_column => $_value )
        {
            //	column => value specified
            if ( !is_numeric( $_column ) )
            {
                $this->{$_column} = $_value;
            }
            else
            {
                //	n => column specified
                $_column = $_value;
            }

            $_columns[] = $_column;
        }

        return parent::update( $_columns );
    }

    /**
     * Forces an exception on failed delete
     *
     * @throws \CDbException
     * @return bool
     */
    public function delete()
    {
        if ( !parent::delete() )
        {
            if ( $this->_throwOnError )
            {
                throw new \CDbException( $this->getErrorsForLogging() );
            }

            return false;
        }

        return true;
    }

    /**
     * Retrieves a list of models based on the current search/filter conditions.
     * This version serves no purpose other than to allow you to pass in an existing criteria set
     *
     * @param \CDbCriteria $criteria
     *
     * @return bool the data provider that can return the models based on the search/filter conditions.
     */
    public function search( $criteria = null )
    {
        $_criteria = $criteria ?: new \CDbCriteria;

        return new \CActiveDataProvider(
            $this, array(
                'criteria' => $_criteria,
            )
        );
    }

    /**
     * @param string $modelClass
     *
     * @return BaseFactoryModel
     */
    public function setModelClass( $modelClass )
    {
        $this->_modelClass = $modelClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getModelClass()
    {
        return $this->_modelClass;
    }

    /**
     * @return \CDbTransaction
     */
    public function getTransaction()
    {
        return $this->_transaction;
    }

    /**
     * @param boolean $throwOnError
     *
     * @return BaseFactoryModel
     */
    public function setThrowOnError( $throwOnError )
    {
        $this->_throwOnError = $throwOnError;

        return $this;
    }

    /**
     * @return boolean
     */
    public function getThrowOnError()
    {
        return $this->_throwOnError;
    }

    //*************************************************************************
    //* Named Scopes
    //*************************************************************************

    /**
     * Selects a row from the database that has a hashed ID. Like from a form.
     * This allows you to hide your PKs/IDs from prying eyes
     *
     * @param string $id
     * @param bool   $isHashed
     * @param string $salt The salt to use for hashing. Defaults to the database password
     *
     * @return BaseFactoryModel
     */
    public function unhashId( $id, $isHashed = false, $salt = null )
    {
        $_salt = str_replace( "'", "''", $salt ?: $this->getDb()->password );

        //	Use MySQL's sha1() function so hashing is now DSP-dependent
        $_condition = <<<TEXT
sha1(concat('{$_salt}',id)) = :hashed_id
TEXT;

        $this->getDbCriteria()->mergeWith(
            array(
                'condition' => $_condition,
                'params'    => array(
                    ':hashed_id' => $isHashed ? $id : $_salt,
                ),
            )
        );

        return $this;
    }

    /**
     * Named scope
     *
     * @param int  $userId
     * @param bool $adminView If true, all users' rows are returned
     *
     * @return $this
     */
    public function userOwned( $userId = null, $adminView = false )
    {
        $_condition = $_params = array();

        //	Admin views have limited restrictions
        if ( $adminView )
        {
            if ( $this->hasAttribute( 'admin_ind' ) || ( $this->hasRelated( 'user' ) && $this->getRelated( 'user' )->hasAttribute( 'admin_ind' ) ) )
            {
                if ( 1 != $this->getRelated( 'user' )->admin_ind )
                {
                    $adminView = false;
                }
            }
        }

        if ( !$adminView )
        {
            $_condition[] = 'user_id = :user_id';
            $_params[':user_id'] = $userId ?: Pii::user()->getId();
        }

        $this->getDbCriteria()->mergeWith(
            array(
                'condition' => implode( ' AND ', $_condition ),
                'params'    => $_params,
            )
        );

        return $this;
    }

    /**
     * Criteria that limits results to system-owned
     *
     * @return BaseFactoryModel
     */
    public function systemOwned()
    {
        return $this->userOwned( 0 );
    }

    //*******************************************************************************
    //* Transaction Management
    //*******************************************************************************

    /**
     * Checks to see if there are any transactions going...
     *
     * @return boolean
     */
    public function hasTransaction()
    {
        return ( null !== $this->_transaction );
    }

    /**
     * Begins a database transaction
     *
     * @throws \CDbException
     * @return \CDbTransaction
     */
    public function transaction()
    {
        if ( $this->hasTransaction() )
        {
            throw new \CDbException( 'Cannot start new transaction while one is in progress.' );
        }

        return $this->_transaction = $this->getDbConnection()->beginTransaction();
    }

    /**
     * Commits the transaction at the top of the stack, if any.
     *
     * @throws \CDbException
     */
    public function commit()
    {
        if ( $this->hasTransaction() )
        {
            $this->_transaction->commit();
        }
    }

    /**
     * Rolls back the current transaction, if any...
     *
     * @throws \CDbException
     */
    public function rollback( \Exception $exception = null )
    {
        if ( $this->hasTransaction() )
        {
            $this->_transaction->rollback();
        }

        //	Throw it if given
        if ( null !== $exception )
        {
            throw $exception;
        }
    }

    /**
     * Executes the SQL statement and returns all rows. (static version)
     *
     * @param mixed   $criteria         The criteria for the query
     * @param boolean $fetchAssociative Whether each row should be returned as an associated array with column names as the keys or the array keys are column indexes (0-based).
     * @param array   $parameters       input parameters (name=>value) for the SQL execution. This is an alternative to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing them in this way can improve the performance. Note that you pass parameters in this way, you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa. binding methods and  the input parameters this way can improve the performance. This parameter has been available since version 1.0.10.
     *
     * @return array All rows of the query result. Each array element is an array representing a row. An empty array is returned if the query results in nothing.
     * @throws \CException execution failed
     * @static
     */
    public static function queryAll( $criteria, $fetchAssociative = true, $parameters = array() )
    {
        if ( null !== ( $_builder = static::getDb()->getCommandBuilder() ) )
        {
            if ( null !== ( $_command = $_builder->createFindCommand( static::model()->getTableSchema(), $criteria ) ) )
            {
                return $_command->queryAll( $fetchAssociative, $parameters );
            }
        }

        return null;
    }

    /**
     * Convenience method to execute a query (static version)
     *
     * @param string $sql
     * @param array  $parameters
     *
     * @return int The number of rows affected by the operation
     */
    public static function execute( $sql, $parameters = array() )
    {
        return Sql::execute( $sql, $parameters, static::getDb()->getPdoInstance() );
    }

    /**
     * Convenience method to execute a scalar query (static version)
     *
     * @param string $sql
     * @param array  $parameters
     *
     * @param int    $columnNumber
     *
     * @return int|string|null The result or null if nada
     */
    public static function scalar( $sql, $parameters = array(), $columnNumber = 0 )
    {
        return Sql::scalar( $sql, $columnNumber, $parameters, static::getDb()->getPdoInstance() );
    }

    /**
     * Convenience method to get a database connection to a model's database
     *
     * @return \CDbConnection
     */
    public static function getDb()
    {
        return static::model()->getDbConnection();
    }

    /**
     * Convenience method to get a database command model's database
     *
     * @param string $sql
     *
     * @return \CDbCommand
     */
    public static function createCommand( $sql )
    {
        return static::getDb()->createCommand( $sql );
    }

    /**
     * Returns an array of data suitable to pass directly to a form
     *
     * @param string     $textColumn THe "name" column for the OPTION tags. This is what shows in the dropdown
     * @param string     $idColumn   The "value" column for the OPTION tags. Defaults to "id"
     * @param string|int $order      The sort order. Defaults to the $textColumn
     *
     * @return array
     */
    public static function listData( $textColumn, $idColumn = 'id', $order = null )
    {
        $_models = static::model()->findAll(
            array(
                'select' => $idColumn . ', ' . $textColumn,
                'order'  => $order ?: 2,
            )
        );

        if ( empty( $_models ) )
        {
            return array();
        }

        $_data = Pii::listData( $_models, $idColumn, $textColumn );

        unset( $_models );

        return $_data;
    }

    /**
     * Returns a generic type suitable for type-casting
     *
     * @param \CDbColumnSchema $column
     *
     * @return string
     */
    public function determineGenericType( $column )
    {
        $_simpleType = strstr( $column->dbType, '(', true );
        $_simpleType = strtolower( $_simpleType ?: $column->dbType );

        switch ( $_simpleType )
        {
            case 'bool':
                return 'boolean';

            case 'double':
            case 'float':
            case 'numeric':
                return 'float';

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
            case 'integer':
                if ( $column->size == 1 )
                {
                    return 'boolean';
                }

                return 'integer';

            case 'binary':
            case 'varbinary':
            case 'blob':
            case 'mediumblob':
            case 'largeblob':
                return 'binary';

            case 'datetimeoffset':
            case 'timestamp':
            case 'datetime':
            case 'datetime2':
                return 'datetime';

            //	String types
            default:
            case 'string':
            case 'char':
            case 'text':
            case 'mediumtext':
            case 'longtext':
            case 'varchar':
            case 'nchar':
            case 'nvarchar':
                return 'string';
        }
    }
}
