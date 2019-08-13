<?php

/*
 * ==================================================
 * Utilities
 * ==================================================
 */


/** returns the utc formatted date string.
 */
function _formatDateToUTC( $dateString ) { 
    // c is ISO 8601 date
    // e.g. 2004-02-12T15:19:21+00:00
    return date( 'c', strtotime( $dateString ) );
}

/*
 * ==================================================
 * Data Classes
 * ==================================================
 */

/** 
 *
 * An abstract class.
 *
 */
class AdvocModelInstance { 

    protected $origObject;

    public function __construct( $arg1 ) { 
        $this->origObject = $arg1;
    }

    public function delete() { 
        $this->origObject->delete();
    }

    public function getRawObject() { 
        return $this->origObject;
    }

    public function getId() { 
        return intval($this->origObject->getId());
    }

    public function getData( $field = null ) { 
        if ($field)
            return $this->origObject->getData($field);
        return $this->origObject->getData();
    }

    public function getJsonData( $field = null ) { 
        if ( $field ) { 
            return Mage::helper('core')->jsonEncode($this->getData($field));
        }
        return Mage::helper('core')->jsonEncode($this->getData());
    }
}

/** Custom coupon object.
 *  $origObject should be of type Mage_SalesRule_Model_Rule
 */
class AdvocPCoupon extends AdvocModelInstance { 

    const ADVOC_COUPON_NAME_PREFIX            = 'ADVOCADO';

    public function isAdvocadoCoupon() { 
        return ( strstr(
            $this->origObject->getName(),
            self::ADVOC_COUPON_NAME_PREFIX
        ) ) ? true : false;
    }

    public function getData( $field=null ) { 

        if ($field) { 
            $data = parent::getData($field);
            return $data;
        }

        $o = $this->origObject;
        $data = array(
            'id' => intval($o->getId()),
            'coupon_code' => $o->getCouponCode(),
            'to_date' =>  $o->getToDate(),
            'amount' => $o->getDiscountAmount()
        );

        $type = $o->getSimpleAction();
        if ( $type == 'by_percent' ) { 
            $type = 'pct';
        } else if ( $type == 'cart_fixed' ) { 
            $type = 'amt';
        } else { 
            $type = null;
        }
        $data['type'] = $type;
        return $data;
    }

}

/**
 *
 * Custom implementation for retrieving an Order from the 
 * instance.
 */
class AdvocPOrder extends AdvocModelInstance {

    protected function getCouponRule($couponCode) { 
        $coupon = Mage::getModel('salesrule/coupon')->load($couponCode, 'code');
        if ($coupon) {
            $rule = Mage::getModel('salesrule/rule')->load($coupon->getRuleId());
            return $rule;
        }
        return null;
    }

    protected function getCouponDiscountAmount($couponCode)  {
        $rule = $this->getCouponRule( $couponCode );
        if ( ! $rule ) { 
            return 0.0;
        }
        return $rule->getDiscountAmount();
    }

    protected function getProductVariantParentId( $orderItem ) { 
        $pdtId = $orderItem->getProductId();
        Mage::log( 'for sku ' . $orderItem->getSku() . ' our pdt id is ' . $pdtId );
        $parentIds = Mage::getModel('catalog/product_type_configurable')
                        ->getParentIdsByChild( $pdtId );
        Mage::log( 'parent Ids is ' . var_export( $parentIds, true ) );
        if ( count($parentIds) > 0 && isset($parentIds[0]) ) { 
            return $parentIds[0];   
        } 
        // it is its own variant we return itself
        return $pdtId;
    }

    protected function getParentProductName( $parentProductId )  {
        if ( $parentProductId ) {
            $pdt = Mage::getModel('catalog/product')
                ->load($parentProductId);
            return $pdt->getName();
        }
        return null;
    }


    protected function printAllItems() { 

        $allItems = $this->origObject->getItemsCollection(array(), false);
        
        foreach( $allItems as $item ) { 
            $_id = $item->getProductId(); 
            $pdt = Mage::getModel( 'catalog/product' )
                ->load($_id);
            if ( $pdt->getId() ) {
                //Mage::log( 'product has type = ' . gettype($pdt) );
                Mage::log( 'item name = '
                    . $pdt->getName()
                    . ', is configurable = '
                    . var_export( $pdt->isConfigurable(), true )
                    . ', and sku = '
                    . $pdt->getSku()
                );
            } else { 
                Mage::log( 'pdt not found' );
            }

            $children = $item->getChildrenItems();
            if ( count( $children ) > 0 ) { 
                Mage::log('has children items');
            } else { 
                Mage::log( 'no child items' );
            }
            foreach( $children as $child ) { 
                $_id = $child->getProductId();
                $pdt = Mage::getModel( 'catalog/product' )
                    ->load($_id);
                if ( $pdt->getId() ) { 
                    Mage::log(' child item has product with sku '
                        . $pdt->getSku() 
                    );
                } else { 
                    Mage::log( 'could not find child product. ');
                }
            }
        }
    }

