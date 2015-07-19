# Stats for Joomla!

This repo is meant to hold the Joomla anonymous stats tracking plugin for the Joomla! CMS, scheduled to be included in Joomla 3.5.

The plugin will collect anonymously the PHP, Database Type and Version and Joomla Version a user is running so that the project can set PHP and MySQL versions more accurately for future Joomla Versions.

# Configuring plugin

In order to make the plugin send the data to your own server, modify the line 
```$uri = new JUri('http://<your url>/submit');```

to use your own URL.
