<?php

class GozoLabs_Advocado_Block_Adminhtml_Login extends
    Mage_Core_Block_Template 
{
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

    /** @return an array of assoc. arrays. each element has 
     * the following fields: 
     * - websiteId
     * - storeGroupId
     * - websiteName
     * - storeGroupName
     */
    public function websiteStoreGroups() { 
        Mage::log('trying to find the errors');
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
