<?php

use Phinx\Migration\AbstractMigration;

class AddImageItemView extends AbstractMigration
{
    public function up()
    {
      $scat= $_ENV['SCAT_DATABASE'] ?: 'scat';
      $this->execute("CREATE VIEW item_to_image AS SELECT * FROM scat.item_to_image");

    }

    public function down()
    {
      $this->execute("DROP VIEW item_to_image");

    }
}
