<?php

use Phinx\Migration\AbstractMigration;

class AddEasypostToAddress extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale_address');
      $table
        ->addColumn('easypost_id', 'string', [ 'limit' => 50, 'default' => '' ])
        ->save();

    }
}
