<?php

namespace Opencart\Admin\Model\Extension\Oasiscatalog\Module;

use Opencart\System\Engine\Model;

class Oasis extends Model
{

    public function getImage(array $where)
    {
        $sql = "SELECT * FROM `" . DB_PREFIX . "oasis_images` WHERE `name` = '" . $this->db->escape($where['name']) . "'";

        if (!empty($where['path'])) {
            $sql .= " AND `path` = '" . $this->db->escape($where['path']) . "'";
        }

        $query = $this->db->query($sql);

        return $query->row;
    }

    public function getImages(array $names)
    {
        if (empty($names)) {
            return [];
        }

        $sql = "SELECT * FROM `" . DB_PREFIX . "oasis_images` WHERE `name` = '" . $this->db->escape(array_shift($names)) . "'";

        if (!empty($names)) {
            foreach ($names as $name) {
                $sql .= " OR `name` = '" . $this->db->escape($name) . "'";
            }
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    public function addImage($data)
    {
        if (isset($data['name']) && isset($data['path']) && isset($data['date_added'])) {
            $this->db->query("
                INSERT INTO `" . DB_PREFIX . "oasis_images` (name, path, date_added) 
                SELECT * FROM (SELECT '" . $this->db->escape($data['name']) . "', '" . $this->db->escape($data['path']) . "', '" . (int)$data['date_added'] . "') AS tmp
                WHERE NOT EXISTS (
                    SELECT name FROM `" . DB_PREFIX . "oasis_images` WHERE name = '" . $this->db->escape($data['name']) . "'
                ) LIMIT 1
            ");
        }
    }

    public function deleteImage($name): void
    {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_images` WHERE `name` = '" . $this->db->escape($name) . "'");
    }

    public function setOption($store_id, $code, $key, $value)
    {
        if (!empty($code) && !empty($key)) {
            if (is_array($value)) {
                $serialized = 1;
                $value = json_encode($value);
            } else {
                $serialized = 0;
            }

            $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting 
            WHERE `store_id` = '" . (int)$store_id . "' 
                AND `code` = '" . $this->db->escape($code) . "'
                AND `key` = '" . $this->db->escape($key) . "'");

            if ($query->row) {
                $this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "', `serialized` = '" . $serialized . "' WHERE `setting_id` = '" . $query->row['setting_id'] . "'");
            } else {
                $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET `store_id` = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', `serialized` = '" . $serialized . "'");
            }
        }
    }

    public function deleteOption($store_id, $code, $key)
    {
        if (!empty($code) && !empty($key)) {
            $this->db->query("DELETE FROM " . DB_PREFIX . "setting 
            WHERE `store_id` = '" . (int)$store_id . "' 
                AND `code` = '" . $this->db->escape($code) . "'
                AND `key` = '" . $this->db->escape($key) . "'");
        }
    }

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

    public function addOasisProduct(array $data)
    {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "oasis_product` (product_id_oasis, rating, option_date_modified, option_value_id, product_id) 
            SELECT * FROM (SELECT '" . $this->db->escape($data['product_id_oasis']) . "' product_id_oasis, '" . (int)$data['rating'] . "' rating, NOW() option_date_modified, '" . (int)$data['option_value_id'] . "' option_value_id, '" . (int)$data['product_id'] . "' product_id) AS tmp
            WHERE NOT EXISTS (
                SELECT product_id_oasis FROM `" . DB_PREFIX . "oasis_product` WHERE product_id_oasis = '" . $this->db->escape($data['product_id_oasis']) . "'
            ) LIMIT 1
        ");
    }

    public function editOasisProduct(string $product_id_oasis, array $data)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "oasis_product SET rating = '" . (int)$data['rating'] . "', option_date_modified = NOW(), option_value_id = '" . (int)$data['option_value_id'] . "', product_id = '" . (int)$data['product_id'] . "' WHERE product_id_oasis = '" . $this->db->escape($product_id_oasis) . "'");
    }

    public function getOasisProduct(string $product_id_oasis): array
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "oasis_product WHERE product_id_oasis = '" . $this->db->escape($product_id_oasis) . "'");

