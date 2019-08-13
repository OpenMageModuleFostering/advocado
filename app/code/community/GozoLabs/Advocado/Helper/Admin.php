<?php


class GozoLabs_Advocado_Helper_Admin extends Mage_Core_Helper_Abstract
{
    public function templateFromUrl( $url, $data, $partials = null ) { 
            $cl = new Varien_Http_Client( $url );
            $cl->setMethod( Varien_Http_Client::GET );
            $response = $cl->request();
            if ( $response->isSuccessful() ) { 
                // template downloaded
                $m = new Mustache_Engine;
                return $m->render( $response->getBody(), $data);
            }
            return 'status: ' . $response->getMessage() . '(' . $response->getStatus() . ')';
    }

    public function templateFromName( $templateName, $data ) { 
        return _getRenderEngine()->render( $templateName, $data ); 
    }
}

?>
