# Flowmailer Magento 2 Connector

This extension allows you to configure Magento 2 to send all emails using Flowmailer including raw data.

## Installation

A normal installation would be something equal to:
```bash
composer require flowmailer/m2connector symfony/http-client nyholm/psr7
```

Choose your preferred [client implementations](https://packagist.org/providers/psr/http-client-implementation) on packagist.  
See [docs.php-http.org](https://docs.php-http.org/en/latest/httplug/users.html) for details on the HttpClient discovery.  

Enable the module:
```bash
bin/magento module:enable Flowmailer_M2Connector --clear-static-content
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento module:status Flowmailer_M2Connector
bin/magento cache:clean
```

## Configuration

Obtain credentials from [Flowmailer credentials wizard](https://dashboard.flowmailer.net/setup/sources/credentialswizard.html)

Go to http://your-magento-store/admin and login with your admin credentials.

Navigate to Stores > Configuration > Flowmailer > Connector and add API Credentials.