    protected function lineItemData( $lineItem ) {

        $parentId = $this->getProductVariantParentId( $lineItem );
        $parentName = $this->getParentProductName( $parentId );
        $parentItem = $lineItem->getParentItem();

        Mage::log( 'parentId = ' . 
            $parentId . 
            ' for line item with sku = ' . 
            $lineItem->getSku() );

        //array_push( $itemPool, array(
        return array(
                'variant_id' => $lineItem->getProductId(),
                'price' => ( $lineItem->getPrice() > 0.0 ) ? 
                    $lineItem->getPrice() : $parentItem->getPrice(),
                'sku' => $lineItem->getSku(),
                'product_id' => $parentId,
                'quantity' => ( $lineItem->getQtyToInvoice() ) ? 
                    $lineItem->getQtyToInvoice() : $parentItem->getQtyToInvoice(),
                'variant_title' => $lineItem->getName(),
                'title' => $parentName,
                'name' => implode(' - ', array( $parentName,
                    $lineItem->getName() ))
            );
    }

    /** returns an array of associative arrays, each
     * associative array is a line item.
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function getLineItems( $quote = null )  {

        $lineItems = array();

        Mage::log('printing');
        $this->printAllItems();

        $allItems = $this->origObject->getItemsCollection(array(), false);

        Mage::log('adding');
        foreach ( $allItems as $item ) {

            Mage::log( 'looking at item ' . $item->getSku() 
                . 'with Product ID ' . $item->getProductId() );

            // we only add line items that are not configurable 
            // products (aka parents to the actual products being
            // purchased )
            $childItems = $item->getChildrenItems();
            if ( count( $childItems ) > 0 ) { 
                Mage::log('has children items');
                foreach( $childItems as $child ) { 
                    Mage::log( 'looking at  child with id = ' . $child->getProductId() 
                        . ' and sku = ' . $child->getSku() );
                    array_push( $lineItems, $this->lineItemData( $child ) );
                }
            } else { 
                Mage::log('no children');
                // no children
                $parentItem = $item->getParentItem();
                if ( ! $parentItem ) { 
                    Mage::log('no parent');
                    //no parent also add
                    //$this->addLineItem( $lineItems, $item );
                    array_push( $lineItems, $this->lineItemData( $item ) );
                }
            }
        }
        return $lineItems;
    }

    protected function getCustomerData() { 
        $custId = $this->origObject->getCustomerId();
        if ( $custId )  {
            $customer = Mage::getModel('customer/customer')
                ->load( $custId );
            $lastOrder = Mage::getResourceModel('sales/order_collection')
                ->addFieldToSelect('*')
                ->addFieldToFilter('customer_id', $custId )
                ->addAttributeToSort( 'created_at', 'DESC' )
                ->setPageSize(1)
                ->getFirstItem();

            $createDate = $customer->getCreatedAt();
            $data = array(
                'email' => $customer->getEmail(),
                'first_name' => $customer->getFirstName(),
                'id' => intval($customer->getId()),
                'last_name' => $customer->getLastName(),
                'last_order_id' => ($lastOrder)?intval($lastOrder->getId()) : null,
                'created_at' => _formatDateToUTC( $createDate )
            );
            return $data;
        }
        return null;
    }

    public function getData( $field = null ) {

        $o = $this->origObject;

        $couponCode = $o->getCouponCode();
        $coupon = null;
        if ( $couponCode )  {
            $coupon = array( 
                'code' => $couponCode,
                'amount' => $this->getCouponDiscountAmount( $couponCode )
            );
        }

        $data = array( 
            'id' => intval($o->getId()),
            'created_at' => _formatDateToUTC( $o->getCreatedAt() ),
            //'created_at' => $o->getCreatedAtDate()->toString(),
            'currency' => $o->getOrderCurrency()->getCurrencyCode(),
            // gets the customer email, no matter guest or registered/logged in
            'email' => $o->getCustomerEmail(),
            'total_line_items_price' => $o->getSubtotal(),
            'total_discounts' => $o->getDiscountAmount(),
            'total_price' => $o->getGrandTotal(),
            'total_tax' => $o->getTaxAmount(),
            'base_total_line_items_price' => $o->getBaseSubtotal(),
            'base_total_discounts' => $o->getBaseDiscountAmount(),
            'base_total_tax' => $o->getBaseTaxAmount(),
            'base_total_price' => $o->getBaseGrandTotal(),
            'discount_codes' => ( $coupon ) ? array( $coupon ) : null,
            'line_items' => $this->getLineItems(),
            'customer' => $this->getCustomerData(),
            'order_number' => $o->getIncrementId(),
            // TODO
            'cancel_reason' => null,
            'cancelled_at' => null,
        );
        return $data;
    }
}

/**
 *
 * Custom implementation of the Cart for checkout.
 * Customized getData()
 */
class AdvocPCart  { 

    protected $origCart;

    public function __construct( $arg ) { 
        $this->origCart = $arg;
    }

    public function hasProduct( $productId ) { 
        Mage::log('cart.needle = ' . $productId);
        Mage::log('cart.productIds = ' . var_export($this->origCart->getProductIds(), true));
        return in_array( intval( $productId ), 
            $this->origCart->getProductIds() );
    }

    protected function lineItemData( $quoteItem ) { 
        $product = $quoteItem->getProduct();
        return array(
            'id' => intval($product->getId()),
            'variant_id' => intval($product->getId()),
            'title' => $product->getName(),
            'qty' => $quoteItem->getQty(),
            'sku' => $product->getSku(),
            'price' => $quoteItem->getBaseCalculationPriceOriginal(),
            // custom price for this item, before tax calculation
            'line_price' => $quoteItem->getBaseCalculationPrice()
        );
    }

