<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class SalePaymentDataAsText extends AbstractMigration
{
    public function up()
    {
      $table= $this->table('sale_payment', [ 'signed' => false ]);
      $table
        ->changeColumn('data', 'text', [
                      'limit' => MysqlAdapter::TEXT_MEDIUM,
                      'null' => true,
                    ])
        ->save();
    }

    public function down()
    {
      // No going back!
    }
}
