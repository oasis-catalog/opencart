<?php

namespace Opencart\Admin\Controller\Extension\Oasis;

use JetBrains\PhpStorm\NoReturn;
use \Opencart\System\Engine\Registry;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use Exception;


class Main
{
	public static OasisConfig $cf;
	private Registry $registry;

	public array $cats_oasis = [];
	public string $var_size = 'Размер';

	public function __construct(Registry $registry)
	{
		$this->registry = $registry;
	}

	/**
	 * @param array $data
	 * @param object $product
	 * @return int
	 * @throws Exception
	 */
	public function checkProduct(array $data, object $product): int
	{
		$product_oc = $this->registry->model_catalog_product->getProducts(['filter_model' => $product->article]);

		if (!$product_oc) {
			$product_id = $this->addProduct($data, $product);
			self::$cf->log('OAId='.$product->id.' add OCId=' . $product_id);
		} else {
			$this->editProduct($product_oc[0], $product, $data['product_option'] ?? []);
			$product_id = intval($product_oc[0]['product_id']);
		}

		return $product_id;
	}

	/**
	 * Check and delete product
	 *
	 * @param string $product_id_oasis
	 * @return void
	 */
	public function checkDeleteProduct(string $product_id_oasis): void
	{
		$product = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProduct($product_id_oasis);

		if (!empty($product)) {
			$this->deleteImgInProduct($this->registry->model_catalog_product->getImages(intval($product['product_id'])));
			$this->registry->model_catalog_product->deleteProduct(intval($product['product_id']));
			$this->registry->db->query("DELETE FROM `" . DB_PREFIX . "oasis_product` WHERE product_id_oasis = '" . $this->registry->db->escape($product_id_oasis) . "'");
			self::$cf->log('OAId='.$product_id_oasis.' delete OCId=' . $product['product_id']);
		}
	}

	/**
	 * @param array $product_info
	 * @param object $product_oasis
	 * @param array $product_option
	 * @return bool
	 * @throws Exception
	 */
	public function editProduct(array $product_info, object $product_oasis, array $product_option = []): bool
	{
		$date_modified = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProductDateModified($product_oasis->id);

		$data = $product_info;
		$data['product_option'] = $this->registry->model_catalog_product->getOptions(intval($product_info['product_id']));

		$option_value_id = 0;
		if ($product_option) {
			$option_value_id = $product_option[0]['product_option_value'][0]['option_value_id'];

			$price = $this->getCalculationPrice($product_oasis);

			if ($data['model'] == $product_oasis->article) {
				$data['price'] = $price;
			}

			if ((float)$data['price'] < $price) {
				$product_option[0]['product_option_value'][0]['price'] = $price - (float)$data['price'];
			} elseif ((float)$data['price'] > $price) {
				$product_option[0]['product_option_value'][0]['price'] = (float)$data['price'] - $price;
				$product_option[0]['product_option_value'][0]['price_prefix'] = '-';
			}
			unset($price);

			if ($data['product_option']) {
				foreach ($data['product_option'][0]['product_option_value'] as $key => $value) {
					if ($value['option_value_id'] === $product_option[0]['product_option_value'][0]['option_value_id']) {
						$data['product_option'][0]['product_option_value'][$key]['quantity'] = $product_option[0]['product_option_value'][0]['quantity'];
					}
				}
				unset($key, $value);

				foreach ($data['product_option'][0]['product_option_value'] as $key => $value) {
					if ($value['option_value_id'] === $product_option[0]['product_option_value'][0]['option_value_id']) {
						$data['product_option'][0]['product_option_value'][$key] = $product_option[0]['product_option_value'][0];
					}
				}

				$key_option = in_array($product_option[0]['product_option_value'][0]['option_value_id'], array_column($data['product_option'][0]['product_option_value'], 'option_value_id'));

				if ($key_option === false) {
					$data['product_option'][0]['product_option_value'][] = $product_option[0]['product_option_value'][0];
				}
				unset($key_option);
			} else {
				$data['product_option'] = $product_option;
			}
		}

		$manufacturer_info = $this->registry->model_catalog_manufacturer->getManufacturer(intval($product_info['manufacturer_id']));

		if ($manufacturer_info) {
			$data['manufacturer'] = $manufacturer_info['name'];
		}
		$data['product_category'] = self::$cf->is_not_up_cat ? $this->registry->model_catalog_product->getCategories($product_info['product_id']) :
															$this->getProductCategories($product_oasis->categories);


		$productImages = $this->registry->model_catalog_product->getImages(intval($product_info['product_id']));

		if (self::$cf->is_up_photo && $this->checkImages($product_oasis->images, $productImages) === false) {
			$this->deleteImgInProduct($productImages);
			$data['product_image'] = $this->prepareImagesProduct($product_oasis->images, end($data['product_category']));

			if (!empty($data['product_image'])) {
				$data['image'] = $data['product_image'][0]['image'];
			}
		} else {
			$data['product_image'] = [];

			foreach ($productImages as $key => $value) {
				$data['product_image'][$key] = [
					'image'      => $value['image'],
					'sort_order' => $value['sort_order'],
				];
			}
			unset($key, $value);
		}

		$product_data = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProduct($product_oasis->group_id);
		if ($product_data) {
			$product_related = $this->registry->model_catalog_product->getRelated(intval($product_data['product_id']));

			if ($product_oasis->group_id !== $product_oasis->id && $product_info['product_id'] !== $product_data['product_id']) {
				$product_related[] = $product_data['product_id'];
			}
			$data['product_related'] = $product_related;
		}

		$arr_product = $this->setProduct($data, $product_oasis, intval($option_value_id));
		$this->registry->model_catalog_product->editProduct(intval($product_info['product_id']), $arr_product);

		if ($product_option) {
			$product_option_value = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValueId(intval($product_info['product_id']), intval($product_option[0]['product_option_value'][0]['option_value_id']));
		}

		$args = [
			'rating'          => $product_oasis->rating,
			'option_value_id' => $product_option_value['product_option_value_id'] ?? '',
			'product_id'      => $product_info['product_id'],
		];

		if (empty($date_modified)) {
			$args['product_id_oasis'] = $product_oasis->id;
			$this->registry->model_extension_oasiscatalog_module_oasis->addOasisProduct($args);
		} else {
			$this->registry->model_extension_oasiscatalog_module_oasis->editOasisProduct($product_oasis->id, $args);
		}
		self::$cf->log('OAId='.$product_oasis->id.' updated OCId=' . $product_info['product_id']);
		return true;
	}

