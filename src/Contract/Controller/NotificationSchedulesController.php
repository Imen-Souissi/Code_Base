<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Contract\Model\Contracts;
use Contract\Model\NotificationOptions;
use Contract\Model\NotificationSchedules;

class NotificationSchedulesController extends AbstractActionController {
	public function indexAction() {
		return new ViewModel(array(
		));
	}

	public function addAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
	
		$name = trim($this->params()->fromPost('name'));
		$days = trim($this->params()->fromPost('days'));
		
		$active = 1;
		$days = split(',', $days);
		//sort desc so we know the last value can be marked as is_last
		arsort($days);
	
		$logger->log(Logger::DEBUG, "attempting to add new notification schedule {$name}");
	
		$notification_options_mdl = NotificationOptions::factory($sm);
		$notification_schedules_mdl = NotificationSchedules::factory($sm);
		
		$status = 0;
		//notification schedule matching name already exists
		if($notification_schedules_mdl->exists(array(
			'name' => array($name)
		))) {
			$error = "Notification schedule matching name already exists";
		}
		else {
			$result = $notification_schedules_mdl->insert(array(
				'name' => $name,
				'active' => $active
			));
			
			if($result) {
				$notification_schedule_id = $result;
	
				foreach($days AS $day) {
					if(!$notification_options_mdl->exists(array(
						'notification_schedule_id' => $notification_schedule_id,
						'days_out' => $day
					))) {
						$notification_option_id = $notification_options_mdl->insert(array(
							'notification_schedule_id' => $notification_schedule_id,
							'days_out' => $day,
							'is_last' => 0
						));
					}
				}
				
				//update last saved option as last
				$notification_options_mdl->update($notification_option_id, array(
					'is_last' => 1
				));
				
				$status = 1;
			}
			else {
				$error = "Unable to complete adding notification schedule, please try again";
			}
		}
	
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
	
	public function editAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
	
		$id = trim($this->params()->fromPost('id'));
		$name = trim($this->params()->fromPost('name'));
		$days = trim($this->params()->fromPost('days'));
		$active = trim($this->params()->fromPost('active'));
	
		$days = split(',', $days);
		//sort desc so we know the last value can be marked as is_last
		arsort($days);
	
		$logger->log(Logger::DEBUG, "attempting to edit notification schedule ID {$id}");
	
		$notification_options_mdl = NotificationOptions::factory($sm);
		$notification_schedules_mdl = NotificationSchedules::factory($sm);
		
		$status = 0;
		//notification schedule matching name already exists
		if($notification_schedules_mdl->exists(array(
			'!id' => $id,
			'name' => array($name)
		))) {
			$error = "Notification schedule matching name already exists";
		}
		else {
			$result = $notification_schedules_mdl->update($id, array(
				'name' => $name,
				'active' => $active
			));
			
			//we'll delete all the previous and then readd them below
			$notification_options_mdl->delete(array(
				'notification_schedule_id' => $id,
			));
			
			foreach($days AS $day) {
				if(!$notification_options_mdl->exists(array(
					'notification_schedule_id' => $id,
					'days_out' => $day
				))) {
					$notification_option_id = $notification_options_mdl->insert(array(
						'notification_schedule_id' => $id,
						'days_out' => $day,
						'is_last' => 0
					));
				}
			}

			//update last saved option as last
			$notification_options_mdl->update($notification_option_id, array(
				'is_last' => 1
			));

			$status = 1;
		}
	
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function deleteAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
	
		$id = trim($this->params()->fromPost('id'));
	
		$logger->log(Logger::DEBUG, "attempting to delete notification schedule ID {$id}");
	
		$contracts_mdl = Contracts::factory($sm);
		$notification_options_mdl = NotificationOptions::factory($sm);
		$notification_schedules_mdl = NotificationSchedules::factory($sm);
	
		$status = 0;
		//verify notification schedule isn't being used
		if($contracts_mdl->exists(array(
			'notification_schedule_id' => $id
		))) {
			$error = "Notification schedule cannot be deleted as it is currently being used by a contract";
		}
		else {
			$notification_schedules_mdl->delete($id);
			$notification_options_mdl->delete(array(
				'notification_schedule_id' => $id
			));
	
			$status = 1;
		}
	
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
}
?>