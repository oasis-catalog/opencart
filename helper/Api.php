<?php

namespace Opencart\Admin\Controller\Extension\Oasis;

use Exception;

class Api
{
    private const API_V4 = 'v4/';
    private const API_V3 = 'v3/';
    private const API_PRODUCTS = 'products';

    /**
     * @param $args
     * @return array
     */
    public static function getProductsOasis($args): array
    {
        return self::curl_query(self::API_V4, self::API_PRODUCTS, $args);
    }

    /**
     * @param $args
     * @return array
     */
    public static function getProductOasis($args): array
    {
        if (isset($args['ids']) && $args['ids'] !== '') {
            $args['ids'] = $args['ids']['id'];
            unset($args['limit'], $args['offset']);
        }

        return self::curl_query(self::API_V4, self::API_PRODUCTS, $args);
    }

    /**
     * Get oasis stat
     *
     * @return mixed|void
     */
    public static function getStatProducts($config)
    {
        $data_args = $config->get('oasiscatalog_args');
        $data = [
            'not_on_order' => $data_args['not_on_order'] ?? '',
            'price_from'   => $data_args['price_from'] ?? '',
            'price_to'     => $data_args['price_to'] ?? '',
            'rating'       => $data_args['rating'] ?? '0,1,2,3,4,5',
            'moscow'       => $data_args['moscow'] ?? '',
            'europe'       => $data_args['europe'] ?? '',
            'remote'       => $data_args['remote'] ?? '',
        ];

        $category = $config->get('oasiscatalog_category');


        if (empty($category)) {
            $data['category'] = implode(',', array_keys(Main::getOasisMainCategories()));
        } else {
            $data['category'] = $category;
        }

        foreach ($data as $key => $value) {
            if ($value) {
                $args[$key] = $value;
            }
        }

        return self::curl_query(self::API_V4, 'stat', $args);
    }

    /**
     * @param array $args
     * @return mixed|void
     */
    public static function getCategoriesOasis(array $args = [])
    {
        return self::curl_query(self::API_V4, 'categories', $args);
    }

    /**
     * @param array $args
     * @return mixed|void
     */
    public static function getCurrenciesOasis(array $args = [])
    {
        return self::curl_query(self::API_V4, 'currencies', $args);
    }

    /**
     * @param array $args
     * @return mixed|void
     */
    public static function getBrandsOasis(array $args = [])
    {
        return self::curl_query(self::API_V3, 'brands', $args);
    }

    /**
     * @return mixed|void
     */
    public static function getStock()
    {
        return self::curl_query(self::API_V4, 'stock', ['fields' => 'id,stock']);
    }

    /**
     * @param $version
     * @param $type
     * @param array $args
     * @param bool $sleep
     * @return mixed|void
     */
    public static function curl_query($version, $type, array $args = [], bool $sleep = true)
    {
        $args = array_merge([
            'key'    => API_KEY,
            'format' => 'json',
        ], $args);

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.oasiscatalog.com/' . $version . $type . '?' . http_build_query($args));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($ch);

            if ($content === false) {
                throw new Exception('Error: ' . curl_error($ch));
            } else {
                $result = json_decode($content);
            }

            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 401) {
                throw new Exception('Error Unauthorized. Invalid API key!');
            } elseif ($http_code != 200) {
                throw new Exception('Error. Code: ' . $http_code);
            }

            if ($sleep) {
                sleep(1);
            }
        } catch (Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            die();
        }

        return $result;
    }
}
