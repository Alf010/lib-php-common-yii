## DreamFactory Yii Components Library v1.5.15
[![Latest Stable Version](https://poser.pugx.org/dreamfactory/lib-php-common-yii/v/stable.svg)](https://packagist.org/packages/dreamfactory/lib-php-common-yii) [![Total Downloads](https://poser.pugx.org/dreamfactory/lib-php-common-yii/downloads.svg)](https://packagist.org/packages/dreamfactory/lib-php-common-yii) [![Latest Unstable Version](https://poser.pugx.org/dreamfactory/lib-php-common-yii/v/unstable.svg)](https://packagist.org/packages/dreamfactory/lib-php-common-yii) [![License](https://poser.pugx.org/dreamfactory/lib-php-common-yii/license.svg)](https://packagist.org/packages/dreamfactory/lib-php-common-yii)

This library is a set of small set of components used by the DreamFactory Services Platform&trade; for use with the [Yii Framework](https://yiiframework.com/).

# Installation

Add a line to your "require" section in your composer configuration:

	"require":           {
		"dreamfactory/lib-php-common-yii": "~1.5.0"
	}

Run a composer update:

    $ composer update

# Helpers

The library is full of mainly helper classes to make Yii less verbose and a little less greedy. Have a look at Utility\Pii.php and see how it may save you some time.

    use DreamFactory\Yii\Utility\Pii;

    if ( Pii::guest() ) {
    	$this->redirect( Pii::user()->loginUrl );
    }
