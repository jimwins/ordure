<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class AddRewardsPlus extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('loyalty');
      $table
        ->addColumn('rewardsplus', 'integer', [
                      'limit' => MysqlAdapter::INT_TINY,
                      'default' => 0,
                    ])
        ->save();

    }
}
