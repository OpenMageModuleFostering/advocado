<?php
/** Installer for v0.1.0 
 *  @author syquek
 */

/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$table = $installer->getConnection()
    ->newTable($installer->getTable('gozolabs_advocado/credentials'))
    // credentials_id primary key for rows
    ->addColumn('credentials_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'identity' => true,
        'nullable' => false,
        'primary'  => true,
    ), 'Entity id')
   // associate a credential with a website (for multi-store setup)
    ->addColumn('website_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
    ), 'Website Id')
  // associate a credential with a store (for multi-store setup)
    ->addColumn('store_group_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'unsigned' => true,
        'nullable' => false,
    ), 'Store Group Id')
    // site_key is used to identify the current site
    ->addColumn('site_key', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'nullable' => true,
    ), 'Site Key')
    // site_secret is the shared secret between the site and advocado's backend
    ->addColumn('site_secret', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'nullable' => true,
    ), 'Site Secret')
    // site_token is used to make calls to the backend API
    ->addColumn('site_token', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'nullable' => true,
    ), 'Site Token')
    // iframe_token is used to permit access for loading the merchant
    // dashboard from the backend
    ->addColumn('iframe_token', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(
        'nullable' => true,
    ), 'IFrame Token')
    // Token update timestamp
    ->addColumn('token_updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable' => true,
        'default' => null,
    ), 'Site Token Update Time')
    // IFrame token update timestamp
    ->addColumn('iftoken_updated_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        'nullable' => true,
    ), 'IFrame Token Update Time')
    ->addIndex($installer->getIdxName(
            $installer->getTable('gozolabs_advocado/credentials'),
            array('store_group_id'),
            Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
        ),
        array('store_group_id'),
        array('type' => Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX)
    )
    ->setComment('Advocado Backend Credentials');

$installer->getConnection()->createTable( $table );
    
?>