	/**
	 * @param array $data
	 * @param object $product
	 * @return integer
	 * @throws Exception
	 */
	public function addProduct(array $data, object $product): int
	{
		$data['product_category'] = $this->getProductCategories($product->categories);

		if (!is_null($product->brand_id)) {
			$data['manufacturer_id'] = $this->addBrand($product->brand_id);
		}

		$data['product_image'] = $this->prepareImagesProduct($product->images, end($data['product_category']));

		if (!empty($data['product_image'])) {
			$data['image'] = $data['product_image'][0]['image'];
		}

		$product_id = $this->registry->model_catalog_product->addProduct($this->setProduct($data, $product));

		if (!empty($data['product_option'])) {
			$product_option_value = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValueId($product_id, $data['product_option'][0]['product_option_value'][0]['option_value_id']);
		}

		$args = [
			'product_id_oasis' => $product->id,
			'rating'           => $product->rating,
			'option_value_id'  => $product_option_value['product_option_value_id'] ?? '',
			'product_id'       => $product_id,
		];
		$this->registry->model_extension_oasiscatalog_module_oasis->addOasisProduct($args);

		return $product_id;
	}

	/**
	 * @param array $data
	 * @param object $product_o
	 * @param int $option_value_id
	 * @return array
	 * @throws Exception
	 */
	public function setProduct(array $data, object $product_o, int $option_value_id = 0): array
	{
		$languages = $this->registry->model_localisation_language->getLanguages();

		if (empty($data['model']) || $data['model'] === $product_o->article) {
			$product['product_description'] = [];

			foreach ($languages as $language) {
				$product['product_description'][$language['language_id']] = [
					'name'             => htmlspecialchars($product_o->full_name, ENT_QUOTES),
					'description'      => htmlspecialchars('<p>' . nl2br($product_o->description) . '</p>', ENT_QUOTES),
					'meta_title'       => htmlspecialchars($product_o->full_name, ENT_QUOTES),
					'meta_description' => '',
					'meta_keyword'     => '',
					'tag'              => '',
				];
			}
			unset($language);
		} else {
			$product_description = $this->registry->model_catalog_product->getProduct(intval($data['product_id']));
			foreach ($languages as $language) {
				$product['product_description'][$language['language_id']] = [
					'name'             => $product_description['name'] ?? '',
					'description'      => $product_description['description'] ?? '',
					'meta_title'       => $product_description['meta_title'] ?? '',
					'meta_description' => '',
					'meta_keyword'     => '',
					'tag'              => '',
				];
			}
		}

		$product['master_id'] = 0;
		$product['price'] = $this->getCalculationPrice($product_o);
		$product['model'] = $data['model'] ?? htmlspecialchars($product_o->article, ENT_QUOTES);
		$product['sku'] = $data['sku'] ?? '';
		$product['upc'] = $data['upc'] ?? '';
		$product['ean'] = $data['ean'] ?? '';
		$product['jan'] = $data['jan'] ?? '';
		$product['isbn'] = $data['isbn'] ?? '';
		$product['mpn'] = $data['mpn'] ?? '';
		$product['location'] = $data['location'] ?? '';
		$product['tax_class_id'] = $data['tax_class_id'] ?? '0';
		$product['minimum'] = $data['minimum'] ?? 1;
		$product['subtract'] = $data['subtract'] ?? 1;
		$product['stock_status_id'] = $data['stock_status_id'] ?? '0';
		$product['shipping'] = $data['shipping'] ?? 1;
		$product['date_available'] = $data['date_available'] ?? date('Y-m-d');
		$product['length'] = $data['length'] ?? '';
		$product['width'] = $data['width'] ?? '';
		$product['height'] = $data['height'] ?? '';
		$product['length_class_id'] = $data['length_class_id'] ?? 1;
		$product['weight'] = $data['weight'] ?? '';
		$product['weight_class_id'] = $data['weight_class_id'] ?? 1;
		$product['sort_order'] = $data['sort_order'] ?? 1;
		$product['manufacturer'] = $data['manufacturer'] ?? '';
		$product['manufacturer_id'] = $data['manufacturer_id'] ?? '0';
		$product['category'] = $data['category'] ?? '';

		if (isset($data['product_category'])) {
			$product['product_category'] = $data['product_category'];
		}

		$product['filter'] = $data['filter'] ?? '';
		$product['product_store'] = $data['product_store'] ?? $this->getStores();
		$product['download'] = $data['download'] ?? '';
		$product['related'] = $data['related'] ?? '';

		if (!empty($data['product_related'])) {
			$product['product_related'] = $data['product_related'];
		}

		$product['product_attribute'] = $this->addAttributes($product_o->attributes);
		$product['option'] = $data['option'] ?? '';

		if (!empty($data['product_option'])) {
			$product['product_option'] = $data['product_option'];
			$product['quantity'] = array_sum(array_column($data['product_option'][0]['product_option_value'], 'quantity'));
		} else {
			$product['quantity'] = $product_o->total_stock;
		}

		if ($product_o->rating === 5) {
			if ($option_value_id) {
				foreach ($product['product_option'][0]['product_option_value'] as $key => $value) {
					if ($value['option_value_id'] == $option_value_id) {
						$product['product_option'][0]['product_option_value'][$key]['quantity'] = 1000000;
					}
				}
			}
			$product['quantity'] = 1000000;
		}

		if ($product['quantity'] > 0 || $product_o->rating === 5) {
			$product['status'] = 1;
		} else {
			$product['status'] = 0;
		}

		$product['image'] = $data['image'] ?? '';

		if (isset($data['product_image'])) {
			$product['product_image'] = $data['product_image'];
		}

		$product['points'] = $data['points'] ?? '';
		$product['product_reward'] = $data['product_reward'] ?? [1 => ['points' => '']];
		$product['product_seo_url'] = $data['product_seo_url'] ?? $this->getSeoUrl($this->getStores(), $this->transliter($product_o->full_name));
		$product['product_layout'] = $data['product_layout'] ?? [0 => ''];

		return $product;
	}

