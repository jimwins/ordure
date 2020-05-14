<?php

use Phinx\Migration\AbstractMigration;

class AddCartToAuthToken extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('auth_token');
      $table
        ->addColumn('cart', 'string', [
          'limit' => 50,
          'null' => true,
          'after' => 'person_id'
        ])
        ->save();

    }
}
