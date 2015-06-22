Mautic-Joomla plugin
====================

This [Joomla](http://joomla.org) Plugin lets you add the [Mautic](http://mautic.org) tracking gif image to your Joomla website and embed Mautic forms in Joomla content. If you authorize this plugin as the Mautic API application, plugin will be able to push data from Joomla registration form to Mautic as a Lead.

This plugin is compatible with Joomla 2.5.x and Joomla 3.x.x.

### Mautic Tracking Image

Tracking image works right after you enable the plugin, insert Base URL and save the plugin. That means it will insert 1 px gif image loaded from your Mautic instance. You can check HTML source code (CTRL + U) of your Joomla website to make sure the plugin works. You should be able to find something like this:

`<img src="http://yourmautic.com/mtracking.gif" />`

There will be probably longer URL query string at the end of the tracking image URL. It is encoded additional data about the page (title, url, referrer, language).

### Form embed

To embed a Mautic form into Joomla content, insert this code snippet:

	{mauticform ID}

ID is the identifier of the Mautic form you want to embed. You can see the ID of the form in the URL of the form detail. For example for ```www.yourmautic.com/forms/view/1```, ID = 1.

### Plugin authorization

It is possible to send Lead data to Mautic via API only after authorization. You can create specific authorization API credentials for each application. To do that, go to your Mautic administration and follow these steps:

1. Go to Mautic Configuration / API Settings and set 'API enabled' to 'Yes', leave 'API mode' to 'OAuth1'. Save changes.
2. At the right-hand side menu where Configuration is should appear new menu item 'API Credentials'. Hit it.
3. Create new credential. Fill in 'Name' ('Joomla plugin' for example) and Callback URL to ```http://{yourJoomla.com}``` (change ```{yourJoomla.com}``` with actual URL of your Joomla instance). Save the credentials.
4. Mautic should generate 'Consumer Key' and 'Consumer Secret' key. Copy those two to Joomla plugin. Save the plugin. Hit the 'Authorize' button and follow instructions.

## Developer notes

If you want to add more Mautic features, submit PR to this plugin or use this plugin as a base and develop your own extension which could do more. mauticApiHelper.php class will configure the Mautic API for you.

### Release of the new version

This plugin uses Joomla Update Server which notifies the Joomla admin about availability of new versions of this plugin. To do that, update the version tag in mautic.xml in the master branch and then update version tag at updateserver.xml in the gh-pages branch accordingly.

[Current updateserver.xml](http://mautic.github.io/mautic-joomla/updateserver.xml)

### Integrate Mautic with another extension

To add Mautic lead generation to another extension is not hard at all. Just let the joomla amin install and configure this plugin then you can use Mautic API calls. Here is an example how to generate Mautic leads on any form save:

```php
// Include MauticApiHelper from the plugin 
require_once __DIR__ . '/mauticApiHelper.php';

$apiHelper  = new mauticApiHelper;
$leadApi    = \Mautic\MauticApi::getContext(
    "leads", 
    $apiHelper->getMauticAuth(), 
    $apiHelper->getMauticBaseUrl() . '/api/'
);

$lead = $leadApi->create(array(
    'ipAddress' => $_SERVER['REMOTE_ADDR'],
    'firstname' => $formData['firstname'],
    'lastname'  => $formData['lastname'],
    'email'     => $formData['email'],
));
```

More information about Mautic API calls can be found at [Mautic API Library](https://github.com/mautic/api-library) which is part of this plugin.
