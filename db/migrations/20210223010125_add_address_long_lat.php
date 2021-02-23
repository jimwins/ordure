<?php

use Phinx\Migration\AbstractMigration;

class AddAddressLongLat extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale_address');
      $table
        ->addColumn('latitude', 'decimal', [
          'precision' => 9,
          'scale' => 5,
          'default' => '0.0',
          'null' => true,
        ])
        ->addColumn('longitude', 'decimal', [
          'precision' => 9,
          'scale' => 5,
          'default' => '0.0',
          'null' => true,
        ])
        ->save();
    }
}
