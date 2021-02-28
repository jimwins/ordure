<?php

use Phinx\Migration\AbstractMigration;

class AddMoreShippingMethods extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale');
      $table
        ->changeColumn('shipping_method', 'enum', [
          'values' => [
            'default', 'economy',
            'bike', 'cargo-bike',
            'local-sm', 'local-md', 'local-lg',
            'local-xl', 'local-xxl', 'local-xxxl',
          ],
          'null' => true,
          'after' => 'shipping_address_id',
        ])
        ->save();

    }
}
