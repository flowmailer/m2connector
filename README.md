# Magento 2 Connector for Flowmailer

This extension allows you to configure Magento 2 to send all emails using Flowmailer including raw data.

See [flowmailer.com](https://flowmailer.com/) for more information.

## Compatibility

| Module version | Magento / Adobe Commerce | PHP            |
|----------------|--------------------------|----------------|
| `^2.2`         | 2.4.7, 2.4.8 (+ patches) | 8.1, 8.2, 8.3, 8.4 |
| `^2.1`         | 2.4.4 – 2.4.7            | 7.4, 8.0, 8.1, 8.2 |
| `^1.0`         | 2.3, 2.4 (< 2.4.4)       | 7.3, 7.4, 8.0      |

Magento 2.4.8 removed the `laminas/laminas-mail` dependency. Module versions `< 2.2` therefore
fail at runtime on 2.4.8 with `Class "Laminas\Mail\Message" not found`. Upgrade to `^2.2`.

## Installation

A normal installation would be something equal to:
```bash
composer require flowmailer/flowmailer-php-sdk flowmailer/m2connector symfony/http-client nyholm/psr7
```

Choose your preferred [flowmailer-php-sdk implementations](https://packagist.org/providers/flowmailer/flowmailer-php-sdk-implementation) on packagist, based on your minimum requirement for PHP.

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

## Upgrading to 2.2.x

The 2.2 release removes the hard dependency on `laminas/laminas-mail`, which was
removed from Magento core in 2.4.8. The plugin now consumes Magento's own
`Magento\Framework\Mail\MessageInterface` directly. Existing template-variable
handling and admin configuration are unchanged.

After upgrade run:
```bash
composer update flowmailer/m2connector flowmailer/flowmailer-php-sdk
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

## Development

Static analysis uses the official PHPStan bridge for Magento.

```bash
composer install
composer analyse
```

See `phpstan.neon` for the configured rule level.
