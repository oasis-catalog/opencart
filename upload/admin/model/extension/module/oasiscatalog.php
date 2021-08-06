<?php

class ModelExtensionModuleOasiscatalog extends Model
{

    public function addOrder($data)
    {
        if (isset($data['order_id']) && isset($data['queue_id'])) {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "oasis_order` (order_id, queue_id) 
                SELECT * FROM (SELECT '" . (int)$data['order_id'] . "', '" . (int)$data['queue_id'] . "') AS tmp
                WHERE NOT EXISTS (
                    SELECT order_id FROM `" . DB_PREFIX . "oasis_order` WHERE order_id = '" . (int)$data['order_id'] . "'
                ) LIMIT 1
            ");
        }
    }

    public function getOrder($order_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_order WHERE order_id = '" . (int)$order_id . "'");

        return $query->row;
    }

    public function addProduct($data)
    {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "oasis_product` (product_id_oasis, option_date_modified, option_value_id, product_id) 
            SELECT * FROM (SELECT '" . $this->db->escape($data['product_id_oasis']) . "', NOW(), '" . (int)$data['option_value_id'] . "', '" . (int)$data['product_id'] . "') AS tmp
            WHERE NOT EXISTS (
                SELECT product_id_oasis FROM `" . DB_PREFIX . "oasis_product` WHERE product_id_oasis = '" . $this->db->escape($data['product_id_oasis']) . "'
            ) LIMIT 1
        ");
    }

    public function editProduct($product_id_oasis, $data)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "oasis_product SET option_date_modified = NOW(), option_value_id = '" . (int)$data['option_value_id'] . "', product_id = '" . (int)$data['product_id'] . "' WHERE product_id_oasis = '" . $this->db->escape($product_id_oasis) . "'");
    }

    public function getProduct($product_id_oasis)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id_oasis = '" . $this->db->escape($product_id_oasis) . "'");

        return $query->row;
    }

    public function getProductDateModified($product_id_oasis)
    {
        $query = $this->db->query("SELECT `option_date_modified` FROM " . DB_PREFIX . "oasis_product WHERE product_id_oasis = '" . $this->db->escape($product_id_oasis) . "'");

        return $query->row;
    }

    public function install()
    {
        $sql = " SHOW TABLES LIKE '" . DB_PREFIX . "oasis%'";
        $query = $this->db->query($sql);
        if (count($query->rows) <= 1) {
            $this->createTables();
        }

    }

    public function getProductOptionValueId($product_id, $option_value_id) {
        $query = $this->db->query("SELECT product_option_value_id FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "' AND option_value_id = '" . (int)$option_value_id . "'");

        return $query->row;
    }

    public function createTables()
    {
        $queries = [];
        $queries[] = "
            CREATE TABLE `" . DB_PREFIX . "oasis_order` (
    `order_id` INT(11) NOT null,
                `queue_id` INT(11) NOT null,
                PRIMARY KEY(`order_id`)
            )
            COLLATE = 'utf8_general_ci'
            ENGINE = MyISAM
            ROW_FORMAT = FIXED
		";
        $queries[] = "
            CREATE TABLE `" . DB_PREFIX . "oasis_product` (
    `product_id` INT(11) NOT null,
                `product_id_oasis` INT(11) NOT null,
                `article_oasis` INT(11) NOT null,
                PRIMARY KEY(`product_id_oasis`)
            )
            COLLATE = 'utf8_general_ci'
            ENGINE = MyISAM
            ROW_FORMAT = FIXED
		";

        foreach ($queries as $query) {
            $this->db->query($query);
        }

        return true;
    }
}
