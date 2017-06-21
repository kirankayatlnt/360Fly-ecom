<?php

class Fly_RestConnect_RestapiController extends Mage_Core_Controller_Front_Action
{
    // Get Base url
    public function getActurl()
    {
        return $acturl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
    }
    
    // Get consumer key and consumer secret key
    public function getConsumerDetails()
    {
        try {
            // Consumer created at admin with name: mobileapp
            $consumerdata = Mage::getModel('oauth/consumer')->getCollection()->addFieldToFilter('name', 'mobileapp');
            foreach ($consumerdata as $consumer) {
                $consumer['key']    = $consumer->getKey();
                $consumer['secret'] = $consumer->getSecret();
                return $consumer;
            }
        }
        catch (Exception $e) {
            $message['error'] = 'Invalid consumer data.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    // Get Customer Id using token key
    public function getCustomerId($token)
    {
        $oauthCollection = Mage::getModel('oauth/token')->getCollection()->addFieldToFilter('token', $token)->addFieldToFilter('revoked', '0')->getFirstItem();
        if ($oauthCollection->getCustomerId()) {
            return $customerid = $oauthCollection->getCustomerId(); //For customer type account
        }
    }
    
    // Get Access token 
    public function getOauthAccessKeyAndSecret($oauthConsumerKey, $oauthConsumerSecret, $username, $password, $baseurl)
    {
        
        //initiate
        $realm                = $baseurl;
        $endpointUrl          = $realm . "oauth/initiate";
        $oauthCallback        = $baseurl;
        $oauthNonce           = uniqid(mt_rand(1, 1000));
        $oauthSignatureMethod = "HMAC-SHA1";
        $oauthTimestamp       = time();
        $oauthVersion         = "1.0";
        $oauthMethod          = "POST";
        
        
        $params = array(
            "oauth_callback" => $oauthCallback,
            "oauth_consumer_key" => $oauthConsumerKey,
            "oauth_nonce" => $oauthNonce,
            "oauth_signature_method" => $oauthSignatureMethod,
            "oauth_timestamp" => $oauthTimestamp,
            "oauth_version" => $oauthVersion
            
        );
        
        $data = http_build_query($params);
        
        $encodedData    = $oauthMethod . "&" . urlencode($endpointUrl) . "&" . urlencode($data);
        $key            = $oauthConsumerSecret . "&";
        $signature      = hash_hmac("sha1", $encodedData, $key, 1);
        $oauthSignature = base64_encode($signature);
        
        $header = "Authorization: OAuth realm=\"$realm\",";
        foreach ($params as $key => $value) {
            $header .= $key . '="' . $value . "\", ";
        }
        $header .= "oauth_signature=\"" . $oauthSignature . '"';
        
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            $header
        ));
        curl_setopt($curl, CURLOPT_URL, $endpointUrl);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $response    = explode('&', $response);
        $key         = explode('=', $response[0]);
        $secret      = explode('=', $response[1]);
        $oauthkey    = $key[1];
        $oauthsecret = $secret[1];
        
        
        //authorize 
        $url = $baseurl . 'oauth/authorize?oauth_token=' . $oauthkey . '&username=' . serialize($username) . '&password=' . serialize($password);
        
        
        $curl = curl_init();
        $ch   = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Must be set to true so that PHP follows any "Location:" header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $a = curl_exec($ch); // $a will contain all headers
        
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        $url      = explode('&', $url);
        $url      = explode('=', $url[1]);
        $verifier = $url[1];
        
        
        //oauth access
        $endpointUrl = $realm . "oauth/token";
        $params2     = array(
            'oauth_consumer_key' => $oauthConsumerKey,
            'oauth_nonce' => uniqid(mt_rand(1, 1000)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
            'oauth_token' => $oauthkey,
            'oauth_verifier' => $verifier
        );
        
        $method = 'POST';
        // this is the url to get Request Token according to Magento doc
        $url    = $endpointUrl;
        
        // start making the signature
        ksort($params2); // @see Zend_Oauth_Signature_SignatureAbstract::_toByteValueOrderedQueryString() for more accurate sorting, including array params 
        $sortedParamsByKeyEncodedForm = array();
        foreach ($params2 as $key => $value) {
            $sortedParamsByKeyEncodedForm[] = rawurlencode($key) . '=' . rawurlencode($value);
        }
        $strParams     = implode('&', $sortedParamsByKeyEncodedForm);
        $signatureData = strtoupper($method) // HTTP method (POST/GET/PUT/...)
            . '&' . rawurlencode($url) // base resource url - without port & query params & anchors, @see how Zend extracts it in Zend_Oauth_Signature_SignatureAbstract::normaliseBaseSignatureUrl()
            . '&' . rawurlencode($strParams);
        
        $key            = rawurlencode($oauthConsumerSecret) . '&' . rawurlencode($oauthsecret);
        $oauthSignature = base64_encode(hash_hmac('SHA1', $signatureData, $key, 1));
        
        $header = "Authorization: OAuth realm=\"$realm\",";
        foreach ($params2 as $key => $value) {
            $header .= $key . '="' . $value . "\", ";
        }
        $header .= "oauth_signature=\"" . $oauthSignature . '"';
        
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            $header
        ));
        curl_setopt($curl, CURLOPT_URL, $endpointUrl);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        
        $response      = explode('&', $response);
        $access_key    = explode('=', $response[0]);
        $access_key    = $access_key[1];
        $access_secret = explode('=', $response[1]);
        $access_secret = $access_secret[1];
        
