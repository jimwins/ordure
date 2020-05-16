<?php

use Phinx\Migration\AbstractMigration;

class AddAddressDistance extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale_address');
      $table
        ->addColumn('distance', 'decimal', [
          'precision' => 9,
          'scale' => 2,
          'default' => '0.0',
          'null' => true,
        ])
        ->save();

    }
}
