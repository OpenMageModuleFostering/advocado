<?php


class GozoLabs_Advocado_Block_Adminhtml_Login extends
    Mage_Core_Block_Template 
{

    public function analyticsScript() { 
        if (isset($_SERVER['ADVOC_LOCAL_DEBUG']) || isset($_SERVER['ADVOC_STAGING'])) {
            // set debug/test magento project
            $token = 'f07a0o0m93';
        } else  {
            $token = '2k80gjofls';
        }
        return '<script type="text/javascript">var analytics=analytics||[];(function(){var e=["identify","track","trackLink","trackForm","trackClick","trackSubmit","page","pageview","ab","alias","ready","group"],t=function(e){return function(){analytics.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var n=0;n<e.length;n++)analytics[e[n]]=t(e[n])})(),analytics.load=function(e){var t=document.createElement("script");t.type="text/javascript",t.async=!0,t.src=("https:"===document.location.protocol?"https://":"http://")+"d2dq2ahtl5zl1z.cloudfront.net/analytics.js/v1/"+e+"/analytics.min.js";var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(t,n)};
  analytics.load("'. $token . '");</script>';
    }

    /** @return the url for controller action that processes a login. */
    public function processLoginUrl() { 
        return Mage::helper('adminhtml')
            ->getUrl('adminhtml/advocado/login');
    }

    public function isStoreConnected() { 
        return Mage::helper('gozolabs_advocado/backend')
            ->isStoreConnected();
    }

    public function advocadoPasswordResetUrl()  {
        return Mage::helper('gozolabs_advocado/backend')
            ->passwordResetUrl();
    }

    public function advocadoDashboardLoginUrl() { 
        return Mage::helper('gozolabs_advocado/backend')
            ->dashboardLoginUrl();
    }

    public function advocadoDashboardUrl()  {
        return Mage::helper('gozolabs_advocado/backend')
            ->dashboardUrl();
    }

    public function advocMagentoLoginUrl() { 
        //return Mage::helper('adminhtml')
            //->getUrl('adminhtml/advocado/loginajax');
        //return $this->getUrl('/advocapi/v1/loginajax');
        return Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK)
            . 'advocapi/v1/loginajax';
    }

    public function advocMagentoRegisterURl() { 
        return Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK)
            . 'advocapi/v1/register';
    }

 /*   public function getSignupUrl() { */
        //return Mage::helper('gozolabs_advocado/backend')
            //->merchantSignupUrl();
    //}

    //private function getWebsite($websiteId) { 
        //$sites = Mage::app()->getWebsites();
        //foreach( $sites as $s ) { 
            //if ($s->getId() == $websiteId) { 
                //return $s;
            //}
        //}
        //return null;
    /*}*/

    public function getAdminEmail() { 
        $email = Mage::getStoreConfig('trans_email/ident_general/email');
        Mage::log('Admin email address: ' . $email);
        return $email;
    }


/*    public function getSiteUrl($websiteId=1) { */
        ////$sites = Mage::app()->getWebsites();
        ////$tgt = null;
        ////foreach( $sites as $s ) { 
            ////if ($s->getId() == $websiteId) { 
                ////$tgt = $s;
                ////break;
            ////}
        ////}
        ////if ($tgt) { 
            ////return $tgt->getDefaultStore()->getHomeUrl();
        ////} else { 
            ////return '';
        ////}
        //Mage::log('Store URL 1: ' . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB));
        //Mage::log('Store URL 2 (returned): ' . Mage::helper('core/url')->getHomeUrl());
        //return Mage::helper('core/url')->getHomeUrl();
    //}

    //public function getSiteName($websiteId=1) { 
        //$sites = Mage::app()->getWebsites();
        //$tgt = null;
        //foreach( $sites as $s ) { 
            //if ($s->getId() == $websiteId) { 
                //$tgt = $s;
                //break;
            //}
        //}
        //if ($tgt) { 
            //Mage::log('Store name is (not used) ' . $tgt->getDefaultStore()->getName());
            //return $tgt->getName();
        //} else { 
            //return '';
        //}
        ////return Mage::app()->getStore()->getName();
    //}

    //public function getCurrencyCode() { 
        //return Mage::app()->getStore()->getCurrentCurrencyCode();
    /*}*/

    /** @return an array of assoc. arrays. each element has 
     * the following fields: 
     * - websiteId
     * - storeGroupId
     * - websiteName
     * - storeGroupName
     */
    public function websiteStoreGroups() { 
        $websites = Mage::getModel('core/website')
            ->getCollection();

        $wsgGroups = array();
        foreach( $websites as $site ) { 

            if ($site->getId() > 0) {
                foreach( $site->getGroupIds() as $groupId ) { 

                    $group = Mage::getModel('core/store_group')
                        ->load($groupId);

                    array_push( $wsgGroups, 
                        array(
                            'websiteName' => $site->getName(),
                            'storeGroupName' => $group->getName(),
                            'storeGroupId' => $group->getId(),
                            'websiteId' => $site->getId()
                        ));
                }
            }
        }    
        return $wsgGroups;
    }
}

?>
