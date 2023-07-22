<?php
namespace Ticket\Salesforce;

use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Json\Json;

abstract class Api {
	protected $host;
	protected $username;
	protected $password;
	protected $securitytoken;

    protected $session_id;
    protected $soap_version;
    protected $rest_version;

    protected $soap_client;
    protected $rest_client;

    const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';
    const MAX_OFFSET = 2000;
	const MAX_LIMIT = 1100;

	public function __construct($host, $username, $password, $securitytoken, $soap_version = '35.0', $rest_version = '38.0') {
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
        $this->securitytoken = $securitytoken;

        $this->soap_version = $soap_version;
        $this->rest_version = $rest_version;
	}

    public static function normalizeDate($date) {
		if(!is_integer($date)) {
			$date = strtotime($date);
		}

		if($date === false) {
			return null;
		}

        return gmdate(self::DATE_FORMAT, $date);
    }

	public static function denormalizeDate($date) {
		if(empty($date)) {
			return null;
		} else if(is_integer($date)) {
			// we convert the timestamp to a date representation, we are assuming the timestamp is in UTC
			$date = date('Y-m-d H:i:s', $date);
			$date = new \DateTime($date, new \DateTimeZone('UTC'));
			$date = $date->format(self::DATE_FORMAT);
		}

		// check if the date has the timezone/offset embeds
		$date = new \DateTime($date);
		$local_tz = new \DateTimeZone(date_default_timezone_get());

		if($date->getTimezone()->getName() != $local_tz->getName()) {
			// we need to convert the timezone to the local time
			$date->setTimezone($local_tz);
		}

		return $date->format('Y-m-d H:i:s');
	}

