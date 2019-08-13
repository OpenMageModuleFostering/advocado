<?php

if ( isset( $_SERVER[ 'ADVOC_LOCAL_DEBUG' ] ) ) { 
    define( 'ADVOCADO_BACKEND_HOST', 'http://localhost:8000' );
} elseif ( isset( $_SERVER[ 'ADVOC_STAGING' ] ) ) {
    define( 'ADVOCADO_BACKEND_HOST', 'http://test.advoca.do' );
} else { 
    define( 'ADVOCADO_BACKEND_HOST', 'http://api.advoca.do' );
}

/** Convenience function for setting multiple parameters 
 *  for a Varien_Http_Client
 *  @param Varien_Http_Client $client
 *  @parma array $args Attribute-value arguments as an associative array.
 */
function setPostParameters( $client, $args ) { 
    foreach ( $args as $k => $v ) { 
        $client->setParameterPost( $k, $v );
    }
    return $client;
}

/** Convenience function for setting stuff up for sending
 *  Http Requests.
 *  @param string $url URL.
 *  @param string $method HTTP Method.
 *  @param array $args Parameters.
 *  @return Varien_Http_Client
 */
function _httpClient( $url, $method, $args ) { 

    $cl = new Varien_Http_Client( $url );
    $cl->setHeaders( 'accept-encoding', '' )
       ->setHeaders( 'content-type', 'application/x-www-form-urlencoded' )
       ->setMethod( $method );
    $fn = null;
    if ( $method == Varien_Http_Client::GET ) { 
        $fn = 'setParameterGet';
    } else if ( $method == Varien_Http_Client::POST) { 
        Mage::log('request is post');
        $fn = 'setParameterPost';
    }
    else if ( $method == Varien_Http_Client::PUT ) { 
        // set raw body
        $qs = http_build_query( $args );
        Mage::log('http_build_query: ' . $qs);
        $cl->setRawData($qs, Varien_Http_Client::ENC_URLENCODED);
        Mage::log('body set'); 
    }
    Mage::log('with $arguments = ' . var_export($args, true));
    if ( $fn ) {
        foreach( $args as $key => $val ) { 
            call_user_func(array($cl, $fn), $key, $val );
        }
    } 
    return $cl;
}

/**
 * Convenience method to compose the url by concatenation.
 */
function _urlCompose($host, $url, $id='') { 
    $u = $host . $url;
    if ( $id != '' )  { 
        $u .= '/' . strval( $id );
    }
    Mage::log( '_urlCompose => ' . $u );
    return $u;
}

/**
 *
 * Sends HTTP requests to the backend for a number of reasons.
 *
 */
class GozoLabs_Advocado_Helper_Backend extends Mage_Core_Helper_Abstract { 

    const URL_MERCHANT_LOGIN        = '/v1/authentication/login';
    const URL_REQUEST_TOKEN         = '/v1/authentication/credentials/siteToken';
    const URL_REQUEST_IFTOKEN       = '/v1/authentication/credentials/ifToken';
    const URL_CAMPAIGNS             = '/v1/campaigns';
    const URL_SHARES                = '/v1/shares';
    const URL_COUPONS               = '/v1/dynamic_codes';
    const URL_SUBSCRIPTIONS         = '/v1/subscriptions';
    const ADMIN_SIGNUP_URL          = '/actors/app_admin/create/';
    const DASHBOARD_URL_PATH        = '/admin_pages/dashboard';
    const DASHBOARD_LOGIN_URL       = '/admin_pages/login/';
    const PASSWORD_RESET_URL        = '/admin_pages/user/password/reset/';
    const COOKIE_STCODES            = 'advoc.stCodes';

    const TEMP_WEBSITE_ID           = 1;
    const TEMP_STORE_GROUP_ID       = 1;


    public function isStoreConnected() { 
        $sc = $this->storeCredentials();
        if ($sc) { 
            return true; 
        } 
        return false;
    }

