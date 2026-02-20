<?php

namespace Opencart\Admin\Controller\Extension\Oasis;

use \Opencart\System\Engine\Registry;
use Opencart\Admin\Controller\Extension\Oasis\Config as OasisConfig;
use Exception;


class Main
{
	public static OasisConfig $cf;
	private Registry $registry;

	public array $cats_oasis = [];
	public const ATTR_SIZE_NAME = 'Размер';

	public const ATTR_COLOR_ID    = 1000000001; // Цвет товара
	public const ATTR_MATERIAL_ID = 1000000002; // Материал товара
	public const ATTR_BRANDING_ID = 1000000008; // Метод нанесения
	public const ATTR_BARCODE_ID  = 1000000011; // Штрихкод
	public const ATTR_GENDER_ID   = 65;        	// Пол
	public const ATTR_FLASH_ID    = 219;        // Объем памяти
	public const ATTR_MARKING_ID  = 254;        // Обязательная маркировка
	public const ATTR_REMOTE_ID   = 310;        // Минимальная сумма для удалённого склада

	public function __construct(Registry $registry)
	{
		$this->registry = $registry;
	}

	/**
	 * @param object $oasisProduct
	 * @param array|null $dbProduct
	 * @param array $productOption
	 */
	public function checkProduct(object $oasisProduct, ?array $dbProduct = null, array $productOption = [])
	{
		$productId = empty($dbProduct) ? null : intval($dbProduct['product_id']);
		$ocProduct = empty($productId) ? [] : $this->registry->model_catalog_product->getProduct($productId);

		if (empty($ocProduct)) {
			$productId = $this->addProduct($oasisProduct, $productOption);
			self::$cf->log('add    OAId=' . $oasisProduct->id . ', OCId=' . $productId);
		} else {
			$this->editProduct($oasisProduct, $productOption, $ocProduct, $dbProduct);
			self::$cf->log('update OAId=' . $oasisProduct->id . ', OCId=' . $productId);
		}
	}

	/**
	 * Check and delete product
	 * @param string $oasisProductId
	 */
	public function deleteProduct(string $oasisProductId)
	{
		$product = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProduct($oasisProductId);
		if (!empty($product)) {
			$this->deleteImages($this->registry->model_catalog_product->getImages(intval($product['product_id'])));
			$this->registry->model_catalog_product->deleteProduct(intval($product['product_id']));
		}
		$this->registry->model_extension_oasiscatalog_module_oasis->deleteOasisProduct($oasisProductId);
		self::$cf->log('OAId=' . $oasisProductId . ' delete OCId=' . (!empty($product) ? $product['product_id'] : '-'));
	}

	/**
	 * @param object $mainOasisProduct
	 * @param array $oasisProducts
	 * @return array
	 */
	public function getProductOption(object $mainOasisProduct, array $oasisProducts)
	{
		$option             = $this->getOption(self::ATTR_SIZE_NAME);
		$optionId           = intval($option['option_id']);
		$mainPrice          = $this->getCalculationPrice($mainOasisProduct);
		$productOptionValue = [];
		foreach ($oasisProducts as $oasisProduct) {
			$price    = $this->getCalculationPrice($oasisProduct);
			$priceOpt = '';
			$prefix   = '+';

			if ($mainPrice < $price) {
				$priceOpt = $price - $mainPrice;
			} elseif ($mainPrice > $price) {
				$priceOpt = $mainPrice - $price;
				$prefix = '-';
			}
			$productOptionValue[] = [
				'option_value_id'         => $this->getOptionValueId($optionId, $oasisProduct->size),
				'product_option_value_id' => '',
				'quantity'                => intval($oasisProduct->total_stock),
				'subtract'                => 1,
				'price_prefix'            => $prefix,
				'price'                   => $priceOpt,
				'points_prefix'           => '+',
				'points'                  => '',
				'weight_prefix'           => '+',
				'weight'                  => '',
				'oasis_opt_data'          => [
					'id'                => $oasisProduct->id,
					'updated_at'        => $oasisProduct->updated_at,
					'images_updated_at' => $oasisProduct->images_updated_at,
				],
			];
		}

		return [[
			'product_option_id'    => '',
			'option_id'            => $optionId,
			'name'                 => $option['name'],
			'type'                 => $option['type'],
			'required'             => 1,
			'product_option_value' => $productOptionValue
		]];
	}

