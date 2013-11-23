TRUNCATE scat_item;

INSERT INTO scat_item
       (code, retail_price, discount_type, discount, stock)
SELECT code, retail_price, discount_type, discount,
       IF(active,
          (SELECT SUM(allocated) FROM scat.txn_line WHERE item = scat.item.id),
          NULL) stock
  FROM scat.item;
