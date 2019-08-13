<?php

function _log( $msg ) { 
    Mage::log( $msg );
}

function _helper() { 
    return Mage::helper('gozolabs_advocado');
}

class GozoLabs_Advocado_V1Controller extends Mage_Core_Controller_Front_Action {

    const PARAM_USERNAME                = 'username';
    const PARAM_PASSWORD                = 'password';
    const PARAM_WEBSITE_STORE_GROUP     = 'website_store_group';
    const PARAM_PRODUCTS_PAGE           = 'page';
    const PARAM_PRODUCTS_PAGE_LIMIT     = 'limit';

    private function getJsonResponse() { 
        return $this->getResponse()->setHeader('Content-Type', 
            'application/json');
    }

    private function jsonify($data) { 
        return Mage::helper('core')->jsonEncode($data);
    }

    /**
     * Authenticates with stored credentials to ensure that 
     * they are well and truly the same.
     *
     * IF at first they are not the same, we get the backend to
     * refresh the credentials. If at the 2nd round of 
     * comparison they are still not the same, this test 
     * fails.
     *
     */
    private function authenticate($siteKey, $siteToken, $creds = null ) { 

        $be = Mage::helper('gozolabs_advocado/backend');
        if ( ! $creds ) {

            $helper = Mage::helper('gozolabs_advocado');
            $websiteId = $helper->getCurrentWebsiteId();
            $storeGroupId = $helper->getCurrentStoreGroupId();

            $creds = $be->storeCredentials(
                $websiteId,
                $storeGroupId
            );
            // if still not there, means they never were
            if ( ! $creds )  {
                $data = $be->requestSiteToken();
                if ( ! $data ) { 
                    return False;
                } else { 
                    $creds = $be->storeCredentials(
                        $websiteId,
                        $storeGroupId 
                    );
                }
            } 
            return $this->authenticate( $siteKey, $siteToken, $creds );

        } else { 
            Mage::log('for store with id '
                . $creds->getStoreId() 
                . ', site credentials exist: - siteKey: ' 
                . $creds->getSiteKey() 
                . ', siteToken: '
                . $creds->getSiteToken()
                . '; cand_siteKey: ' 
                . $siteKey 
                . ', cand_siteToken: '
                . $siteToken
            );
            // test equality
            if ( $siteToken == $creds->getSiteToken()
                    && $siteKey == $creds->getSiteKey() )  {
                return True;
            }
        }
        return False;
    }

    /** For logging in in the admin dashboard.
     */
    public function loginajaxAction()  { 
        Mage::log(' in login ajax ');
        $backend = Mage::helper('gozolabs_advocado/backend');

        $username = $this->getRequest()->getParam( self::PARAM_USERNAME );
        $pw = $this->getRequest()->getParam( self::PARAM_PASSWORD );
        $wsSgPairId = $this->getRequest()->getParam( self::PARAM_WEBSITE_STORE_GROUP );
        // split the pair
        $wsSgPair = ( $wsSgPairId ) ? explode( '_', $wsSgPairId ) : array();
        // $wsSgPair MUST have at least 2 elements

        Mage::log('username = ' . $username . ', pw = ' . $pw );
        if ( $username && $pw ) {
            Mage::log(' deferring to backend for login ');
            $isAuthenticated = $backend->login( 
                $username, 
                $pw,
                intval( $wsSgPair[0] ),
                intval( $wsSgPair[1] )
            );
            Mage::log( 'login returned with value ' . var_export( $isAuthenticated, true ) );
            if ( $isAuthenticated ) { 

                Mage::log('authenticated( username = ' . $username 
                    . ', password = ' 
                    . $pw 
                    . ' )');

                //$this->_redirect('adminhtml/advocado/dashboard');
                //return;
                //return an ajax response
                $this->getJsonResponse()
                    ->setHttpResponseCode(200)
                    ->setBody(
                        Mage::helper('core')->jsonEncode(
                            array(
                                'status' => 200,
                                'text' => 'OK'
                            ))
                        );

            } else { 
                Mage::log( 'was not authenticated' );
            }

        } else { 
            // errors
            Mage::log( 'no username and or password. Username = ' . $username 
                . ', password: ' . $pw );
        }

    }    


