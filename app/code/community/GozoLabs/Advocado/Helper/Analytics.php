<?php 

// test commit
if ( isset( $_SERVER[ 'ADVOC_LOCAL_DEBUG' ] ) || isset( $_SERVER['ADVOC_STAGING'] ) ) { 
    define( 'SEGMENT_IO_PIN', '8sm4kvvp9hyzwn99ykdr' );
} else { 
    define( 'SEGMENT_IO_PIN', 'mdsygwjezbsovqi0j31y' );
}

//require_once(Mage::getBaseDir('lib') . '/Raven/Client.php');
require_once(Mage::getBaseDir('lib') . '/Analytics/Analytics.php');

/**
 * Logging tool for Advocado's use.
 * There are specific cases for its use, however we need to take note that 
 * we are just an extension. So we should not be capturing all exceptions. 
 * Rather we should just catch exceptions that are relevant to this 
 * extension's smooth operation (for iteration).
 *
 */
class GozoLabs_Advocado_Helper_Analytics extends Mage_Core_Helper_Abstract { 

    public $isInit;
    public $siteUrl;

    public function isInitialized()  {
        return isset($this->isInit) && $this->isInit;
    }

    private function getBaseIdTraits() { 
        if (!isset($this->siteUrl) || !$this->siteUrl) { 
            $dh = Mage::helper('gozolabs_advocado');
            $this->siteUrl = $dh->getSiteUrl();
        }
        return array( 
            'Site URL'=> $this->siteUrl,
            'Platform' => 'Magento'
        );
    }

    public function initialize()  {
        Mage::log('[Analytics] Initialising with ' . SEGMENT_IO_PIN);
        Analytics::init(SEGMENT_IO_PIN);
        Mage::log('[Analytics] Initialised');
        $this->isInit = true;
        $baseTraits = $this->getBaseIdTraits();
        Mage::log('[Analytics] got base traits');
        $this->identify($baseTraits);
        Mage::log('[Analytics] identified');
    }

    public function identify($traits) { 
        if (!$this->isInitialized()) { 
            $this->initialize();
        }
        Analytics::identify('019mr8mf4r', $traits);
    }

    public function track($eventName, $traits) { 
        if (!$this->isInitialized()) { 
            Mage::log('[Analytics] Starting initialisation');
            $this->initialize();
            Mage::log('[Analytics] Done initialisation');
        }
        Mage::log('[Analytics] Getting the base traits');
        $baseIdTraits = $this->getBaseIdTraits();
        Mage::log('[Analytics] base traits: ' . var_export($baseIdTraits, true));
        $t = array_merge($traits, $this->getBaseIdTraits());
        Mage::log('[Analytics] Tracking: '.$eventName.' , ' . var_export($traits, true));
        Analytics::track('019mr8mf4r', $eventName, $t);
    }
}


?>
