<?php

use Phinx\Migration\AbstractMigration;

class AddDropShippable extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('scat_item');
      $table
        ->addColumn('is_dropshippable', 'integer', [
                      'null' => true,
                      'default' => 0
                    ])
        ->save();
    }
}