    /**
     * for accessing orders. Privileged action, requires
     * authentication with store credentials.
     */
    public function ordersAction()  {
        $req = $this->getRequest();
        $siteKey = $req->getParam('site_key');
        $siteToken = $req->getParam('site_token');
        if ( $this->authenticate( $siteKey, $siteToken ) ) { 
            $id = $req->getParam('id');
            if  ($id ) { 
                $helper = _helper();
                Mage::log('getting order with id ' . $id);
                $order = $helper->getOrders(intval($id));
                if ( $order ) { 
                    $data = $order->getData();
                    Mage::log( 'order data = ' . var_export( $data, true ) );
                    $this->getJsonResponse()
                        ->setBody( $this->jsonify( 
                             $data ));
                } else { 
                    // return not found
                    $response = $this->getJsonResponse()
                        ->setHttpResponseCode(404)
                        ->appendBody(
                            $this->jsonify(
                                array( 'error_code'=> 404,
                                'error_msg'=> 'Invalid ID'
                            )));
                }
            } else { 
                $response = $this->getJsonResponse()
                    ->setHttpResponseCode(403)
                    ->appendBody(
                        $this->jsonify(
                            array( 'error_code' => 403,
                                'error_msg' => 'Only IDs allowed'
                            )));
            }
        } else { 
            /// not allowed
            $response = $this->getJsonResponse()
                ->setHttpResponseCode(403)
                ->appendBody(
                    $this->jsonify(
                        array('error_code' => 403,
                        'error_msg' => 'Authentication required.' 
                    )));
        }
    }


    public function productsAction() { 

        $id = $this->getRequest()->getParam('id');
        $response = $this->getJsonResponse();

        if ( abs( intval( $id ) ) ) {
            $pdt = _helper()->getProducts( $id, null );
            $response->setBody( $this->jsonify( $pdt->getData() ));
        } else {
            
            // get paging details if any (based on Shopify's API)
            $page = $this->getRequest()->getParam(self::PARAM_PRODUCTS_PAGE);
            $pageSize = $this->getRequest()->getParam(self::PARAM_PRODUCTS_PAGE_LIMIT);

            $pdts = _helper()->getProducts(null, $page, $pageSize, null);
            _log('Get products: '. count($pdts->getItems()));
            $response->setBody( $this->jsonify(
                array( 'products' => $pdts->getItems() ) 
                ));
        }
    }

    public function cartAction() { 

        $cart = Mage::helper( 'gozolabs_advocado' )
                ->getCart();
        $productId = $this->getRequest()->getParam( 'product' );
        $response = $this->getJsonResponse(); 
        if ( $productId && $cart->hasProduct( $productId ) ) { 
            Mage::log('cart has product.');
            $response->setBody( $this->jsonify(
                  $cart->getData() ));
        } else if ( ! $productId ) { 
            Mage::log( 'no product: ' . var_export( $productId, true ));
            $cartData = $cart->getData();
            Mage::log( 'the cart data: ' . var_export( $cartData, true ));
            $response->setBody(
                $this->jsonify( $cartData )
                );
        } else { 
            $response->setBody('{}');
        }

    }

    private function _parseRawUrlEncodedBody( $request ) { 
        $raw = $request->getRawBody();
        $pairs = explode( '&', $raw );
        $params = array();
        foreach ( $pairs as $pair ) { 
            $nv = explode( '=', $pair );
            $params[$nv[0]] = urldecode( $nv[1] );
        }
        return $params;
    }

    private function _validateCouponParams( $params ) { 
        // check for required parameters
        if ( array_key_exists( 'site_token', $params ) && 
            array_key_exists( 'site_key', $params ) && 
            array_key_exists( 'coupon_code', $params ) ) { 

            // check to_date format
            if ( array_key_exists( 'to_date', $params ) ) { 
                $tokens = explode( '-', $params['to_date'] );

                return count($tokens) == 3 && 
                    is_numeric($tokens[0]) && 
                    is_numeric($tokens[1]) && 
                    is_numeric($tokens[2]); 
            }
            return true;
        } else { 
            return false;
        }
    }

