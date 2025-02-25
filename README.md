# Qoliber - Magerun

Command Lines for Magerun to 
* data-trimmed DB dumps (additional tables added)
* compile themes and run production mode 95% faster.
* optimize `NonComposerComponentRegistration.php` file (saves between `30-80ms` on page loading times) 
For more info visit the YAML file.


## Installation

### Require the package

>  composer require qoliber/magerun

## New Commands:

* `qoliber:magerun:theme:active`: Get list of used themes

    ### Example:
    ```bash
    www-data@www-data@mageos-php-fpm:/var/www/html$ ./n98-magerun2.phar qoliber:magerun:theme:active
    --theme Qoliber/default --theme Magento/luma --theme Hyva/default --theme Magento/backend adsasd
    ```


* `qoliber:magerun:locale:active`: Get list of active locales

    ### Example:
    ```bash
    www-data@mageos-php-fpm:/var/www/html$ ./n98-magerun2.phar qoliber:magerun:locale:active
    en_US pl_PL
    ```

  * `qoliber:magerun:mode:production`: Set production mode but compile only used themes and locales
  
      ### Only compiles required themes and locales that are active, examples. Static compilation:

    | `php bin/magento deploy:mode:set production`                          | `n98-magerun2.phar qoliber:magerun:mode:production`          |
    |-----------------------------------------------------------------------|--------------------------------------------------------------|
    | **2 store views** <br/> **2 locales** | 2 store views <br/> 2 locales                                |
    | Time: **Execution time: <span style="color: red;">49.60s</span>**     | Execution time: **<span style="color: green;">4.13s</span>** |
    | **8 store views** <br/> **2 locales** | 8 store views <br/> 2 locales                                |
    | Time: **Execution time: <span style="color: red;">373.39s</span>**    | Execution time: **<span style="color: green;">5.57s</span>** |


* `qoliber:magerun:non-composer-autoloader`: Removes `glob` from `app/etc/NonComposerComponentRegistration.php` - **use only in production mode**

    ### Replaces content of `app/etc/NonComposerComponentRegistration.php` file like this:

    ```php
      $registrationFiles = array (
          0 => '/var/www/html/app/code/MerchantUniqueFeatures/ShippingBoxes/registration.php',
          1 => '/var/www/html/app/code/MerchantUniqueFeatures/Showoutofstockprice/registration.php',
          2 => '/var/www/html/app/code/MerchantUniqueFeatures/Extensions/registration.php',
          3 => '/var/www/html/app/code/MerchantUniqueFeatures/UrlOptimization/registration.php',
          4 => '/var/www/html/app/code/MerchantUniqueFeatures/LayoutProcessorPlugin/registration.php',
          5 => '/var/www/html/app/code/MerchantUniqueFeatures/AutoAssignSources/registration.php',
          6 => '/var/www/html/app/code/MerchantUniqueFeatures/StockFilter/registration.php',
          7 => '/var/www/html/app/code/MerchantUniqueFeatures/Pay2Ship/registration.php',
          //
          97 => '/var/www/html/app/design/frontend/MerchantUniqueTheme/Theme1/registration.php',
          98 => '/var/www/html/app/design/frontend/MerchantUniqueTheme/Theme2/registration.php',
    [...]
    ```

Enjoy, @qoliber team