	public function getOption(string $optionName): array
	{
		static $options;
		if (empty($options[$optionName])) {
			$option = $this->registry->model_catalog_option->getOptions(['filter_name' => $optionName]);
			if (empty($option)) {
				$opt = [
					'name' => $optionName,
					'value' => [],
				];
				$option = $this->registry->model_catalog_option->getOption($this->addOption($opt));
			}
			$options[$optionName] = $option[0];
		}
		return $options[$optionName];
	}

	/**
	 * @param int $optionId
	 * @param string $value
	 * @return int
	 */
	public function getOptionValueId(int $optionId, string $value): int
	{
		static $opt;
		if (empty($opt[$optionId][$value])) {
			$optionValues = $this->registry->model_catalog_option->getValues($optionId);
			$key = array_search($value, array_column($optionValues, 'name'));
			if ($key === false) {
				$this->editOption($optionId, $value);
				$optionValues = $this->registry->model_catalog_option->getValues($optionId);
				$key = array_search($value, array_column($optionValues, 'name'));
			}
			$opt[$optionId][$value] = $optionValues[$key]['option_value_id'];
		}
		return $opt[$optionId][$value];
	}

	/**
	 * @param object $oasisProduct
	 * @param array $productOption
	 * @param array $ocProduct
	 * @param array $dbProduct
	 */
	public function editProduct(object $oasisProduct, array $productOption, array $ocProduct, array $dbProduct)
	{
		$productId = intval($dbProduct['product_id']);
		$data      = $ocProduct;

		$data['product_option']   = $productOption;
		$data['product_category'] = self::$cf->is_not_up_cat ? $this->registry->model_catalog_product->getCategories($productId) :
															$this->getProductCategories($oasisProduct->categories);

		if (!self::$cf->is_fast_import) {
			$productImages = $this->registry->model_catalog_product->getImages($productId);
			if (self::$cf->is_up_photo || $this->getNeedImagesUp($oasisProduct, $dbProduct)) {
				$this->deleteImages($productImages);
				$data['product_image'] = $this->prepareImagesProduct($oasisProduct->images, $data['product_category']);

				if (!empty($data['product_image'])) {
					$data['image'] = $data['product_image'][0]['image'];
				}
				else {
					$data['image'] = '';
				}
			} else {
				$data['product_image'] = array_map(function($img) {
					return [
						'image'      => $img['image'],
						'sort_order' => $img['sort_order'],
					];
				}, $productImages);
			}
			$this->updateImageCDN($oasisProduct);
		}

		// todo: group_id может быть не актуален
		$dbGroupProduct = $this->registry->model_extension_oasiscatalog_module_oasis->getOasisProduct($oasisProduct->group_id);
		if ($dbGroupProduct) {
			$product_related = $this->registry->model_catalog_product->getRelated(intval($dbGroupProduct['product_id']));
			if ($oasisProduct->group_id !== $oasisProduct->id && $productId !== $dbGroupProduct['product_id']) {
				$product_related[] = $dbGroupProduct['product_id'];
			}
			$data['product_related'] = $product_related;
		}

		$this->registry->model_catalog_product->editProduct($productId, $this->setProduct($oasisProduct, $data));

		if (empty($productOption)) {
			$this->registry->model_extension_oasiscatalog_module_oasis->editOasisProduct($oasisProduct->id, [
				'option_value_id'   => '',
				'product_id'        => $productId,
				'updated_at'        => $oasisProduct->updated_at,
				'images_updated_at' => self::$cf->is_fast_import ? '' : $oasisProduct->images_updated_at,
			]);
		}
		else {
			foreach ($productOption as $productOptionItem) {
				foreach ($productOptionItem['product_option_value'] as $opt) {
					$optData = $opt['oasis_opt_data'];

					$oValueId = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValueId($productId, $opt['option_value_id']);
					$this->registry->model_extension_oasiscatalog_module_oasis->editOasisProduct($optData['id'], [
						'option_value_id'   => $oValueId,
						'product_id'        => $productId,
						'updated_at'        => $optData['updated_at'],
						'images_updated_at' => self::$cf->is_fast_import ? '' : $optData['images_updated_at'],
					]);
				}
			}
		}
	}

