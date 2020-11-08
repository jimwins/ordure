<?php

use Phinx\Migration\AbstractMigration;

class AddPayPalOrderId extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale');
      $table
        ->addColumn('paypal_order_id', 'string', [
          'limit' => 255,
          'null' => true,
          'after' => 'amz_order_reference_id',
        ])
        ->save();

    }
}
