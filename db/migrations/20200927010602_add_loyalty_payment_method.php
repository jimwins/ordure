<?php

use Phinx\Migration\AbstractMigration;

class AddLoyaltyPaymentMethod extends AbstractMigration
{
    public function up()
    {
      $table= $this->table('sale_payment', [ 'signed' => false ]);
      $table
        ->changeColumn('method', 'enum', [
                        'values' => [ 'credit', 'amazon', 'paypal',
                                      'gift', 'loyalty', 'other' ]
                      ])
        ->save();
    }

    public function down()
    {
      // We don't actually undo this, no harm in leaving it
    }
}
