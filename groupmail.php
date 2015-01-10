<?php

/**
 * This is a simple solution to send e-mails to a specific group of people.
 *
 * Use case: you want to send an e-mail to a group of users, but you do not
 * want to use mailchimp, googlegroups or sympa.
 *
 * You only need a POP account and a server with PHP and cron.
 *
 * How it works: specify the connection and group details using the web form,
 * add a cronjob which calls the same file periodically and send the mails
 * to the specified mailbox. This script will then pick up the mails and relay
 * them to the given list of users.
 *
 * FAQ
 * ===
 * Q: What is the "secret" for?
 * A: You don't want to allow anyone to send mails to the group - you therefore
 *    configure a "secret" per group and add the secret to the subject of an
 *    e-mail you want to send. The script will check the secret and remove it
 *    from the subject line. The subject needs to have this format:
 *    "[secret] my subject" (without the quotes)
 *
 * Q: But it's not save to store the mailbox config in a plain text file!?
 * A: Use a htaccess file to protect the form and the .cfg file
 *
 * Q: Putting the data in a file is not thread save!?
 * A: We want to keep things simple - if you need a more sophisticated solution
 *    you can still modify the configHandler class.
 *
 *
 * @author        Nicolas Christener <nicolas.christener@adfinis-sygroup.ch>
 * @author        Cyrill von Wattenwyl <cyrill.vonwattenwyl@adfinis-sygroup.ch>
 * @author        David Vogt <david.vogt@adfinis-sygroup.ch>
 * @copyright     Adfinis SyGroup AG 2014
 * @license       http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

date_default_timezone_set('Europe/Zurich'); 

$log = array();

// If not in "cli"-mode, display the web form, else run the cronjob
if (php_sapi_name() !== 'cli') {
	if ($_POST) {
		$stanzas = $_POST['stanzas'];

		$ch = configHandler::getInstance();

		$data = array();

		for($i = 1; $i <= $stanzas; $i++) {
			if ((
				$_POST['identifier'.$i] !== '' ||
				$_POST['host'.$i]       !== '' ||
				$_POST['user'.$i]       !== '' ||
				$_POST['secret'.$i]     !== '' ||
				$_POST['recipients'.$i] !== '') && !isset($_POST['delete'.$i])
			) {
				$identifier = $_POST['identifier'.$i];

				// explode recipients
				$lines      = explode("\n", $_POST['recipients'.$i]);
				$recipients = array();
				foreach($lines as $line) {
					$line = trim($line);
					if($line !== '' && filter_var($line, FILTER_VALIDATE_EMAIL)) {
						$recipients[] = $line;
					}
				}

				if(isset($data[$identifier])) {
					$log[] = sprintf('You cannot use a list address more than once! (%s)', $identifier);
				}

				$data[$identifier] = array(
					'identifier' => $_POST['identifier'.$i],
					'host'       => $_POST['host'.$i],
					'user'       => $_POST['user'.$i],
					'pass'       => $_POST['pass'.$i],
					'secret'     => $_POST['secret'.$i],
					'recipients' => $recipients
				);
			}
		}
		$ch->writeConfig($data);
	}

	$ch   = configHandler::getInstance();
	$data = $ch->data;

	include('groupmail.tpl.php');
}
else {
	// cron job mode
	$ch   = configHandler::getInstance();
	$data = $ch->data;

	foreach ($data as $identifier => $stanza) {
		try{
			$mh = new mailHandler($stanza);
		}
		catch(Exception $e) {
			printf(
				"Error connection to mailbox (%s): %s\n",
				$identifier,
				$e->getMessage()
			);

			continue;
		}

		$recipients = $ch->getRecipients($identifier);

		while($mail = $mh->getNextMail()) {
			foreach($recipients as $recipient) {
				$mh->sendMail(
					$mail['from'],
					$recipient,
					$mail['subject'],
					$mail['body'],
					$mail['additionalHeader']
				);
			}
		}
		$mh->close();
	}
}

/**
 * Function used in the "template" to output sanitized user content
 *
 * @param         string
 * @return        void
 */
