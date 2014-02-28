<?php

// based on https://drupal.org/node/1770582

  $to = 'charlene.ferguson@acquia.com,drew.webber@acquia.com';
  //  $from = '';
  $subject = 'Test e-mail (ticket 101321)';
  $body = $subject;



  $from = empty($from) ? $from = variable_get('site_mail', ini_get('sendmail_from')) : $from;
  $headers = array();
  $headers['From'] = $headers['Sender'] = $headers['Return-Path'] = $headers['Errors-To'] = $from;

  // D7 implementation of drupal_mail
  if(function_exists('drupal_mail_system')) {
    // Prepare the message.
    $message = drupal_mail('drush', 'key', $to, NULL, array(), $from, FALSE);

    $message['subject'] = $subject;
    $message['body'] = array();
    $message['body'][] = $body;
    $message['headers'] = $headers;

    // Retrieve the responsible implementation for this message.
    $system = drupal_mail_system('drush', 'key');
    // Format the message body.
    $message = $system->format($message);
    // Send e-mail.
    $message['result'] = $system->mail($message);
    $result = $message['result'];

  // D6 implementation of drupal_mail_send
  } else {
    $message = array(
      'to' => $to,
      'subject' => $subject,
      'body' => $body,
      'headers' => $headers,
    );
    $result = drupal_mail_send($message);
  }

  // Return result.
  if($result) {
    print t('E-mail message sent to <!to>', array('!to' => $to)) . "\n";
  }
  else {
    print "An error occurred while sending the e-mail message.\n";
  }
