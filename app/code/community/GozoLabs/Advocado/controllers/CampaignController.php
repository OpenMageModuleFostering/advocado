<?php

function _log( $msg ) { 
    Mage::log( $msg );
}

function _helper() { 
    return Mage::helper('gozolabs_advocado');
}

class GozoLabs_Advocado_CampaignController extends Mage_Core_Controller_Front_Action {

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

    private function doCategoryUpdate($type, $req) {

        // should be a comma delimited
        $csvProductIds = $req->getParam('product_ids');
        $siteKey = $req->getParam('site_key');
        $siteToken = $req->getParam('site_token');

        if ($this->authenticate($siteKey, $siteToken))  {

            $productIds = explode(',', $csvProductIds);
            Mage::log('product Ids = ' . var_export($productIds, true));
            $_ = Mage::helper('gozolabs_advocado');
            $specialCat = $_->getAdvocadoCategory();
            $catId = $specialCat->getId();
            Mage::log('Advocado Category with ID = ' . $specialCat->getId());
            $catApi = Mage::getSingleton('catalog/category_api');

            // TODO: might need catch an exception here and return an 
            // appropriate response
            foreach( $productIds as $pId )  {
                // add the product to the category
                if ($type == 'add') { 
                    $catApi->assignProduct(
                        $specialCat->getId(),
                        $pId
                    );
                } else if ($type == 'remove') { 
                    // check product exists in category before removing
                    $product = Mage::getModel('catalog/product')->load($pId);
                    if (in_array($catId, $product->getCategoryIds())) { 
                        $catApi->removeProduct($catId, $pId);
                    }
                    //$catApi->removeProduct(
                        //$specialCat->getId(),
                        //$pId
                    //);
                } else { 
                    $log = Mage::helper('gozolabs_advocado/analytics');
                    $log->track('Invalid category update type', array(
                        'Site Key' => $siteKey
                    ));
                }
            }

            $this->getJsonResponse()
                ->setHttpResponseCode(200);
        } else { 
            $this->getJsonResponse()
                ->setHttpResponseCode(403)
                ->appendBody(
                    $this->jsonify(
                        array(
                            'error_code' => 403,
                            'error_msg' => 'Not allowed'
                        )
                    ));
        }

    }

    /** Places products under a special Advocado category so that
     *  they can receive a discount.
     */
    public function addcategoryAction() { 

        $req = $this->getRequest();
        Mage::log('Adding to advocado category');
        $this->doCategoryUpdate('add', $req);

    }

    public function removecategoryAction() { 
        $req = $this->getRequest(); 
        Mage::log('Removing from advocado category');
        $this->doCategoryUpdate('remove', $req);
    }
}

?>
