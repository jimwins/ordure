<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class InitialSetup extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('auth', [
                             'id' => false,
                             'primary_key' => [ 'person_id' ]
                           ]);
      $table
        ->addColumn('person_id', 'integer', [
                      'signed' => false,
                    ])
        ->addColumn('password_hash', 'string', [
                      'limit' => 255,
                      'null' => true
                    ])
        ->addColumn('otp_key', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('last_auth', 'datetime', [ 'null' => true ])
        ->addColumn('failures', 'integer', [
                      'signed' => false,
                      'default' => 0
                    ])
        ->create();

      $table= $this->table('auth_token', [ 'signed' => false ]);
      $table
        ->addColumn('selector', 'char', [ 'limit' => 12, 'null' => true ])
        ->addColumn('token', 'char', [ 'limit' => 64, 'null' => true ])
        ->addColumn('person_id', 'integer', [ 'signed' => false ])
        ->addColumn('expires', 'datetime', [ 'null' => true ])
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('modified', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->create();

      $table= $this->table('loyalty', [ 'signed' => false ]);
      $table
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('email', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('subscribe', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('phone', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('loyalty_number', 'string', [
                      'limit' => 32,
                      'null' => true
                    ])
        ->addColumn('code', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('processed', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('verified', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->create();

      $table= $this->table('page', [ 'signed' => false ]);
      $table
        ->addColumn('title', 'string', [
                      'limit' => 255,
                      'null' => true,
                      'default' => ''
                    ])
        ->addColumn('slug', 'string', [ 'limit' => 255 ])
        ->addColumn('format', 'enum', [
                      'values' => [ 'markdown', 'html' ],
                      'default' => 'markdown',
                    ])
        ->addColumn('content', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('script', 'string', [
                      'limit' => 255,
                      'null' => true,
                    ])
        ->addColumn('description', 'text', [ 'null' => true ])
        ->addIndex(['slug'], [ 'unique' => true ])
        ->create();

      $table= $this->table('person', [ 'signed' => false ]);
      $table
        ->addColumn('role', 'enum', [
                      'values' => [ 'customer', 'employee', 'vendor' ],
                      'null' => true,
                      'default' => 'customer'
                    ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('email', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('active', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 1,
                    ])
        ->addColumn('deleted', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addIndex(['email'], [ 'unique' => true ])
        ->create();

      $table= $this->table('sale', [ 'signed' => false ]);
      $table
        ->addColumn('uuid', 'string', [ 'limit' => 50, 'null' => true ])
        ->addColumn('created', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('modified', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('status', 'enum', [
                      'values' => [ 'new', 'cart', 'review', 'unpaid',
                                    'paid', 'processing', 'shipped',
                                    'cancelled', 'onhold' ],
                      'default' => 'new',
                    ])
        ->addColumn('person_id', 'integer', [ 'signed' => false ])
        ->addColumn('billing_address_id', 'integer', [
                      'signed' => false,
                      'null' => true,
                    ])
        ->addColumn('shipping_address_id', 'integer', [
                      'signed' => false,
                      'null' => true,
                    ])
        ->addColumn('shipping', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'default' => '0.00'
                    ])
        ->addColumn('shipping_tax', 'decimal', [
                      'precision' => 9,
                      'scale' => 3,
                      'null' => true,
                      'default' => '0.000'
                    ])
        ->addColumn('shipping_manual', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('name', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('email', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('tax_exemption', 'string', [
                      'limit' => 50,
                      'null' => true
                    ])
        ->addColumn('tax_calculated', 'datetime', [
                      'null' => true,
                    ])
        ->addColumn('amz_order_reference_id', 'string', [
                      'limit' => 255,
                      'null' => true
                    ])
        ->addIndex(['uuid'], [ 'unique' => true ])
        ->create();

      $table= $this->table('sale_address', [ 'signed' => false ]);
      $table
        ->addColumn('name', 'string', [ 'limit' => 255 ])
        ->addColumn('company', 'string', [ 'limit' => 255, 'null' => true ])
        ->addColumn('address1', 'string', [ 'limit' => 255 ])
        ->addColumn('address2', 'string', [ 'limit' => 255, 'default' => '' ])
        ->addColumn('city', 'string', [ 'limit' => 255 ])
        ->addColumn('state', 'char', [ 'limit' => 2 ])
        ->addColumn('zip5', 'string', [ 'limit' => 20 ])
        ->addColumn('zip4', 'char', [ 'limit' => 4, 'default' => '0000' ])
        ->addColumn('phone', 'string', [ 'limit' => 50, 'default' => '' ])
        ->addColumn('verified', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->create();

      $table= $this->table('sale_item', [ 'signed' => false ]);
      $table
        ->addColumn('sale_id', 'integer', [ 'signed' => false ])
        ->addColumn('item_id', 'integer', [ 'signed' => false ])
        ->addColumn('quantity', 'integer')
        ->addColumn('override_name', 'string', [
                      'limit' => 255,
                      'null' => true
                    ])
        ->addColumn('retail_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                    ])
        ->addColumn('discount_type', 'enum', [
                      'values' => [ 'percentage', 'relative', 'fixed' ],
                      'null' => true
                    ])
        ->addColumn('discount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('discount_manual', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->addColumn('tic', 'char', [ 'limit' => 5, 'default' => '00000' ])
        ->addColumn('tax', 'decimal', [
                      'precision' => 9,
                      'scale' => 3,
                      'default' => '0.000'
                    ])
        ->addIndex(['sale_id'])
        ->addIndex(['item_id'])
        ->create();

      $table= $this->table('sale_note', [ 'signed' => false ]);
      $table
        ->addColumn('sale_id', 'integer', [ 'signed' => false ])
        ->addColumn('person_id', 'integer', [ 'signed' => false ])
        ->addColumn('content', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->addColumn('added', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('modified', 'datetime', [
                      'update' => 'CURRENT_TIMESTAMP',
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addIndex(['sale_id'])
        ->create();

      $table= $this->table('sale_payment', [ 'signed' => false ]);
      $table
        ->addColumn('sale_id', 'integer', [ 'signed' => false ])
        ->addColumn('method', 'enum', [
                      'values' => [ 'credit', 'amazon', 'paypal',
                                    'gift', 'other' ]
                    ])
        ->addColumn('amount', 'decimal', [
                      'precision' => 9,
                      'scale' => 3
                    ])
        ->addColumn('processed', 'datetime', [
                      'default' => 'CURRENT_TIMESTAMP',
                    ])
        ->addColumn('captured', 'datetime', [ 'null' => true ])
        ->addColumn('data', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->addIndex(['sale_id'])
        ->create();

      $table= $this->table('sale_shipment', [ 'signed' => false ]);
      $table
        ->addColumn('sale_id', 'integer', [ 'signed' => false ])
        ->addColumn('created', 'datetime')
        ->addColumn('ship_date', 'date')
        ->addColumn('carrier', 'string', [ 'limit' => 50 ])
        ->addColumn('service', 'string', [ 'limit' => 50 ])
        ->addColumn('tracking_number', 'string', [ 'limit' => 50 ])
        ->addColumn('shipping_cost', 'decimal', [
                      'precision' => 9,
                      'scale' => 2
                    ])
        ->addColumn('data', 'blob', [
                      'limit' => MysqlAdapter::BLOB_MEDIUM,
                      'null' => true,
                    ])
        ->create();

      $table= $this->table('scat_item', [
                            'id' => false,
                            'primary_key' => [ 'code' ]
                          ]);
      $table
        ->addColumn('retail_price', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'default' => '0.00'
                    ])
        ->addColumn('discount_type', 'enum', [
                      'values' => [ 'percentage', 'relative', 'fixed' ],
                      'null' => true
                    ])
        ->addColumn('discount', 'decimal', [
                      'precision' => 9,
                      'scale' => 2,
                      'null' => true
                    ])
        ->addColumn('stock', 'integer', [ 'null' => true ])
        ->addColumn('minimum_quantity', 'integer', [
                      'null' => true,
                      'default' => 0
                    ])
        ->addColumn('purchase_quantity', 'integer', [
                      'null' => true,
                      'default' => 1
                    ])
        ->addColumn('code', 'string', [ 'limit' => 50 ])
        ->create();
    }
}
