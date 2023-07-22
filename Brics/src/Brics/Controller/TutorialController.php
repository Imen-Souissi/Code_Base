<?php
namespace Brics\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Els\Mvc\Controller\PublicInterface;

class TutorialController extends AbstractActionController implements PublicInterface {
	public function videoAction() {
		$this->layout('layout/simple');
		$this->layout()->setVariable('layout_type', 'h');
		
		$id = $this->params()->fromRoute('id');
		
		if(!in_array($id, array(1, 2, 3, 4, 5))) {
			return $this->notFoundAction();
		}
		
		return new ViewModel(array(
			'id' => $id
		));
	}
}
?>