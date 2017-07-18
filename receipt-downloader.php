<?php
include 'vendor/autoload.php';

use Ddeboer\Imap\Server as ImapServer;
use Ddeboer\Imap\Search\Date\AbstractDate;
use Ddeboer\Imap\Search\Date\After;
use Ddeboer\Imap\SearchExpression;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$username = getenv('USERNAME');
$password = getenv('PASSWORD');
$download_dir = getenv('DOWNLOAD_DIR');

// create a log channel
$log = new Logger('receipt-downloader');
$log->pushHandler(new StreamHandler('./receipt-downloader.log'));

try {

  $server = new ImapServer("imap.gmail.com", 993, '/imap/ssl/novalidate-cert/norsh');

  // $connection is instance of \Ddeboer\Imap\Connection
  $connection = $server->authenticate($username, $password);

  $mailbox = $connection->getMailbox('INBOX');

  $search = new SearchExpression();
  $search->addCondition(new After(new DateTime("01/01/2016 00:00:00")));

  $messages = $mailbox->getMessages($search);

  foreach ($messages as $message) {
    $attachments = $message->getAttachments();
    echo ".";
    
    if (!empty($attachments) || preg_match('/receipt/i', $message->getSubject()) ||
        preg_match('/invoice/i', $message->getSubject())) {
      $date = $message->getDate();
      $year = $date->format('Y');
      $month = $date->format('m');
      $date = $date->format('d');

      $attachment_prefix = "{$download_dir}/{$year}/{$month}/{$date}_";

      if (!file_exists(dirname(($attachment_prefix)))) {
        $log->info("Creating directory " . dirname($attachment_prefix));
        mkdir(dirname($attachment_prefix), 0777, true);
      }

      if (preg_match('/receipt/i', $message->getSubject()) ||
          preg_match('/invoice/i', $message->getSubject())) {
        $message_text = $message->getBodyText();
        $message_filename = $attachment_prefix . preg_replace("/\//", "_", $message->getSubject()) . ".txt";
        $log->info("Saving message from {$message->getFrom()} as $message_filename");
        file_put_contents($message_filename, $message_text);
      }

      foreach ($attachments as $attachment) {
        $attachment_filename = $attachment_prefix . $attachment->getFilename();
        if (preg_match('/\.pdf$/i', $attachment_filename)) {
          $log->info("Saving pdf from {$message->getFrom()} as {$attachment_filename}");
          $log->info($attachment->getSize() . " - $attachment_filename");
          file_put_contents($attachment_filename, $attachment->getDecodedContent());
        }
      }
    }
  }
}
catch (Exception $e) {
  $log->warning("Caught an exception - " . $e->getMessage());
  echo $e->getMessage() . "\n";
}