    public function getLineItems()  {
        $lineItems = array();
        if ( $this->origCart ) { 
            $quote = $this->origCart->getQuote();
            $items = $quote->getAllItems();
            foreach ( $items as $item ) { 
                $lineItems[] = $this->lineItemData( $item );
            }
        }
        return $lineItems;
    }

    public function getData( $field = null ) { 
        $o = $this->origCart;
        $cart = array(
            // the ID of a cart corresponds to
            // the Mage_Sales_Model_Quote
            'id' => intval($o->getQuote()->getId()),
            'items' => $this->getLineItems()
        );
        return $cart;
    }
}

/** 
 * Custom implementation for the product variant. 
 * Customized getData() return value.
 */
class AdvocPVariant extends AdvocModelInstance { 

    protected $parent;

    public function __construct( $arg1, $parent ) { 
        parent::__construct( $arg1 );
        $this->parent = $parent;
    }

    public function updatePrice( $newPrice ) { 
        $this->origObject->setPrice( $newPrice );
    }

    /** checks if this product variant has a parent with
     *  ID == $testId.
     *  @return true if it's the case, false otherwise.
     */
    public function hasParentWithId( $testId ) { 
        if ( $this->parent ) { 
            return intval( $this->parent->getId() ) == intval( $testId );
        } else { 
            // find the parent
           $parentIds = Mage::getModel('catalog/product_type_configurable')
                    ->getParentIdsByChild( $this->origObject->getId() );

           Mage::log( 'variant->hasParentWithId, needle = ' . $testId 
               . ', products = ' 
               . var_export( $parentIds, true )
           );

           if ( count( $parentIds ) > 0 ) { 
               $p0 = $parentIds[0];
               if ( is_string ($p0 )) { 
                   return in_array( strval( $testId ), $parentIds );
               } elseif ( is_int( $p0 ) ) { 
                   return in_array( intval( $testId ), $parentIds );
               } else { 
                   Mage::log( 'unidentifiable id type for a product' );
               }
           }
        }
        return false;
    }

    /** duplicates this product.
     */
    public function duplicate() { 
        $p = $this->origObject->duplicate();
        // wrap the duplicate in our own class
        $ref = new ReflectionClass( get_class( $this ) );
        $dup = $ref->newInstanceArgs( array( $p ) );
        return $dup;
    }

    /**
     *
     * @param Mage_Catalog_Model_Category $category
     */
    protected function setCategory( $category ) { 
        $this->origObject->setCategoryIds( array( $category->getId() ) );
        $this->origObject->save();
    }

    /** 
     *
     * Get configurable options for a product.
     * @param Mage_Catalog_Model_Product $product
     *
     */
    protected function getProductOptions( $product ) { 
        $attrs = array();
        if ( $product->isConfigurable() ) {
            $attrs = $product->getTypeInstance( true )
                ->getConfigurableAttributesAsArray( $product );
        }
        return $attrs;
    }

    /** Get the options that define this particular variant.
     *  @return array An array of variant options.
     */
    protected function variantOptions( $variant, $parentProduct ) { 
        $vals = array();
        // parent product may be null
        if ( $parentProduct )  { 

            $attrs = $this->getProductOptions( $parentProduct );
            //Mage::log(' variant options = ' . var_export( $attrs, true ));
            foreach( $attrs as $attr ) { 
                array_push( $vals, 
                    $variant->getAttributeText($attr['attribute_code']) );
            }
            //Mage::log( 'variant options backcheck = ' . var_export( $vals, true ));
        }

        $options = array();
        for( $i = 0; $i < 3; $i++ ) { 
            $optionKey = 'option'.strval($i + 1);
            if( count( $vals ) > $i ) { 
                $options[ $optionKey ] = $vals[$i];
            } else { 
                $options[ $optionKey ] = null;
            }
        }
        return $options;
    }

    protected function getStockQty( $product ) { 
        $stock = Mage::getModel('cataloginventory/stock_item')
                        ->loadByProduct( $product )
                        ->getQty();
        return intval( $stock );
    }

    public function getData( $field = null ) { 

        $o = $this->origObject;
        $data = $this->variantOptions( $o, $this->parent );
        $currencySymbol = Mage::app()->getLocale()
            ->currency( Mage::app()->getStore()->getCurrentCurrencyCode() )
            ->getSymbol();
        $currencyCode = Mage::app()->getStore()
            ->getCurrentCurrencyCode();

        return array_merge( $data,  
            array(
                'id' => intval($o->getId()),
                'title' => $o->getName(),
                'created_at' => _formatDateToUTC( $o->getCreatedAt() ),
                'product_id' => ($this->parent)?intval($this->parent->getId()) : null,
                'price' => $o->getPrice(),
                //'currency_symbol' => $currencySymbol,
                //'currency_code' => $currencyCode,
                'sku' => $o->getSku(),
                'inventory_quantity' => $this->getStockQty( $o )
            ) );
    }
}


/**
 * Custom implementation for getData() for AdvocModelInstance.
 *
 */
class AdvocPProduct extends AdvocPVariant { 

    protected $variantsData;

    public function __construct( $arg ) { 
        parent::__construct( $arg, null );
        $this->variantsData = null;
    }

    public function hasVariantWithId( $variantId ) { 
        $variants = $this->getVariants();
        foreach( $variants as $v ) { 
            if ( intval( $variantId ) == intval( $v->getId() ) ) { 
                return true;
            }
        }
        return false;
    }