	public function getSoapClient() {

        /*$this->soap_client = new \SoapClient(null, array(
            'location' => "https://{$this->host}/services/Soap/u/{$this->soap_version}",
            'uri' => "partner.soap.sforce.com"
        ));*/
		// The code commented out used to work until 10/23/2018. I had to create
		// a work around by posting the xml body directly into
		$soap_client = new Client(null, array(
            'adapter' => '\Zend\Http\Client\Adapter\Curl',
            'curloptions' => array(
                CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_SSLVERSION => 6,
            ),
        ));
		$soap_client->setHeaders(array(
            'Content-Type' => "text/xml",
			'SOAPAction' => 'login'
        ));
		$soap_client->setUri("https://{$this->host}/services/Soap/u/{$this->soap_version}");
		$soap_client->setRawBody('<?xml version="1.0" encoding="utf-8" ?>
<env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:env="http://schemas.xmlsoap.org/soap/envelope/">
  <env:Body>
    <n1:login xmlns:n1="urn:partner.soap.sforce.com">
      <n1:username>' . $this->username . '</n1:username>
      <n1:password>' . $this->password . $this->securitytoken . '</n1:password>
    </n1:login>
  </env:Body>
</env:Envelope>');
		$soap_client->setMethod(Request::METHOD_POST);
		$response = $soap_client->send();
		$p = xml_parser_create();
		xml_parse_into_struct($p, $response->getBody(), $vals, $index);
		xml_parser_free($p);

		$this->session_id = $vals[8][value];

        if(empty($this->session_id)) {
            throw new \Exception("unable to login to SalesForce");
        }

        return $this->soap_client;
	}

    public function getRestClient() {
        if(empty($this->rest_client)) {
            $this->rest_client = new Client(null, array(
                'adapter' => '\Zend\Http\Client\Adapter\Curl',
                'curloptions' => array(
                    CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_SSLVERSION => 6,
                ),
            ));
        }

        if(empty($this->session_id)) {
            // we need to grab the soap client
            $soap_client = $this->getSoapClient();
            // if login fails, an exception will be thrown
        }

        if(!empty($this->session_id)) {
            $this->rest_client->setHeaders(array(
                'Authorization' => "Bearer {$this->session_id}"
            ));
        }

        return $this->rest_client;
    }

	protected function resetRestClient() {
		$this->rest_client->setHeaders(array());

		if(!empty($this->session_id)) {
            $this->rest_client->setHeaders(array(
                'Authorization' => "Bearer {$this->session_id}"
            ));
        }

		// clear the raw body
		$this->rest_client->setRawBody('');
	}

	public function create(
		$request,
		$products = '',
		$owner_id = null,
		$lab_location_required = null,
		$region = null,
		$preparation_start_date = null,
		$presentation_start_date = null,
		$record_type_id = null,
		$activity_product_release_state = 'Non-GA',
		$activity_service = 'Partner Testing',
		$activity_stage = 'Product Investigation',
		$activity_type = null,
		$business_unit = 'Data Center',
		$customer_presentation_location = 'No Presentation Required'
	) {
		$client = $this->getRestClient();
		$this->resetRestClient();

		$client->setUri("https://{$this->host}/services/data/v{$this->rest_version}/sobjects/Opportunity_Support_Request__c/");
		$content = array(
			'Testing_Requirements__c' => $request,
			'Product_Under_Test__c' => $products
		);

		if (!empty($owner_id)) {
			$content['OwnerId'] = $owner_id;
		}
		if (!empty($activity_product_release_state)) {
			$content['Activity_Product_Release_State__c'] = $activity_product_release_state;
		}
		if (!empty($activity_service)) {
			$content['Activity_Service__c'] = $activity_service;
		}
		if (!empty($activity_stage)) {
			$content['Activity_Stage__c'] = $activity_stage;
		}
		if (!empty($activity_type)) {
			$content['Activity_Type__c'] = $activity_type;
		}
		if (!empty($business_unit)) {
			$content['Business_Unit__c'] = $business_unit;
		}
		if (!empty($customer_presentation_location)) {
			$content['Customer_Presentation_Location__c'] = $customer_presentation_location;
		}
		if (!empty($preparation_start_date)) {
			$content['Preparation_Start_Date__c'] = date('Y-m-d', strtotime($preparation_start_date));
		}
		if (!empty($presentation_start_date)) {
			$content['Presentation_Start_Date__c'] = date('Y-m-d', strtotime($presentation_start_date));
		}
		if (!empty($region)) {
			$content['Region__c'] = $region;
		}
		if (!empty($lab_location_required)) {
			$content['Lab_Location_Required__c'] = $lab_location_required;
		}
		if (!empty($record_type_id)) {
			$content['RecordTypeId'] = $record_type_id;
		}

		$client->setMethod(Request::METHOD_POST);
		$client->setRawBody(Json::encode($content));
		$client->getRequest()->getHeaders()->addHeaderLine('Content-Type', 'application/json');

		$response = $client->send();
		$results = Json::decode($response->getBody(), Json::TYPE_ARRAY);

		return $results;
	}

	public function addNote(
		$osr_id,
		$owner_id,
		$title,
		$body
	) {
		$client = $this->getRestClient();
		$this->resetRestClient();

		$client->setUri("https://{$this->host}/services/data/v{$this->rest_version}/sobjects/Note/");
		$content = array(
			'ParentId' => $osr_id,
			'Title' => $title,
			'Body' => $body
		);

		// NOTE: our current user cannot set the owner id yet, no permissions at the moment
		if (false && !empty($owner_id)) {
			$content['OwnerId'] = $owner_id;
		}

		$client->setMethod(Request::METHOD_POST);
		$client->setRawBody(Json::encode($content));
		$client->getRequest()->getHeaders()->addHeaderLine('Content-Type', 'application/json');

		$response = $client->send();
		$results = Json::decode($response->getBody(), Json::TYPE_ARRAY);

		return $results;
	}

	public function addAttachment(
		$osr_id,
		$owner_id,
		$file,
		$description
	) {
		$client = $this->getRestClient();
		$this->resetRestClient();

		// TODO: need to convert the body to base64
		$file_name = basename($file);
		$content_type = mime_content_type($file);
		$body = base64_encode(file_get_contents($file));

		$client->setUri("https://{$this->host}/services/data/v{$this->rest_version}/sobjects/Attachment/");
		$content = array(
			'ParentId' => $osr_id,
			'Name' => $file_name,
			'ContentType' => $content_type,
			'Body' => $body,
			'Description' => $description
		);

		// NOTE: our current user cannot set the owner id yet, no permissions at the moment
		if (false && !empty($owner_id)) {
			$content['OwnerId'] = $owner_id;
		}

		$client->setMethod(Request::METHOD_POST);
		$client->setRawBody(Json::encode($content));
		$client->getRequest()->getHeaders()->addHeaderLine('Content-Type', 'application/json');

		$response = $client->send();
		$results = Json::decode($response->getBody(), Json::TYPE_ARRAY);

		return $results;
	}

	/**
	 * User creation will not work using the current account we have. We will have to rely that the
	 * user making the request already exists in Salesforce.  If they do not, we will not set a user.
	 *
	public function createUser(
		$username,
		$alias,
		$first_name,
		$last_name,
		$email,
		$company_name = null,
		$department = null,
		$title = null
	) {
		$client = $this->getRestClient();
		$this->resetRestClient();

		$client->setUri("https://{$this->host}/services/data/v{$this->rest_version}/sobjects/User/");
		$content = array(
			'Alias' => $alias,
			'TimeZoneSidKey' => 'America/Los_Angeles',
			'LocaleSidKey' => 'en_US',
			'EmailEncodingKey' => 'ISO-8859-1',
			'LanguageLocaleKey' => 'en_US',
			'Username' => $username,
			'FirstName' => $first_name,
			'LastName' => $last_name,
			'Email' => $email
		);

		if (!empty($company_name)) {
			$content['CompanyName'] = $company_name;
		}
		if (!empty($department)) {
			$content['Department'] = $department;
		}
		if (!empty($title)) {
			$content['Title'] = $title;
		}

		$client->setMethod(Request::METHOD_POST);
		$client->setRawBody(Json::encode($content));
		$client->getRequest()->getHeaders()->addHeaderLine('Content-Type', 'application/json');

		$response = $client->send();
		$results = Json::decode($response->getBody(), Json::TYPE_ARRAY);

		return $results;
	}
	*/

	public function query($query) {
        $client = $this->getRestClient();
		$this->resetRestClient();

        $client->setUri("https://{$this->host}/services/data/v{$this->rest_version}/query/?q={$query}");
        $client->setMethod(Request::METHOD_GET);

        $response = $client->send();
        $results = Json::decode($response->getBody(), Json::TYPE_ARRAY);

        return $results;
	}

	public function describe($name) {
		$client = $this->getRestClient();
		$this->resetRestClient();

        $client->setUri("https://{$this->host}/services/data/v{$this->rest_version}/sobjects/{$name}/describe/");
        $client->setMethod(Request::METHOD_GET);

        $response = $client->send();
        $results = Json::decode($response->getBody(), Json::TYPE_ARRAY);

        return $results;
	}

	public function total($where) {
		$where = $this->where($where);
		$query = "
			SELECT
				COUNT()
			FROM Opportunity_Support_Request__c
			{$where}
		";

		$result = $this->query($query);
		if ($result['done']) {
			return $result['totalSize'];
		}

		return 0;
	}

	public function dump($where, $offset = 0, $limit = null) {
		$where = $this->where($where);

		if (empty($limit)) {
			$limit = 25;
		}

		$query = "
			SELECT
				Id,
				Activity_Product_Release_State__c,
				Activity_Service__c,
				Activity_Stage__c,
				Activity_Type__c,
				Business_Unit__c,
				CreatedById,
				CreatedDate,
				Customer_Name__c,
				Customer_Presentation_Location__c,
				IsDeleted,
				LastModifiedById,
				LastModifiedDate,
				Name,
				OwnerId,
				Technical_Contact__c,
				Preparation_Start_Date__c,
				Presentation_Start_Date__c,
				Product_Under_Test__c,
				RecordTypeId,
				Region__c,
				Lab_Location_Required__c,
				Service_Type__c,
				Status__c,
				Testing_Requirements__c
			FROM Opportunity_Support_Request__c
			{$where}
			ORDER BY Name
			LIMIT {$limit}
			OFFSET {$offset}
		";

		$result = $this->query($query);

		if ($result['done']) {
			return $result['records'];
		} else {
			return array();
		}
	}

	protected function where($where) {
		if(is_string($where)) {
			// we will assume an osr is passed
			$where = array("Id = '{$where}'");
		}

		$sql_where = "";
		$and = 'WHERE';

		foreach($where as $value) {
			if (!empty($value)) {
				$sql_where .= " {$and} {$value} ";
				$and = "AND";
			}
		}

		return $sql_where;
	}

	public function getUserIdByEmail($email) {
		$result = $this->query("Select Id From User Where Email = '{$email}'");
		if ($result['totalSize'] > 0) {
			$record = $result['records'][0];
			if ($record) {
				return $record['Id'];
			}
		}

		return false;
	}
}
?>
