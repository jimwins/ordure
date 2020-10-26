<?php

use Phinx\Migration\AbstractMigration;

class AddStripePaymentIntent extends AbstractMigration
{
    public function change()
    {
      $table= $this->table('sale');
      $table
        ->addColumn('stripe_payment_intent_id', 'string', [
          'limit' => 255,
          'null' => true,
          'after' => 'amz_order_reference_id',
        ])
        ->save();

    }
}
