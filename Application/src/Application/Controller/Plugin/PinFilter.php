<?php
namespace Application\Controller\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Json\Json;
use Application\Model\Settings;

class PinFilter extends AbstractPlugin {	
    public function __invoke() {
        return $this;
    }
	
	public function applyPinFilter($user_id) {
		$controller = $this->getController();
        $sm = $controller->getServiceLocator();
		$request = $sm->get('Request');
		$filtering = $request->getQuery('filtering');
		
		if(intval($filtering) === 1) {
			return;
		}
		
		$settings = $this->getPinFilter($user_id);
		if($settings) {
			$query = $settings['query'];
			// we will force the filtering flag so that we don't loop redirect forever
			$query['filtering'] = 1;
			
			// let's redirect to this setting
			$controller->redirect()->toRoute($settings['route'], array(
				'controller' => $settings['controller'],
				'action' => $settings['action'],
				'id' => $settings['id']
			), array(
				'query' => $query
			));
		}
	}
	
    public function getPinFilter($user_id) {
        $controller = $this->getController();
        $sm = $controller->getServiceLocator();
        
        $router = $sm->get('Router');
		$request = $sm->get('Request');
        $route_matched = $router->match($request);
        
        $route_name = $route_matched->getMatchedRouteName();
        $controller_name = $route_matched->getParam('controller');
        $action_name = $route_matched->getParam('action');
        $id = $route_matched->getParam('id');
        
        $checksum = md5("{$route_name},{$controller_name},{$action_name},{$id}");        
        
        $sm = $controller->getServiceLocator();
        $config = $sm->get('Application\Config');		
		$app = $config['app'];        
        
        $settings_mdl = Settings::factory($sm);
        
        $settings = $settings_mdl->get(array(
            'user_id' => $user_id,
            'app' => $app,
            'field' => "filter_{$checksum}"
        ));
        
        if($settings) {
            try {
                return Json::decode($settings->value, Json::TYPE_ARRAY);
            } catch(\Exception $e) {
                // do nothing for now
            }
        }
        
        return false;
    }
}
?>