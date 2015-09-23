# emailparse (or emailblog)

This module is called emailparse because you can send messages from your phone to
your emailparse. A better name may have been emailblog, as you really just need
to send emails to your server and then the system will export sent emails to
your blog. 

# Requirements

You will need a working IMAP server. With some modifications you would be able 
to create this with e.g. pop3 or any other mail server system, but this uses
IMAP because IMAP has more options than pop3, and at the same time it is 
possible to keep it quite simple. 

# IMAP setup

courier IMAP setup is a long story. All I can say is that the users table is
setup the same way as described in this article: 

http://articles.slicehost.com/2008/9/2/mail-server-adding-domains-and-users-to-mysql

# Database

Normal if your are hosting multiple emails you want to keep your users in
a separate database. Create a database called 'mail'

    CREATE DATABASE `mail`;

# Users table

As described in the slicehost article all IMAP users exists in the following
table:

    CREATE TABLE `users` (
    `email` varchar(80) NOT NULL,
    `password` varchar(20) NOT NULL,
    PRIMARY KEY (`email`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1

We need some additionel info and create an user_alias table

    CREATE TABLE `users_alias` (
    `server_name` varchar(80) NOT NULL,
    `user_id` varchar(80) NOT NULL,
    `parent_id` int(10) NOT NULL,
    PRIMARY KEY (`email`)
    ) ENGINE=MyISAM DEFAULT CHARSET=latin1

As everything in the emailparse is imported from IMAP server we also need to change
this a bit. We add another password, which users have to specify in emails 
sent to the servers. If this password is not present then messages is just 
deleted. 

    ALTER table `users` ADD COLUMN `password_email` varchar(8) DEFAULT '';

The password defaults to '' because you may have other emails in your 
users table, added in other ways, which are not used with emailparse.

# Edit configuration:

edit emailparse/emailparse.ini, and set:

    ; the email domain you will be using
    emailparse_domain = 'os-cms.dk'
    ; the name of the mail users database
    emailparse_database = 'mail'
    ; the default IMAP password of all users
    ; the same password is used for ALL users. Set a good one
    ; the password is in clear text, and will be encrypted by
    ; mysql
    emailparse_imap_password = 'password'

INSERT INTO `users` (email, password)  values ('mail@phoneblogger.me', encrypt('password') );