	/**
	 * @param object $oasisProduct
	 * @param array $productOption
	 * @return integer
	 * @throws Exception
	 */
	public function addProduct(object $oasisProduct, array $productOption): int
	{
		$data = [
			'product_option'   => $productOption,
			'product_category' => $this->getProductCategories($oasisProduct->categories),
		];
		if (!empty($oasisProduct->brand_id)) {
			$data['manufacturer_id'] = $this->getBrand($oasisProduct->brand_id);
		}
		if (!self::$cf->is_fast_import) {
			$data['product_image'] = $this->prepareImagesProduct($oasisProduct->images, $data['product_category']);

			if (!empty($data['product_image'])) {
				$data['image'] = $data['product_image'][0]['image'];
			}
			else{
				$data['image'] = '';
			}
		}

		$productId = $this->registry->model_catalog_product->addProduct($this->setProduct($oasisProduct, $data));

		if (empty($productOption)) {
			$this->registry->model_extension_oasiscatalog_module_oasis->addOasisProduct([
				'option_value_id'   => '',
				'product_id'        => $productId,
				'product_id_oasis'  => $oasisProduct->id,
				'updated_at'        => $oasisProduct->updated_at,
				'images_updated_at' => self::$cf->is_fast_import ? '' : $oasisProduct->images_updated_at,
			]);
		}
		else {
			foreach ($productOption as $productOptionItem) {
				foreach ($productOptionItem['product_option_value'] as $opt) {
					$optData = $opt['oasis_opt_data'];

					$oValueId = $this->registry->model_extension_oasiscatalog_module_oasis->getProductOptionValueId($productId, $opt['option_value_id']);
					$this->registry->model_extension_oasiscatalog_module_oasis->addOasisProduct([
						'option_value_id'   => $oValueId,
						'product_id'        => $productId,
						'product_id_oasis'  => $optData['id'],
						'updated_at'        => $optData['updated_at'],
						'images_updated_at' => self::$cf->is_fast_import ? '' : $optData['images_updated_at'],
					]);
				}
			}
		}
		if(!self::$cf->is_fast_import) {
			$this->updateImageCDN($oasisProduct);
		}
		return $productId;
	}

