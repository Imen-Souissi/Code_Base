<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Contract\Model\ContractDocuments;

class ContractDocumentsController extends AbstractActionController {
    public function downloadAction() {
        $sm = $this->getServiceLocator();
		$db = $sm->get('db');
		$logger = $sm->get('logger');
		
		$error = "";
		$status = 0;
		$id = $this->params()->fromRoute('id');
        
        $contract_documents_mdl = ContractDocuments::factory($sm);
        $contract_document = $contract_documents_mdl->get($id);
        
        if($contract_document !== false) {
            $response = $this->getResponse();
            $response->setContent($contract_document->content);
            $response->getHeaders()
                    ->addHeaderLine('Cache-Control: must-revalidate, post-check=0, pre-check=0')
                    ->addHeaderLine('Cache-Control: private',false)
                    ->addHeaderLine('Cache-Control: no-cache')
                    ->addHeaderLine("Content-Disposition: attachment; filename=\"{$contract_document->name}\"")
                    ->addHeaderLine('Content-Transfer-Encoding: binary')
                    ->addHeaderLine("Content-Type: {$contract_document->type}")
                    ->addHeaderLine('Content-Length: ' . strlen($contract_document->content))
                    ->addHeaderLine('Connection: close');
            return $response;
        }
        
        return $this->notFoundAction();
    }
    
	public function deleteAction() {
		$sm = $this->getServiceLocator();
		$db = $sm->get('db');
        $config = $sm->get('Config');
		$logger = $sm->get('logger');
		
        $contract_doc_dir = $config['contract']['docdir'];
		if(empty($contract_doc_dir)) {
			throw new \Exception("no valid contract doc directory");
		}
        
		$error = "";
		$status = 0;
		$id = $this->params()->fromRoute('id');
		
		$contract_documents_mdl = ContractDocuments::factory($sm);
		
		if($this->getRequest()->isPost()) {
			$con = $db->getDriver()->getConnection();
			
			try {
				$con->beginTransaction();
				
                $document = $contract_documents_mdl->get($id);
                
                if($document) {
                    $new_contract_doc_dir = $contract_doc_dir . DIRECTORY_SEPARATOR . $document->contract_id;
                    
                    $cnt = $contract_documents_mdl->delete($id);
                    if($cnt > 0) {
                        // we need to remove the doc from our storage
                        $doc_file = $new_contract_doc_dir . DIRECTORY_SEPARATOR . $document->path;
                        @unlink($doc_file);
                    }
                }
                
				$con->commit();
				
				$status = 1;
			} catch(\Exception $e) {
				$con->rollback();
				
				$p = $e->getPrevious();
				if($p) {
					$e = $p;
				}
				
				if(empty($error)) {
					$error = "Unable to complete deleting contract document, please try again";
				}
				$logger->log(Logger::ERR, "unable to delete contract document : " . $e->getMessage());
			}
		}
		
		return new JsonModel(array(
			'id' => $id,
			'status' => $status,
			'error' => $error
		));
	}
}
?>