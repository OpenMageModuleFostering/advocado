<?php

/**
 * Advocado Admin controller.
 *
 */
class GozoLabs_Advocado_Adminhtml_AdvocadoController extends 
    Mage_Adminhtml_Controller_Action
{

    const PARAM_USERNAME                = 'advoc-username';
    const PARAM_PASSWORD                = 'advoc-password';
    const PARAM_WEBSITE_STORE_GROUP     = 'advoc-website-store-group';

    private function getJsonResponse() { 
        return $this->getResponse()->setHeader(
            'Content-Type', 
            'application/json'
        );
    }

    protected function _initAction() 
    {
        $this->loadLayout()
            ->_setActiveMenu('advocado/dashboard')
            ->_addBreadcrumb(
                Mage::helper('gozolabs_advocado')->__('Advocado'),
                Mage::helper('gozolabs_advocado')->__('Advocado')
            )
            ->_addBreadcrumb(
                Mage::helper('gozolabs_advocado')->__('Dashboard'),
                Mage::helper('gozolabs_advocado')->__('Dashboard')
            );
        return $this;
    }


    public function dashboardAction() { 

        $this->_initAction();
        $backend = Mage::helper( 'gozolabs_advocado/backend' );
        $block = $this->getLayout()
        ->createBlock( 'core/text', 'advocado-dashboard' )
        ->setText(
            '<h1>Dashboard</h1>' . 
            '<iframe src="' . 
            $this->_getIframeSrc() . 
            '" id="advoc-admin-iframe" />'
        );
        $this->_addContent( $block );
        $this->renderLayout();
    }

    //public function loginajaxAction()  { 
        //Mage::log(' in login ajax ');
        //$backend = Mage::helper('gozolabs_advocado/backend');

        //$username = $this->getRequest()->getParam( self::PARAM_USERNAME );
        //$pw = $this->getRequest()->getParam( self::PARAM_PASSWORD );
        //$wsSgPairId = $this->getRequest()->getParam( self::PARAM_WEBSITE_STORE_GROUP );
        //// split the pair
        //$wsSgPair = ( $wsSgPairId ) ? explode( '_', $wsSgPairId ) : array();
        //// $wsSgPair MUST have at least 2 elements

        //Mage::log('username = ' . $username . ', pw = ' . $pw );
        //if ( $username && $pw ) {
            //Mage::log(' deferring to backend for login ');
            //$isAuthenticated = $backend->login( 
                //$username, 
                //$pw,
/*                intval( $wsSgPair[0] ),*/
                //intval( $wsSgPair[1] )
            //);
            //Mage::log( 'login returned with value ' . var_export( $isAuthenticated ) );
            //if ( $isAuthenticated ) { 

                //Mage::log('authenticated( username = ' . $username 
                    //. ', password = ' 
                    //. $pw 
                    //. ' )');

                ////$this->_redirect('adminhtml/advocado/dashboard');
                ////return;
                ////return an ajax response
                //$this->getJsonResponse()
                    //->setHttpResponseCode(200)
                    //->appendBody(
                        //Mage::helper('core')->jsonEncode(
                            //array(
                                //'status' => 200,
                                //'text' => 'OK'
                            //))
                        //);

            //} else { 
                //Mage::log( 'was not authenticated' );
            //}

        //} else { 
            //// errors
            //Mage::log( 'no username and or password. Username = ' . $username 
                //. ', password: ' . $pw );
        //}

    /*}    */

    /** Login action.
     *  Validates a merchant's username and password,
     *  then redirects to the next screen (to load the iframe for example)
     */
    public function loginAction() { 

        Mage::log('in loginAction');

        // if valid then go to next screen (load iframe, for example)
        // otherwise show the login template again, but this time with errors
        $backend = Mage::helper('gozolabs_advocado/backend'); 
        
        $username = $this->getRequest()->getParam( self::PARAM_USERNAME );
        $pw = $this->getRequest()->getParam( self::PARAM_PASSWORD );

        Mage::log('username = ' . $username . ', pw = ' . $pw );

        if ( $username && $pw ) {
            Mage::log(' deferring to backend for login ');
            $isAuthenticated = $backend->login( $username, $pw );
            Mage::log( 'login returned with value ' . var_export( $isAuthenticated ) );
            if ( $isAuthenticated ) { 

                Mage::log('authenticated( username = ' . $username 
                    . ', password = ' 
                    . $pw 
                    . ' )');

                $this->_redirect('adminhtml/advocado/dashboard');
                return;
            } else { 
                Mage::log( 'was not authenticated' );
            }

        } else { 
            // errors
            Mage::log( 'no username and or password. Username = ' . $username 
                . ', password: ' . $pw );
        }
    }

    public function indexAction() { 
        $this->_title($this->__('Advocado'))
            ->_title($this->__('Dashboard'));
        $this->_initAction();
        $this->renderLayout();
    }
}

?>
