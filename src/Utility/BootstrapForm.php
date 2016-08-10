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
namespace DreamFactory\Yii\Utility;

use DreamFactory\Common\Enums\PageLocation;
use Kisma\Core\Utility\Bootstrap;
use Kisma\Core\Utility\Option;

/**
 * BootstrapForm
 * Yii helper junk
 */
class BootstrapForm extends Bootstrap
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * Creates a standard page header
     *
     * @param array $options
     *
     * @return array
     */
    public function pageHeader( $options = array() )
    {
        $_title = null;
        $_saveToState = Option::get( $options, 'save_to_state', false );

        //	Shortcut... only passed in the title...
        if ( !empty( $options ) && is_string( $options ) )
        {
            $_title = $options;

            $options = array(
                'title'       => Pii::appName() . ' | ' . $_title,
                'breadcrumbs' => array($_title),
            );
        }

        $_formId = Option::get( $options, 'id', 'df-edit-form' );

        //	Put a cool flash span on the page
        if ( Option::get( $options, 'enable_flash', true, true ) )
        {
            $this->displayFlashMessage( $options );
        }

        $_formOptions = array(
            'id'                    => $_formId,
            'show_dates'            => Option::get( $options, 'show_dates', false ),
            'method'                => Option::get( $options, 'method', 'POST' ),
            'form_type'             => Option::get( $options, 'form_type', static::Vertical ),
            'form_class'            => Option::get( $options, 'form_class' ),
            'error_css'             => Option::get( $options, 'error_css', 'error' ),
            //	We want error summary...
            'error_summary'         => Option::get( $options, 'error_summary', true ),
            'error_summary_options' => array(
                'header' => '<p class="df-error-summary">The following problems occurred:</p>',
            ),
            'validate'              => Option::get( $options, 'validate', true ),
            'validate_options'      => $_validateOptions = array_merge(
                array(
                    'ignore_title' => true,
                    'error_class'  => 'df-validate-error',
                ),
                Option::get( $options, 'validate_options', array() )
            ),
        );

        $_crumbs = Option::get( $options, 'breadcrumbs', null, true );

        if ( !empty( $_crumbs ) )
        {
            $_trail = '<ul class="breadcrumb">';

            foreach ( $_crumbs as $_name => $_link )
            {
                if ( false !== $_link )
                {
                    $_trail .= '<li><a href="' . ( '/' != $_link ? Pii::url( $_link ) : $_link ) . '">' . $_name . '</a></li>';
                }
                else
                {
                    $_trail .= '<li class="active">' . $_name . '</li>';
                }
            }

            Pii::setState( 'local.breadcrumbs', $_formOptions['breadcrumbs'] = ( $_trail . '</ul>' ) );

            if ( !$_saveToState )
            {
                echo $_formOptions['breadcrumbs'];
            }
        }

        if ( !Pii::isEmpty( $_header = Option::get( $options, 'header' ) ) )
        {
            Pii::setState(
                'local.header',
                $_formOptions['header'] = static::wrap(
                    'h2',
                    $_header . static::wrap(
                        'small',
                        '<em>' . Option::get( $options, 'sub_header' ) . '</em>',
                        array('style' => 'margin-left:8px;')
                    )
                )
            );

            if ( !$_saveToState )
            {
                echo $_formOptions['header'];
            }
        }

        if ( Option::get( $options, 'validate', true ) )
        {
            Validate::register( 'form#' . $_formId, $_validateOptions );
        }

        //	Show any pent-up error messages
        $this->displayErrorMessage( $options );

        return $_formOptions;
    }

    /**
     * @param array $options
     */
    public function displayFlashMessage( $options = array() )
    {
        $_flashClass = Option::get( $options, 'flash_success_class', 'success' );
        $_flashTitle = 'Success!';

        if ( null === ( $_message = Pii::getFlash( 'success' ) ) )
        {
            if ( null !== ( $_message = Pii::getFlash( 'failure' ) ) )
            {
                $_flashTitle = 'There was a problem...';
                $_flashClass = Option::get( $options, 'flash_failure_class', 'error' );
            }
        }

        if ( null !== ( $_flashText = $_message ) )
        {
            $_spanId = Option::get( $options, 'flash_span_id', 'operation-result', true );

            Pii::setState(
                'form_flash_html',
                static::tag(
                    'span',
                    array(
                        'id'    => $_spanId,
                        'class' => $_flashClass
                    ),
                    $_message
                )
            );

            //	Register a nice little fader...
            $_fader = <<<SCRIPT
notify('default',{title:'{$_flashTitle}',text:'{$_flashText}'});
SCRIPT;
            Pii::script( spl_object_hash( $this ) . '.' . $_spanId . '.fader', $_fader, PageLocation::DocReady );
        }
    }

    /**
     * @param array $options
     */
    public function displayErrorMessage( $options = array() )
    {
        $_alert = null;

        if ( null !== ( $_model = Option::get( $options, 'model' ) ) )
        {
            $_headline = Option::get( $options, 'alertMessage', 'An error occurred...' );
            $_errors = $_model->getErrors();

            if ( !empty( $_errors ) )
            {
                $_messages = null;

                foreach ( $_errors as $_error )
                {
                    foreach ( $_error as $_message )
                    {
                        $_messages .= '<p>' . $_message . '</p>';
                    }
                }

                $_fader = <<<SCRIPT
notify('default',{title:'{$_headline}',text:'{$_messages}'});
SCRIPT;
                Pii::script( spl_object_hash( $this ) . '.fader', $_fader, PageLocation::DocReady );
//				$_alert
//					= <<<HTML
//	<div class="alert alert-error alert-block alert-fixed fade in" data-alert="alert">
//		<strong>{$_headline}</strong> {$_messages}
//	</div>
//HTML;
            }
        }

        echo $_alert;
    }

    /**
     * @param string $type
     * @param array  $attributes
     * @param string $contents
     *
     * @return mixed
     * @throws \InvalidArgumentException
     * @internal param $field
     */
    protected function _handleUnknownField( $type, array $attributes = array(), $contents = null )
    {
        switch ( strtolower( $type ) )
        {
            case 'select_enum':
                if ( null === ( $_enum = Option::get( $attributes, 'enum', null, true ) ) )
                {
                    throw new \InvalidArgumentException( 'You must supply an "enum" name to use "select_enum".' );
                }

                if ( !class_exists( $_enum ) )
                {
                    throw new \InvalidArgumentException( 'The enum class "' . $_enum . '" cannot be found.' );
                }

                return static::select(
                    call_user_func( array($_enum, 'getDefinedConstants'), true ),
                    $attributes
                );
        }

        return parent::_handleUnknownField( $type, $attributes, $contents );
    }

    /**
     * Given an array of breadcrumbs, convert them to HTML and return
     *
     * @param array $crumbs
     * @param bool  $echoOutput
     *
     * @return string
     */
    public function setBreadcrumbs( $crumbs = array(), $echoOutput = false )
    {
        $_crumbs = Option::clean( $crumbs );

        if ( empty( $_crumbs ) )
        {
            return;
        }

        $_trail = '<ul class="breadcrumb">';

        foreach ( $_crumbs as $_name => $_link )
        {
            if ( false !== $_link )
            {
                $_trail .= '<li><a href="' . ( '/' != $_link ? Pii::url( $_link ) : $_link ) . '">' . $_name . '</a></li>';
            }
            else
            {
                $_trail .= '<li class="active">' . $_name . '</li>';
            }
        }

        $_trail .= '</ul>';

        if ( false === $echoOutput )
        {
            return $_trail;
        }

        echo $_trail;
    }
}
