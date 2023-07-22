<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ConsoleModel;

use Zend\Mail\Message;
use Zend\Mail\Transport\Smtp;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

use Contract\Model\Contracts;
use Contract\Model\ContractUserLinks;
use Contract\Model\NotificationLogs;
use Contract\Model\NotificationLogUsers;
use Contract\Model\NotificationOptions;
use Contract\Model\NotificationSchedules;
use Contract\Model\Payments;

use Contract\Util\Calendar AS CalendarUtil;

class ScriptController extends AbstractActionController {
	const STATUS_TEXT_ACTIVE = 'Active';

	const PAYMENT_TYPE_TEXT_PAID = 'Paid';
	const PAYMENT_TYPE_TEXT_SCHEDULED = 'Scheduled';

	protected function getLogger() {
		return $this->getServiceLocator()->get('console_logger');
	}

	public function calendarQuartersAddAction() {
		$sm = $this->getServiceLocator();

		$calendar_util = new CalendarUtil($sm);
		$calendar_util->addNextQuarter();
	}

	public function paymentNotificationAction() {
		$dry_run = $this->params()->fromRoute('dry-run');
		$to = $this->params()->fromRoute('to');

		$sm = $this->getServiceLocator();
		$logger = $this->getLogger();

		// get all the contracts from the class Contracts (php)
		$contracts_mdl = Contracts::factory($sm);

		// get all the Contract User link from the class ContractUserLinks
		$contract_user_links_mdl = ContractUserLinks::factory($sm);

		$payments_mdl = Payments::factory($sm);
		$notification_logs_mdl = NotificationLogs::factory($sm);
		$notification_log_users_mdl = NotificationLogUsers::factory($sm);
		$notification_options_mdl = NotificationOptions::factory($sm);

		$contracts = $contracts_mdl->filter(array(
			'status' => self::STATUS_TEXT_ACTIVE
		), array(), array(), $contracts_total);

		//build array of dates to notify on for each schedule
		$notification_schedules = $this->buildNotificationSchedulesArray();

		//loop through all active contracts
		foreach($contracts AS $contract) {
			$notification_days = array();

			if($contract->notification_schedule_id) {
				//check if there are any NON-paid payments matching a notifiction date
				$payments = $payments_mdl->filter(array(
					'contract_id' => $contract->id,
					'!payment_type' => self::PAYMENT_TYPE_TEXT_PAID,
					'payment_date' => $notification_schedules[$contract->notification_schedule_id]
				), array(), array(), $payments_total);

				if($payments_total) {
					$payments = $payments->toArray();

					$body = $this->buildNotificatiomEmailBody($contract->id, $payments[0]['id']);

					$contract_user_links = $contract_user_links_mdl->filter(array(
						'contract_id' => $contract->id
					), array(), array(), $contract_user_links_total);

					$tos = array();
					foreach($contract_user_links AS $contract_user_link) {
						$tos[] = array(
							'user_id' => $contract_user_link->user_id,
							'email' => $contract_user_link->email
						);
					}

					//overwrite with command line supplied
					if($to != '') {
						$tos = array(array(
							'email' => $to
						));
						$logger->log(Logger::DEBUG, "overwriting payment notification TO address");
					}

					if(!$dry_run) {
						if(!empty($tos)) {
							$this->sendMail(array_map(function($to) {
								return $to['email'];
							}, $tos), '(BRICS) Contract Payment Due Notification', $body);

							//log notification
							$notification_id = $notification_logs_mdl->insert(array(
								'contract_id' => $contract->id,
								'payment_id' => $payments[0]['id']
							));

							if($notification_id) {
								foreach($tos AS $to) {
									if($to['user_id']) {
										$notification_log_users_mdl->insert(array(
											'notification_log_id' => $notification_id,
											'user_id' => $to['user_id']
										));
									}
								}
							}
						}
						else {
							$logger->log(Logger::DEBUG, "payment notifiction not sent, no user links present");
						}
					}
					$logger->log(Logger::DEBUG, "contract ID: {$contract->id}, payment ID: {$payments[0]['id']}");
				}
			}
		}
	}