        // get customer id
        $customerId = $this->getCustomerId($access_key);
        
        $token['uuid']         = $customerId;
        $token['token']        = $access_key;
        $token['token_secret'] = $access_secret;
        return $token;
        
    }
    
    // get user access token and secret key
    public function authorizeAction()
    {
        
        $postdata = json_decode(file_get_contents('php://input'), true);
        
        
        if ($postdata['username'] && $postdata['password']) {
            $consumer = $this->getConsumerDetails();
            
            $access_token = $this->getOauthAccessKeyAndSecret($consumer['key'], $consumer['secret'], $postdata['username'], $postdata['password'], $this->getActurl());
            echo Mage::helper('core')->jsonEncode($access_token);
            exit;
        } else {
            $message['error'] = 'Invalid username or password';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    // get user access token and secret key
    public function signonAction()
    {
        
        $postdata = json_decode(file_get_contents('php://input'), true);
        
        
        if ($postdata['username'] && $postdata['password']) {
            $consumer = $this->getConsumerDetails();
            
            $access_token = $this->getOauthAccessKeyAndSecret($consumer['key'], $consumer['secret'], $postdata['username'], $postdata['password'], $this->getActurl());
            if ($access_token['uuid'] && $access_token['token_secret']) {
                // create customer session
                $customer = Mage::getModel('customer/customer');
                $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
                $session = Mage::getSingleton('customer/session');
                $customer->loadByEmail($postdata['username']);
                
                $session->setCustomerAsLoggedIn($customer);
                
                $url                = Mage::getBaseurl() . 'customer/account';
                $message['success'] = $url;
                echo Mage::helper('core')->jsonEncode($message);
                exit;
                
            } else {
                $message['error'] = 'Invalid username or password';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
        } else {
            $message['error'] = 'Invalid username or password';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    // Get Request/Post data 
    public function getReqeustData()
    {
        
        $postdata = json_decode(file_get_contents('php://input'), true);
        
        if (isset($postdata)) {
            return $postdata;
        }
        
        return false;
        
        
    }
    
    // Get customer information
    public function validateCustomer($params)
    {
        
        $header_request = $params;
        
        if ($header_request) {
            $tokens = Mage::getModel('oauth/token')->getCollection()->addFieldToFilter('token', $header_request['token']);
            foreach ($tokens as $token) {
                $token_secret = $token->getSecret();
                $customerid   = $token->getCustomerId();
            }
            
            if ($header_request['uuid'] != $customerid) {
                $message['error'] = 'Invalid token or Invalid customer';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
            
            // Get consumer details
            $consumer = $this->getConsumerDetails();
        } else {
            $message['error'] = 'Invalid token';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
        $params = array(
            
            'siteUrl' => $this->getActurl() . 'oauth',
            'requestTokenUrl' => $this->getActurl() . 'oauth/initiate',
            'accessTokenUrl' => $this->getActurl() . 'oauth/token',
            'consumerKey' => $consumer['key'], //Consumer key registered in server administration
            'consumerSecret' => $consumer['secret'], //Consumer secret registered in server administration
            'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER
            
        );
        
        // Initiate oAuth consumer
        $consumer   = new Zend_Oauth_Consumer($params);
        // Using oAuth parameters and request Token we got, get access token
        $acessToken = new Zend_Oauth_Token_Access;
        $acessToken->setParams(array(
            'oauth_token' => $header_request['token'],
            'oauth_token_secret' => $token_secret
        ));
        
        
        // do a request
        $restClient = $acessToken->getHttpClient($params);
        $restClient->setUri($this->getActurl() . 'api/rest/customers/' . $customer_id);
        $restClient->setHeaders('Accept', 'application/json');
        $restClient->setMethod(Zend_Http_Client::GET);
        $response = $restClient->request();
        
        return json_decode($response->getBody());
        
    }
	
    
    // Get customer information
    public function customerinfoAction()
    {
        
        $header_request = $this->getReqeustData();
        
        if ($header_request) {
            $tokens = Mage::getModel('oauth/token')->getCollection()->addFieldToFilter('token', $header_request['token']);
            foreach ($tokens as $token) {
                $token_secret = $token->getSecret();
                $customerid   = $token->getCustomerId();
            }
            
            if ($header_request['uuid'] != $customerid) {
                $message['error'] = 'Invalid token or Invalid customer';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
            
            // Get consumer details
            $consumer = $this->getConsumerDetails();
        } else {
            $message['error'] = 'Invalid token';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
        $params = array(
            
            'siteUrl' => $this->getActurl() . 'oauth',
            'requestTokenUrl' => $this->getActurl() . 'oauth/initiate',
            'accessTokenUrl' => $this->getActurl() . 'oauth/token',
            'consumerKey' => $consumer['key'], //Consumer key registered in server administration
            'consumerSecret' => $consumer['secret'], //Consumer secret registered in server administration
            'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER
            
        );
        
        // Initiate oAuth consumer
        $consumer   = new Zend_Oauth_Consumer($params);
        // Using oAuth parameters and request Token we got, get access token
        $acessToken = new Zend_Oauth_Token_Access;
        $acessToken->setParams(array(
            'oauth_token' => $header_request['token'],
            'oauth_token_secret' => $token_secret
        ));
        
        
        // do a request
        $restClient = $acessToken->getHttpClient($params);
        $restClient->setUri($this->getActurl() . 'api/rest/customers/' . $customer_id);
        $restClient->setHeaders('Accept', 'application/json');
        $restClient->setMethod(Zend_Http_Client::GET);
        $response = $restClient->request();
        
        echo ($response->getBody());
        
    }
    
    // Get subscription plans
    public function productsAction()
    {
        
        $header_request = $this->getReqeustData();
        
        
        if ($header_request) {
            $tokens = Mage::getModel('oauth/token')->getCollection()->addFieldToFilter('token', $header_request['token']);
            foreach ($tokens as $token) {
                $token_secret = $token->getSecret();
                $customerid   = $token->getCustomerId();
            }
            
            if ($header_request['uuid'] != $customerid) {
                $message['error'] = 'Invalid token or Invalid customer';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
            
            // Get consumer details
            $consumer = $this->getConsumerDetails();
            
        } else {
            $message['error'] = 'Invalid token';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
        $params = array(
            
            'siteUrl' => $this->getActurl() . 'oauth',
            'requestTokenUrl' => $this->getActurl() . 'oauth/initiate',
            'accessTokenUrl' => $this->getActurl() . 'oauth/token',
            'consumerKey' => $consumer['key'], //Consumer key registered in server administration
            'consumerSecret' => $consumer['secret'], //Consumer secret registered in server administration
            'requestScheme' => Zend_Oauth::REQUEST_SCHEME_HEADER
            
        );
        
        // Initiate oAuth consumer
        $consumer   = new Zend_Oauth_Consumer($params);
        // Using oAuth parameters and request Token we got, get access token
        $acessToken = new Zend_Oauth_Token_Access;
        $acessToken->setParams(array(
            'oauth_token' => $header_request['token'],
            'oauth_token_secret' => $token_secret
        ));
        
        
        // do a request
        $restClient = $acessToken->getHttpClient($params);
        $restClient->setUri($this->getActurl() . 'api/rest/products');
        $restClient->setHeaders('Accept', 'application/json');
        $restClient->setMethod(Zend_Http_Client::GET);
        
		
		
		// Filters
		$restClient->setParameterGet('filter[1][attribute]', 'storage');
        $restClient->setParameterGet('filter[1][neq]', 0);
		
		/*$attrSetName = 'subscription';
		$attributeSetId = Mage::getModel('eav/entity_attribute_set')
			->load($attrSetName, 'attribute_set_name')
			->getAttributeSetId();
	
        $restClient->setParameterGet('filter[1][attribute]', 'attribute_set_id');
        $restClient->setParameterGet('filter[1][eq]', $attributeSetId);*/
        
        $response = $restClient->request();
        // Here we can see that response body contains json list of products
        
        
        $flag = 0;
        foreach (json_decode($response->getBody()) as $prod) {
            
            $product_data = Mage::getModel('catalog/product')->load($prod->entity_id);
            if ($product_data->isRecurring() && $profile = $product_data->getRecurringProfile()) {
                $prod->recurring_profile = ($profile);
            }
            
            if ($prod->storage) {
                $storage        = $prod->storage;
                $attributes     = Mage::getModel('catalogsearch/advanced')->getAttributes();
                $attributeArray = array();
                foreach ($attributes as $a) {
                    if ($a->getAttributeCode() == 'storage') {
                        foreach ($a->getSource()->getAllOptions(false) as $option) {
                            
                            if ($option['value'] == $storage) {
                                $prod->storage = $option['label'];
                                //break;
                            }
                        }
                    }
                }
            }
            
            if ($prod->entity_id) {
                $i          = $prod->entity_id;
                $products[] = $prod;
                $flag       = 1;
            }
            
            
        }
        
        if ($flag != 1) {
            $message['error'] = 'No any subscription plan found.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        } else {
            echo (json_encode((array) $products));
        }
        
        
    }
    
    //     Add subscription package/recurring product to customer cart
    public function subscribeAction()
    {
		$storeid = Mage::app()->getStore()->getStoreId();
		$header_request = $this->getReqeustData();
        
        $validateCustomer = $this->validateCustomer($header_request);
        
        if (isset($validateCustomer)) {
            
            if ($header_request['planid']) {
                
				$baseurl = $this->getActurl();
                $sub_req_url = $baseurl . 'restapi/subscribe.php';
                
                $ch      = curl_init($sub_req_url);
                $encoded = '';
                foreach ($header_request as $name => $value) {
                    $encoded .= urlencode($name) . '=' . urlencode($value) . '&';
                }
                $encoded .= urlencode('storeid'). '='. urlencode($storeid).'&';
                // chop off last ampersand
                $encoded = substr($encoded, 0, strlen($encoded) - 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);
                $response = curl_exec($ch);
                
                if ($response == true) {
                    $this->placeOrder($header_request['uuid']);
                }
                
                curl_close($ch);
            } else {
                $message['error'] = 'Invalid action or plan id';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
        } else {
            $message['error'] = 'Invalid customer.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    
    //     Buy additional storage - package/recurring product
    public function topupAction()
    {
		$storeid = Mage::app()->getStore()->getStoreId();
        $header_request = $this->getReqeustData();
        
        $validateCustomer = $this->validateCustomer($header_request);
        
        if (isset($validateCustomer)) {
            
			$collection = Mage::getModel('sales/recurring_profile')->getCollection()->addFieldToFilter('customer_id', array(
                'eq' => $header_request['uuid']
            ))
            ->addFieldToFilter('state', array('eq' => 'active'))
            ->setOrder('created_at', 'DESC')->setPageSize(1);
            
            foreach ($collection as $profile) {
                
                $profiledata = Mage::getModel('sales/recurring_profile')->load($profile->getId());
                $id          = $profiledata['profile_id'];
                $profiles[]  = $profiledata->getData();
            }
            if (count($profiles) <= 0) {
				$message['error'] = 'Sorry, You can not subscribe for additional storage.';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
			
            if ($header_request['planid']) {
                
                $baseurl = $this->getActurl();
                $sub_req_url = $baseurl . 'restapi/subscribe.php';
                
                $ch      = curl_init($sub_req_url);
                $encoded = '';
                foreach ($header_request as $name => $value) {
                    $encoded .= urlencode($name) . '=' . urlencode($value) . '&';
                }
                $encoded .= urlencode('storeid'). '='. urlencode($storeid).'&';
                // chop off last ampersand
                $encoded = substr($encoded, 0, strlen($encoded) - 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);
                $response = curl_exec($ch);
                
                if ($response == true) {
                    $this->placeOrder($header_request['uuid']);
                }
                
                curl_close($ch);
            } else {
                $message['error'] = 'Invalid action or plan id';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
        } else {
            $message['error'] = 'Invalid customer.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    //     Buy additional storage - package/recurring product
    public function topupbandwidthAction()
    {
        $storeid = Mage::app()->getStore()->getStoreId();
		$header_request = $this->getReqeustData();
        
        $validateCustomer = $this->validateCustomer($header_request);
        
        if (isset($validateCustomer)) {
            
			$collection = Mage::getModel('sales/recurring_profile')->getCollection()->addFieldToFilter('customer_id', array(
                'eq' => $header_request['uuid']
            ))
            ->addFieldToFilter('state', array('eq' => 'active'))
            ->setOrder('created_at', 'DESC')->setPageSize(1);
            
            foreach ($collection as $profile) {
                
                $profiledata = Mage::getModel('sales/recurring_profile')->load($profile->getId());
                $id          = $profiledata['profile_id'];
                $profiles[]  = $profiledata->getData();
            }
            if (count($profiles) <= 0) {
				$message['error'] = 'Sorry, You can not subscribe for additional bandwith.';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
			
            if ($header_request['planid']) {
                
                $baseurl = $this->getActurl();
                $sub_req_url = $baseurl . 'restapi/subscribe.php';
                
                $ch      = curl_init($sub_req_url);
                $encoded = '';
                foreach ($header_request as $name => $value) {
                    $encoded .= urlencode($name) . '=' . urlencode($value) . '&';
                }
                $encoded .= urlencode('storeid'). '='. urlencode($storeid).'&';
                // chop off last ampersand
                $encoded = substr($encoded, 0, strlen($encoded) - 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);
                $response = curl_exec($ch);
                
                if ($response == true) {
                    $this->placeOrder($header_request['uuid']);
                }
                
                curl_close($ch);
            } else {
                $message['error'] = 'Invalid action or plan id';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
        } else {
            $message['error'] = 'Invalid customer.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    //     Create login session of customer and redirect to checkout page of ecomm site/magento site
    public function placeOrder($customerid)
    {
        
        try {
            
            $customer = Mage::getModel('customer/customer');
            $customer->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
            $session = Mage::getSingleton('customer/session');
            $customer->load($customerid);
            
            $session->setCustomerAsLoggedIn($customer);
            
            $url  = Mage::getBaseurl() . 'checkout/onepage';
            $message['success'] = $url;
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        catch (Exception $e) {
            $message['error'] = 'Invalid customer.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
    }
    
    // Get list of subscribed plan by customer with their details
    public function mysubscriptionsAction()
    {
        
        $header_request = $this->getReqeustData();
        
        $validateCustomer = $this->validateCustomer($header_request);
        
        if (isset($validateCustomer)) {
            
            $collection = Mage::getModel('sales/recurring_profile')->getCollection()
			->addFieldToFilter('state', array('eq' => 'active'))->addFieldToFilter('customer_id', array(
                'eq' => $header_request['uuid']));
			
			if(count($collection)<=0)
			{
				$collection = Mage::getModel('custompayment/order')->getCollection()->addFieldToFilter('customer_id', $header_request['uuid'])
				->addFieldToFilter('state','active');
			}
			
			foreach ($collection as $profile) {
            
                $iteminfo = unserialize($profile['order_item_info']);
				$profile['order_item_info'] = $iteminfo;
				$profiles[]  = $profile->getData();
				$flag = 1;

            }
			

            if (count($profiles) > 0) {
			
                echo Mage::helper('core')->jsonEncode($profiles);
                exit;
            } else {
			
				$orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_id', $header_request['uuid'])
				->addFieldToFilter('custom_attribute', array('eq' => 'subscription' ));
				
				$productid = "0";
				foreach($orders as $order){
					//$order     = Mage::getModel('sales/order')->load($_orderId);

					if($order->getId())
					{
					
						$items = $order->getAllItems();
						foreach($items as $item){
						$product = Mage::getModel("catalog/product")->load($item->getProductId());
						 
							//if($order->getSubtotal()<=0.0000 && $order->getGrandTotal()<=0.0000)
							if($product->getPrice()<=0.0000 && $product->getStorage())
							{ 
								$freeitem = $item;
								$productid = $item->getProductId();
								 if ($product->getStorage()) {
									$storage        = $product->getStorage();
									$attributes     = Mage::getModel('catalogsearch/advanced')->getAttributes();
									$attributeArray = array();
									foreach ($attributes as $a) {
										if ($a->getAttributeCode() == 'storage') {
											foreach ($a->getSource()->getAllOptions(false) as $option) {
												
												if ($option['value'] == $storage) {
													$storagelabel = $option['label'];
													$item->storage = $option['label'];
													//break;
												}
											}
										}
									}
								}
								break;
							}
											
						}
					}
					
					
				}
				
				
				if($productid)
				{
					$order = (array)$order->getData();
					$order['items'] = (array)$freeitem->getData();
					echo Mage::helper('core')->jsonEncode($order);
					exit;
				}
				else
				{
					$message['error'] = 'No subscribed package found.';
					echo Mage::helper('core')->jsonEncode($message);
					exit;
				}
        }    
        } else {
            $message['error'] = 'Invalid token';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
    }
    
    
    // Update subscribed profile status to activate, suspend or cancel.
    function profileactAction()
    {
        $header_request = $this->getReqeustData();
        
        $validateCustomer = $this->validateCustomer($header_request);
		
		if($header_request['profile'] === 0)
		{
			$header_request['profile'] = 'mobile';
		}
        
        if (isset($validateCustomer)) {
            
            if ($header_request['action'] && $header_request['profile']) {
                
                $baseurl = $this->getActurl();
                $sub_req_url = $baseurl . 'restapi/action.php';
                
                $ch      = curl_init($sub_req_url);
                $encoded = '';
                foreach ($header_request as $name => $value) {
                    $encoded .= urlencode($name) . '=' . urlencode($value) . '&';
                }
                
                // chop off last ampersand
                $encoded = substr($encoded, 0, strlen($encoded) - 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);
                $response = curl_exec($ch);
                
                curl_close($ch);
            } else {
                $message['error'] = 'Invalid action or profile id';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
            
        } else {
            $message['error'] = 'Invalid token';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
    
    // Get list of subscribed plan by customer with their details
    public function subscriptiondetailsAction()
    {
        
        $header_request = $this->getReqeustData();
        
        $validateCustomer = $this->validateCustomer($header_request);
        
        if (isset($validateCustomer)) {
            
            $collection = Mage::getModel('sales/recurring_profile')->getCollection()->addFieldToFilter('customer_id', array(
                'eq' => $header_request['uuid']
            ))->addFieldToFilter('state', array(
                'eq' => 'active'
            ))->setOrder('created_at', 'DESC');
            
            foreach ($collection as $profile) {
                
                $profiledata = Mage::getModel('sales/recurring_profile')->load($profile->getId());
                $id          = $profiledata['profile_id'];
                $profiles[]  = $profiledata->getData();
				$flag = 1;
            }
            if (count($profiles) > 0) {
			
                echo Mage::helper('core')->jsonEncode($profiles);
                exit;
            } else {
			
				$orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('customer_id', $header_request['uuid'])
				->addFieldToFilter('custom_attribute', array('eq' => 'subscription' ));
				
				$productid = "0";
				foreach($orders as $order){
					//$order     = Mage::getModel('sales/order')->load($_orderId);

					if($order->getId())
					{
					
						$items = $order->getAllItems();
						foreach($items as $item){
						$product = Mage::getModel("catalog/product")->load($item->getProductId());
						 
							//if($order->getSubtotal()<=0.0000 && $order->getGrandTotal()<=0.0000)
							if($product->getPrice()<=0.0000 && $product->getStorage())
							{ 
								$freeitem = $item;
								$productid = $item->getProductId();
								 if ($product->getStorage()) {
									$storage        = $product->getStorage();
									$attributes     = Mage::getModel('catalogsearch/advanced')->getAttributes();
									$attributeArray = array();
									foreach ($attributes as $a) {
										if ($a->getAttributeCode() == 'storage') {
											foreach ($a->getSource()->getAllOptions(false) as $option) {
												
												if ($option['value'] == $storage) {
													$storagelabel = $option['label'];
													$item->storage = $option['label'];
													//break;
												}
											}
										}
									}
								}
								break;
							}
											
						}
					}
					
					
				}
				
				
				if($productid)
				{
					$order = (array)$order->getData();
					$order['items'] = (array)$freeitem->getData();
					echo Mage::helper('core')->jsonEncode($order);
					exit;
				}
				else
				{
					$message['error'] = 'No subscribed package found.';
					echo Mage::helper('core')->jsonEncode($message);
					exit;
				}
        } 
            
        } else {
            $message['error'] = 'Invalid token';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
    }
	
	
	//     Buy additional storage - package/recurring product
    public function mobilepayAction()
    {
		$storeid = Mage::app()->getStore()->getStoreId();
        $header_request = $this->getReqeustData();
		$header_request['storeid'] = $storeid;
        
		
        $validateCustomer = $this->validateCustomer($header_request);
        
        if (isset($validateCustomer)) {
		
			
			
			if ($header_request['uuid'] && $header_request['planid']) {
                
                $baseurl = $this->getActurl();
                $sub_req_url = $baseurl . 'restapi/mobileactions.php';
                
                $ch      = curl_init($sub_req_url);
                $encoded = '';
                foreach ($header_request as $name => $value) {
                    $encoded .= urlencode($name) . '=' . urlencode($value) . '&';
                }
                
                // chop off last ampersand
                $encoded = substr($encoded, 0, strlen($encoded) - 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_POST, 1);
                $response = curl_exec($ch);
                
                curl_close($ch);
            } else {
                $message['error'] = 'Invalid action or plan id';
                echo Mage::helper('core')->jsonEncode($message);
                exit;
            }
            	
		
            
        } else {
            $message['error'] = 'Invalid customer.';
            echo Mage::helper('core')->jsonEncode($message);
            exit;
        }
        
    }
	
}
?>