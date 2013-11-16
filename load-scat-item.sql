TRUNCATE scat_item;

INSERT INTO scat_item
       (code, retail_price, discount_type, discount, stock)
SELECT code, retail_price, discount_type, discount,
       IFNULL((SELECT SUM(allocated)
                 FROM scat.txn_line
                WHERE item = scat.item.id), 0) stock
  FROM scat.item;