    protected function getTags() { 
        $m = Mage::getModel('tag/tag');
        $tags = $m->getResourceCollection()
            ->addPopularity()
            ->addStatusFilter($m->getApprovedStatus())
            ->addProductFilter($this->origObject->getId())
            ->setFlag('relation', true)
            ->addStoreFilter(Mage::app()->getStore()->getId())
            ->setActiveFilter()
            ->load();
        return (isset($tags) && !empty($tags)) ? $tags: null;
    }

    protected function getTagsString() { 
        $tags = $this->getTags();
        if ($tags) { 
            $tagArr = array();
            foreach( $tags as $tag ) { 
                array_push($tagArr, $tag->getName());
            }
            return implode(', ', $tagArr);
        }
        return '';
    }

    protected function getImageData( $image ) { 
        return array(
            'created_at' => _formatDateToUTC( $image->getCreatedAt() ),
            'id' => intval($image->getId()),
            'product_id' => intval($this->origObject->getId()),
            'src' => Mage::helper( 'catalog/image' )->init( 
                $this->origObject, 'image', $image->getFile())
                ->__toString(),
            // The below is a record of the various trials I attempted (unsuccessfully) to
            // get the image URL
            //'src' => $this->origObject->getImageUrl(),
            //'src' => Mage::getModel( 'catalog/product_media_config' )
                        //->getMediaUrl( $this->origObject->getThumbnail() ),
            //'src' => Mage::helper( 'catalog/image' )
            //->init( $this->origObject, 'small_image', $image->getFile() )
            //->__toString(),
            //'src' => $image->getFileUrl(),
            'updated_at' => _formatDateToUTC( $image->getUpdatedAt() ) 
        );
    }

    /**
     * @return array of image data arrays.
     */
    protected function getImages() { 
        $imgs = array();
        $product = Mage::getModel( 'catalog/product' )
            ->load( $this->origObject->getId() );
        $imgModels = $product->getMediaGalleryImages();
        //Mage::log( 'get images for pdt sku = '
            //. $product->getSku() 
            //. ', num images = '
            //. count($imgModels));
        if ( $imgModels && $imgModels->getSize() > 0 ) { 
            //Mage::log( 'product ' . $this->origObject->getName() 
                //. 'has ' . count( $imgModels ) . ' images' );
            foreach ( $imgModels as $img ) { 
                array_push( $imgs, $this->getImageData( $img ) );
            }
        } else { 

            $pImage = $product->getImage();
            if ( $pImage ) { 
                Mage::log('using getImage instead of image gallery: ' . $pImage);
                //$attributes = $product->getTypeInstance(true)->getSetAttributes($product);
                //if (isset($attributes['media_gallery'])) { 
                    //$mediaGalleryAttrib = $attributes['media_gallery'];
                    //$img = $mediaGalleryAttrib->getBackend()->getImage($product, $pImage);
                    //array_push( $imgs, $this->getImageData($img) );
                //}
                $_read = Mage::getSingleton('core/resource')->getConnection('catalog_read');
                $_mediaGalleryData = $_read->fetchAll(
                    'SELECT * from catalog_product_entity_media_gallery where value="' .
                    $pImage .
                    '" and entity_id=' . 
                    $product->getId() . 
                    ';'
                );
                foreach ( $_mediaGalleryData as $i ) { 
                    array_push( $imgs, array(
                        'created_at' => '',
                        'id' => $i['value_id'],
                        'src' => $product->getImageUrl(),
                        'updated_at' => ''
                    ));
                }
            }
        }
        return $imgs;
    }

    /** get the variants for the current product.
     */
    public function getVariants( $product=null ) { 

        if ( ! $product ) { 
            $product = $this->origObject;
        }

        $children = $product
            ->getTypeInstance()
            ->getUsedProducts();
        
        $childArray = array();
        foreach( $children as $child ) { 
            $wrapper = new AdvocPVariant($child, $product);
            array_push($childArray, $wrapper);
        }
        return $childArray;
    }

    /** 
     * @return array An array of the various types of a product,
     * assuming origObject has already been set, including the
     * various inventory counts for each of them.
     *
     * @param Mage_Catalog_Model_Product $product 
     *
     */
    protected function getVariantsData( $product ) { 
        if ( ! $this->variantsData ) { 
            //$children = Mage::getModel('catalog/product_type_configurable')
                             //->getUsedProducts(null, $product);
            //$children = $product->getTypeInstance()->getUsedProducts();
            $variants = array();
            foreach( $this->getVariants($product) as $v ) { 
                //$wrapper = new AdvocPVariant($v, $product);
                //array_push( $variants, $wrapper->getData() );
                array_push( $variants, $v->getData() );
            }
            $this->variantsData = $variants;
        }
        return $this->variantsData;
    }

    protected function getCategory() { 
        $cats = $this->origObject->getCategoryIds();
        if ( $cats && count( $cats ) > 0 ) { 
            $cat = Mage::getModel('catalog/category')->load($cats[0]);
            return $cat;
        }
        return null;
    }