    private function _getAdvocadoWebsiteIds() { 
        $helper = Mage::helper( 'gozolabs_advocado/backend' );
        $creds = $helper->storeCredentials();
        Mage::log('getAdvocadoWebsiteIds : ' . var_export(
            $creds->getWebsiteId(), true ));
        return array( intval( $creds->getWebsiteId() ) );
    }

    private function _actionMethod( $request ) { 
        return $request->getParam('action');
    }

    /**
     *
     * Handles request related to coupons.
     * A bit long - needs some trimming/refactorign.
     */
    public function couponsAction() { 

        $req = $this->getRequest();

        $helper = Mage::helper( 'gozolabs_advocado' );
        Mage::log('coupons!');
        $action = $this->_actionMethod( $req );

        if ( ! $action || $action == 'create' ) { 

            Mage::log('coupons: is post');
            // create
            $siteKey = $req->getParam('site_key');
            $siteToken = $req->getParam('site_token');

            if ( $this->authenticate( $siteKey, $siteToken ) ) { 
                // date passed in params is in UTC
                // need to convert to system date
                $result = $helper->createCartCoupon(
                    $req->getParam('coupon_code'),
                    $req->getParam('amount'),
                    // to_date comes in 'Y-m-d' which is the 
                    // format accepted anyway
                    $req->getParam('to_date'),
                    $req->getParam('type'),
                    $this->_getAdvocadoWebsiteIds());

                $this->getJsonResponse()
                    ->setHttpResponseCode(201)
                    ->setBody( $this->jsonify(
                        array( $result->getData() )
                    ) );
            } else { 
                $response = $this->getJsonResponse()
                    ->setHttpResponseCode(403)
                    ->appendBody(
                        $this->jsonify(
                            array('error_code' => 403,
                            'error_msg' => 'Authentication required.' 
                        )));
            }

        } else if ( $action == 'delete' || $action == 'update' ) { 

            Mage::log('coupons: is either delete or put');
            $params = $this->_parseRawUrlEncodedBody( $req );
            if ( $this->_validateCouponParams( $params ) ) { 

                $siteToken = $params['site_token'];
                $siteKey = $params['site_key'];

                if ( $this->authenticate( $siteKey, $siteToken ) ) { 

                    $result = null;
                    if ( $action == 'delete' ) {
                        Mage::log('coupons: is delete & authenticated');
                        $result = $helper->deleteCartCoupon( $params['coupon_code'] );

                    } else if ( $action == 'update' ) { 
                        Mage::log('coupons: is put authenticated');
                        $result = $helper->updateCartCoupon( $params['coupon_code'], 
                            $params['amount'] );
                    }

                    if ( ! $result ) { 
                        $this->getJsonResponse()
                            ->setHttpResponseCode(403)
                            ->setBody( $this->jsonify(
                                array(
                                    'error_msg'=>'Invalid update',
                                    'error_code' => 403 )
                                ) );
                    } else {
                        $this->getJsonResponse()
                            ->setBody( $this->jsonify(
                                array( (is_object($result)) ? 
                                    $result->getData() : null ) )
                                );
                    }

                } else { 
                    $response = $this->getJsonResponse()
                        ->setHttpResponseCode(403)
                        ->appendBody(
                            $this->jsonify(
                                array('error_code' => 403,
                                'error_msg' => 'Authentication required.' 
                            )));
                }
            } else { 
                   $response = $this->getJsonResponse()
                        ->setHttpResponseCode(400)
                        ->appendBody(
                            $this->jsonify(
                                array('error_code' => 400,
                                'error_msg' => 'Bad request.' 
                            )));

            }
        } else { 
            $this->getJsonResponse()
                ->setHttpResponseCode(405);
        }
    }
}

?>