    public function passwordResetUrl() {
        return ADVOCADO_BACKEND_HOST . self::PASSWORD_RESET_URL;
    }

    public function merchantSignupUrl() { 
        return ADVOCADO_BACKEND_HOST . self::ADMIN_SIGNUP_URL;
    }

    /** URL for accessing the dashboard */
    public function dashboardUrl() {
        Mage::log('dashboardUrl  = ' . ADVOCADO_BACKEND_HOST . self::DASHBOARD_URL_PATH);
        return ADVOCADO_BACKEND_HOST . self::DASHBOARD_URL_PATH;
    }

    /**
     * URL for logging in, formally a form
     */
    public function dashboardLoginUrl() { 
        Mage::log('dashboardLoginUrl = ' . ADVOCADO_BACKEND_HOST . self::DASHBOARD_LOGIN_URL);
        return ADVOCADO_BACKEND_HOST . self::DASHBOARD_LOGIN_URL;
    }

    /** Either gets or sets the advocado credentials for this store,
     *  depending on whether $newData is specified or not.
     *  @param array $newData Credentials fields to be set/updated for the current store.
     *  @return GozoLabs_Advocado_Model_Credentials.
     */
    //TODO: change the website and storegroup
    public function storeCredentials( $websiteId=1, $storeGroupId=1, $newData = null ) {

        //$storeGroupId = Mage::app()->getStore()->getGroupId(); 
        Mage::log(' getting store credentials for store group ID ' . $storeGroupId);
        $creds = Mage::getModel('gozolabs_advocado/credentials')
                ->getCollection()
                ->addFilter('website_id', $websiteId)
                ->addFilter('store_group_id', $storeGroupId);

        // our credentials
        $c = null;
        if ( $creds->getSize() > 0 ) { 
            Mage::log('there are creds: ' . $creds->getSize());
            $c = $creds->getFirstItem();
        } else { 
            if ( $newData )  {
                $c = Mage::getModel('gozolabs_advocado/credentials');
                $newData['store_group_id'] = $storeGroupId;
                $newData['website_id'] = $websiteId;
            } else { 
                Mage::log('giving up, returning null since there are no creds');
                return null;
            }
        }

        if ( $newData ) { 
            Mage::log('updating credentials');
            foreach ( $newData as $attr => $val )  {
                $c->setData( $attr, $val );
            }
            $c->save();
        }
        return $c;
    }

