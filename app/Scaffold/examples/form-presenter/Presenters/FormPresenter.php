<?php

namespace App\Presenters;

use App\Core\BasePresenter;

/**
 * EXAMPLE PRESENTER — the scaffold/examples/form-presenter/ recipe,
 * demonstrating the BasePresenter pattern (Core\BasePresenter is a
 * framework primitive; this concrete presenter is example content) — see
 * this folder's README.md. Safe to delete the whole form-presenter/
 * folder if you don't need this example.
 */
class FormPresenter extends BasePresenter
{
/**
* Generic Tag/Chip Field
*/
	public function tags(string $property, array $source = [], string $placeholder = "Select..."): string
	{
		$value = json_encode($this->model->{$property} ?? []);
		$sourceJson = json_encode($source);
		$id = "sf_tags_" . $property;

		return "
            <input type='hidden' id='hidden_{$id}' name='{$property}'>
            <div data-sf-type='tags' id='{$id}' data-target='hidden_{$id}'
                 data-value='{$value}' data-source='{$sourceJson}'
                 data-placeholder='{$placeholder}'>
            </div>";
	}

/**
* Generic Date Field (formatted for your UIInitializer)
*/
	public function date(string $property): string
	{
		$val = $this->model->{$property};
		// Format for the JS Date() constructor if it's a DateTime object
		$formatted = ($val instanceof \DateTimeInterface) ? $val->format('Y-m-d') : $val;

		return "<input type='text' name='{$property}' data-sf-type='date' value='{$formatted}'>";
	}

/**
* Generic Currency Field
*/
	public function currency(string $property, string $symbol = "GBP"): string
	{
		$val = $this->model->{$property} ?? 0;
		return "<input type='text' name='{$property}' data-sf-type='currency' data-currency='{$symbol}' value='{$val}'>";
	}
}

?>