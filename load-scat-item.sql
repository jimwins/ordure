TRUNCATE scat_item;

INSERT INTO scat_item
       (code, retail_price, discount_type, discount, stock, minimum_quantity)
SELECT code, retail_price, discount_type, discount,
       IF(active,
          (SELECT SUM(allocated) FROM scat.txn_line WHERE item = scat.item.id),
          NULL) stock,
       minimum_quantity
  FROM scat.item
 WHERE active AND NOT deleted;
