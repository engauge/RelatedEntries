Related Entries V 0.1
=====================

Related Entries is a plugin for ExpressionEngine to automagically generate related entries. Once installed a simple call to the module will retrieve a list of related entries based on their content.

Installation
============

1. Copy the 'related' folder to you EE installation's 'third_party' folder

2. Copy cache.php to a directory outside your web root

3. Edit the specified lines in cache.php

4. Set up a Cron job to run cache.php. I have mine set up as follows:
	
	*/5 * * * * php /home/stage.plantersplace.com/cache.php

5. Enable the plugin in EE's control panel.

Usage
=====

{exp:related:entries entry_id="1" field="post" limit="5" }
	<a href='/path/to/{entry_id}'>{title}</a><br>
{/exp:related:entries}

License
=======

Related Entries is under the GPL v2 license.