function out($string) {
	echo htmlspecialchars($string);
}

/**
 * This class handles the emails
 */
class mailHandler {
	/**
	 * @var        data array containing the config of one group
	 */
	private $data = array();

	/**
	 * @var        conn IMAP connection resource
	 */
	private $conn;

	/**
	 * @var        currentMailId the IMAP ID of the mail in the mailbox, used
	 *                           for iterating over the mails in the box
	 */
	private $currentMailId = 1;

	/**
	 * Class initializer
	 *
	 * @param         array $data
	 * @throws        Exception
	 */
	public function __construct($data) {
		$this->data = $data;

		$requiredArgs = array('host', 'user', 'pass');
		foreach($requiredArgs as $arg) {
			if ($this->data[$arg] == '') {
				throw new Exception(sprintf('No %s was given!', $arg));
			}
		}

		$mailbox    = sprintf('{%s:110/pop3/novalidate-cert}', $this->data['host']);
		$this->conn = imap_open($mailbox, $this->data['user'], $this->data['pass']);

		if (!$this->conn){
			throw new Exception('Mail connection failed');
		}

		// The PHP imap library reports an "error" if the mailbox is empty
		// and stores it in it's internal error list. If you don't clear this
		// error list, you will get the following, useless message upon the end
		// of the script: "PHP Notice:  Unknown: Mailbox is empty (errflg=1) in
		// Unknown on line 0"
		// Side knowledge: This has apparently been a problem for more than 10
		// years: http://php.net/manual/en/function.imap-open.php#48144
		$errors = imap_errors();
		$alerts = imap_alerts();
	}

	/**
	 * Class destructor
	 */
	public function __destruct() {
		$this->close();
	}

	/**
	 * Clean up the IMAP connection
	 *
	 * This function is directly called as well as used by the destructor - this
	 * helps to aviod race conditions when accessing the same mailbox from
	 * different instances.
	 *
	 * @return        void
	 */
	public function close() {
		if (!$this->conn) {
			return;
		}

		// delete marked messages
		imap_expunge($this->conn);

		// close connection
		imap_close($this->conn);

		$this->conn = null;
	}

	/**
	 * Fetch and handle emails
	 *
	 * @throws        Exception
	 * @return        array|bool
	 */
	public function getNextMail() {
		// get number of mails
		$numMails = imap_num_msg($this->conn);

		// loop trough all mails
		for ($this->currentMailId; $this->currentMailId <= $numMails; $this->currentMailId++) {
			$header  = imap_headerinfo($this->conn, $this->currentMailId);
			$from    = $header->fromaddress;
			$subject = $header->subject;

			$expectedInSubject = sprintf("[%s]", $this->data['secret']);

			if (strstr($subject, $expectedInSubject) === false) {
				printf("Deleting message w/o matching secret, from: %s, subject: %s\n", $from, $subject);
				imap_delete($this->conn, $this->currentMailId);
				continue;
			}

			// remove secret from subject
			$subject = trim(str_replace($expectedInSubject, '', $subject));

			// we need the mail boundary as well - this is not present in $header
			$header = imap_fetchheader($this->conn, $this->currentMailId);
			// source: http://php.net/manual/en/function.imap-fetchheader.php#82339
			preg_match_all('/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)\r\n/m', $header, $matches);
			$index       = array_search('Content-Type', $matches[1]);
			$additionalHeader = $matches[0][$index];

			// get mail body
			// $body = imap_qprint(imap_body($this->conn, $this->currentMailId));
			$body = imap_body($this->conn, $this->currentMailId);

			$retval = array(
				'from'             => $from,
				'subject'          => $subject,
				'body'             => $body,
				'additionalHeader' => $additionalHeader
			);

			// mark message for deletion
			imap_delete($this->conn, $this->currentMailId);

			// because we don't finish the loop, we have to manually increment
			$this->currentMailId++;

			return $retval;
		}

		return false;
	}

