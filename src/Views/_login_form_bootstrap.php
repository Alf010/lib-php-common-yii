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
use Kisma\Core\Utility\Bootstrap;

/**
 * Expected variables:
 *
 * @var \DreamFactory\Yii\Models\Forms\SimpleLoginForm $model     The form model
 * @var boolean                                        $loginPost True if this load is a post-back
 * @var boolean                                        $success   True if post-back and login success
 * @var CPSController                                  $this
 * @var string                                         $header
 *
 * Optional variable:
 * @var string                                         $modelName Defaults to SimpleLoginForm
 */

if ( !isset( $modelName ) )
{
    $modelName = 'SimpleLoginForm';
}
else
{
    $modelName = str_replace( '\\', '_', $modelName );
}

$_errors = null;

if ( !isset( $loginPost ) )
{
    $loginPost = false;
}

if ( !isset( $success ) )
{
    $success = false;
}

if ( isset( $header ) )
{
    echo $header;
}

if ( isset( $model ) )
{
    $_errors = $model->getErrors();

    if ( !empty( $_errors ) )
    {
        echo <<<HTML
		<div class="alert alert-error fade in" data-alert="alert">
			<a class="close" href="#">x</a>

			<p><strong>There was a problem</strong></p>
HTML;

        foreach ( $_errors as $_error )
        {
            foreach ( $_error as $_message )
            {
                echo Bootstrap::tag( 'p', array(), $_message );
            }
        }

        echo <<<HTML
		</div>
HTML;
    }
    else
    {
        echo <<<HTML
<div class="alert fade in" data-alert="alert">
	<strong>Login Required</strong>
	<p>Please enter your email address and password below.</p>
</div>
HTML;
    }
}
?>
<form id="login-bootstrap" method="POST" class="form-horizontal" action>
    <fieldset>
        <legend>Login</legend>
        <div class="control-group">
            <label class="control-label" for="<?php echo $modelName; ?>_username">Email Address</label>

            <div class="controls">
                <input class="input-xlarge" id="<?php echo $modelName; ?>_username" name="<?php echo $modelName; ?>[username]" type="text">
            </div>
        </div>
        <div class="control-group">
            <label class="control-label" for="<?php echo $modelName; ?>_password">Password</label>

            <div class="controls">
                <input class="input-medium" id="<?php echo $modelName; ?>_password" name="<?php echo $modelName; ?>[password]" type="password">
            </div>
        </div>
        <div class="form-actions">
            <input type="submit" class="btn btn-primary" value="Continue">
            &nbsp;
            <button type="reset" class="btn">Cancel</button>
        </div>
    </fieldset>
</form>
