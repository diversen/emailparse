<?php

use diversen\conf;
use diversen\date;
use diversen\db;
use diversen\file;
use diversen\imap;
use diversen\log;
use diversen\moduleloader;
use diversen\strings;
use diversen\strings\normalize;


moduleloader::includeModule('image/ext');



function emailparse_parse_all_users () {
    
    $db = new db();
    $users_alias = $db->selectAll('emailparse_email');

    foreach ($users_alias as $user) {                
        emailparse_parse_user_emails($user);
    }
}


/**
 * parse a single users emails
 * @param array $user
 */
function emailparse_parse_user_emails($user) {

    $db = new db();
    $connect = array(
        'host' => conf::getMainIni('imap_host'),
        'port' => conf::getMainIni('imap_port'),
        'user' => $user['email'],
        'password' => conf::getMainIni('imap_password'),
        'ssl' => conf::getMainIni('imap_ssl')
    );

    $imap = new imap();
    $imap->connect($connect);

    $num_mes = $imap->countMessages();

    $imap->noop();
    for ($i = 1; $i <= $num_mes; $i++) {

        // get from emails as an array
        $from_email = $imap->getMessageFromEmail($i);
        $from_emails = emailparse_email_from_str($user['email_from']);

        // if email sender is not set we continue
        if (!in_array($from_email, $from_emails)) {
            continue;
        }

        $message = $imap->getMessage($i);
        $parts = $imap->getParts($message);

        $values = array();

        // insert entry
        db::begin();

        $values['entry'] = strings::toUTF8($parts['plain']);
        $values['title'] = strings::toUTF8($parts['subject']);
        $values['user_id'] = $user['user_id'];
        $values['created'] = date::getDateNow(array('hms' => true));
        $values['published'] = 1;

        $db->insert('blog', $values);

        $last_insert_id = $db->lastInsertId();
        db::commit();

        // add images to post
        $ary = emailparse_parse_image_parts($parts, $last_insert_id, $values);

        // generate markdown text - attach
        foreach ($ary as $image) {
            $values['entry'].= "\n\n![$image[title]](/image/download/$image[id]/$image[title])\n\n";
        }

        db::begin();
        $db->update('blog', array('entry' => $values['entry']), $last_insert_id);
        db::commit();
    }

    // remove messages
    for ($i = $num_mes; $i >= 1; $i--) {
        log::debug("removing message with id : $i");
        $imap->removeMessage($i);
    }
}

/**
 * parse the image parts
 * @param array $parts
 * @param int $last_insert_id
 * @param array $entry
 * @return array $ary array with all images
 */
function emailparse_parse_image_parts($parts, $last_insert_id, $entry) {
    $c = 1;
    $ret_ary = array();
    foreach ($parts['images'] as $image) {
        $ary = array();
        $values = array();
        $ext = file::getExtension($image['name']);
        $title = strings::utf8SlugString($entry['title']);
        if (empty($title)) {
            $title = 'no_title';
        }

        $values['title'] = "$title-$c.$ext";
        $ary['title'] = $values['title'];
        $values['mimetype'] = $image['type'];
        $values['file_org'] = $image['file'];
        $values['parent_id'] = $last_insert_id;
        $values['reference'] = 'blog';
        $values['user_id'] = $entry['user_id'];

        db::begin();
        $i = new image_ext();
        $i->insertFile($values);
        $last_id = db::lastInsertId();
        db::commit();
        $ary['id'] = $last_id;

        $ret_ary[] = $ary;

        $c++;
    }
    return $ret_ary;
}




/**
 * 
 * XXX: Remove double
 * get all emails as an array from a string
 * @param string $from
 * @return array
 */
function emailparse_email_from_str($from) {
    $from_emails = normalize::newlinesToUnix($from);
    $from_emails = explode("\n", $from_emails);
    return $from_emails;
}


if (conf::isCli()) {

    self::setCommand('emailparse', array(
        'description' => "Cron command for emailparse ",
    ));

    self::setOption('emailparse_parse_all_users', array(
        'long_name' => '--cron',
        'description' => 'Will import IMAP mails from all users, and put them on the web',
        'action' => 'StoreTrue'
    ));
}