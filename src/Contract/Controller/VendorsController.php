<?php
namespace Contract\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;

use Contract\Model\Vendors;
use Contract\Model\VendorContacts;

class VendorsController extends AbstractActionController {
	
	public function indexAction() {
		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
			'controller' => 'vendors',
			'action' => 'index',
		)));
		
		return new ViewModel(array(
		));
	}
	
	public function viewAction() {
		$sm = $this->getServiceLocator();
		
		$id = $this->params()->fromRoute('id');
		$onload = $this->params()->fromQuery('onload');
		
		$vendors_mdl = Vendors::factory($sm);
		$vendor = $vendors_mdl->get($id);
		
		if($onload=='') {
			// we should set the wizard redirect to be here
			$this->wizard()->setRedirect('contracts', $this->url()->fromRoute('contract/default', array(
				'controller' => 'vendors',
				'action' => 'view',
				'id' => $id
			)));
		}
		
		return new ViewModel(array(
			'id' => $id,
			'name' => $vendor->name,
			'notes' => $vendor->notes,
			'active' => $vendor->active,
			'logo' => $vendor->logo,
			'onload' => $onload
		));
	}
	
	public function addAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$name = trim($this->params()->fromPost('name'));
		$notes = trim($this->params()->fromPost('notes'));
		$active = 1;
		
		$logger->log(Logger::DEBUG, "attempting to add new vendor {$name}");
		
		$vendors_mdl = Vendors::factory($sm);
		
		$status = 0;
		//vendor name already exists
		if($vendors_mdl->exists(array(
			'name' => $name
		))) {
			$error = "Vendor with matching name already exists";
		}
		else {
			$result = $vendors_mdl->insert(array(
				'name' => $name, 
				'notes' => $notes, 
				'active' => $active
			));
			if($result) {
				$status = 1;
			}
			else {
				$error = "Unable to complete adding vendor, please try again";
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
		$notes = trim($this->params()->fromPost('notes'));
		$active = trim($this->params()->fromPost('active'));
		
		$logger->log(Logger::DEBUG, "attempting to edit vendor ID {$id}");
		
		$vendors_mdl = Vendors::factory($sm);
		
		$status = 0;
		if(!$vendors_mdl->exists($id)) {
			$error = "Vendor no longer exists";
		}
		//vendor name already exists
		else if($vendors_mdl->get(array(
			'name' => $name,
			'!id' => $id
		))) {
			$error = "Vendor with matching name already exists";
		}
		else {
			$vendors_mdl->update($id, array(
				'name' => $name,
				'notes' => $notes,
				'active' => $active
			));
			
			$status = 1;
		}
		
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}

	public function addContactAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$vendor_id = trim($this->params()->fromPost('vendor_id'));
		$description = trim($this->params()->fromPost('description'));
		$phone_number = trim($this->params()->fromPost('phone_number'));
		$email = trim($this->params()->fromPost('email'));
		$url = trim($this->params()->fromPost('url'));
		$notes = trim($this->params()->fromPost('notes'));
		$default = 0;
		$active = 1;
		
		$logger->log(Logger::DEBUG, "attempting to add new vendor support contact for vendor ID {$id}");
		
		$vendor_contacts_mdl = VendorContacts::factory($sm);
		$result = $vendor_contacts_mdl->insert(array(
			'vendor_id' => $vendor_id,
			'description' => $description,
			'phone_number' => $phone_number,
			'email' => $email,
			'url' => $url,
			'notes' => $notes,
			'default' => $default,
			'active' => $active
		));
		
		$status = 0;

		if($result) {
			$status = 1;
		}
		else {
			$error = "Unable to complete adding vendor support contact, please try again";
		}
	
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
	
	public function editContactAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$id = trim($this->params()->fromPost('id'));
		$description = trim($this->params()->fromPost('description'));
		$phone_number = trim($this->params()->fromPost('phone_number'));
		$email = trim($this->params()->fromPost('email'));
		$url = trim($this->params()->fromPost('url'));
		$notes = trim($this->params()->fromPost('notes'));
		
		$vendor_contacts_mdl = VendorContacts::factory($sm);
		$vendor_contact = $vendor_contacts_mdl->get($id);
		
		if($vendor_contact) {
			$vendor_contacts_mdl->update($id, array(
				'description' => $description,
				'phone_number' => $phone_number,
				'email' => $email,
				'url' => $url,
				'notes' => $notes
			));
		}

		$status = 1;

		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
	
	public function deleteContactAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$id = trim($this->params()->fromPost('id'));

		
		$vendor_contacts_mdl = VendorContacts::factory($sm);
		$vendor_contact = $vendor_contacts_mdl->get($id);
		
		if($vendor_contact) {
			$vendor_contacts_mdl->update($id, array(
				'active' => 0,
				'default' => 0
			));
		}
		
		$status = 1;
		
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
	
	public function defaultContactAction() {
		$sm = $this->getServiceLocator();
		$logger = $sm->get('logger');
		
		$id = trim($this->params()->fromPost('id'));
		
		$logger->log(Logger::DEBUG, "attempting to mark as default support contact ID {$id}");
		
		$vendor_contacts_mdl = VendorContacts::factory($sm);
		$vendor_contact = $vendor_contacts_mdl->get($id);
		
		if($vendor_contact) {
			$vendor_id = $vendor_contact->vendor_id;
			$result = $vendor_contacts_mdl->update($id, array(
				'default' => 1
			));
			
			$logger->log(Logger::DEBUG, "mark as default support contact ID {$id}, result: {$result}");
			
			//unmark any possible previous defaults
			$vendor_contacts = $vendor_contacts_mdl->filter(array(
				'vendor_id' => $vendor_id,
				'default' => 1,
				'!id' => $id
			), array(), array(), $vendor_contacts_total);
			
			foreach($vendor_contacts AS $vendor_contact) {
				$vendor_contacts_mdl->update($vendor_contact->id, array(
					'default' => 0
				));
			}
		}
		$status = 0;
		
		if($result) {
			$status = 1;
		}
		else {
			$error = "Unable to complete marking as default vendor support contact, please try again";
		}
		
		return new JsonModel(array(
			'status' => $status,
			'error' => $error
		));
	}
}
?>