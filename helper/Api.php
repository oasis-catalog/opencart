<?php

namespace Opencart\Admin\Controller\Extension\Oasis;

use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use Exception;


class Api {
    public static OasisConfig $cf;

    private const API_V4 = 'v4/';
    private const API_V3 = 'v3/';
    private const API_PRODUCTS = 'products';

    /**
     * @param array $IDS
     * @return array
     */
    public static function getProductsOasisOnlyFieldCategories(array $IDS = []): array {
        $result = [];
        $args = [
            'fields' => 'id,categories',
            'strict' => true,
        ];

        $products = self::curl_query(self::API_V4, self::API_PRODUCTS, $args);

        if (!empty($IDS)) {
            if (!empty($products)) {
                foreach ($products as $product) {
                    if (in_array($product->id, $IDS)) {
                        $result[] = (object)[
                            'id'         => $product->id,
                            'categories' => $product->categories,
                        ];
                    }
                }
            }
        } else {
            foreach ($products as $product) {
                unset($product->included_branding, $product->full_categories);
            }

            $result = $products;
        }

        return $result;
    }

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
    public static function getStatProducts($categories)
    {
        $args = [
            'showDeleted'   => 1,
            'not_on_order'  => self::$cf->is_not_on_order,
            'price_from'    => self::$cf->price_from,
            'price_to'      => self::$cf->price_to,
            'rating'        => self::$cf->rating,
            'moscow'        => self::$cf->is_wh_moscow,
            'europe'        => self::$cf->is_wh_europe,
            'remote'        => self::$cf->is_wh_remote,
            'category'      => implode(',', empty(self::$cf->categories) ? Main::getOasisMainCategories( $categories ) : self::$cf->categories)
        ];
        foreach ($args as $key => $value) {
            if (empty($value)) {
                unset($args[$key]);
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
            'key'    => self::$cf->api_key,
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
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
            return [];
        }

        return $result;
    }
}