	/**
	 * @param object $oasisProduct
	 * @param array $data
	 * @return array
	 */
	public function setProduct(object $oasisProduct, array $data): array
	{
		$product = [
			'master_id'           => 0,
			'price'               => $this->getCalculationPrice($oasisProduct),
			'model'               => $data['model'] ?? htmlspecialchars($oasisProduct->article, ENT_QUOTES),
			'product_attribute'   => $this->getAttributes($oasisProduct->attributes, empty($data['product_option'])),
			'product_store'       => $data['product_store'] ?? $this->getStores(),
			'image'               => $data['image'] ?? '',
			'sku'                 => $data['sku'] ?? '',
			'upc'                 => $data['upc'] ?? '',
			'ean'                 => $data['ean'] ?? '',
			'jan'                 => $data['jan'] ?? '',
			'isbn'                => $data['isbn'] ?? '',
			'mpn'                 => $data['mpn'] ?? '',
			'location'            => $data['location'] ?? '',
			'tax_class_id'        => $data['tax_class_id'] ?? (self::$cf->is_no_vat ? self::$cf->tax_class_id : 0),
			'minimum'             => $data['minimum'] ?? 1,
			'subtract'            => $data['subtract'] ?? 1,
			'stock_status_id'     => $data['stock_status_id'] ?? '0',
			'shipping'            => $data['shipping'] ?? 1,
			'date_available'      => $data['date_available'] ?? date('Y-m-d'),
			'length'              => $data['length'] ?? '',
			'width'               => $data['width'] ?? '',
			'height'              => $data['height'] ?? '',
			'length_class_id'     => $data['length_class_id'] ?? 1,
			'weight'              => $data['weight'] ?? '',
			'weight_class_id'     => $data['weight_class_id'] ?? 1,
			'sort_order'          => $data['sort_order'] ?? 1,
			'manufacturer_id'     => $data['manufacturer_id'] ?? '0',
			'category'            => $data['category'] ?? '',
			'filter'              => $data['filter'] ?? '',
			'download'            => $data['download'] ?? '',
			'related'             => $data['related'] ?? '',
			'points'              => $data['points'] ?? '',
			'product_reward'      => $data['product_reward'] ?? [1 => ['points' => '']],
			'product_seo_url'     => $data['product_seo_url'] ?? $this->getSeoUrl($this->getStores(), $this->transliter($oasisProduct->full_name)),
			'product_layout'      => $data['product_layout'] ?? [0 => ''],

		];

		if (isset($data['product_description'])) {
			$product['product_description'] = $data['product_description'];
		}
		else {
			$name = htmlspecialchars($oasisProduct->full_name, ENT_QUOTES);
			$desc = nl2br(($oasisProduct->description ?? '') . (empty($oasisProduct->defect) ? '' : ('<p>' . $oasisProduct->defect . '</p>')));
			$desc = htmlspecialchars($desc, ENT_QUOTES);
			$product['product_description'] = [];
			foreach ($this->getLanguages() as $language) {
				$product['product_description'][$language['language_id']] = [
					'name'             => $name,
					'description'      => $desc,
					'meta_title'       => $name,
					'meta_description' => '',
					'meta_keyword'     => '',
					'tag'              => '',
				];
			}
		}

		if (!empty($data['product_image'])) {
			$product['product_image'] = $data['product_image'];
		}
		if (!empty($data['product_category'])) {
			$product['product_category'] = $data['product_category'];
		}
		if (!empty($data['product_related'])) {
			$product['product_related'] = $data['product_related'];
		}
		if (!empty($data['product_option'])) {
			$product['product_option'] = $data['product_option'];
			$quantity = 0;
			foreach ($data['product_option'] as $productOptionItem) {
				foreach ($productOptionItem['product_option_value'] as $opt) {
					$quantity += ($opt['quantity'] ?? 0);
				}
			}
			$product['quantity'] = $quantity;
		} else {
			$product['quantity'] = $oasisProduct->total_stock;
		}
		$product['status'] = ($product['quantity'] > 0) ? 1 : 0;

		return $product;
	}

	/**
	 * Get calculation price product
	 * @param object $product
	 * @return float
	 */
	public function getCalculationPrice(object $oasisProduct): float
	{
		$price = self::$cf->is_price_dealer ? $oasisProduct->discount_price : $oasisProduct->price;
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
		return array_values(array_unique($result));
	}

	public function getCategoryParents($cat_id): array {
		$list = [];
		while($cat_id != 0){
			$category = $this->registry->model_catalog_category->getCategory($cat_id);
			if (empty($category)) {
				break;
			}
			$list[] = $category;
			$cat_id = $category['parent_id'];
		}
		return array_reverse($list);
	}

