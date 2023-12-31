<?php
namespace Gem\Controller;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\View\Model\JsonModel;
use Zend\Log\Logger;
use Zend\Form\Factory;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Where;
use Gem\Model\Autogenerated;
use Gem\Model\LabviewUsers;
use Gem\Model\Labviews;
use Gem\Model\LabviewGroups;
use Gem\Model\LabviewDepartments;
use Gem\Model\LabviewProjects;
use Gem\Model\LabviewTestbeds;
use Gem\Model\LabviewDevices;
use Gem\Model\LabviewRacks;
use Gem\Model\LabviewLabs;
use Gem\Model\LabviewSites;
use Gem\Model\LabviewContracts;
use Gem\Model\LabviewVirtuals;
use Gem\Model\Labview\Gem\AssetDevices;
use Gem\Model\Labview\Gem\AssetRacks;
use Gem\Model\Labview\Gem\AssetLabs;
use Gem\Model\Labview\Gem\AssetSites;
use Gem\Model\Labview\Project\Projects;
use Gem\Model\Labview\Gem\Testbeds;
use Gem\Model\Labview\Gem\AssetVirtuals;
use Gem\Labview\Linkage\Device as LabviewLinkageDevice;
use Gem\Labview\Linkage\Rack as LabviewLinkageRack;
use Gem\Labview\Linkage\Lab as LabviewLinkageLab;
use Gem\Labview\Linkage\Site as LabviewLinkageSite;
use Gem\Labview\Linkage\Project as LabviewLinkageProject;
use Gem\Labview\Linkage\Testbed as LabviewLinkageTestbed;
use Gem\Labview\Linkage\Contract as LabviewLinkageContract;
use Gem\Labview\Linkage\Virtual as LabviewLinkageVirtual;
use Gem\Model\Sphinx\DeviceIndex;
use Gem\Model\Sphinx\VirtualIndex;
use Gem\Gearman\Worker\DeviceIndexer;
use Gem\Gearman\Worker\VirtualIndexer;
use Els\Gearman\Client as ElsGearmanClient;

class AutogeneratedController extends AbstractActionController {
	public function indexAction() {
		// we want to change the layout type to 'horizontal'
		$this->layout()->setVariable('layout_type', 'h');
		// we want to allow users to pin the filter on this page
		$this->layout()->setVariable('allow_pin_filter', true);

		// apply the pin filtering
		$this->pinfilter()->applyPinFilter($this->authentication()->getAuthenticatedUserId());

		// we should set the wizard redirect to be here
		$this->wizard()->setRedirect('labview', $this->url()->fromRoute('gem/default', array(
			'controller' => 'autogenerated',
			'action' => 'index'
		)));
    $query = $this->params()->fromQuery();
		$query['keywords'] = (empty($query['keywords'])) ? '' : $query['keywords'];

		return new ViewModel($query);
	}
}
 ?>
