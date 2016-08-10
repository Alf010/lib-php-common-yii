## Change Log

### v1.5.15 (2015-03-01)
* Hosted system reporting changes

### v1.5.14 (2014-11-20)
* Pii updates

### v1.5.13 (2014-11-14)
* Pii updates for bug fixes and more decoupling.

### v1.5.11 (2014-10-04)
* Pii updates for fabric hosting and bug fixes.

### v1.5.3 (2014-08-12)
* ensure private storage area for hosted DSPs is correct

### v1.5.2 (2014-06-23)
* composer update for data format fix in common

### v1.5.1 (2014-06-10)
* fix data format for datetime format option

### v1.5.0 (2014-06-04)
* update yii dependency to get our yii framework changes 

### v1.4.6 (2014-05-15)
* src/Controllers/BaseFactoryController.php: 
	Graylog pushes now only occur when the "dsp.fabric_hosted" common configuration option is TRUE

### v1.4.3 (2014-03-18)
* Moved dependent libraries to GitHub from BitBucket but the composer.lock file had a prior version of dreamfactory/lib-php-common that pointed to BitBucket.
