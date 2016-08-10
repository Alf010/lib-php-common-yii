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

use DreamFactory\Common\Interfaces\PageLocation;
use Kisma\Core\Utility\Option;

/**
 * Validate.php
 * A jQuery Validation (http://docs.jquery.com/Plugins/Validation) helper.
 */
class Validate implements PageLocation
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var string
     */
    const DEFAULT_VERSION = '1.11.1';
    /**
     * @var string
     */
    const VERSION_TAG = '{version}';
    /**
     * @var string The CDN root
     */
    const CDN_ROOT = '//ajax.aspnetcdn.com/ajax/jquery.validate/{version}/';

    //********************************************************************************
    //* Methods
    //********************************************************************************

    /**
     * Registers the needed CSS and JavaScript.
     *
     * @param string       $selector
     * @param string|array $options
     *
     * @return \CClientScript The current app's ClientScript object
     */
    public static function register( $selector, $options = array() )
    {
        //	Only load scripts on page once...
        static $_firstRun = true;

        $_cleanName = preg_replace( array("/[^a-z0-9\.\-]/i", "/\-/"), array('', '_'), $selector );
        $_validate = null;

        //	Don't screw with div formatting...
        if ( null === Option::get( $options, 'error_placement' ) )
        {
            $options['errorPlacement'] = <<<SCRIPT
function(error,element) {
	error.appendTo(
	    $(element).closest('.form-group')
    );
}
SCRIPT;
        }

        if ( null === Option::get( $options, 'highlight' ) )
        {
            $options['highlight'] = <<<SCRIPT
function( element, errorClass ) {
	$(element)
    	.closest('.form-group')
       	    .removeClass('has-success has-feedback')
	        .addClass('has-error has-feedback');
}
SCRIPT;
        }

        if ( null === Option::get( $options, 'unhighlight' ) )
        {
            $options['unhighlight'] = <<<SCRIPT
function( element, errorClass ) {
	$(element)
	    .closest('.form-group')
	        .removeClass('has-error has-feedback')
	        .addClass('has-success has-feedback');
}
SCRIPT;
        }

        //	Get the options...
        $_scriptOptions = is_string( $options ) ? $options : PiiScript::encodeOptions( $options );

        if ( $_firstRun )
        {
            //	Add US Phone validator if requested
            if ( false !== Option::get( $options, 'add_phone_us', false, true ) )
            {
                $_validate .= <<<SCRIPT
jQuery.validator.addMethod("phoneUS",function(phone_number, element) { phone_number = phone_number.replace(/\s+/g, ""); return this.optional(element) || phone_number.length > 9 && phone_number.match(/^(1[\s\.-]?)?(\([2-9]\d{2}\)|[2-9]\d{2})[\s\.-]?[2-9]\d{2}[\s\.-]?\d{4}$/);},"Please specify a valid phone number");
SCRIPT;
            }

            //	Add postal code validator if requested
            if ( false !== Option::get( $options, 'add_postal_code', false, true ) )
            {
                $_validate .= <<<SCRIPT
jQuery.validator.addMethod("postalCode",function(code, element) {return this.optional(element) || code.match(/(^\d{5}(-\d{4})?$)|(^[ABCEGHJKLMNPRSTVXYabceghjklmnpstvxy]{1}\d{1}[A-Za-z]{1} ?\d{1}[A-Za-z]{1}\d{1})$/);},"Please specify a valid postal/zip code");
SCRIPT;
            }

            $_validate .= <<<SCRIPT
SCRIPT;

            $_cdnRoot = str_replace( static::VERSION_TAG, Pii::getParam( 'version.jquery-validate', static::DEFAULT_VERSION ), static::CDN_ROOT );

            //	Register the jquery plugin
            Pii::scriptFile(
                array(
                    $_cdnRoot . 'jquery.validate.min.js',
                    $_cdnRoot . 'additional-methods.min.js',
                ),
                static::End
            );

            $_firstRun = false;
        }

        $_validate .= PHP_EOL . 'var _validator_' . ( $_cleanName ) . ' = $("' . $selector . '").validate(' . $_scriptOptions . ');';

        //	Add to the page load
        return Pii::script( 'df.validate.' . $_cleanName, $_validate );
    }
}