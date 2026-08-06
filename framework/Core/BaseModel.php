<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
*/

namespace Frank\Core;

use PDO;
use ReflectionClass;
use ReflectionProperty;
use DateTimeImmutable;
use Exception;

abstract class BaseModel
{
	protected PDO $db;
	protected string $table;
	protected string $primaryKey = 'id';

	public function __construct(?PDO $pdo = null)
	{
		$this->db = $pdo ?? Database::getPdo();

		// If table name isn't set, pluralize the class name (e.g., User -> users)
		if (empty($this->table)) {
			$reflect = new ReflectionClass($this);
			$this->table = strtolower($reflect->getShortName()) . 's';
		}
	}

/**
* HYDRATOR: Converts raw input strings to PHP types based on property definitions.
*/
public function fill(array $data): void
{
	$reflection = new ReflectionClass($this);
	foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
		$name = $property->getName();
		$type = $property->getType()?->getName();

		// Handle Bool fields which will be missing if OFF
		if ($type === 'bool') {
			if (!array_key_exists($name, $data)) {
				$property->setValue($this, 0);
				continue;
			}
		}	
		

		if (array_key_exists($name, $data)) {
			$value = $data[$name];

			// Handle "Unselected" fields and Empty inputs
			if ($value === "-1" || $value === "" || $value === null) {
				if ($property->getType()?->allowsNull()) {
					$property->setValue($this, null);
					continue;
				}
			}
			
			// For array types, empty string should become empty array
			if ($type === 'array' && ($value === "" || $value === null)) {
				$property->setValue($this, []);
				continue;
			}

			// Perform Type Casting logic
			$property->setValue($this, $this->castValue($value, $type));
		}
	}
}

/**
* CASTING ENGINE: Logic for Currency, Dates, Arrays and Booleans
*/
	protected function castValue(mixed $value, ?string $type): mixed
	{
		if ($value === null)
			return null;

		return match ($type) {
			'int'   => (int)$value,
			'float' => (float)str_replace(['$', ','], '', $value), // Currency cleaning
			'bool'  => filter_var($value, FILTER_VALIDATE_BOOLEAN),
			'DateTimeImmutable' => new DateTimeImmutable($value),
			'DateTime'          => new \DateTime($value),
			'array' 			=> $this->castToArray($value), // JSON Array handling
			default 			=> $value,
		};
	}
/**
* CASTING ENGINE : JSON ARRAY HANDLER: Converts various input formats to PHP arrays
* Handles:
* - JSON strings from database: '["tax","advisory"]'
* - Comma-separated strings from forms: "tax, advisory, audit"
* - Already-parsed arrays: ["tax", "advisory"]
* is called from then main Type Resolver: castValue
*/
	protected function castToArray(mixed $value): array
	{
		// Already an array
		if (is_array($value)) {
			return $value;
		}

		// Null or empty
		if ($value === null || trim((string)$value) === '') {
			return [];
		}

		$stringValue = (string)$value;

		// Check if it's JSON (starts with [ or {)
		if (str_starts_with(trim($stringValue), '[') || str_starts_with(trim($stringValue), '{')) {
			$decoded = json_decode($stringValue, true);
			if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
				return $decoded;
			}
		}

		// Treat as comma-separated list (from form input)
		// "tax, advisory, audit" -> ["tax", "advisory", "audit"]
		return array_values(array_filter(
		array_map('trim', explode(',', $stringValue)),
		fn($v) => $v !== ''
		));
	}

/**
* CREATE / UPDATE (UPSERT): Saves the current state of the object to DB.
*/
	public function save(): bool
	{
		$data = $this->toArray();
		
		$columns = array_keys($data);
		$placeholders = array_map(fn($col) => ":$col", $columns);
		
		// Build the UPDATE part, but exclude immutable columns like 'id', 'tenant' or 'created_at'
		$updateCols = array_filter($columns, fn($col) => !in_array($col, ['id', 'created_at', 'updated_at', 'tenant_id']));
		// SQL using "ON DUPLICATE KEY UPDATE" handles both insert and update
		$updateStatement = implode(', ', array_map(fn($col) => "$col = VALUES($col)", $updateCols));

		$sql = sprintf(
		"INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s",
		$this->table,
		implode(', ', $columns),
		implode(', ', $placeholders),
		$updateStatement
		);	
		
		return $this->db->prepare($sql)->execute($data);
	}

/**
* DELETE: Remove the record while enforcing tenant security if property exists.
*/
	public function delete(): bool
	{
		
		$id = $this->{$this->primaryKey} ?? null;
		if (!$id)
			return false;

		$sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
		$params = ['id' => $id];

		if (property_exists($this, 'tenant_id')) {
			$sql .= " AND tenant_id = :tid";
			$params['tid'] = $this->tenant_id;
		}

		return $this->db->prepare($sql)->execute($params);
	}

/**
* STATIC FINDERS: Return objects instead of raw array and enforce Tenant Security 
*/
	public static function find(int $id, int $tenantID): ?static
	{
		$instance = new static();
		$stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = :id AND tenant_id = :tenantID LIMIT 1");
		$stmt->execute(['id' => $id, 'tenantID' => $tenantID]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? static::hydrateOne($row) : null;
	}

	public static function all($tenantID): array
	{
		$instance = new static();
		$stmt = $instance->db->query("SELECT * FROM {$instance->table} WHERE tenant_id = {$tenantID} ORDER BY created_at DESC");
		return static::hydrateMany($stmt->fetchAll(PDO::FETCH_ASSOC));
	}

	public static function where(int $tenantID, string $column, mixed $value, string $operator = '='): array
	{
		$instance = new static();
		$stmt = $instance->db->prepare("SELECT * FROM {$instance->table} WHERE $column $operator :val AND tenant_id = :tenantID");
		$stmt->execute(['val' => $value, 'tenantID' => $tenantID]);
		return static::hydrateMany($stmt->fetchAll(PDO::FETCH_ASSOC));
	}

/* *
* Changed to protected so that Child Classes can use these helper methods
*/
	protected static function hydrateOne(array $row): static
	{
		$obj = new static();
		$obj->fill($row);
		return $obj;
	}

	protected static function hydrateMany(array $rows): array
	{
		return array_map(fn($row) => static::hydrateOne($row), $rows);
	}

	private function toArray(): array
	{
		$props = get_object_vars($this);
		
		// Clean up framework-internal properties
		unset($props['db'], $props['table'], $props['primaryKey']);

		// Convert objects (like DateTime, Bool, Array) back to strings for MySQL
		// - Updated to handle Arrays for MySQL JSON fields
		// Bool False to 0 fix : Sanitize data for MySQL Strict Mode
		foreach ($props as &$value) {
			if ($value instanceof \DateTimeInterface) {
				$value = $value->format('Y-m-d H:i:s');
			}
			if (is_array($value)) {
				$value = json_encode($value);
			}
			if (is_bool($value)) {
				$value = $value ? 1 : 0;
			}
		}
		
		
		
		return $props;
	}
}