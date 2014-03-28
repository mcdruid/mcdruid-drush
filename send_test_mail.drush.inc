<?php

// based on https://drupal.org/node/1770582

/**
 * Implements hook_drush_command().
 */
function send_test_mail_drush_command() {
  $items = array();

  $items['send-test-mail'] = array(
    'description' => 'Send a test e-mail through Drupal.',
    'callback' => 'drush_send_test_mail',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'arguments' => array(
      'to-address' => 'Address to which the test e-mail should be sent (comma separate multiple addresses).',
      'from-address' => '(Optional) address from which the test e-mail should be sent.',
      'subject' => '(Optional) custom subject for the test e-mail.)',
    ),
    'required-arguments' => 1, // only to-address is required
    'examples' => array(
      'drush stm dev@example.com' => 'Send a test e-mail to dev@example.com',
      'drush stm dev@example.com,pm@example.com' => 'Send a test e-mail to dev@example.com and pm@example.com',
      "drush stm dev@example.com website@example.com 'testing 1 2 3'" => "Send a test e-mail to dev@example.com from website@example.com with the subject 'testing 1 2 3'",
    ),
    'aliases' => array('stm'),
  );

  return $items;
}

function drush_send_test_mail($to_address, $from_address = NULL, $subject = NULL) {

  $to = $to_address; // todo: validate at least this?
  $from = empty($from_address) ? $from_address = variable_get('site_mail', ini_get('sendmail_from')) : $from_address;
  $headers = array();
  $headers['From'] = $headers['Sender'] = $headers['Return-Path'] = $headers['Errors-To'] = $from;
  $subject = empty($subject) ? 'Test e-mail from ' . variable_get('site_name', 'Drupal') : $subject;
  $body = $subject;

  if(function_exists('drupal_mail_system')) {
    // D7 implementation

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

  }
  else if (function_exists(drupal_mail_send)) {
    // D6 implementation
    $message = array(
      'to' => $to,
      'subject' => $subject,
      'body' => $body,
      'headers' => $headers,
    );
    $result = drupal_mail_send($message);
  }
  else {
    drush_set_error(dt('No usable Drupal mail system found.'));
    exit;
  }

  // Return result.
  if($result) {
    drush_print(dt('E-mail message sent to <!to>', array('!to' => $to)));
  }
  else {
    drush_set_error(dt('An error occurred while sending the e-mail message.'));
  }
}