	/**
	 * Get calculation price product
	 *
	 * @param object $product
	 * @return float
	 */
	public function getCalculationPrice(object $product): float
	{
		$price = self::$cf->is_price_dealer ? $product->discount_price : $product->price;

		if (!empty(self::$cf->price_factor)) {
			$price = $price * self::$cf->price_factor;
		}

		if (!empty(self::$cf->price_increase)) {
			$price = $price + self::$cf->price_increase;
		}

		return (float)$price;
	}

	/**
	 * @param array $categories
	 * @return array
	 * @throws Exception
	 */
	public function getProductCategories(array $categories): array
	{
		$result = [];
		foreach ($categories as $category) {
			$rel_id = self::$cf->getRelCategoryId($category);
			if(isset($rel_id)){
				$parents = $this->getCategoryParents($rel_id);
				$result = array_merge($result, array_map(fn($x) => $x['category_id'], $parents));
			}
			else{
				$full_categories = $this->getOasisParentsCategoriesId($category);

				foreach ($full_categories as $categoryId) {
					$result[] = $this->getCategoryId($categoryId);
				}
			}
		}
		return $result;
	}

	public function getCategoryParents($cat_id): array {
		$list = [];
		while($cat_id != 0){
			$category = $this->registry->model_catalog_category->getCategory($cat_id);
			$list []= $category;
			$cat_id = $category['parent_id'];
		}
		return array_reverse($list);
	}

	/**
	 * Get oasis parents id categories
	 *
	 * @param null $cat_id
	 *
	 * @return array
	 */
	public function getOasisParentsCategoriesId($cat_id): array {
		$result = [];
		$parent_id = $cat_id;

		while($parent_id){
			foreach ($this->cats_oasis as $category) {
				if ($parent_id == $category->id) {
					array_unshift($result, $category->id);
					$parent_id = $category->parent_id;
					continue 2;
				}
			}
			break;
		}
		return $result;
	}

