<?php

namespace modules\emailparse;

use diversen\conf;
use diversen\db;
use diversen\db\admin;
use diversen\html;
use diversen\http;
use diversen\lang;
use diversen\layout;
use diversen\random;
use diversen\session;
use diversen\valid;

class module {
    
    public function settingsAction () {
        
        if (!session::isUser()) {
            http::locationHeader('/account/index', lang::translate('Login or sign up'));
        }
        
        $parent = conf::getModuleIni('emailparse_parent');        
        layout::setParentModuleMenu($parent);

        if (!emailparse_user_exists()) {

            if (isset($_POST['submit'])) {
                $res = emailparse_validate_email_user();        
                if ($res !== true) {
                    html::errors($res);
                } else {
                    emailparse_create_email();
                    $message = lang::translate('emailparse_email_added_action');
                    http::locationHeader('/emailparse/settings', $message);
                }
            }
            echo emailparse_settings_form();
        } else {
            echo emailparse_user_info();
        }
    }
    
    public function editAction () {
        
        if (!session::isUser()) {
            http::locationHeader('/account/index', lang::translate('Login or sign up'));
        }
        
        $parent = conf::getModuleIni('emailparse_parent');        
        layout::setParentModuleMenu($parent);

        if (isset($_POST['submit'])) {
            $res = emailparse_validate_email_from($_POST['email_from']);
            if ($res !== true) {
                html::errors($res);
            } else {
                $values = array ('email_from' => $_POST['email_from']);
                $db = new db();
                $db->update(
                        'emailparse_email', 
                        $values, 
                        array ('user_id' => session::getUserId()));
                session::setActionMessage(lang::translate('Settings has been updated!'));
            }

        }
        echo emailparse_settings_email_form();
    }

    
    /**
     * events: attach the account_locales module to a parent module
     * @param type $args
     * @return type
     */
    public static function events ($args) {
        if ($args['action'] == 'attach_module_menu') {
            return self::getModuleMenuItem($args);
        }

        if ($args['action'] == 'insert') {            
            return self::insertEvent($args);
        }
    }
    
    /**
     * create a module menu item for setting locales per account
     * @param array $args
     * @return array $menu menu item
     */
    public static function getModuleMenuItem ($args) {
        
        $ary = array(
            'title' => lang::translate('Enable email'),
            'url' => '/emailparse/settings?no_action=true',
            'auth' => 'user');
        return $ary;
    }
}


/**
 * checks if a user exists in table emailparse_email
 * @return boolean
 */
function emailparse_user_exists () {

    $db = new db();
    $row = $db->selectOne('emailparse_email', 'user_id', session::getUserId());
    if (!empty($row)) {
        return true;
    }
    return false;
}

/**
 * return the settings html form
 * @return string $html
 */
function emailparse_settings_form () {
    $_POST = html::specialEncode($_POST);
    $f = new html();
    $f->formStart();
    $f->init(null, 'submit');
    $f->legend(lang::translate('Create an email you can send messages to'));
    
    $domain = conf::getModuleIni('emailparse_domain');
    $f->label('email_user', lang::translate("Enter your user name. Only a-z and 0-9 and at least 7 characters"), 
            array ('required' => true));
    $f->text('email_user');
    $f->label('email_from', lang::translate('Add email which you will send from - so that anybody can not just send to your blog'),
            array ('required' => true));
    $f->textareaSmall('email_from');
    $f->submit('submit', lang::translate('Submit'));
    $f->formEnd();
    return $f->getStr();
}

/**
 * returns the form which sets the emails the user can send from
 * @return string
 */
function emailparse_settings_email_form () {
    $db = new db();
    $row = $db->selectOne('emailparse_email', 'user_id', session::getUserId());
    
    
    $f = new html();
    $f->init($row, 'submit');
    $f->formStart();
    $f->legend(lang::translate('Email address from where we accept posts'));
    $f->label('email_from', lang::translate('Allowed sender email(s). To keep order MUST add newlines. You can only send from these emails!'));
    $f->textareaSmall('email_from');
    $f->submit('submit', lang::translate('Submit'));
    $f->formEnd();
    return $f->getStr();
}


