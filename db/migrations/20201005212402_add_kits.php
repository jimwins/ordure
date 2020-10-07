<?php

use Phinx\Migration\AbstractMigration;

class AddKits extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale_item', [ 'signed' => false ]);
      $table
        ->addColumn('kit_id', 'integer', [
          'after' => 'item_id',
          'signed' => false,
          'null' => true,
        ])
        ->save();

    }
}