        return $query->row;
    }

    public function getOasisProducts()
    {
        $query = $this->db->query("SELECT product_id_oasis, rating, option_value_id, product_id FROM " . DB_PREFIX . "oasis_product");

        return $query->rows;
    }

    public function getOasisProductIdByOption($option_value_id)
    {
        $query = $this->db->query("SELECT product_id_oasis FROM " . DB_PREFIX . "oasis_product WHERE option_value_id = '" . (int)$option_value_id . "'");

        return $query->row;
    }

    public function getOasisProductIdByProductId($product_id)
    {
        $query = $this->db->query("SELECT product_id_oasis FROM " . DB_PREFIX . "oasis_product WHERE product_id = '" . (int)$product_id . "'");

        return $query->row;
    }

    public function getOasisProductDateModified($product_id_oasis)
    {
        $query = $this->db->query("SELECT `option_date_modified` FROM " . DB_PREFIX . "oasis_product WHERE product_id_oasis = '" . $this->db->escape($product_id_oasis) . "'");

        return $query->row;
    }

    public function upProductQuantity($product_id, $quantity)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . (int)$quantity . "' WHERE product_id = '" . (int)$product_id . "'");
    }

    public function disableProduct($product_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET status = '0' WHERE product_id = '" . (int)$product_id . "'");
    }

    public function getProductOptionValues($product_id)
    {
        $query = $this->db->query("SELECT product_option_value_id, quantity FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");

        return $query->rows;
    }

    public function getProductOptionValueId(int $product_id, int $option_value_id)
    {
        $query = $this->db->query("SELECT product_option_value_id FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . $product_id . "' AND option_value_id = '" . $option_value_id . "'");

        return $query->row;
    }

    public function upProductOptionValue($product_option_value_id, $quantity)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product_option_value SET quantity = '" . (int)$quantity . "' WHERE product_option_value_id = '" . (int)$product_option_value_id . "'");
    }

    public function getCategory(int $category_id): array
    {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category WHERE category_id = '" . $category_id . "'");

        return $query->row;
    }

    public function getCategoryPath($category_id)
    {
        $query = $this->db->query("SELECT category_id, path_id, level FROM " . DB_PREFIX . "category_path WHERE category_id = '" . (int)$category_id . "'");

        return $query->rows;
    }

    public function getSeoUrls(array $where): array
    {
        $sql = "SELECT * FROM `" . DB_PREFIX . "seo_url` WHERE `keyword` = '" . $this->db->escape($where['keyword']) . "'";

        if (!empty($where['key'])) {
            $sql .= " AND `key` = '" . $this->db->escape($where['key']) . "'";
        }

        if (!empty($where['value'])) {
            $sql .= " AND `value` = '" . $this->db->escape($where['value']) . "'";
        }

        $query = $this->db->query($sql);

        return $query->row;
    }

    public function getIdOcCategory(int $id): array
    {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "oasis_category` WHERE oasis_id = '" . $id . "'");

        return $query->row;
    }

    public function addOasisCategory($data)
    {
        $this->db->query("
            INSERT INTO `" . DB_PREFIX . "oasis_category` (oasis_id, category_id) 
            SELECT * FROM (SELECT '" . (int)$data['oasis_id'] . "' oasis_id, '" . $this->db->escape($data['category_id']) . "' category_id) AS tmp
            WHERE NOT EXISTS (
                SELECT oasis_id FROM `" . DB_PREFIX . "oasis_category` WHERE oasis_id = '" . (int)$data['oasis_id'] . "'
            ) LIMIT 1
        ");
    }

    public function deleteOasisCategory(int $category_id): void
    {
        $this->db->query("DELETE FROM `" . DB_PREFIX . "oasis_category` WHERE `category_id` = '" . $category_id . "'");
    }

    public function install(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_order` (
		  `order_id` INT(11) NOT NULL,
		  `queue_id` INT(11) NOT NULL,
		  PRIMARY KEY(`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_product` (
		  `product_id_oasis` CHAR(12) NOT NULL,
		  `rating` TINYINT(1) NOT NULL,
		  `option_date_modified` DATETIME NOT NULL,
		  `option_value_id` INT(11) NOT NULL DEFAULT '0',
		  `product_id` INT(11) NOT NULL,
		  PRIMARY KEY (`product_id_oasis`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "oasis_category` (
		  `oasis_id` CHAR(12) NOT NULL,
		  `category_id` INT NOT NULL,
		  PRIMARY KEY (`oasis_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->db->query("CREATE TABLE `" . DB_PREFIX . "oasis_images` (
          `name` char(50) NOT NULL,
          `path` char(255) NOT NULL,
          `date_added` int unsigned NOT NULL,
          PRIMARY KEY (`name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
    }
}