    /**
     * Duplicates the variants of this product and set them as variants
     * of another parent product.
     *
     * @param AdvocPProduct $newParentProduct
     * @return mixed array AdvocPVariant if $newParentProduct is a configurable product,
     * else null.
     */
    public function duplicateVariants( $newParentProduct ) { 
        if ( $this->isConfigurable() ) { 
            $variants = array();
            $children = Mage::getModel('catalog/product_type_configurable')
                             ->getUsedProducts(null, $this->origObject);
            
            $cat = $newParentProduct->getCategory();
            foreach( $children as $child ) { 

                $newChild = $child->duplicate();
                $newChild->setCategoryIds( array( $cat->getId() ) );
                $newChild->save();
                array_push( $variants, 
                    new AdvocPVariant( $newChild, 
                        $newParentProduct->origObject ) 
                );
            }
            return $variants;
        }
        return null;
    }

    public function isConfigurable() { 
        return $this->origObject->isConfigurable();
    }

    protected function getCategoryName( $product ) { 
        $cat = $product->getCategory();
        if( $cat ) { 
            //Mage::log('product '.$product->getName().' has category');
            return $cat->getName();
        } else { 
            $cats = $product->getCategoryIds();
            if ( $cats && count( $cats ) > 0 ) { 
                //Mage::log('product '.$product->getName().' has categories');
                $cat = Mage::getModel('catalog/category')->load($cats[0]);
                return $cat->getName();
            }
        }
        return '';
    }

    public function getProductOptionsData() { 
        $options = $this->getProductOptions( $this->origObject );
        $dataArray = array();
        $pid = $this->origObject->getId();
        for ( $i = 0; $i < count($options);  $i++) { 
            //Mage::log( 'an option = ' . var_export( $options[$i], true ));
            array_push( $dataArray, 
                array(
                    'name' => $options[$i]['label'],
                    'position' => $i + 1,
                    'product_id' => $pid ,
                    'id' => $options[$i]['attribute_id']
                ));
        }
        return $dataArray;
    }


    /** 
     * Maps the attributes from a product to an actual array.
     *
     * @return mixed array of attributes and values as required
     * by a product for platform API. 
     * */
    public function getData( $field = null ) { 

        $o = $this->origObject;
        if ( $field == 'id' ) { 
            return $o->getId();
        } else if ( $field ) { 
            return $o->getData( $field );
        } else { 
            $data = array( 
                'id'    => intval($o->getId()),
                'title'  => $o->getName(),
                'product_type' => $this->getCategoryName( $o ),
                'currency_code' => Mage::app()->getStore()
                                    ->getCurrentCurrencyCode(),
                'currency_symbol' => Mage::app()->getLocale()
                                        ->currency(Mage::app()->getStore()
                                            ->getCurrentCurrencyCode())
                                        ->getSymbol(),
                'images' => $this->getImages(),
                'tags' => $this->getTagsString()
            );

            $data['variants'] = null;
            if ( $o->isConfigurable() ) { 
                $data['variants'] = $this->getVariantsData( $o );
            } else { 
                //$simpleData = array( 
                        //'price'     => $o->getPrice(),
                        //'sku'       => $o->getSku(),
                        //'inventory_quantity' => $this->getStockQty( $o ),
                        //'created_at' => _formatDateToUTC( $o->getCreatedAt() )
                    //);
                // if this is going to be its own variant
                // then we pass in itself to its variant class
                // as its own parent. We know there is no infinite
                // recursion because the variants cannot have their
                // own variants.
                $v = new AdvocPVariant( $o, $o );
                $data['variants'] = array( $v->getData() );
                //$data = array_merge( $data, $simpleData );
            }
            $data['options'] = $this->getProductOptionsData();
            return $data;
        }
    }
}

/**
 * _Mapper is a helper class to initialize our standard model objects.
 *
 */
class _Mapper { 

    public static function initCollection( $modelName, $collection ) { 
        $my = array( 
                'product' => array(
                        'name',
                        'sku',
                        'price',
                        'created_at'
                    ),
                'order' => '*'
                );

        if ( array_key_exists( $modelName, $my ) ) { 
            Mage::log($modelName .' initializing collection');
            $collection->addAttributeToSelect( $my[$modelName] );
        }
    }

    /** instantiates a new AdvocPModelInstance based on 
     * the modelName (see $my in the function code).
     */
    public static function init( $modelName )  {
        $my =  array(
            'product' => 'AdvocPProduct',
            'order' => 'AdvocPOrder'
        );
        $cls = $my[$modelName];
        $ref = new ReflectionClass( $cls );
        if ( func_num_args() > 1 )
            $new = $ref->newInstanceArgs( array_slice(func_get_args(), 1) );
        else
            return null;
        return $new;
    }
}


/**
 *
 * Not part of the iterator.
 *
 */
class AdvocModelIterator implements Iterator { 

    private $pos = 0;
    private $collection;
    private $items;
    private $modelName;

    private function normalize( $rawCollection, $modelName, $filterFuncs ) { 

        $this->modelName = $modelName;
        $this->items = array();
        foreach( $rawCollection as $c ) { 
            foreach ( $filterFuncs as $fn ) {
                if ( call_user_func( $fn, $c ) ) { 
                    array_push( $this->items, $c );
                }
            }
        }
    }

    public function __construct( $collection, $modelName, $filterFuncs = null ) { 
        $this->collection = $collection;
        $this->normalize($collection, $modelName, $filterFuncs);
    }

    public function rewind() { 
        $this->pos = 0;
    }

    public function current() { 
        return _Mapper::init($this->modelName, $this->items[$this->pos]);
    }

    public function key() { 
        return $this->pos;
    }

