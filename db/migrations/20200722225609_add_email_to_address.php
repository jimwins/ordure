<?php

use Phinx\Migration\AbstractMigration;

class AddEmailToAddress extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale_address');
      $table
        ->addColumn('email', 'string', [
          'limit' => 255,
          'after' => 'name',
          'null' => 'true',
          'default' => null,
        ])
        ->save();
    }
}
