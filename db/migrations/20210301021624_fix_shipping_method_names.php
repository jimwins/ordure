<?php

use Phinx\Migration\AbstractMigration;

class FixShippingMethodNames extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale');
      $table
        ->changeColumn('shipping_method', 'enum', [
          'values' => [
            'default', 'economy',
            'bike', 'cargo_bike',
            'local_sm', 'local_md', 'local_lg',
            'local_xl', 'local_xxl', 'local_xxxl',
          ],
          'null' => true,
          'after' => 'shipping_address_id',
        ])
        ->save();

    }
}
