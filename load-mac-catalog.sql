-- Load the catalog
TRUNCATE TABLE mac_catalog;
LOAD DATA LOCAL INFILE 'Gen_Catalog.txt'
     INTO TABLE mac_catalog
     CHARACTER SET latin1
     FIELDS TERMINATED BY '\t'
     LINES TERMINATED BY '\r\n'
     IGNORE 1 LINES
     (item_no, sku, internal_name, name, description, unit_of_sale,
      retail_price, product_code_type, product_code, purchase_qty, abcflag,
      category_code, category_description, breadcrumb, chapter, category,
      product_title, product_subtitle, phaseoutdate, length, width, height,
      weight, small_100, medium_200, large_480);

-- Load the brands
TRUNCATE TABLE mac_item_brands;
LOAD DATA LOCAL INFILE 'item_brands.csv'
     INTO TABLE mac_item_brands
     FIELDS TERMINATED BY ','
     LINES TERMINATED BY '\n'
     IGNORE 1 LINES
     (item_no, internal_name, brand_name, vendor_number, @x);

-- Clean up the brands
UPDATE mac_item_brands
   SET brand_name = 'Art Alternatives'
 WHERE brand_name LIKE 'Art Alternatives%';

UPDATE mac_item_brands
   SET brand_name = 'PanPastel'
 WHERE brand_name LIKE 'PanPastel%';

DELETE FROM mac_item_brands
 WHERE brand_name LIKE 'Aaron Bros%';
DELETE FROM mac_item_brands
 WHERE brand_name LIKE 'Dick Blick%';
DELETE FROM mac_item_brands
 WHERE brand_name LIKE 'Discontinued Items%';

-- Load the brands into our table
INSERT IGNORE INTO brand (name, slug)
  SELECT brand_name,
         REPLACE(REPLACE(LOWER(brand_name), '&', 'and'), ' ', '-') slug
    FROM mac_item_brands ORDER BY brand_name;

-- And link them back to the mac_item_brands info
UPDATE mac_item_brands, brand SET brand_id = id WHERE brand_name = name;

-- Figure out the departments
TRUNCATE department;
INSERT INTO department (name, slug, pos)
SELECT DISTINCT chapter,
                REPLACE(REPLACE(LOWER(chapter), '&', 'and'), ' ', '-'),
                0
  FROM mac_catalog
 WHERE category != '';

-- Figure out the sub-departments
INSERT INTO department (parent, name, slug, pos)
SELECT DISTINCT (SELECT id FROM department
                  WHERE parent IS NULL
                    AND name = chapter) dept,
                category,
                REPLACE(REPLACE(LOWER(category), '&', 'and'), ' ', '-'),
                0
  FROM mac_catalog
 WHERE category != '';

-- Figure out products
TRUNCATE product;
INSERT IGNORE INTO product
       (department, brand, name, description, slug, image, from_item_no)
SELECT DISTINCT
       (SELECT id FROM department WHERE name = category LIMIT 1) dept,
       (SELECT brand_id FROM mac_item_brands
         WHERE mac_item_brands.item_no = mac_catalog.item_no) brand,
       product_title,
       description,
       REPLACE(REPLACE(LOWER(product_title), '&', 'and'), ' ', '-') slug,
       large_480,
       item_no
  FROM mac_catalog
HAVING dept AND brand;

-- Figure out items
TRUNCATE item;
INSERT INTO item
       (product, code, mac_sku, name, short_name,
        unit_of_sale, retail_price, purchase_qty,
        length, width, height, weight,
        thumbnail)
SELECT (SELECT id FROM product
         WHERE department = (SELECT id FROM department
                              WHERE department.name = category LIMIT 1)
           AND product.name = product_title LIMIT 1) product,
       item_no, IF(sku, sku, NULL) sku, internal_name, name,
       unit_of_sale, retail_price, purchase_qty,
       length, width, height, weight,
       small_100
  FROM mac_catalog
HAVING product;
