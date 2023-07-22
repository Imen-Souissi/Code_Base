<?php
namespace Application\Controller\Script;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;
use Zend\View\Model\ConsoleModel;
use Zend\Json\Json;

use Application\Model\Notifications as ApplicationNotifications;
use Application\Model\NotificationMigrates as ApplicationNotificationMigrates;

use Els\Gearman\Client as ElsGearmanClient;
// TODO: we should shift the email to the Application module in the next release
use Gem\Gearman\Worker\Emailer;

class NotificationController extends AbstractActionController {
	public function emailAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$app_config = $sm->get('Application\Config');
		$config = $sm->get('Config');
		$logger = $sm->get('console_logger');
		$result = new ConsoleModel();
		
		$app = $app_config['app'];
		if(empty($app)) {
			$logger->log(Logger::ERR, "application not setup with proper name");
			$result->setErrorLevel(1);
		}
		
		$offset = $this->params()->fromRoute('offset');
		if(!empty($offset)) {
			$offset = date('Y-m-d H:i:s', strtotime($offset));
		} else {
			$offset = date('Y-m-d H:i:s', strtotime('-1 hour'));
		}
		
		$reemail = $this->params()->fromRoute('reemail');
		
		$application_notifications_mdl = ApplicationNotifications::factory($sm);
		$gearman_client = ElsGearmanClient::factory($sm);
		
		$logger->log(Logger::INFO, "starting notifications emailing");
		
		$filter = array(
			'seen' => 0,
			'email_template' => new Expression('email_template IS NOT NULL AND LENGTH(TRIM(email_template)) > 0')
		);
		
		if($reemail) {
			$filter['emailed'] = 1;
			$filter['<emailed_on'] = $offset;
			$email_subject = "{$app} Unseen Notifications";
		} else {
			$filter['emailed'] = 0;
			$filter['<ctime'] = $offset;
			$email_subject = "{$app} Recent Notifications";
		}
		
		$con = $db->getDriver()->getConnection();
		
		// first grab all the user and templates that have notifications pending
		$notification_user_templates = $application_notifications_mdl->filterUserAndTemplate($filter, array(), array(), $total);
		$logger->log(Logger::INFO, "found {$total} user's notifications to process");
		
		foreach($notification_user_templates as $user_template) {
			try {
				$con->beginTransaction();
				
				// now we need to pull and group all the notifications with matching user and template to be sent as 1 email
				$new_filter = $filter;
				$new_filter['to_user_id'] = $user_template->to_user_id;				
				$new_filter['email_template'] = $user_template->email_template;
				
				$notification_ids = array();
				$notification_params = array();
				
				$notifications = $application_notifications_mdl->filter($new_filter, array(), array(), $total);
				$logger->log(Logger::INFO, "found {$total} notifications to send");
				
				foreach($notifications as $notification) {
					$notification_ids[] = $notification->id;
					if(!empty($notification->email_params)) {
						try {
							$params = Json::decode($notification->email_params, Json::TYPE_ARRAY);
							// merge this params with the current notification params
							$notification_params = array_merge_recursive($notification_params, $params);
						} catch(\Exception $e) {
							// do nothing
						}
					}
				}
				
				$notification_params['user'] = $user_template->display_name;
				
				if(count($notification_ids) > 0) {
					//$logger->log(Logger::INFO, print_r($notification_params, true));
					
					// trigger an email to the reserver that this reservation has expired
					$gearman_client->doBackground(Emailer::NAME, array(
						'from' => $config['system']['email'],
						// DEBUG
						//'to' => 'xxiong@brocade.com',
						'to' => $user_template->email,
						'subject' => $email_subject,
						'template' => $user_template->email_template,
						'params' => $notification_params,
						'notification_ids' => $notification_ids
					));
					
					foreach($notification_ids as $notification_id) {
						$application_notifications_mdl->update($notification_id, array(
							'triggered_on' => new Expression('NOW()')
						));
					}
				}
				
				$con->commit();
				
				$logger->log(Logger::INFO, "successfully triggered email notification to {$user_template->to_username}");
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				$logger->log(Logger::ERR, "unable email notifications to {$user_template->to_username} : " . $e->getMessage());
				$result->setErrorLevel(1);
				return $result;
			}
		}
		
		$logger->log(Logger::INFO, "finished notifications emailing");
		
		return $result;
	}
}
?>