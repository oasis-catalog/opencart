<?php

namespace Opencart\Catalog\Model\Extension\Oasiscatalog\Module;

use Opencart\System\Engine\Model;

class Oasis extends Model
{
	public function getImgsCDNFromID(int $product_id): array
	{
		$query = $this->db->query("
			SELECT img.* FROM `" . DB_PREFIX . "oasis_cdn_images` as img
			LEFT JOIN `" . DB_PREFIX . "oasis_product` as p ON p.id = img.oasis_product_id
			WHERE p.product_id = ".$product_id
		);

		return $query->rows ?? [];
	}
}