groupmail
=========

PHP simple group mail

This is a simple solution to send e-mails to a specific group of people.

Use case
--------

you want to send an e-mail to a group of users, but you do not want to use
mailchimp, googlegroups or sympa.

You only need a POP account and a server with PHP and cron.

How it works
------------

Specify the connection and group details using the web form, add a cronjob
which calls the same file periodically and send the mails to the specified
mailbox. This script will then pick up the mails and relay them to the given
list of users.

FAQ
---

* *Q: What is the "secret" for?*
   A: You don't want to allow anyone to send mails to the group - you therefore
   configure a "secret" per group and add the secret to the subject of an
   e-mail you want to send. The script will check the secret and remove it
   from the subject line. The subject needs to have this format:
   "[secret] my subject" (without the quotes)
* *But it's not save to store the mailbox config in a plain text file!?*
   A: Use a htaccess file to protect the form and the .cfg file
* *Q: Putting the data in a file is not thread save!?*
   A: We want to keep things simple - if you need a more sophisticated solution
   you can still modify the configHandler class.

Example
-------
See this example to see how it looks like:
![groupmail example](https://raw.githubusercontent.com/adfinis-sygroup/groupmail/master/screenshot.png "Groupmail example")