	public function getCategoryId(int $cat_id): int {
		$category_id_oc = $this->getIdCategoryByOasisId($cat_id);

		if (!empty($category_id_oc)) {
			$exist_category_oc = $this->registry->model_extension_oasiscatalog_module_oasis->getCategory($category_id_oc);
			if (!$exist_category_oc) {
				$this->registry->model_extension_oasiscatalog_module_oasis->deleteOasisCategory($category_id_oc);
				$category_id_oc = 0;
			}
			unset($exist_category_oc);
		}

		if (!$category_id_oc) {
			$category_id_oc = $this->addCategory($cat_id);
		}

		return $category_id_oc;
	}

	/**
	 * @param int $id
	 * @return int
	 * @throws Exception
	 */
	public function addCategory(int $id): int
	{
		$category = self::searchObject($this->cats_oasis, $id);

		if (!$category) {
			return 0;
		}

		$category_id_oc = $this->getIdCategoryByOasisId(intval($category->id));

		if ($category_id_oc) {
			return $category_id_oc;
		}

		$languages = $this->registry->model_localisation_language->getLanguages();
		$data['category_description'] = [];

		foreach ($languages as $language) {
			$data['category_description'][$language['language_id']] = [
				'name'             => $category->name,
				'description'      => '',
				'meta_title'       => $category->name,
				'meta_description' => '',
				'meta_keyword'     => '',
			];
		}
		unset($language);

		$data['path'] = '';
		$data['parent_id'] = 0;

		if (!is_null($category->parent_id)) {
			$parent_category_id = $this->getIdCategoryByOasisId(intval(self::searchObject($this->cats_oasis, $category->parent_id)->id));

			if ($parent_category_id) {
				$data['parent_id'] = $parent_category_id;
			} else {
				$data['parent_id'] = $this->addCategory($category->parent_id);
			}
		}

		$data['filter'] = '';
		$data['category_store'] = $this->getStores();
		$data['image'] = '';
		$data['column'] = 1;
		$data['sort_order'] = 0;
		$data['status'] = true;
		$data['category_seo_url'] = $this->getSeoUrl($data['category_store'], $category->slug);
		$data['category_layout'] = [0 => ''];

		$category_id = $this->registry->model_catalog_category->addCategory($data);
		$this->registry->model_extension_oasiscatalog_module_oasis->addOasisCategory([
			'oasis_id'    => $category->id,
			'category_id' => $category_id,
		]);

		return $category_id;
	}

	/**
	 * @param array $attributes
	 * @return array
	 * @throws Exception
	 */
	public function addAttributes(array $attributes): array
	{
		$languages = $this->registry->model_localisation_language->getLanguages();
		$result = [];

		foreach ($attributes as $attribute) {
			$name = $attribute->name;
			if ($name !== $this->var_size) {
				$neededAttribute = [];

				$attributes_store = $this->registry->model_catalog_attribute->getAttributes();
				$neededAttribute = array_filter($attributes_store, function ($e) use ($name) {
					return $e['name'] == $name;
				});

				if ($neededAttribute) {
					$attr = array_shift($neededAttribute);

					$key_attr = array_search($attr['name'], array_column($result, 'name'));

					if ($key_attr !== false) {
						foreach ($result[$key_attr]['product_attribute_description'] as $key => $value) {
							$result[$key_attr]['product_attribute_description'][$key]['text'] .= ', ' . $attribute->value;
						}
						unset($key, $value);
					} else {
						$result[] = [
							'name'                          => $attr['name'],
							'attribute_id'                  => $attr['attribute_id'],
							'product_attribute_description' => $this->toLanguagesArr($languages, 'text', (string)$attribute->value),
						];
					}
				} else {
					$data_attribute['attribute_description'] = $this->toLanguagesArr($languages, 'name', (string)$attribute->name);
					$data_attribute['attribute_group_id'] = $this->getAttributeGroupId($languages);
					$data_attribute['sort_order'] = '';

					$result[] = [
						'name'                          => $attribute->name,
						'attribute_id'                  => $this->registry->model_catalog_attribute->addAttribute($data_attribute),
						'product_attribute_description' => $this->toLanguagesArr($languages, 'text', (string)$attribute->value),
					];
				}
				unset($attr, $key_attr, $data_attribute);
			}
		}
		unset($attribute);

		return $result;
	}

	/**
	 * @param string $id
	 * @return int
	 * @throws Exception
	 */
	public function addBrand(string $id): int
	{
		$brand = self::searchObject(Api::getBrandsOasis(), $id);

		if (!$brand) {
			return 0;
		}

		$manufacture_id_oc = $this->getManufacturerIdByKeyword($brand->slug);

		if ($manufacture_id_oc) {
			return $manufacture_id_oc;
		}

		$data['name'] = $brand->name;
		$data['manufacturer_store'] = $this->getStores();
		$data['sort_order'] = '';
		$data['manufacturer_seo_url'] = $this->getSeoUrl($data['manufacturer_store'], $brand->slug);

		if (!empty($brand->logotype)) {
			$data_img = [
				'folder' => 'catalog/oasis/manufacturers',
				'source' => $brand->logotype,
			];

			$data['image'] = $this->saveImg($data_img);
		} else {
			$data['image'] = '';
		}

		return $this->registry->model_catalog_manufacturer->addManufacturer($data);
	}

