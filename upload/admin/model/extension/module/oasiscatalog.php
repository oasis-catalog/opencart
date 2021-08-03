<?php

class ModelExtensionModuleOasiscatalog extends Model
{

    public function getOrder($order_id)
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_order WHERE order_id = '" . (int)$order_id . "'");

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

    public function createTables()
    {
        $queries = [];
        $queries[] = "
            CREATE TABLE `" . DB_PREFIX . "oasis_order` (
                `order_id` INT(11) NOT NULL,
                `queue_id` INT(11) NOT NULL,
                PRIMARY KEY (`order_id`)
            )
            COLLATE='utf8_general_ci'
            ENGINE=MyISAM
            ROW_FORMAT=FIXED
		";
        $queries[] = "
            CREATE TABLE `" . DB_PREFIX . "oasis_product` (
                `product_id` INT(11) NOT NULL,
                `product_id_oasis` INT(11) NOT NULL,
                `article_oasis` INT(11) NOT NULL,
                PRIMARY KEY (`product_id_oasis`)
            )
            COLLATE='utf8_general_ci'
            ENGINE=MyISAM
            ROW_FORMAT=FIXED
		";

        foreach( $queries as $query ){
            $this->db->query( $query );
        }

        return true;
    }
}
