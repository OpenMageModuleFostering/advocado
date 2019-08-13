<?php

/** Block included on every product page to
 *  expose advocado specific info to its included javascripts.
 */
class GozoLabs_Advocado_Block_Site extends
    Mage_Core_Block_Template
{ 

    public function siteKey() { 
        $be = Mage::helper('gozolabs_advocado/backend');
        $data = Mage::helper('gozolabs_advocado');
        if ( $be ) { 
            Mage::log('backend exists: ' . var_export($be, true));
        } else { 
            Mage::log( 'backend is not being returned' );
        }
        $creds = $be->storeCredentials(
            $data->getCurrentWebsiteId(),
            $data->getCurrentStoreGroupId()
        );
        if ( $creds ) { 
            return $creds->getData('site_key');
        } else { 
            Mage::log( 'error: could not get siteKey when loading '
                . 'product page. -->' . var_export($creds, true) );

        }
        return '';
    }

    private function factorial($n) { 
        if ($n == 0) { return 1; }
        return $n * $this->factorial($n-1);
    }

    /** 
     *
     * Have to find all combinations of a particular product.
     *
     * @return a map of the product variant to the combination
     * of its super attributes.
     *
     */
    public function productVariantsMap() { 

        $product = Mage::registry('current_product');
        $attribMatrix = array();

        if ($product && $product->isConfigurable()) { 

            $helper = Mage::helper('gozolabs_advocado');
            $wrappedProduct = $helper->getProducts($product->getId());
            $variants = $wrappedProduct->getVariants();
            $attribs = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);

            Mage::log('in product variants map');
            if (count($attribs) > 0) { 
                Mage::log('getConfigurableAttributes - attribs > 0: ' . strval(count($attribs)));
                foreach( $variants as $v ) { 
                    Mage::log('looking at variant');
                    // for each variant, find out all attributes that 
                    // make it up and add to attribMatrix
                    $vAttribs = array();
                    foreach( $attribs as $att ) { 

                        Mage::log('$att = ' . var_export($att, true));
                        $attId = $att['attribute_id'];
                        $vAttr = $v->getRawObject()
                            ->getResource()
                            ->getAttribute($att['attribute_code']);
                        if ($vAttr->usesSource()) { 
                            $vAttribs[$attId] = $vAttr->getSource()
                                ->getOptionId(
                                    $v->getRawObject()->getAttributeText(
                                        $att['attribute_code']
                                    )
                                );
                        }
                    }
                    array_push($attribMatrix, array( 
                        $v->getRawObject()->getId() => $vAttribs ));
                } 
            }
        }
        return $attribMatrix;
    }
}

?>
