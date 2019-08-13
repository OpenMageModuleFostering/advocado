<?php
/**
 * Observer to an event that allows us to 
 * modify price of item added to the cart.
 */
class GozoLabs_Advocado_Model_Observer { 

    const DISCOUNT_TYPE_AMT             = 'amt';
    const DISCOUNT_TYPE_RATE            = 'pct';
    const ATTRIB_SHARE_DISCOUNT_PCT     = 'dvpc_refer_pct';
    const ATTRIB_SHARE_DISCOUNT_AMT     = 'dvpc_refer_amt';

    public function campaignData( $product ) { 

        // if product is a variant, then also check if 
        // its parent (configurable) product also has campaign
        $parentIds = Mage::getModel('catalog/product_type_configurable')
            ->getParentIdsByChild($product->getId());

        $be = Mage::helper( 'gozolabs_advocado/backend' );
        $campaignData = null;

        if ( $parentIds && count( $parentIds ) > 0 ) { 
            Mage::log('campaignData for product id ' .
                strval( $product->getId() ) .
                ', has parent Ids ' .
                var_export($parentIds, true ));
            // there is a parent ID ( there might be more than one )
            // find the first configurable product that has a live
            // campaign running and use it
            foreach( $parentIds as $pid ) { 
                $campaignData = $be->campaignForProductId( $pid );
                if ( $campaignData ) { 
                    Mage::log(' found campaign Data: ' .
                        var_export($campaignData, true ));
                    break;             
                } else { 
                    Mage::log( ' no campaign data  for ' .
                        strval($pid) );
                }
            }
        } else { 
            // simple product
            $campaignData = $be->campaignForProductId( $product->getId() );
        }
        return $campaignData;
    }

    private function campaignType( $campaignData ) { 
        Mage::log( 'checking campaign type in campaign: ' 
            . var_export( $campaignData, true ) );
        return $campaignData['dvpc_type'];
    }

    /**
     *
     * TODO: need to consider how to modify when currency is taken
     * into consideration.
     * @return float Price after application of discount.
     */
    public function applyCampaignDiscount( $campaignData, $price ) { 
        if ( $this->campaignType( $campaignData ) == 
            self::DISCOUNT_TYPE_AMT ) 
        { 
            $price -= floatval( $campaignData[self::ATTRIB_SHARE_DISCOUNT_AMT] );
        } else { 
            // with percentages we need to be careful with how we
            // deduct. We round up to nearest 2 dp
            $price = round( 
                $price *
                ( 1.0 - floatval( 
                    $campaignData[self::ATTRIB_SHARE_DISCOUNT_PCT] ) / 100.0 ),
            2 );
        }
        return $price;
    }


    public function addItemToCart(Varien_Event_Observer $observer) { 
       
        // check if product has running advocado campaign and
        // if the user has shared about this product.
        // get campaign mechanics and modify price accordingly
        $quoteItem = $observer->getEvent()
                            ->getQuoteItem();
        $product = $quoteItem->getProduct();
        $price = $product->getPrice();

        $campaign = $this->campaignData( $product );
        $shareCodes = Mage::helper('gozolabs_advocado')
            ->getShareCodes();
        Mage::log( 'in addItemToCart ');
        if ( $campaign && Mage::helper('gozolabs_advocado/backend')
                            ->isValidForDiscount(
                                $product->getId(), $shareCodes ) ) {

            Mage::log('addItemToCart: YES apply discount');
            //$this->normalizeAllSimilarItemsInCart(
                //$quoteItem->getQuote(),
                //$product,
                //$campaign
            //);
            $newPrice = $this->applyCampaignDiscount( 
                $campaign,
                //$product->getPrice()
                $price
            );

            // setting quote price
            Mage::log('original custom price = ' . 
                var_export($quoteItem->getOriginalCustomPrice(), true).
                ', new price = ' .
                var_export($newPrice, true) .
                ', original price = ' . 
                var_export($price, true));

            $quoteItem->setOriginalCustomPrice( $newPrice );
            $quoteItem->getProduct()->setIsSuperMode( true );
            //$this->modifyQuoteItemPrice( $quoteItem, $campaign );
            // IMPORTANT: i leave the below as a warning
            // $quoteItem is passed by reference and eventually will 
            // be saved. You should not run x->save() in an observer
            //$quoteItem->save();
        } else { 
            Mage::log('don\'t apply discount for addItemToCart. ' .
                'shareCodes = ' . 
                var_export($shareCodes, true) .
                ', product Id = ' .
                strval($product->getId()) .
                ', campaignData = ' .
                var_export( $campaign, true ));
        }
    }

    /**
     * Event handler called when a successful checkout takes place 
     * and an order is created. We verify that one of Advocado's coupons
     * has been used, remove it from this system and inform the backend
     * that it has been used.
     *
     * @param $observer Observer.
     */
    public function verifyAdvocCouponUsed( Varien_Event_Observer $observer ) { 

        $order = $observer->getEvent()
                        ->getOrder();
        $couponCode = $order->getCouponCode();
        Mage::log('verify advocCoupon Used: got order -> ' . $order->getId() );
        Mage::log('got coupon code -> ' . $couponCode );

        if ( $couponCode ) { 
            $coupon = Mage::helper( 'gozolabs_advocado' )
                ->getCouponByCode( $couponCode );
            if ( $coupon->isAdvocadoCoupon() ) {
                $backend = Mage::helper( 'gozolabs_advocado/backend' );
                $backCoupon = $backend->coupons( 
                    null, 
                    array( 
                        'code' => $couponCode 
                    ) );
                if ( $backCoupon && count($backCoupon) > 0 )  {

                    // update
                    $backCoupon = $backCoupon[0];
                    $backCoupon = $backend->invalidateCoupon(
                        $backCoupon['id']);
                    if ( $backCoupon && count($backCoupon) > 0 ) { 
                        // delete
                        $coupon->delete();
                    } else { 
                        Mage::log('backend failed to update coupon with ' .
                            'code = ' . $couponCode );
                    }
                } else { 
                // we would normally delete
                // the coupon on the system, but we keep it 
                // so we can find out which is the offending coupon
                // and do some kind of post-mortem
                    Mage::log('a coupon error');
                }
            }
        }
    }
}

?>
