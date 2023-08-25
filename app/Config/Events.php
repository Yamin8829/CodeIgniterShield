<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Exceptions\LogicException;
use CodeIgniter\Shield\Exceptions\RuntimeException;

// notice I am passing two variables from the controller $user and $tmpPass
// I will force the user to change password on first login
Events::on('newRegistration', static function ($user, $tmpPass) {

    $userEmail = $user->email;
    if ($userEmail === null) {
        throw new LogicException(
            'Email Activation needs user email address. user_id: ' . $user->id
        );
    }

    $date = Time::now()->toDateTimeString();

    // Send the email
    $email = emailer()->setFrom(setting('Email.fromEmail'), setting('Email.fromName') ?? '');
    $email->setTo($userEmail);
    $email->setSubject(lang('Auth.emailActivateSubject'));
    $email->setMessage(view(setting('Auth.views')['email_manual_activate_email'], ['userEmail' => $userEmail, 'tmpPass' => $tmpPass, 'date' => $date]));

    if ($email->send(false) === false) {
        throw new RuntimeException('Cannot send email for user: ' . $user->email . "\n" . $email->printDebugger(['headers']));
    }

    // Clear the email
    $email->clear();

});
Events::on('pre_system', static function () {
    if (ENVIRONMENT !== 'testing') {
        if (ini_get('zlib.output_compression')) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && !is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        Services::toolbar()->respond();
    }
});