    protected function generateRandom( $length = 10 ) { 
        
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz' 
            . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public function hmacTokens( $siteSecret ) { 
        $random = $this->generateRandom();
        $verify = hash_hmac( 'sha1', $random, $siteSecret );
        return  array( 'random' => $random, 'verify' => $verify );
    }

    public function requestSiteToken( $credsModel = null ) { 

        if ( ! $credsModel ) { 
            $helper = Mage::helper('gozolabs_advocado');
            $credsModel = $this->storeCredentials(
                $helper->getCurrentWebsiteId(),
                $helper->getCurrentStoreGroupId()
            );
        }

        // no ID, means credentials for this store not requested yet
        if ( ! $credsModel || ! $credsModel->getId() ) { 
            return null;
        }

        $siteSecret = $credsModel->getData( 'site_secret' );
        $siteKey = $credsModel->getData( 'site_key' );
        Mage::log('siteKey: ' . $siteKey . ', siteSecret: ' . $siteSecret);
        $hmac = $this->hmacTokens( $siteSecret );


        $cl = _httpClient( _urlCompose(
            ADVOCADO_BACKEND_HOST, self::URL_REQUEST_TOKEN ),
            Varien_Http_Client::POST,
            array(
                'site_key' => $siteKey,
                'random'  => $hmac['random'],
                'verify'  => $hmac['verify']
            ));

        $response = $cl->request();
        if ( $response->isSuccessful() ) { 
            $data = Mage::helper('core')->jsonDecode( $response->getRawBody() );
            $credsModel->setData( 'site_token', $data['site_token'] );
            //$credsModel->setData( 'iframe_token', $data['if_token'] );
            $credsModel->save();
            return $data;
        } else { 
            Mage::log('error getting token. status = ' . $response->getStatus() 
                . ', body = (' 
                .$response->getMessage()
                . ') '
                . $response->getRawBody());
        }
        return null;
    }

    public function login( $username, $password, $websiteId=1, $storeGroupId=1 ) { 

        $cl = _httpClient( _urlCompose(
            ADVOCADO_BACKEND_HOST, self::URL_MERCHANT_LOGIN ),
            Varien_Http_Client::POST,
            array(
                'email' => $username,
                'password' => $password
            ));

        Mage::log('sending request to ' . _urlCompose( ADVOCADO_BACKEND_HOST, 
           self::URL_MERCHANT_LOGIN ) );

        try { 

            $response = $cl->request();
            Mage::log(' request completed. ');
            $body = $response->getRawBody();
            Mage::log( 'request body --> ' . $body );

            if ( $response->isSuccessful() ) { 

                Mage::log( 'successful login method: ' . $body );
                $data = Mage::helper('core')->jsonDecode( $body );
                Mage::log( 'sucessful login method: ' . var_export( $data, true ) );
                $creds = $this->storeCredentials( 
                    $websiteId, 
                    $storeGroupId, 
                    array(
                        'site_key'      => $data['site_key'],
                        'site_secret'   => $data['site_secret']
                    ));
                $this->requestSiteToken( $creds );
                return true;
            } else { 
                Mage::log(' Error ' );
                // errors: TODO handle them
                Mage::log('Error: ' . $response->getMessage()
                    . ' ('
                    . $response->getStatus()
                    . ') -> ');

                Mage::log('Error body: ' . $response->getRawBody());
            }
        } 
        catch (Exception $e) { 
            Mage::log( 'request error: ' . $e->getMessage() );
        }
        return false;
    }

    public function register($username, $password) { 

        $dataHelper = Mage::helper('gozolabs_advocado');
        $url = _urlCompose(ADVOCADO_BACKEND_HOST, self::ADMIN_SIGNUP_URL);
        Mage::log('Registration url: ' . $url);
        $client = _httpClient(
            $url,
            Varien_Http_Client::POST,
            array(
                'email' => $username,
                'password' => $password,
                'site_name' => $dataHelper->getSiteName(),
                'site_url' => $dataHelper->getSiteUrl(),
                'platform' => 'Magento',
                'default_currency_code' => $dataHelper->getCurrencyCode()
            ));

        $response = $client->request();
        if ($response->isSuccessful()) { 
            // return the username and pasword    
            $data = Mage::helper('core')->jsonDecode( $response->getRawBody() );
            return array('code'=>$response->getStatus(), 'data'=>$data);
        } else {
            Mage::log(' Error: ' . $response->getStatus() . ' -> ' . $response->getMessage());
            return array('code'=>$response->getStatus(), 'data'=>$response->getMessage());
        }
        return false;
    }
    
    /** 
     *
     * Gets the iframe token from the backend using the site key and
     * site token.
     *
     */
    public function ifToken() { 

        $creds = $this->storeCredentials();

        $cl = _httpClient( _urlCompose(
            ADVOCADO_BACKEND_HOST, self::URL_REQUEST_IFTOKEN ),
            Varien_Http_Client::POST,
            array(
                'site_key' => $creds->getSiteKey(),
                'site_token' => $creds->getSiteToken()
            ));

        $response = $cl->request();
        if ( $response->isSuccessful() ) { 
            $data = Mage::helper('core')->jsonDecode( $response->getRawBody() );
            $creds = $this->storeCredentials(
                self::TEMP_WEBSITE_ID,
                self::TEMP_STORE_GROUP_ID,
                array(
                        'iframe_token' => $data['iftoken']
                    ) );
            return $data['iftoken'];
        } else { 
            Mage::log( 'error getting iftoken: ' . $response->getMessage() 
                . ' ( '
                . $response->getStatus() 
                . ' ) ' );
        }
        return null;
    }

    public function updateDashboardUrl() {
        $ifToken = $this->ifToken();
        return self::DASHBOARD_URL  . $ifToken;
    }

    /**
     *
     * Interesting! Cache retrieval scheme.
     * Products are stored in the cache like this: 
     * (using php associative array to approximate the key-value
     * store)
     *
     * array( 'advoc::p<productId>' => <campaignData> )
     *
     */
    public function cacheCampaignData( $productId, $campaignData = null ) { 

        if ( ! $campaignData ) { 
            // retrieve
            $data = Mage::app()->getCache()
                ->load( 'advoc::p' . strval( $productId ) );
            return unserialize( $data );
        }
        Mage::app()->getCache()
            ->save( serialize( $campaignData ), 
                'advoc::p' . strval( $productId ), 
                array( 'advocado.campaigns' ),
                //time in seconds - we store for 2 hours
                2*60*60 );
        return null;
    }

    /*====================================================================
     * CART RELATED METHODS
     * ==================================================================*/
    /**
     *
     * @param Mage_Catalog_Model_Product $product.
     * 
     * @return array Describing campaign.
     */
    public function campaignForProductId( $productId ) { 

        $campaignData = $this->cacheCampaignData( $productId );
        if ( $campaignData ) { 
            return $campaignData;
        } else { 

            $helper = Mage::helper('gozolabs_advocado');

            $creds = $this->storeCredentials(
                $helper->getCurrentWebsiteId(),
                $helper->getCurrentStoreGroupId()
            );
            
            // no credentials, don't service
            if ( ! $creds )  { 
                return null;
            }

            Mage::log('querying for campaign data with sitekey ' .
                $creds->getSiteKey() .
                ' and site token = ' .
                $creds->getSiteToken() .
                ' and site product id ' .
                strval($productId));

            $cl = _httpClient( _urlCompose( 
                ADVOCADO_BACKEND_HOST, self::URL_CAMPAIGNS ), 
                Varien_Http_Client::GET,
                array(
                    'site_key' => $creds->getSiteKey(),
                    'site_token' => $creds->getSiteToken(),
                    'site_product_id' => strval($productId),
                    'status' => '1' // active campaigns only
                ));

            try { 
                $response = $cl->request();
                if ( $response->isSuccessful() ) { 
                    $data = Mage::helper('core')->jsonDecode( $response->getRawBody() );
                    $data = $data['o'];
                    if ( count( $data ) > 0 ) { 
                        // yes a campaign exists. cache it & return it
                        $this->cacheCampaignData( $productId, $data[0] );
                        Mage::log('got campaign! ' . var_export($data[0], true));
                        return $data[0];
                    }
                    return null;
                } else { 
                    Mage::log(' error_send_request: ' . 
                        $response->getMessage() . 
                        ' (' . 
                        $response->getStatus() . 
                        ')' );
                }
            } catch (Exception $e) { 
                Mage::log( 'campaignForProduct: ' . $e->getMessage() );
            }
        }
        return null;
    }

    public function shares( $stCode = null ) { 

        // get shares
        if ( $stCode ) { 

            $helper = Mage::helper('gozolabs_advocado');

            $creds = $this->storeCredentials(
                $helper->getCurrentWebsiteId(),
                $helper->getCurrentStoreGroupId()
            );
            $cl = _httpClient( _urlCompose( 
                ADVOCADO_BACKEND_HOST, self::URL_SHARES ), 
                Varien_Http_Client::GET,
                array(
                    'st_code' => $stCode,
                    'site_key' => $creds->getSiteKey(),
                    'site_token' => $creds->getSiteToken(),
                    // is_subscribed is false,
                    // i.e. no subscription created
                    'is_subscribed' => 0
                ) );
            $rp = $cl->request();
            if ( $rp->isSuccessful() ) { 
                $data = Mage::helper('core')->jsonDecode( $rp->getRawBody() );
                return $data['o'];
            } else { 
                Mage::log('error requesting share for stCode = ' . $stCode );
            }
        } 
        return null;
    }

    public function subscriptions( $subId='') { 
        $helper = Mage::helper('gozolabs_advocado');
        $creds = $this->storeCredentials(
            $helper->getCurrentWebsiteId(),
            $helper->getCurrentStoreGroupId()
        );
        $url = _urlCompose( 
            ADVOCADO_BACKEND_HOST,
            self::URL_SUBSCRIPTIONS,
            $subId
        );
        Mage::log('getting subscriptions');
        $cl = _httpClient( 
            $url,
            Varien_Http_Client::GET,
            array(
                'site_key' => $creds->getSiteKey(),
                'site_token' => $creds->getSiteToken()
            ));
        $rp = $cl->request();
        Mage::log('done with subscriptions');
        if ( $rp->isSuccessful() ) { 
            $data = Mage::helper('core')->jsonDecode( $rp->getRawBody() );
            Mage::log('subscriptions returned: ' . var_export($data, true));
            return $data['o'];
        } else { 
            Mage::log('error requesting subscription with id ' . $subId);
        }
        return null;
    }


    /**
     *
     * Verifies that a product has been shared by pulling stCode from 
     * cookies. We use this stCode and check against the backend.
     */
    public function hasValidShareAssociated( $productId, $shareCodes=null ) { 

        $helper = Mage::helper('gozolabs_advocado');
        if ( ! $shareCodes ) { 
            $shareCodes = $helper->getShareCodes();
        }

        $productId = strval( $productId );
        if ( array_key_exists( $productId,  $shareCodes ) ) { 
            $stCodeMap = $shareCodes[$productId][$helper::SHARE_CODES_SHARES];
            foreach( $stCodeMap as $s ) { 
                $shares = $this->shares( $s[$helper::SHARE_CODES_ST_CODE]);
                if ( $shares && count( $shares ) > 0 ) { 
                    return true;
                }
            }
            //$shares = $this->shares( $shareCodes[ $productId ][1]  );
            //if ( $shares && count( $shares ) > 0 ) { 
                //return true;
            //}
        } 
        return false;
    }

    public function hasValidParentSubscription( $subId ) { 
        $sub = $this->subscriptions( $subId );
        return $sub != null;
    }

    /** 
     *  Checks to see if $productId has a parent with ID 
     *  in $idList.
     *  @return element in $idList that is present, else returns null.
     *  If the product represented by productId is a simple product with
     *  no parent, return $productId.
     */
    private function hasVariantRelationship( $productId, $idList ) { 

        $product = Mage::helper('gozolabs_advocado')
            ->getProducts( $productId );

        if ( $product ) { 
            foreach( $idList as $e ) { 
                if ( $product->hasParentWithId( $e ) || 
                 strval($productId) == strval($e) ) { 
                    Mage::log( 'product with ID = ' 
                        . $productId 
                        . ' has parent with id = ' 
                        . $e
                    );
                    return $e;
                }
            }
        }
        return null;
    }


    /**
     * Checks that a product is valid for discount against the 
     * shareCodes. shareCodes show which product has been shared,
     * whether there were referrals, etc.
     *
     * Checking a single productId is not enough. We have to check
     * if its variants are also in shareCodes, before we can
     * decide.
     *
     */
    public function isValidForDiscount( $productId, $shareCodes )  {

        $shareIdList = array();
        foreach ( $shareCodes as $sharePid => $v ) { 
            array_push( $shareIdList, $sharePid );
        }

        $sourceId = $this->hasVariantRelationship(
            $productId,
            $shareIdList
        );

        // test that shareCodes contains an ID that is either the parent
        // or a variant of product with ID $productId
        if ( array_key_exists( $productId, $shareCodes ) || 
            $sourceId != null ) {

            $pMap = $shareCodes[ $sourceId ];
            $helper = Mage::helper('gozolabs_advocado');
            //$parent_sub = $pMap[ $helper::SHARE_CODES_PARENT_SUB ];

            //if ( $parent_sub != 0 && $parent_sub != '0' ) {

                //return $this->hasValidParentSubscription(
                    //$parent_sub ) || 
                    return $this->hasValidShareAssociated( 
                        $sourceId,
                        $shareCodes 
                    );
            //}
            //return true;
        }
        return false;
    }

    function _couponOperation( $method, $couponId=null, $couponData=null ) {

        $helper = Mage::helper('gozolabs_advocado');
        $creds = $this->storeCredentials(
            $helper->getCurrentWebsiteId(),
            $helper->getCurrentStoreGroupId() 
        );

        $data = array(
                    'site_key'=> $creds->getSiteKey(),
                    'site_token' => $creds->getSiteToken()
                );
        if ( $couponData && is_array( $couponData ) ) {
            $data = array_merge( $data, $couponData );
        }

        Mage::log('_couponOperation starting');
        $client = _httpClient(
            _urlCompose(
                ADVOCADO_BACKEND_HOST,
                self::URL_COUPONS,
                ($couponId)? strval($couponId) : ''
            ),
            $method,
            $data );
        Mage::log('instantiated client');

        $rp = $client->request();
        Mage::log('_couponOperation request sent');
        if ( $rp->isSuccessful() ) { 
            Mage::log('success!');
            $raw = $rp->getRawBody();
            Mage::log('raw = ' . $raw );
            $result = Mage::helper('core')
                ->jsonDecode( $raw );
            return $result['o'];

        } else { 
            Mage::log('error getting dynamic codes: (' .
                strval($rp->getStatus()) .
                ')' .
                $rp->getMessage() . 
                ' for method ' .
                $method);
        }
        return null;

    }

    function _searchCouponsByData( $fieldsData )  {
        return $this->_couponOperation( 
            Varien_Http_Client::GET,
            null,
            $fieldsData );
    }

    function _getCouponById( $couponId ) { 
        return $this->_couponOperation(
            Varien_Http_Client::GET,
            $couponId );
    }

    function _updateCouponData( $couponId, $couponData ) { 
        return $this->_couponOperation(
            Varien_Http_Client::PUT,
            $couponId,
           $couponData); 
    }

    function invalidateCoupon( $couponId ) { 

        $helper = Mage::helper( 'gozolabs_advocado' );

        $url = _urlCompose(
            ADVOCADO_BACKEND_HOST, 
            self::URL_COUPONS,
            ($couponId)? strval($couponId) : ''
        );
        $url .= '/redeem/';

        $creds = $this->storeCredentials(
            $helper->getCurrentWebsiteId(),
            $helper->getCurrentStoreGroupId()
        );

        Mage::log('invalidateCoupon: ' . $url);

        $cl = _httpClient(
            $url,
            Varien_Http_Client::POST,
            array(
                'site_key'=>$creds->getSiteKey(),
                'site_token' =>$creds->getSiteToken()
            )
        );

        $rp = $cl->request();

        if ( $rp->isSuccessful() ) { 
            Mage::log('success!');
            $raw = $rp->getRawBody();
            Mage::log('raw = ' . $raw );
            $result = Mage::helper('core')
                ->jsonDecode( $raw );
            return $result['o'];

        } else { 
            Mage::log('error getting dynamic codes: (' .
                strval($rp->getStatus()) .
                ')' .
                $rp->getMessage());
        }

    }

    public function coupons( $couponId=null, $couponData=null ) { 

        // search for coupon by field
        if ( ! $couponId && $couponData ) { 
            return $this->_searchCouponsByData( $couponData );
        } else if ( $couponId && $couponData ) { 
            return $this->_updateCouponData( $couponId, $couponData );
        } else if ( $couponId ) { 
            return $this->_getCouponById( $couponId );
        } else { 
            Mage::log('error - nothing passed in');
            return null;
        }
    }
}

?>
