<?php

use Phinx\Migration\AbstractMigration;

class AddShippingMethodToSale extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale');
      $table
        ->addColumn('shipping_method', 'enum', [
          'values' => [ 'default', 'bike', 'cargo', 'truck' ],
          'null' => true,
          'after' => 'shipping_address_id',
        ])
        ->save();

    }
}