	public function expirationNotification(){

		$logger = $this->getLogger();
		$sm = $this->getServiceLocator();
		$dry_run = $this->params()->fromRoute('dry-run');
		$to = $this->params()->fromRoute('to');

		$logger->log(Logger::DEBUG, "Expiration notification action started...");

		// fetch all the contracts from the Contracts class factory function
		$contracts_mdl = Contracts::factory($sm);


		// we will filter all the array, we all get all the rows that will
		// expire according to somw date
		$contracts = $contracts_mdl->filter(array(
			'status' => self::STATUS_TEXT_ACTIVE,
			'end_date' => new Expression("end_date == ?", $end_date)
		), array(), array(), $contracts_total);

		// Now we have all contracts kind of the db table, each row is a contract
		// starts here we will loop over each contract and
		// check if each row which is a contract
		// this contract has contract->id, contract->type, etc.
		// ----------- contract 1
		// ----------- contract 2
		// ...
		foreach($contracts AS $contract) {
			$notification_days = array();

			if($contract->notification_schedule_id) {
				//check if there are any NON-paid payments matching a notifiction date
				$payments = $payments_mdl->filter(array(
					'contract_id' => $contract->id,
					'!payment_type' => self::PAYMENT_TYPE_TEXT_PAID,
					'payment_date' => $notification_schedules[$contract->notification_schedule_id]
				), array(), array(), $payments_total);

				if($payments_total) {
					$payments = $payments->toArray();

					$body = $this->buildNotificatiomEmailBody($contract->id, $payments[0]['id']);

					$contract_user_links = $contract_user_links_mdl->filter(array(
						'contract_id' => $contract->id
					), array(), array(), $contract_user_links_total);

					$tos = array();
					foreach($contract_user_links AS $contract_user_link) {
						$tos[] = array(
							'user_id' => $contract_user_link->user_id,
							'email' => $contract_user_link->email
						);
					}

					//overwrite with command line supplied
					if($to != '') {
						$tos = array(array(
							'email' => $to
						));
						$logger->log(Logger::DEBUG, "overwriting payment notification TO address");
					}

					if(!$dry_run) {
						if(!empty($tos)) {
							$this->sendMail(array_map(function($to) {
								return $to['email'];
							}, $tos), '(BRICS) Contract Payment Due Notification', $body);

							//log notification
							$notification_id = $notification_logs_mdl->insert(array(
								'contract_id' => $contract->id,
								'payment_id' => $payments[0]['id']
							));

							if($notification_id) {
								foreach($tos AS $to) {
									if($to['user_id']) {
										$notification_log_users_mdl->insert(array(
											'notification_log_id' => $notification_id,
											'user_id' => $to['user_id']
										));
									}
								}
							}
						}
						else {
							$logger->log(Logger::DEBUG, "Contract Expiration notifiction not sent, no user links present");
						}
					}
					$logger->log(Logger::DEBUG, "contract ID: {$contract->id}, payment ID: {$payments[0]['id']}");
				}
			}
		}
		// ends here
	}


	protected function buildNotificationSchedulesArray() {
		$sm = $this->getServiceLocator();

		$notification_options_mdl = NotificationOptions::factory($sm);

		$notification_options = $notification_options_mdl->filter(array(), array(), array(), $notification_options_total);

		$notification_schedules = array();
		foreach($notification_options AS $notification_option) {
			$notification_date_dt = new \DateTime(date('Y-m-d'));
			$notification_date_dt->add(new \DateInterval("P{$notification_option->days_out}D"));

			$notification_schedules[$notification_option->notification_schedule_id][] = $notification_date_dt->format('Y-m-d');
		}

		return $notification_schedules;
	}

	protected function buildNotificatiomEmailBody($contract_id, $payment_id) {
		$sm = $this->getServiceLocator();
		$logger = $this->getLogger();

		$contracts_mdl = Contracts::factory($sm);
		$payments_mdl = Payments::factory($sm);

		$contract = $contracts_mdl->get($contract_id);
		$payment = $payments_mdl->get($payment_id);

		$vendor = $contract->vendor;
		$contract_number = $contract->contract_number;
		$description = $contract->description;
		$amount = $payment->amount;
		$payment_date = $payment->payment_date;
		$payment_type = $payment->payment_type;

		$payment_date_dt = new \DateTime($payment_date);
		$payment_date_styled = $payment_date_dt->format('n/j/y');

		$today_dt = new \DateTime(date('Y-m-d'));
		$interval = $today_dt->diff($payment_date_dt);
		$days_remaining = $interval->format("%a");

		$body = "The following contract has a payment coming up:\n";
		$body .= "<table border=1 cellspacing=0 cellpadding=1>";

		$body .= "<tr>";
		$body .= "<th>Payment Due Date</th>";
		$body .= "<th>Days Remaining</th>";
		$body .= "<th>Vendor</th>";
		$body .= "<th>Contract Number</th>";
		$body .= "<th>Description</th>";
		$body .= "<th>Est Next Payment Cost</th>";
		$body .= "</tr>";

		$body .= "<tr>";
		$body .= "<td>{$payment_date_styled}</td>";
		$body .= "<td>{$days_remaining}</td>";
		$body .= "<td>{$vendor}</td>";
		$body .= "<td>{$contract_number}</td>";
		$body .= "<td>{$description}</td>";
		$body .= "<td>\${$amount}</td>";
		$body .= "</tr>";

		$body .= "</table>";

		return $body;
	}

	protected function sendMail($tos, $subject, $body) {
		$sm = $this->getServiceLocator();
		$config = $sm->get('Config');
		$logger = $this->getLogger();

		$html = new MimePart($body);
		$html->type = "text/html";

		$body = new MimeMessage();
		$body->setParts(array($html));

		$sender = $config['system']['email'];

		$message = new Message();
		$message->setSubject($subject);
		$message->addFrom($sender);
		foreach($tos AS $to) {
			$logger->log(Logger::DEBUG, "adding {$to} to To list");
			$message->addTo($to);
		}

		$message->setBody($body);

		$transport = new Smtp();
		$options = new SmtpOptions(array(
			'host' => $config['mail']['smtp_host']
		));

		try {
			$transport->setOptions($options);
			$transport->send($message);
			$logger->log(Logger::DEBUG, "payment notification email has been sent");
		} catch(\Exception $e) {
			$logger->log(Logger::DEBUG, "could not send payment notification email" . $e->getMessage());
		}
	}
}

?>
