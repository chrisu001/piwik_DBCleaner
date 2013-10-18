Plugin DBClenaer
========================
 

If you want to delete an obsolete website from your Piwik-installation or you want to
drop already processed log_* entries from your database, but you also want to archive
those data for later usage, this plugin does the job ;-)


Features
========

- extract "old"  and already processed raw-log entries (stored in the log_*-tables) to the filesystem
- archive and delete a selected WebSite 
- easy GUI frontend to execute the maintenance tasks of your database
- backup parts of your piwik database online without the need of phpmyadmin et. al.

Auto-Installation
============

1. Download latest version of the auto installer from [online repository](http://plugin.suenkel.org/).
2. Extract the plugin folder to your piwik/plugins/ folder
3. activate the plugin in Settings->Plugins 
4. navigate to the Settings->Marketplace admins panel and install DBCleaner

Manual Installation
============

1. Download latest version from [online repository](http://plugin.suenkel.org/plugin/detail/DBCleaner).
2. Extract the plugin folder to your piwik/plugins/ folder
3. activate the plugin in Settings->Plugins 
4. goto the admin panel and run DBCleaner   

Changelog
=========
> v0.3   (2013-06-25)
> -------------------
>  * upgrade to piwik 2.0
>  * minor fixes
> v0.2   (2013-05-12)
> -------------------
>  * dynamic execution time and memory usage based on hardware resources
> v0.1   (2013-04-08)
> -------------------
>  * initial release


Ressources
==========

   * Download [online repository](http://plugin.suenkel.org).
   * [News](http://plugin.suenkel.org/blog).
   * Screenshots:

![DBCleaner](http://plugin.suenkel.org/pic/g/2013/04/dbcIndex-300x147.png)

![DBCleaner](http://plugin.suenkel.org/pic/g/2013/04/dbcWebsite-300x141.png)

![DBCleaner](http://plugin.suenkel.org/pic/g/2013/04/dbcCleanup-300x162.png)

![DBCleaner](http://plugin.suenkel.org/pic/g/2013/04/dbcFiles-300x153.png)

![DBCleaner](http://plugin.suenkel.org/pic/g/2013/04/dbcPreferences-300x131.png)



MIT License
===========

Copyright (C) 2013 Christian Suenkel

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.