    public function next() { 
        ++$this->pos;
    }

    public function valid() {
        if ( count( $this->items ) > $this->pos ) { 
            return isset( $this->items[$this->pos] );
        }
        return false;
    }

}

/**
 *
 * Implements collection methods.
 *
 */
class AdvocModelCollection implements IteratorAggregate { 

    private $wrapped_collection;
    private $modelName;
    private $filterFuncs;
    private $pageSize;
    private $pageIndex;

    public function __construct( $arg1, $arg2 ) { 
        $this->wrapped_collection = $arg1;
        $this->modelName = $arg2;
        $this->filterFuncs = array();
        $this->pageSize = $this->pageIndex = null;
        _Mapper::initCollection($arg2, $this->wrapped_collection);
    }

    public function addFieldToFilter( $field, $value ) { 
        $this->wrapped_collection->addFieldToFilter($field, $value);
    }

    public function addORFilterByFunc( $filterfunc ) { 
        array_push($this->filterFuncs, $filterfunc);
        return $this;
    }

    public function getFirstItem() { 
        $name = $this->modelName;
        $data = $this->wrapped_collection->getFirstItem();
        return _Mapper::init($name, $data);
    }

    /** return string json version of this collection */
    public function jsonEncode() { 

        $the_array = array();
        foreach ( $this as $c ) { 
            array_push( $the_array, $c->getData() );
        }
        return Mage::helper('core')->jsonEncode( $the_array );
    }

    protected function includeItem( $item ) { 
        $result = true;
        foreach ( $this->filterFuncs as $fn ) { 
            $result = $result && call_user_func( $fn, $item ); 
        } 
        return $result;
    }

    /**
     * @return array The items in this collection.
     */
    public function getItems() { 
        $items = array();
        // this was written because there's a problem with paging:
        // Let's say there are 5 items in the collection and we set pageIndex=1 
        // and pageSize = 6, we should be iterating through 5 items. this is correct.
        // But if we set pageIndex = 2 and pageSize =6, we iterate through the last page
        // which is the original 5 items. BUT that's not right.
        // So now we need to ensure that we are returning an empty array if the above
        // incorrect situation occurs.
        if ($this->pageIndex && $this->pageSize) { 
            if ($this->getSize() - (($this->pageIndex - 1)*$this->pageSize) <= 0)
                return $items;
        }

        foreach ( $this->wrapped_collection as $p ) { 
            if ( $this->includeItem( $p ) ) {
                array_push( $items, 
                    _Mapper::init( $this->modelName, $p)->getData() );
            }
        }
        return $items;
    }

    public function getSize() { 
        return $this->wrapped_collection->getSize();
    }

    public function getIterator() {
        return new AdvocModelIterator($this->wrapped_collection, 
            $this->modelName, $this->filterFuncs);
    }

    /*
     * @param $pageIndex The index into the page.
     * @param $limit The limit of the number of results per page.
     */
    public function setPage($pageIndex, $limit) { 
        $this->pageIndex = $pageIndex;
        $this->pageSize = $limit;
        $this->wrapped_collection->setPageSize($limit)->setCurPage($pageIndex);
    }
}

/* only thing is each product is correctly wrapped */
class AdvocCustomProductCollection extends AdvocModelCollection  {

    public function __construct( $arg1 ) { 
        parent::__construct($arg1, 'product');
    }
}

/** 
 *  The AdvocModel_Mapper can serve as a wrapper for 
 *  model objects on other platforms.
 *
 */
class AdvocModelMapper { 


    /* This could be an object specific to the platform
     * that we wrap to enable us to do read/write operations.
     */
    private $wrapped;
    private $modelName;

    public function __construct( $wrapped, $modelName = null ) { 
        $this->wrapped = $wrapped;
        $this->modelName = $modelName;
    }

    /* @return AdvocModelCollection instance */
    public function getCollection($customCollection=null, $customModelName=null) { 
        if ($customCollection && $customModelName) { 
            return new AdvocModelCollection($customCollection, $customModelName);
        }
        return new AdvocModelCollection($this->wrapped->getCollection(), 
            $this->modelName);
    }

    public function withId($id) { 
        $this->wrapped->load($id);
        if ( !$this->wrapped->getId() ) 
            return null;
        //return new _Mapper::model($this->modelName)($this->wrapped);
        return _Mapper::init($this->modelName, $this->wrapped);
    }

    public function getData( $field = null )  {
        if ( !$this->wrapped->getId() ) { 
            return null;
        }
        return _Mapper::init($this->modelName, $this->wrapped);
    }
}

/** 
 * Root class for model interactions in Advocado's standard model interaction.
 */
class AdvocP { 

    static $_map = array(
            'product' => 'catalog/product',
            'order' => 'sales/order',
        );

    public static function getModel( $modelName ) { 
        if ( array_key_exists( $modelName, self::$_map ) ) {
            return new AdvocModelMapper(
                Mage::getModel(self::$_map[$modelName]),
                $modelName);
        }
        return null;
    }
}


class GozoLabs_Advocado_Helper_Data extends Mage_Core_Helper_Data { 

    const COOKIE_KEY_ADVOC_STCODES          = 'advoc_st_codes';
    const SHARE_CODES_SHARE_TYPE            = 'share_type';
    const SHARE_CODES_PARENT_SUB            = 'parent_sub_id';
    const SHARE_CODES_ST_CODE               = 'st_code';
    const SHARE_CODES_SHARES                = 'shares';

