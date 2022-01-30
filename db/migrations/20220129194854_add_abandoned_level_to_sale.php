<?php

use Phinx\Migration\AbstractMigration;

class AddAbandonedLevelToSale extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale');
      $table
        ->addColumn('abandoned_level', 'integer', [
          'signed' => false,
          'default' => 0,
        ])
        ->update();
    }
}