	/**
	 * Get oasis parents id categories
	 * @param null $cat_id
	 * @return array
	 */
	public function getOasisParentsCategoriesId($cat_id): array
	{
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

	public function getCategoryId(int $cat_id): int
	{
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

		$data['category_description'] = [];
		foreach ($this->getLanguages() as $language) {
			$data['category_description'][$language['language_id']] = [
				'name'             => $category->name,
				'description'      => '',
				'meta_title'       => $category->name,
				'meta_description' => '',
				'meta_keyword'     => '',
			];
		}

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
	 * @param bool $isSimpleProduct
	 * @return array
	 */
	public function getAttributes(array $attributes, bool $isSimpleProduct): array
	{
		$result = [];
		$attributesStore = $this->registry->model_catalog_attribute->getAttributes();

		foreach ($attributes as $attribute) {
			if (in_array($attribute->id ?? null, [self::ATTR_BARCODE_ID, self::ATTR_MARKING_ID, self::ATTR_REMOTE_ID])) {
				continue;
			}
			$name = $attribute->name ?? '';
			if ($isSimpleProduct || $name !== self::ATTR_SIZE_NAME) {
				$neededAttribute = array_filter($attributesStore, function ($e) use ($name) {
					return $e['name'] == $name;
				});

				if ($neededAttribute) {
					$attr = array_shift($neededAttribute);

					$key_attr = array_search($attr['name'], array_column($result, 'name'));

					if ($key_attr !== false) {
						foreach ($result[$key_attr]['product_attribute_description'] as $key => $value) {
							$result[$key_attr]['product_attribute_description'][$key]['text'] .= ', ' . $attribute->value;
						}
					} else {
						$result[] = [
							'name'                          => $attr['name'],
							'attribute_id'                  => $attr['attribute_id'],
							'product_attribute_description' => $this->toLanguagesArr('text', (string)$attribute->value),
						];
					}
				} else {
					$attr = [
						'sort_order'            => '',
						'attribute_description' => $this->toLanguagesArr('name', (string)$attribute->name),
						'attribute_group_id'    => $this->getAttributeGroupId(),
					];
					$result[] = [
						'name'                          => $attribute->name,
						'attribute_id'                  => $this->registry->model_catalog_attribute->addAttribute($attr),
						'product_attribute_description' => $this->toLanguagesArr('text', (string)$attribute->value),
					];
					$attributesStore = $this->registry->model_catalog_attribute->getAttributes();
				}
			}
		}
		return $result;
	}

	/**
	 * @param string $id
	 * @return int
	 */
	public function getBrand(string $id): int
	{
		static $brandsOasis;
		if (empty($brandsOasis)) {
			$brandsOasis = Api::getBrandsOasis();
		}
		$brand = self::searchObject($brandsOasis, $id);
		if (!$brand) {
			return 0;
		}
		$result = $this->registry->model_extension_oasiscatalog_module_oasis->getSeoUrls([
			'keyword' => $brand->slug,
			'key'     => 'manufacturer_id',
		]);
		if (!empty($result)) {
			return intval($result['value']);
		}
		$data = [
			'sort_order'           => '',
			'name'                 => $brand->name,
			'manufacturer_store'   => $this->getStores(),
			'manufacturer_seo_url' => $this->getSeoUrl($this->getStores(), $brand->slug),
			'image'                => empty($brand->logotype) ? '' : $this->saveImg(['source' => $brand->logotype, 'folder' => 'catalog/oasis/manufacturers'])
		];
		return $this->registry->model_catalog_manufacturer->addManufacturer($data);
	}

	/**
	 * @param array $option
	 * @return int
	 */
	public function addOption(array $option): int
	{
		$data = [
			'option_description' => $this->toLanguagesArr('name', (string)$option['name']),
			'type'				 => 'radio',
			'sort_order'		 => '',
			'validation'		 => ''
		];
		foreach ($option['value'] as $item) {
			$data['option_value'][] = [
				'option_value_id'          => '',
				'option_value_description' => $this->toLanguagesArr('name', (string)$item),
				'image'                    => '',
				'sort_order'               => '',
			];
		}
		return $this->registry->model_catalog_option->addOption($data);
	}

	/**
	 * @param int $option_id
	 * @param string $value
	 * @return void
	 */
	public function editOption(int $option_id, string $value): void
	{
		$data = [
			'option_description' => $this->registry->model_catalog_option->getDescriptions($option_id),
			'type'				 => 'radio',
			'sort_order'		 => '',
			'validation'		 => ''
		];
		$option_values = $this->registry->model_catalog_option->getValueDescriptions($option_id);

		$option_values[] = [
			'option_value_id'          => '',
			'option_value_description' => $this->toLanguagesArr('name', $value),
			'image'                    => '',
			'sort_order'               => '',
		];

		$data['option_value'] = $option_values;
		$this->registry->model_catalog_option->editOption($option_id, $data);
	}

	/**
	 * @return int
	 */
	public function getAttributeGroupId(): int
	{
		$name  = 'Характеристики';
		$groups = $this->registry->model_catalog_attribute_group->getAttributeGroups();
		$key   = array_search($name, array_column($groups, 'name'));

		if ($key !== false) {
			return intval($groups[$key]['attribute_group_id']);
		} else {
			$group = [
				'sort_order' => ''
			];
			foreach ($this->getLanguages() as $language) {
				$group['attribute_group_description'][$language['language_id']] = [
					'name' => $name,
				];
			}
			return $this->registry->model_catalog_attribute_group->addAttributeGroup($group);
		}
	}

	/**
	 * @param array $stores
	 * @param string $slug
	 * @return array
	 */
	public function getSeoUrl(array $stores, string $slug): array
	{
		$data = [];
		foreach ($stores as $store) {
			$i = 0;
			$postfix = '';
			foreach ($this->getLanguages() as $language) {
				if ($i > 0) {
					$postfix = '-' . $i;
				}
				$data[$store][$language['language_id']] = $slug . $postfix;
				$i++;
			}
		}
		return $data;
	}

	/**
	 * @return array
	 */
	public function getStores(): array
	{
		static $result;
		if (empty($result)) {
			if ($stores = $this->registry->model_setting_store->getStores()) {
				foreach ($stores as $store) {
					$result[] = $store['store_id'];
				}
			} else {
				$result = [0];
			}
		}
		return $result;
	}

	public function getIdCategoryByOasisId(int $id): int
	{
		$result = $this->registry->model_extension_oasiscatalog_module_oasis->getIdOcCategory($id);

		return $result ? intval($result['category_id']) : 0;
	}

	/**
	 * Get categories level 1
	 * @return array
	 */
	public static function getOasisMainCategories(): array
	{
		static $result;
		if (empty($result)) {
			$result = [];
			$categories = Api::getCategoriesOasis();
			foreach ($categories as $category) {
				if ($category->level === 1) {
					$result[] = $category->id;
				}
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
	 * @return array
	 */
	private function getLanguages(): array
	{
		static $languages;
		if (empty($languages)) {
			$languages = $this->registry->model_localisation_language->getLanguages();
		}
		return $languages;
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @return array
	 */
	public function toLanguagesArr(string $key, string $value): array
	{
		$result = [];
		foreach ($this->getLanguages() as $language) {
			$result[$language['language_id']] = [
				$key => $value,
			];
		}
		return $result;
	}

	/**
	 * Build tree categories
	 * @param $data
	 * @param array $checkedArr
	 * @param array $relCategories
	 * @param int $parent_id
	 * @param bool $parent_checked
	 * @return string
	 */
	public static function buildTreeCats($data, array $checkedArr = [], array $relCategories = [], int $parent_id = 0, bool $parent_checked = false): string
	{
		$treeItem = '';
		if (!empty($data[$parent_id])) {
			foreach ($data[$parent_id] as $item) {
				$checked = $parent_checked || in_array($item['id'], $checkedArr);

				$rel_cat = $relCategories[$item['id']] ?? null;
				$rel_label = '';
				$rel_value = '';
				if($rel_cat){
					$rel_value = $item['id'].'_'.$rel_cat['id'];
					$rel_label = $rel_cat['rel_label'];
				}

				$treeItemChilds = self::buildTreeCats($data, $checkedArr, $relCategories, $item['id'], $checked);

				if(empty($treeItemChilds)){
					$treeItem .= '<div class="oa-tree-leaf">
						<div class="oa-tree-label ' . ($rel_value ? 'relation-active' : '') . '">
							<input type="hidden" class="oa-tree-inp-rel" name="categories_rel[]" value="' . $rel_value . '" />
							<label>
								<input type="checkbox" class="oa-tree-cb-cat" name="categories[]" value="' . $item['id'] . '"' . ($checked ? ' checked="checked"' : '' ) . '/>
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
								<input type="checkbox" class="oa-tree-cb-cat" name="categories[]" value="' . $item['id'] . '"' . ($checked ? ' checked="checked"' : '' ) . '/>
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
	 * @param $data
	 * @param int $checked_id
	 * @param int $parent_id
	 * @return string
	 */
	public static function buildTreeRadioCats($data, ?int $checked_id = null, int $parent_id = 0): string
	{
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
	public function deleteImages($images): void
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
	 * Check need update product images
	 * @param $productOasis
	 * @param $dbProduct
	 * @return bool
	 */
	public function getNeedImagesUp($productOasis, $dbProduct): bool
	{
		return empty($dbProduct) || ($productOasis->images_updated_at ?? '1') > ($dbProduct['images_updated_at'] ?? '');
	}

	/**
	 * Prepare images for product
	 *
	 * @param $images
	 * @param array $categories
	 * @return array
	 */
	public function prepareImagesProduct($images, array $categories = []): array
	{
		$result = [];
		if(!self::$cf->is_cdn_photo && is_array($images)){
			$subCatalog = end($categories) ?: '';
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
	 * Update Image CDN
	 * @param $productOasis
	 */
	public function updateImageCDN($productOasis)
	{
		$this->registry->model_extension_oasiscatalog_module_oasis->delImgsCDNFromOID($productOasis->id);
		if (self::$cf->is_cdn_photo) {
			$main = 1;
			foreach($productOasis->images as $img){
				$this->registry->model_extension_oasiscatalog_module_oasis->addImgCDNFromOID($productOasis->id, [
					'main' => $main,
					'url_superbig' => $img->superbig ?? '',
					'url_big' => $img->big ?? '',
					'url_small' => $img->small ?? '',
					'url_thumbnail' => $img->thumbnail ?? '',
				]);
				$main = 0;
			}
		}
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
			return '';
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
		$str = str_replace(['-', '+', '.', '?', '/', '\\', '*', ':', '|'], ' ', $str);
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
	 * Filter categories
	 * @param array categories
	 * @return array
	 */
	public static function getEasyCategories(array $categories): array
	{
		$list = [];
		foreach (Api::getCategoriesOasis() as $cat) {
			$l = $cat->level;
			if (empty($list[$l])) {
				$list[$l] = [];
			}
			if (empty($list[$l][$cat->id])) {
				$list[$l][$cat->id] = [];
			}
			if ($cat->parent_id) {
				if (empty($list[$l][$cat->parent_id])) {
					$list[$l][$cat->parent_id] = [];
				}
				$list[$l][$cat->parent_id][] = $cat->id;
			}
		}
		ksort($list);
		$list = array_reverse($list);
		while (true) {
			foreach ($list as $group) {
				foreach ($group as $id => $childs) {
					if (count($childs) > 0 && count(array_diff($childs, $categories)) == 0){
						$categories = array_diff($categories, $childs);
						$categories[] = $id;
						continue 3;
					}
				}
			}
			break;
		}
		return array_values(array_unique($categories));
	}

	/**
	 * @param $array
	 * @param $keys
	 * @return bool
	 */
	public static function arrayKeysExists($array, $keys): bool
	{
		foreach ($keys as $key) {
			if (array_key_exists($key, $array)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find item
	 * @param $array
	 * @param $callback
	 * @return mixed|null
	 */
	public static function findItem(array $array, callable $callback)
	{
		foreach ($array as $key => $value) {
			if ($callback($value, $key)) {
				return $value;
			}
		}
		return null;
	}
}