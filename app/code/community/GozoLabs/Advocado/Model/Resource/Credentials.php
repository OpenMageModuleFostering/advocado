<?php

class GozoLabs_Advocado_Model_Resource_Credentials extends 
    Mage_Core_Model_Resource_Db_Abstract 
{
    protected function _construct() 
    { 
        $this->_init('gozolabs_advocado/credentials', 'credentials_id');
    }
}

?>