	/**
	 * Send (relay) the mail to the new recipient
	 *
	 * @param         string $from
	 * @param         string $to
	 * @param         string $subject
	 * @param         string $body
	 * @param         string $additionalHeader - needs \r\n at the end
	 * @throws        Exception
	 * @return        void
	 */
	public function sendMail($from, $to, $subject, $body, $additionalHeader) {
		$headers  = sprintf("From: %s\r\n", $from);
		$headers .= $additionalHeader;
		$result = mail($to, $subject, $body, $headers);
		if($result === false) {
			throw new Exception('Could not send e-mail');
		}
		else {
			printf("Send mail to: %s, subject: %s\n", $to, imap_utf8($subject));
		}
	}
}

/**
 * This class handles reading/writing the config file
 */
class configHandler {
	/**
	 * @var        configHandler instance of the singleton
	 */
	protected static $instance = null;

	/**
	 * @var        array holds the config values
	 */
	public $data = array();

	/**
	 * This function returns a instance of itself.
	 *
	 * This class is implemented as a singleton, to avoid many file reads.
	 * 
	 * @return        configHandler
	 */
	public static function getInstance() {
		if(self::$instance === null) {
			self::$instance = new configHandler();
		}

		return self::$instance;
	}

	/**
	 * Thou shalt not construct that which is unconstructable!
	 */
	protected function __construct() {
		if (!file_exists('groupmail.cfg')) {
			return;
		}

		$this->data = unserialize(file_get_contents('groupmail.cfg'));
	}

	/**
	 * Me not like clones! Me smash clones!
	 */
	protected function __clone() {
	}

	/**
	 * Checks if a given identifier actually is a identifier
	 *
	 * @param         string $identifier
	 * @return        bool
	 */
	public function isIdentifier($identifier) {
		return isset($this->data[$identifier]);
	}

	/**
	 * Returns the group members recursively
	 *
	 * Creates a list of all recipients of this group. If a recipient matches
	 * an identifier of an other group, that group's recipients are added
	 * to the list as well.
	 *
	 * Special care is taken to avoid loops and duplicates.
	 *
	 * @param         string $identifier
	 * @param         array  $seen internally used for avoiding loops
	 * @return        array
	 */
	public function getRecipients($identifier, $seen = array()) {
		$recipients = array_unique($this->data[$identifier]['recipients']);

		/**
		 * It is possible to use an address which itself is a group
		 * Iterate over the recipients and find such identifiers, then
		 * pull their groups members in our final list of recipients.
		 */
		$finalRecipients = array();
		foreach ($recipients as $recipient) {
			// check if current recipient is a group as well
			if (isset($seen[$recipient])) {
				continue;
			}
			$seen[$recipient] = true;
			if($this->isIdentifier($recipient)) {
				foreach ($this->getRecipients($recipient, $seen) as $subrecipient) {
					$finalRecipients[] = $subrecipient;
				}
			}
			else {
				$finalRecipients[] = $recipient;
			}
		}

		// make unique
		$finalRecipients = array_unique($finalRecipients);

		// remove list addresses (identifiers)
		$me = $this;
		$finalRecipients = array_filter(
			$finalRecipients,
			function($r) use ($me) { return !$me->isIdentifier($r); }
		);

		return $finalRecipients;
	}

	/**
	 * Write the config to the file
	 *
	 * If you need a more sophisticated solution it should be easy to use a
	 * MySQL connection here.
	 *
	 * @param         array data
	 * @throws        Exception
	 * @return        void
	 */
	public function writeConfig($data) {
		$result = file_put_contents('groupmail.cfg', serialize($data));
		if ($result === false) {
			throw new Exception('Could not write config file - can PHP write in to this folder?');
		}

		$this->data = $data;
	}
}
