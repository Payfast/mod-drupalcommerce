#drupal_commerce2.*

Integration instructions:
 You will need a working Drupal with the latest version of Drupal Commerce installed.

 Manually copy files

1. Unzip and copy the PayFast folder to the ../modules/contrib/commerce/modules directory
2. Log into the admin dashboard and install Commerce PayFast on the 'Extend' page.
3. Navigate to Commerce>Configuration>Payments and click on 'new payment gateway'
4. Select PayFast and configure as required.
5. Click Save.

 Using composer

1. In your composer.json add the following under **"repositories": [** 

    { "type": "package", "package": { "name": "payfast", "version": "1.2.0", "type": "drupal-module", "source": { "url": "https://github.com/PayFast/mod-drupalcommerce-8.git", "type": "git", "reference": "master" } } },

2. In your composer.json add the following under **"extra": { "installer-paths": {**  
    
    "modules/commerce/modules": [ "payfast" ]

3. run $ composer require 'payfast:^1.2.0'