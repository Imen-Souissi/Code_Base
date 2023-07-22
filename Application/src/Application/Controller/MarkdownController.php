<?php
namespace Application\Controller;

use Els\Mvc\Controller\PublicInterface;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Json\Json;

class MarkdownController extends AbstractActionController implements PublicInterface {
	public function indexAction() {
        $this->layout('layout/empty');
        
        $cwd = getcwd();
        
        $files = array(
            1 => 'changes.md',
        );
        
        $file_id = $this->params()->fromQuery('file_id');
        $file = "{$cwd}/{$files[$file_id]}";
        
        if(!empty($file) && file_exists($file)) {
            return new ViewModel(array(
                'filename' => basename($file),
                'data' => file_get_contents($file)
            ));
        } else {
            return new ViewModel(array());
        }
	}
}
?>