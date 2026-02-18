<?php

namespace Opencart\Admin\Controller\Extension\Oasis;

use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use Opencart\Admin\Controller\Extension\Oasis\Main;
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

		$products = self::curl_query(self::API_PRODUCTS, $args);

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
	 * Get products oasis
	 * @param array $args
	 * @return array
	 */
	public static function getProductsOasis(array $args = []): array
	{
		$fields = 'id,article,group_id,parent_size_id'
					. ',is_deleted,is_stopped'
					. ',name,full_name,description,defect'
					. ',total_stock'
					. ',brand_id'
					. ',size,colors,rating'
					. ',price,old_price'
					. ',attributes,categories,images'
					. ',updated_at,images_updated_at';

		if (self::$cf->is_price_dealer) $fields .= ',discount_price';

		$default = [
			'format'       => 'json',
			'fields'       => $fields,
			'not_on_order' => self::$cf->is_not_on_order,
			'currency'     => self::$cf->currency,
			'no_vat'       => self::$cf->is_no_vat,
			'price_from'   => self::$cf->price_from,
			'price_to'     => self::$cf->price_to,
			'rating'       => self::$cf->rating,
			'moscow'       => self::$cf->is_wh_moscow,
			'europe'       => self::$cf->is_wh_europe,
			'remote'       => self::$cf->is_wh_remote,
		];
		foreach ($default as $key => $value) {
			if ($value && empty($args[$key])) {
				$args[$key] = $value;
			}
		}
		$products = self::curl_query(self::API_PRODUCTS, $args);

		if (!empty($products) && Main::arrayKeysExists($args, ['limit', 'ids', 'articles'])) {
			unset($args['limit'], $args['offset'], $args['ids'], $args['articles']);

			$group_ids = [$products[array_key_first($products)]->group_id];

			if (count($products) > 1) {
				$group_ids[] = $products[array_key_last($products)]->group_id;
			}

			$args['group_id'] = implode( ',', array_unique($group_ids));
			$addProducts = self::curl_query(self::API_PRODUCTS, $args);

			foreach ($addProducts as $addProduct) {
				if (!Main::findItem($products, fn($item) => $item->id == $addProduct->id)) {
					$products[] = $addProduct;
				}
			}
		}
		return $products;
	}

	/**
	 * Get product oasis
	 * @param $id
	 * @return array
	 */
	public static function getProductOasis($id): array
	{
		return self::getProductsOasis([
			'ids' => strval($id)
		]);
	}

	/**
	 * Get oasis stat
	 *
	 * @return mixed|void
	 */
	public static function getStatProducts()
	{
		$args = [
			'not_on_order'  => self::$cf->is_not_on_order,
			'price_from'    => self::$cf->price_from,
			'price_to'      => self::$cf->price_to,
			'rating'        => self::$cf->rating,
			'moscow'        => self::$cf->is_wh_moscow,
			'europe'        => self::$cf->is_wh_europe,
			'remote'        => self::$cf->is_wh_remote,
			'category'      => implode(',', self::$cf->categories ?: Main::getOasisMainCategories())
		];
		foreach ($args as $key => $value) {
			if (empty($value)) {
				unset($args[$key]);
			}
		}

		return self::curl_query('stat', $args);
	}

	/**
	 * @param array $args
	 * @return mixed|void
	 */
	public static function getCategoriesOasis(string $fields = '')
	{
		return self::curl_query('categories', ['fields' => $fields ?? 'id,parent_id,root,level,slug,name,path']);
	}

	/**
	 * @param array $args
	 * @return mixed|void
	 */
	public static function getCurrenciesOasis(array $args = [])
	{
		return self::curl_query('currencies', $args);
	}

	/**
	 * @param array $args
	 * @return mixed|void
	 */
	public static function getBrandsOasis(array $args = [])
	{
		return self::curl_query('brands', $args, 'v3');
	}

	/**
	 * @return mixed|void
	 */
	public static function getStock()
	{
		return self::curl_query('stock', ['fields' => 'id,stock,stock-remote']);
	}

	/**
	 * @param array $data
	 * @param array $params
	 * @return array|mixed
	 */
	public static function brandingCalc($data, $params) {
		return self::curl_send('branding/calc', $data, $params);
	}

	/**
	 * @param $id
	 * @param $admin
	 * @return array|mixed
	 */
	public static function getBrandingCoef($id, $admin = false) {
		return self::curl_query('branding/coef', ['id' => $id ]);
	}

	/**
	 * @param $type
	 * @param array $args
	 * @param $version
	 * @param bool $sleep
	 * @return mixed|void
	 */
	public static function curl_query($type, array $args = [], string $version = 'v4', bool $sleep = true)
	{
		$args = array_merge([
			'key'    => self::$cf->api_key,
			'format' => 'json',
		], $args);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, "https://api.oasiscatalog.com/{$version}/{$type}?" . http_build_query($args));
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

		return $result;
	}

	/**
	 * Send data by POST method
	 * @param string $type
	 * @param array $data
	 * @param array $params
	 * @param $version
	 * @return array|mixed
	 */
	public static function curl_send(string $type, array $data, array $params = [], string $version = 'v4') {
		$args_pref = [
			'key'    => self::$cf->api_key,
			'format' => 'json',
		];

		try {
			$ch = curl_init("https://api.oasiscatalog.com/{$version}/{$type}?" . http_build_query($args_pref));
			curl_setopt_array($ch, [
				CURLOPT_POST			=> 1,
				CURLOPT_POSTFIELDS		=> json_encode($data),
				CURLOPT_HTTPHEADER		=> ['Content-Type: application/json'],
				CURLOPT_RETURNTRANSFER	=> true,
				CURLOPT_SSL_VERIFYPEER	=> false,
				CURLOPT_HEADER			=> false,
				CURLOPT_TIMEOUT			=> $params['timeout'] ?? 0
			]);
			$content = curl_exec($ch);

			if ($content === false) {
				throw new Exception('Error: ' . curl_error($ch));
			} else {
				$result = json_decode($content, true);
			}

			$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			if ($http_code === 401) {
				throw new Exception('Error Unauthorized. Invalid API key!');
			} elseif ($http_code != 200 && $http_code != 500) {
				throw new Exception('Error: ' . ($result->error ?? '') . PHP_EOL . 'Code: ' . $http_code);
			}
		} catch (Exception $e) {
			throw new Exception('Error: ' . $e->getMessage());
		}

		return $result;
	}
}