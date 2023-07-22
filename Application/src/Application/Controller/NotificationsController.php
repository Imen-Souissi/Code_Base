<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;

use Application\Model\Notifications;

class NotificationsController extends AbstractActionController {	
	public function indexAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$user_id = $this->authentication()->getAuthenticatedUserId();
		$notifications_mdl = Notifications::factory($sm);		
		
		if($this->getRequest()->isPost()) {
			// remove all notifications selected
			$remove = $this->params()->fromPost('remove');
			$remove = (is_array($remove)) ? $remove : array($remove);
			
			if(count($remove) > 0) {
				$con = $db->getDriver()->getConnection();
				
				try {
					$con->beginTransaction();
					
					foreach($remove as $notification_id) {
						$notifications_mdl->update($notification_id, array(
							'seen' => 1,
							'seen_on' => new Expression("NOW()")
						));
					}
					
					$con->commit();
				} catch(\Exception $e) {
					$con->rollback();
					
					$p = $e->getPrevious();
					if($p) {
						$e = $p;
					}
					
					$logger->log(Logger::ERR, "unable to remove selected notifications : " . $e->getMessage());
					$logger->log(Logger::ERR, $e->getTraceAsString());
				}
			}
			
			$this->redirect()->toRoute('application/application/default', array(
				'controller' => 'notifications',
			));
		}
		
		return new ViewModel(array(
			'notifications' => $notifications_mdl->filter(array(
                'to_user_id' => $user_id,
                'seen' => 0
            ), array(), array(), $total)
		));
	}
}
?>