<?php
/**
* The FrankPHP is a product created by Ed Stivala, N3WMedia Labs
* Copyright (c) 2026 Ed Stivala Limited
* License: MIT
*/

namespace Frank\Core;

abstract class BasePresenter
{
	public function __construct(protected object $model)
	{
	}

	// Fallback: If the presenter doesn't have a method, get it from the model
	public function __get($property)
	{
		return $this->model->{$property};
	}
}

?>