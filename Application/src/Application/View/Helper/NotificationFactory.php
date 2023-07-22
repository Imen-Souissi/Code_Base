<?php
namespace Application\View\Helper;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Application\Model\Notifications;

class NotificationFactory implements FactoryInterface {
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $serviceManager = $serviceLocator->getServiceLocator();
		$controllerPluginManager = $serviceManager->get('controllerpluginmanager');
		$authentication = $controllerPluginManager->get('authentication');
		$user_id = $authentication->getAuthenticatedUserId();
		$config = $serviceManager->get('Config');
		
		$items = array();
		$max = $config['notification']['max'];
		$max = (empty($max)) ? 5 : $max;
		$total = 0;
		
		if(!empty($user_id)) {
			// grab the user
			$db = $serviceManager->get('db');
			
			// we need to retrieve the amount of notifications they have
			$notifications_mdl = Notifications::factory($serviceManager);
			$notifications = $notifications_mdl->filter(array(
                'seen' => 0,
                'to_user_id' => $user_id
            ), array(), array(), $ptotal);
            
			foreach($notifications as $notification) {
				if($total < $max) {
					$items[] = $notification->getArrayCopy();
				}
				$total++;
			}
		}
		
		return new Notification($items, $total);
    }
}
?>