/**
 * validates a email before adding it to the system
 * @return array|boolean $res array with errors or true
 */
function emailparse_validate_email_user () {
    $errors = array ();
    if (!preg_match('/^([a-z0-9]+)$/', $_POST['email_user'])) {
        $errors[] = lang::translate('Not a valid email - only a-z and 0-9 characters');
    }
    
    if (strlen($_POST['email_user']) < 7 ) {
        $errors[] = lang::translate('Email needs to be at least 7 characters long');
    }
      
    $db = new db();
    $row = $db->selectOne('emailparse_email', 'id', session::getUserId());
    if (!empty($row)) {
        $errors[] = lang::translate('This email can not be picked - it already exists in our system');
    }
    
    $admin = new admin();
    $email_db = conf::getModuleIni('emailparse_database');
    $admin->changeDB($email_db);
    
    $email = $_POST['email_user'] . '@' . conf::getModuleIni('emailparse_domain');
    $row_users = $db->selectOne('users', 'email', $email);
    
    if (!empty($row_users)) {
        $errors[] = lang::translate('This email can not be picked - it already exists in our system');
        return $errors;
    } 

    $res = emailparse_validate_email_from($_POST['email_from']);

    if ($res !== true) {
        $errors = array_merge($res, $errors);
    }
    
    if (!empty($errors)) {
        return $errors;
    } 
    
    $admin->changeDb(); 
    return true;
}

/**
 * validate the emails which the user can send from
 * @param string $from
 * @return array|true $res array with errors or true
 */
function emailparse_validate_email_from ($from) {
    $errors = array () ;
    $mails = explode("\n" , $from);
    $num = count($mails);
    if ($num > 10 ) {
        $errors[] = lang::translate('You can only have 10 email addresses from where you send emails');
    }
    
    foreach ($mails as $mail) {
        $mail = trim($mail);
        
        if (!valid::email($mail)) {
            $errors[] = lang::translate('Not all emails are valid - remember new lines between each email');
            break;
        }
    }    
    
    if (!empty($errors)) {
        return $errors;
    } 
    return true;
}


/**
 * generates the email which the user will be sending emails to
 */
function emailparse_create_email () {

    $email = $_POST['email_user'] . "@" . conf::getModuleIni('emailparse_domain');
    
    // generate random password
    $random = strtolower(random::string(8));
    $values = array (
        'email' => $email, 
        'email_from' => $_POST['email_from'],
        'password' => $random,
        'server_name' => conf::getMainIni('server_name'),
        'user_id' => session::getUserId());
    
    $db = new db();
    $db->insert('emailparse_email', $values);

    $email_db = conf::getModuleIni('emailparse_database');
    
    $admin = new admin();
    $admin->changeDB($email_db);
    
    // default imap email password. Used for tests
    $password = conf::getModuleIni('emailparse_imap_password');
    
    $sql = "INSERT INTO `users` (email, password) 
            values 
            ('$email', encrypt('$password') )";
    
    $db->rawQuery($sql);
    $admin->changeDB();
}


function emailparse_user_info () {
    $db = new db();
    $row = $db->selectOne('emailparse_email', 'user_id', session::getUserId());
   
    $str = '';
    $str.= lang::translate('You have enabled blogging with a phone');
    $str.= "<br />";

    $str.= lang::translate('Your email is');
    
    $str.= MENU_SUB_SEPARATOR_SEC;
    $str.= "<br />\n";
    
    $str.= $row['email'] ; //email = $user_id . "@" . $domain;
    $str.= "<br />\n";
    $str.= lang::translate('You can send from');
    
    $str.= MENU_SUB_SEPARATOR_SEC;
    $str.= "<br />\n";
    
    $mails = emailparse_email_from_str ($row['email_from']);
    $from = implode("<br />", $mails);
    $str.= $from;

    $str.= "<br />\n";
    $str.= html::createLink('/emailparse/edit', lang::translate('Edit from emails'));
    
    return $str;
}