	/**
	 * @param array $option
	 * @return int
	 */
	public function addOption(array $option): int
	{
		$languages = $this->registry->model_localisation_language->getLanguages();
		$data['option_description'] = $this->toLanguagesArr($languages, 'name', (string)$option['name']);
		$data['type'] = 'radio';
		$data['sort_order'] = '';

		foreach ($option['value'] as $item) {
			$data['option_value'][] = [
				'option_value_id'          => '',
				'option_value_description' => $this->toLanguagesArr($languages, 'name', (string)$item),
				'image'                    => '',
				'sort_order'               => '',
			];
		}
		unset($item);

		return $this->registry->model_catalog_option->addOption($data);
	}

	/**
	 * @param int $option_id
	 * @param string $value
	 * @return void
	 */
	public function editOption(int $option_id, string $value): void
	{
		$data['option_description'] = $this->registry->model_catalog_option->getDescriptions($option_id);
		$data['type'] = 'radio';
		$data['sort_order'] = '';
		$option_values = $this->registry->model_catalog_option->getValueDescriptions($option_id);
		$languages = $this->registry->model_localisation_language->getLanguages();

		$option_values[] = [
			'option_value_id'          => '',
			'option_value_description' => $this->toLanguagesArr($languages, 'name', $value),
			'image'                    => '',
			'sort_order'               => '',
		];

		$data['option_value'] = $option_values;
		$this->registry->model_catalog_option->editOption($option_id, $data);
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function setOption(array $data): array
	{
		$option[0] = [
			'product_option_id' => '',
			'name'              => $data['option']['name'],
			'option_id'         => $data['option']['option_id'],
			'type'              => $data['option']['type'],
			'required'          => 1,
		];

		$option[0]['product_option_value'] = [];

		foreach ($data['values'] as $value) {
			$option[0]['product_option_value'][] = [
				'option_value_id'         => $value['option_value_id'],
				'product_option_value_id' => '',
				'quantity'                => $value['quantity'],
				'subtract'                => 1,
				'price_prefix'            => '+',
				'price'                   => '',
				'points_prefix'           => '+',
				'points'                  => '',
				'weight_prefix'           => '+',
				'weight'                  => '',
			];
		}
		unset($value);

		return $option;
	}

	/**
	 * @param string $option_name
	 * @param string $value
	 * @param int $quantity
	 * @return array
	 * @throws Exception
	 */
	public function getOption(string $option_name, string $value, int $quantity): array
	{
		$data['option'] = $this->registry->model_catalog_option->getOptions(['filter_name' => $option_name]);

		if (!$data['option']) {
			$opt['name'] = $option_name;
			$opt['value'][] = $value;
			$data['option'] = $this->registry->model_catalog_option->getOption($this->addOption($opt));
		} else {
			$data['option'] = $data['option'][0];
		}
		unset($opt);

		$values = $this->getOptionValue(intval($data['option']['option_id']), $value);

		if (!$values) {
			$this->editOption(intval($data['option']['option_id']), $value);

			$values = $this->getOptionValue(intval($data['option']['option_id']), $value);
		}

		$values['quantity'] = $quantity;
		$data['values'][] = $values;

		return $data;
	}

	/**
	 * @param int $option_id
	 * @param string $needle
	 * @return array
	 */
	public function getOptionValue(int $option_id, string $needle): array
	{
		$option_values = $this->registry->model_catalog_option->getValues($option_id);
		$key = array_search($needle, array_column($option_values, 'name'));

		return $key !== false ? $option_values[$key] : [];
	}

	/**
	 * @param array $languages
	 * @return int
	 */
	public function getAttributeGroupId(array $languages): int
	{
		$attribute_groups = $this->registry->model_catalog_attribute_group->getAttributeGroups();
		$name = 'Характеристики';
		$key = array_search($name, array_column($attribute_groups, 'name'));

		if ($key !== false) {
			$attribute_group_id = intval($attribute_groups[$key]['attribute_group_id']);
		} else {
			$data_attribute_group = [];

			foreach ($languages as $language) {
				$data_attribute_group['attribute_group_description'][$language['language_id']] = [
					'name' => $name,
				];
			}
			unset($language);

			$data_attribute_group['sort_order'] = '';
			$attribute_group_id = $this->registry->model_catalog_attribute_group->addAttributeGroup($data_attribute_group);
		}

		return $attribute_group_id;
	}

	/**
	 * @param array $stores
	 * @param string $slug
	 * @return array
	 */
	public function getSeoUrl(array $stores, string $slug): array
	{
		$data = [];
		$languages = $this->registry->model_localisation_language->getLanguages();

		foreach ($stores as $store) {
			$i = 0;
			$postfix = '';
			foreach ($languages as $language) {
				if ($i > 0) {
					$postfix = '-' . $i;
				}
				$data[$store][$language['language_id']] = $slug . $postfix;
				$i++;
			}
			unset($language);
		}
		unset($store);

		return $data;
	}

	/**
	 * @return array
	 * @throws Exception
	 */
	public function getStores(): array
	{
		$data = [];
		$stores = $this->registry->model_setting_store->getStores();

		if ($stores) {
			foreach ($stores as $store) {
				$data[] = $store['store_id'];
			}
			unset($store);
		} else {
			$data = [0];
		}

		return $data;
	}

	public function getIdCategoryByOasisId(int $id): int
	{
		$result = $this->registry->model_extension_oasiscatalog_module_oasis->getIdOcCategory($id);

		return $result ? intval($result['category_id']) : 0;
	}

	/**
	 * @param string $keyword
	 * @return integer
	 */
	public function getManufacturerIdByKeyword(string $keyword): int
	{
		$result = $this->registry->model_extension_oasiscatalog_module_oasis->getSeoUrls([
			'keyword' => $keyword,
			'key'     => 'manufacturer_id',
		]);

		return $result ? intval($result['value']) : 0;
	}

	/**
	 * Get oasis main categories - level = 1
	 *
	 * @param null $categories
	 * @return array
	 */
	public static function getOasisMainCategories($categories = null): array
	{
		$result = [];

		if (!$categories) {
			$categories = Api::getCategoriesOasis();
		}

		foreach ($categories as $category) {
			if ($category->level === 1) {
				$result[$category->id] = $category->name;
			}
		}

		return $result;
	}

	/**
	 * @param array $data
	 * @param $id
	 * @return mixed
	 */
	public static function searchObject(array $data, $id): mixed
	{
		$neededObject = array_filter($data, function ($e) use ($id) {
			return $e->id == $id;
		});

		if (!$neededObject) {
			return false;
		}

		return array_shift($neededObject);
	}

	/**
	 * @param array $languages
	 * @param string $key
	 * @param string $value
	 * @return array
	 */
	public function toLanguagesArr(array $languages, string $key, string $value): array
	{
		$result = [];

		foreach ($languages as $language) {
			$result[$language['language_id']] = [
				$key => $value,
			];
		}
		unset($language);

		return $result;
	}

	/**
	 * Build tree categories
	 *
	 * @param $data
	 * @param array $checkedArr
	 * @param array $relCategories
	 * @param int $parent_id
	 *
	 * @return string
	 */
	public static function buildTreeCats( $data, array $checkedArr = [], array $relCategories = [], int $parent_id = 0 ): string {
		$treeItem = '';
		if ( ! empty( $data[ $parent_id ] ) ) {
			foreach($data[ $parent_id ] as $item){
				$checked = in_array( $item['id'], $checkedArr ) ? ' checked' : '';

				$rel_cat = $relCategories[$item['id']] ?? null;
				$rel_label = '';
				$rel_value = '';
				if($rel_cat){
					$rel_value = $item['id'].'_'.$rel_cat['id'];
					$rel_label = $rel_cat['rel_label'];
				}

				$treeItemChilds = self::buildTreeCats( $data, $checkedArr, $relCategories, $item['id'] );

				if(empty($treeItemChilds)){
					$treeItem .= '<div class="oa-tree-leaf">
						<div class="oa-tree-label ' . ($rel_value ? 'relation-active' : '') . '">
							<input type="hidden" class="oa-tree-inp-rel" name="categories_rel[]" value="' . $rel_value . '" />
							<label>
								<input type="checkbox" class="oa-tree-cb-cat" name="categories[]" value="' . $item['id'] . '"' . $checked . '/>
								<div class="oa-tree-btn-relation"></div>' . $item['name'] . '
							</label>
							<div class="oa-tree-dashed"></div>
							<div class="oa-tree-relation">' . $rel_label . '</div>
						</div>
					</div>';
				}
				else{
					$treeItem .= '<div class="oa-tree-node oa-tree-collapsed">
						<div class="oa-tree-label ' . ($rel_value ? 'relation-active' : '') . '">
							<input type="hidden" class="oa-tree-inp-rel"  name="categories_rel[]" value="' . $rel_value . '" />
							<span class="oa-tree-handle-p">+</span>
							<span class="oa-tree-handle-m">-</span>
							<label>
								<input type="checkbox" class="oa-tree-cb-cat" name="categories[]" value="' . $item['id'] . '"' . $checked . '/>
								<div class="oa-tree-btn-relation"></div>' . $item['name'] . '
							</label>
							<div class="oa-tree-dashed"></div>
							<div class="oa-tree-relation">' . $rel_label . '</div>
						</div>
						<div class="oa-tree-childs">' . $treeItemChilds . '</div>
					</div>';
				}
			}
		}

		return $treeItem ?? '';
	}

	/**
	 * Build tree categories
	 *
	 * @param $data
	 * @param int $checked_id
	 * @param int $parent_id
	 *
	 * @return string
	 */
	public static function buildTreeRadioCats( $data, array $checked_id = null, int $parent_id = 0 ): string {
		$treeItem = '';
		if ( ! empty( $data[ $parent_id ] ) ) {
			foreach($data[ $parent_id ] as $item){
				$checked = $checked_id === $item['id'];

				$treeItemChilds = self::buildTreeRadioCats( $data, $checked_id, $item['id'] );

				if(empty($treeItemChilds)){
					$treeItem .= '<div class="oa-tree-leaf">
						<div class="oa-tree-label">
							<label><input type="radio" name="oasis_radio_tree" value="' . $item['id'] . '"' . $checked . '/>' . $item['name'] . '</label>
						</div>
					</div>';
				}
				else{
					$treeItem .= '<div class="oa-tree-node oa-tree-collapsed">
						<div class="oa-tree-label">
							<span class="oa-tree-handle-p">+</span>
							<span class="oa-tree-handle-m">-</span>
							<label><input type="radio" name="oasis_radio_tree" value="' . $item['id'] . '"' . $checked . '/>' . $item['name'] . '</label>
						</div>
						<div class="oa-tree-childs">' . $treeItemChilds . '</div>
					</div>';
				}
			}
		}

		return $treeItem ?? '';
	}

	/**
	 * Delete images in product
	 *
	 * @param $images
	 * @return void
	 */
	public function deleteImgInProduct($images): void
	{
		foreach ($images as $image) {
			$ext = pathinfo($image['image']);
			$this->registry->model_extension_oasiscatalog_module_oasis->deleteImage($ext['basename']);

			if (file_exists(DIR_IMAGE . $image['image'])) {
				unlink(DIR_IMAGE . $image['image']);
			}
		}
	}

	/**
	 * Checking product images for relevance
	 *
	 * Usage:
	 *
	 * Check is good - true
	 *
	 * Check is bad - false
	 *
	 * @param $images
	 * @param $dbProductImages
	 *
	 * @return bool
	 */
	public function checkImages($images, $dbProductImages): bool
	{
		if (empty($dbProductImages)) {
			return false;
		}

		if (count($images) !== count($dbProductImages)) {
			return false;
		}

		$imgNames = [];

		foreach ($dbProductImages as $dbProductImage) {
			if (!file_exists(DIR_IMAGE . $dbProductImage['image'])) {
				return false;
			}

			$extUrl = pathinfo($dbProductImage['image']);
			$imgNames[] = $extUrl['basename'];
		}

		$dataDbOaImages = $this->registry->model_extension_oasiscatalog_module_oasis->getImages($imgNames);

		if (empty($dataDbOaImages)) {
			return false;
		}

		foreach ($images as $image) {
			if (empty($image->superbig)) {
				return false;
			}

			$keyNeeded = array_search(basename($image->superbig), array_column($dataDbOaImages, 'name'));

			if ($keyNeeded === false || $image->updated_at > intval($dataDbOaImages[$keyNeeded]['date_added'])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Prepare images for product
	 *
	 * @param $images
	 * @param string $subCatalog
	 * @return array
	 */
	public function prepareImagesProduct($images, string $subCatalog = ''): array
	{
		$result = [];

		if (is_array($images)) {
			foreach ($images as $image) {
				if (isset($image->superbig)) {
					$data_img = [
						'folder' => 'catalog/oasis/products/' . $subCatalog,
						'source' => $image->superbig,
					];

					$result[] = [
						'image'      => $this->saveImg($data_img),
						'sort_order' => '',
					];
				}
			}

		}

		return $result;
	}

	/**
	 * Save image
	 *
	 * @param $data
	 * @return string
	 */
	protected function saveImg($data): string
	{
		$ext = pathinfo($data['source']);

		if (!array_key_exists('extension', $ext) || $ext['extension'] === 'tif') {
			return false;
		}

		$imgDb = $this->registry->model_extension_oasiscatalog_module_oasis->getImage(['name' => $ext['basename']]);

		if (empty($imgDb)) {
			$img = $this->getOrCreateDir(DIR_IMAGE . $data['folder'] . '/') . $ext['basename'];

			if (!file_exists($img)) {
				$pic = file_get_contents($data['source'], true, stream_context_create([
					'http' => [
						'ignore_errors'   => true,
						'follow_location' => true
					],
					'ssl'  => [
						'verify_peer'      => false,
						'verify_peer_name' => false,
					],
				]));

				if (!preg_match("/200|301/", $http_response_header[0])) {
					return '';
				}
				file_put_contents($img, $pic);
			}

			$this->registry->model_extension_oasiscatalog_module_oasis->addImage(['name' => $ext['basename'], 'path' => $data['folder'], 'date_added' => time()]);
			$result = $data['folder'] . '/' . $ext['basename'];

		} else {
			if (!file_exists($this->getOrCreateDir(DIR_IMAGE . $imgDb['path'] . '/') . $imgDb['name'])) {
				$this->registry->model_extension_oasiscatalog_module_oasis->deleteImage($imgDb['name']);
				$result = $this->saveImg($data);
			} else {
				$result = $imgDb['path'] . '/' . $imgDb['name'];
			}
		}

		return $result;
	}

	/**
	 * @param string $path
	 * @return string|void
	 */
	public static function getOrCreateDir(string $path)
	{
		try {
			if (!file_exists($path)) {
				$create = mkdir($path, 0755, true);
				if (!$create) {
					throw new Exception('Failed to create directory: ' . $path);
				}
			}

		} catch (Exception $e) {
			echo $e->getMessage() . PHP_EOL;
			exit();
		}

		return $path;
	}

	/**
	 * @param string $str
	 * @return string
	 */
	protected function transliter(string $str): string
	{
		$arr_trans = [
			'А'  => 'A',
			'Б'  => 'B',
			'В'  => 'V',
			'Г'  => 'G',
			'Д'  => 'D',
			'Е'  => 'E',
			'Ё'  => 'E',
			'Ж'  => 'J',
			'З'  => 'Z',
			'И'  => 'I',
			'Й'  => 'Y',
			'К'  => 'K',
			'Л'  => 'L',
			'М'  => 'M',
			'Н'  => 'N',
			'О'  => 'O',
			'П'  => 'P',
			'Р'  => 'R',
			'С'  => 'S',
			'Т'  => 'T',
			'У'  => 'U',
			'Ф'  => 'F',
			'Х'  => 'H',
			'Ц'  => 'TS',
			'Ч'  => 'CH',
			'Ш'  => 'SH',
			'Щ'  => 'SCH',
			'Ъ'  => '',
			'Ы'  => 'YI',
			'Ь'  => '',
			'Э'  => 'E',
			'Ю'  => 'YU',
			'Я'  => 'YA',
			'а'  => 'a',
			'б'  => 'b',
			'в'  => 'v',
			'г'  => 'g',
			'д'  => 'd',
			'е'  => 'e',
			'ё'  => 'e',
			'ж'  => 'j',
			'з'  => 'z',
			'и'  => 'i',
			'й'  => 'y',
			'к'  => 'k',
			'л'  => 'l',
			'м'  => 'm',
			'н'  => 'n',
			'о'  => 'o',
			'п'  => 'p',
			'р'  => 'r',
			'с'  => 's',
			'т'  => 't',
			'у'  => 'u',
			'ф'  => 'f',
			'х'  => 'h',
			'ц'  => 'ts',
			'ч'  => 'ch',
			'ш'  => 'sh',
			'щ'  => 'sch',
			'ъ'  => 'y',
			'ы'  => 'yi',
			'ь'  => '',
			'э'  => 'e',
			'ю'  => 'yu',
			'я'  => 'ya',
			'.'  => '-',
			' '  => '-',
			'?'  => '-',
			'/'  => '-',
			'\\' => '-',
			'*'  => '-',
			':'  => '-',
			'>'  => '-',
			'|'  => '-',
			'\'' => '',
			'('  => '',
			')'  => '',
			'!'  => '',
			'@'  => '',
			'%'  => '',
			'`'  => '',
		];
		$str = str_replace(['-', '+', '.', '?', '/', '\\', '*', ':', '*', '|'], ' ', $str);
		$str = htmlspecialchars_decode($str);
		$str = strip_tags($str);
		$pattern = '/[\w\s\d]+/u';
		preg_match_all($pattern, $str, $result);
		$str = implode('', $result[0]);
		$str = preg_replace('/[\s]+/us', ' ', $str);
		$str_trans = strtr($str, $arr_trans);

		return strtolower($str_trans);
	}

	/**
	 * Get ids product by group_id
	 *
	 * @param string $groupId
	 *
	 * @return void
	 */
	#[NoReturn] public static function getIdsByGroupId(string $groupId): void
	{
		$oasisCategories = Api::getCategoriesOasis(['fields' => 'id']);
		$ids = [];

		foreach ($oasisCategories as $oasisCategory) {
			$ids[] = $oasisCategory->id;
		}

		$args = [
			'fields'   => 'id,group_id',
			'category' => implode(',', $ids)
		];

		$products = Api::getProductsOasis($args);
		$result = [];

		foreach ($products as $product) {
			if ($product->group_id == $groupId) {
				$result[] = $product->id;
			}
		}

		print_r('$args[\'ids\'] = \'' . implode(',', $result) . '\';');
		exit();
	}
}
