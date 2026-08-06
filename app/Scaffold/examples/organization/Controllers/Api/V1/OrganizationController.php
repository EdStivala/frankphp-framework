<?php

declare(strict_types=1);

/**
 * EXAMPLE CONTROLLER — one part of the scaffold/examples/organization/
 * recipe, illustrating the BaseApiController / Syncfusion DataManager
 * paging pattern for a tenant-scoped API resource. Ships alongside
 * Models/Organization.php and sql/organizations.example.sql in this same
 * folder — see this folder's README.md for how to adopt it. Safe to
 * delete the whole organization/ folder if you don't need this example.
 */

namespace App\Controllers\Api\V1;

use App\Core\BaseApiController;
use App\Models\Organization;

class OrganizationController extends BaseApiController
{
/**
* Syncfusion DataManager READ endpoint
* Returns: { result: [...], count: N }
*
* Accepts POST body (preferred) or query params with DataManager fields:
*  - skip, take
*  - sorted: [{ name, direction }]
*  - where: [{ field, operator, value }, ...]
*  - search: string | { key, fields }
*/
	public function index($request, $args = [])
	{	
		try {
			// 1) Resolve tenant (throws RuntimeException -> caught below)
			$tenantId = $this->tenantId($request, $args);
			
			// 2) Parse DataManager params using BaseApiController helper
			$dm = $this->dmParams($request);

			// Optional: enforce a hard maximum page size to avoid huge payloads
			$maxTake = 2000;
			$take = min($dm['take'], $maxTake);

			// 3) Delegate to model (enforces tenant scoping internally)
			[$rows, $count] = Organization::findPagedByTenant(
			$tenantId,
			(int)$dm['skip'],
			(int)$take,
			(string)$dm['sortCol'],
			(string)$dm['sortDir'],
			(array)$dm['filters'],
			$dm['search']
			);

			// 4) Return Syncfusion DataManager shape
			$this->dmResponse($rows, $count);
			return;
		} catch (\Throwable $e) {
			// In production, use your logger instead of echoing the exception.
			$this->error('Server error', 500);
			return;
		}
	}
}