    function getCurrentStoreGroupId() { 
        return Mage::app()->getStore()
            ->getGroupId();
    }

    function getCurrentWebsiteId() { 
        return Mage::app()->getStore()
            ->getWebsiteId();
    }

    function isConfigurableProduct( $product ) { 
        return $product->isConfigurable();
    }

    /** 
     * @param $product1 Mage_Catalog_Model_Product
     * @param $product2 Mage_Catalog_Model_Product
     * @return true if both products share the same parent.
     */
    function productsShareSameParent( $product1, $product2 ) { 
        if ( ! $this->isSimpleRootProduct( $product1 ) && 
            ! $this->isSimpleRootProduct( $product2) ) {

            $parent1Ids = Mage::getModel('catalog/product_type_configurable')
                    ->getParentIdsByChild($product1->getId());
            $parent2Ids = Mage::getModel('catalog/product_type_configurable')
                ->getParentIdsByChild($product2->getId());
            return $parent1Ids[0] == $parent2Ids[0];
        }
        return false;
    }

    /** checks if is a simple product and that 
     *  it does not have a parent product
     *  @return bool true if the above applies, false otherwise.
     */
    function isSimpleRootProduct( $product ) { 
        $parentIds = Mage::getModel('catalog/product_type_configurable')
                        ->getParentIdsByChild( $product->getId() );
        return !$parentIds || !isset( $parentIds[0] );
    }

    /** written because we need to filter variants. They are presented in a different way. 
     */
    function isProductVariant( $product ) { 
        if ( $product->getTypeId() == 'simple' ) { 
            $parentIds = Mage::getModel('catalog/product_type_configurable')
                            ->getParentIdsByChild($product->getId());
            if ( $parentIds && isset( $parentIds[0] ) ) { 
                // yes is variant
                return true;
            }
        }
        return false;
    }

    /** Helper method to get products from the system. 
     *  The relevant products are:
     *  - configurable products ( with children that are simple/virtual/downloadable products )
     *  - simple/virtual/downloadable products that have no parents
     *
     *  NOTE: we can consider Bundle products in future, but that doesn't really fit our
     *  alpha customers, so we shall give it up for now
     *
     *  @return mixed Returns array if $id is null, else returns a single AdvocPProduct.
     */
    function getProducts( $id, $pageNumber=1, $pageSize=null, $filters=null ) { 

        $model = AdvocP::getModel('product');
        if ( !$id ) { 

            $_collect = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId(Mage::app()->getStore()->getStoreId());

            // version 1 of the join - doesn't work
            //$_collect->getSelect()
                //->join( 
                    //array('super_table'=>'catalog_product_super_link'), 
                    //'main_table.product_id = super_table.product_id',
                    //array('super_table.*'),
                    //'schema_name_if_different'
                //);
            //$_collect = $_collect->addAttributeToFilter('parent_id', array( 'eq'=> null));

            //$collect = $model->getCollection();
                             //->addORFilterByFunc( array( $this, 'isSimpleRootProduct' ) )
                             //->addORFilterByFunc( array( $this, 'isConfigurableProduct' ) );
            // version 2 of getting products
            Mage::getSingleton('catalog/product_visibility')
                ->addVisibleInCatalogFilterToCollection($_collect);

            //$_collect = $_collect->addAttributeToFilter(
                //array(
                    //array(
                        //'type_id' => 'configurable'
                    //),
                    //array(
                        //'type_id' => 'simple'
                    //)
                //)
            //);
            $collect = new AdvocCustomProductCollection($_collect);

            if (is_array( $filters ) && count( $filters ) > 0 ) {
                foreach ( $filters as $k => $v ) { 
                    $collect->addFieldToFilter($k, $v);
                }
            }

            if ($pageSize) { 
                $collect->setPage($pageNumber, $pageSize);
            }

            return $collect;
        } else { 
            $m = $model->withId( $id ); 
            if ( ! $m )
                return null;
            return $m;
        }
    }

    function getOrders( $id )  {
        $model = AdvocP::getModel('order');
        if ( $id )  {
            $m = $model->withId( $id );
            if ( ! $m )
                return null;
            return $m;
        } else { 
            // return all orders? not advisable
            throw new Exception( 'Not permitted to retrieve all orders' );
        }
    }

    /**
     * =============================================================================== 
     * COUPON CODE METHODS
     * =============================================================================== 
     */

    /** 
     * Generates the name of a rule to be used.
     */
    function _ruleNamer( $code ) {
        return '[' . 
            implode( '_', array( 
            AdvocPCoupon::ADVOC_COUPON_NAME_PREFIX,
            $code ) ) . 
            ']';
    }

    function _getAllCustomerGroups() { 

        $custGroupCollection = Mage::getModel('customer/group')
            ->getCollection();
        $custGroupCollection->addFieldToFilter( 
            'customer_group_code', array( 'nlike' => '%auto%' ));
        $groups = array();
        foreach ( $custGroupCollection as $group ) { 
            $groups[] = $group->getId();
        }
        return $groups;
    }

    function _allWebSiteIds() { 
        //get all websites
        $websites = Mage::getModel('core/website')->getCollection();
        $websiteIds = array();
        foreach ($websites as $website){
            $websiteIds[] = $website->getId();
        }
        return $websiteIds;
    }

