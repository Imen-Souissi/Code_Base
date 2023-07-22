<?php
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Db\Sql\Expression;

use Application\Model\MaskStrings;

class MaskStringsController extends AbstractActionController {	
	public function viewAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
        $mask_strings_mdl = MaskStrings::factory($sm);
		
        $id = $this->params()->fromRoute('id');
        $full = null;
        
        $mask_string = $mask_strings_mdl->get($id);
        if($mask_string) {
            $unmasker = null;
            
            if(!empty($mask_string->unmasker) && class_exists($mask_string->unmasker)) {
                $unmasker = $mask_string->unmasker;
                $unmasker = new $unmasker();
                if($unmasker instanceof ServiceLocatorAwareInterface) {
                    $unmasker->setServiceLocator($sm);
                }
                
                $full = $unmasker->unmask($id, $mask_string);
            }
        }
        
        return new JsonModel(array(
            'full' => ($full) ? $full : 'Not available for view!'
        ));
	}
}
?>