    function createCartCoupon( $code, $amt, $toDate, $type='amt',
        $websiteIds=null ) { 

        if ( $type == 'pct' ) { 
            $type = 'by_percent';
        } else { 
            $type = 'cart_fixed';
        }

        if ( ! $websiteIds ) { 
            $websiteIds = $this->_allWebSiteIds();
        }

        $rule = $this->getRawCouponByCode( $code );
        if ( $rule ) { 
            Mage::log('found previously existing coupn with code ' . $code );
            $rule->setToDate( $toDate )
                ->setDiscountAmount( $amt )
                ->setSimpleAction( $type );
        } else { 

            Mage::log('creating new coupon with code ' . $code );
        
            // $type at this point is not very useful
            // we only use a fixed amt off the cart at this point
            $rule = Mage::getModel('salesrule/rule');
            $rule->setName($this->_ruleNamer($code))
                ->setDescription($this->_ruleNamer($code))
                ->setFromDate('')
                ->setToDate( $toDate )
                ->setCouponType(2) // 1 means no need coupon, 2 is opposite
                ->setCouponCode($code)
                ->setUsesPerCustomer(1)
                ->setUsesPerCoupon(1)
                ->setIsActive(1)
                ->setConditionsSerialized('')
                ->setActionsSerialized('')
                ->setConditionsSerialized('')
                ->setCustomerGroupIds($this->_getAllCustomerGroups())
                ->setStopRulesProcessing(0)
                ->setIsAdvanced(1)
                ->setProductIds('')
                ->setSortOrder(0)
                ->setSimpleAction( $type )
                ->setDiscountAmount( $amt )
                ->setDiscountQty(null)
                ->setDiscountStep(0)
                ->setSimpleFreeShipping('0')
                ->setApplyToShipping('0')
                ->setIsRss(0)
                ->setWebsiteIds( $websiteIds );
        }
        $rule->save();
        return new AdvocPCoupon( $rule );
    }

    private function getRawCouponByCode( $code ) { 

        $coupon = Mage::getModel('salesrule/coupon')
            ->getCollection()
            ->addFieldToFilter( 'code', array( 'eq' => $code ) );

        if ( $coupon->getSize() > 0 ) { 
            $coupon = $coupon->getFirstItem();
            $rule = Mage::getModel('salesrule/rule')->load(
                $coupon->getRuleId());
            Mage::log('getRawCouponByCode: found rule! ' . $rule->getId());
            return $rule;
        }
        Mage::log('getRawCouponByCode: could not find coupon with code ' . $code);
        return null;
    }

    function getCouponByCode( $code ) { 
        $raw = $this->getRawCouponByCode( $code );
        if ( $raw ) { 
            return new AdvocPCoupon( $raw );
        }
        // return null
        return $raw;
    }

    function updateCartCoupon( $code, $updatedAmt ) { 

        $rule = $this->getRawCouponByCode( $code );
        if ( $rule ) { 
            $rule->setDiscountAmount( $updatedAmt );
            $rule->save();
            return new AdvocPCoupon( $rule );
        }
        return null;
    }

    function deleteCartCoupon( $code ) { 

        $rule = $this->getRawCouponByCode( $code );
        if ( $rule ) {
            Mage::log('deleting coupon with code ' . $code );
            $rule->delete();
            return true;
        }
        Mage::log('did not delete coupon with code ' . $code . ', does it exist?');
        return false;
    }

    function decomposeShareString($shareString) { 
        $shares = explode(',', $shareString);
        $shareArray = array();
        if (count($shares) == 1 && $shares[0] == '') { 
           return $shareArray; 
        }
        foreach( $shares as $s )  {
            $pair = explode('_', $s);
            array_push($shareArray, array(
                self::SHARE_CODES_SHARE_TYPE=>$pair[0],
                self::SHARE_CODES_ST_CODE=>$pair[1]
            ));
        }
        return $shareArray;
    }

    /**
     *
     * version 1.0 of the advocStCodes format.
     * <siteProductId>::<share_type>::<stCode_for_share>[##<same>]
     *
     * @return pull out the relevant cookie and parse it to extract an associative
     * array.
     */
    function getShareCodes() { 

        $textCodes = Mage::app()->getCookie()->get(self::COOKIE_KEY_ADVOC_STCODES);
        //$textCodes = null;
        //if ( array_key_exists( self::COOKIE_KEY_ADVOC_STCODES, $_COOKIE ) ) {
            //$textCodes = $_COOKIE[self::COOKIE_KEY_ADVOC_STCODES];        
        //}
        //suspect need to url decode
        Mage::log('getShareCodes::textCodes = ' . $textCodes);
        $shareCodes = array();
        if ( $textCodes ) { 
            $tokens = explode('#', $textCodes);
            foreach( $tokens as $t ) { 
                $comps = explode( '::', $t );
                $shareCodes[$comps[0]] = array( 
                    //self::SHARE_CODES_SHARE_TYPE=>$comps[1], 
                    self::SHARE_CODES_SHARES=>$this->decomposeShareString($comps[1]),
                    self::SHARE_CODES_PARENT_SUB=>$comps[2],
                    //self::SHARE_CODES_ST_CODE=>$comps[3] 
                );
            }
        }
        return $shareCodes;
    }


    function getCart() { 
        $c = Mage::getSingleton( 'checkout/cart' );
        $cart = new AdvocPCart($c);
        return $cart;
    }

}

?>
