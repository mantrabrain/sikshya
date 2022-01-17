<?php

abstract class Sikshya_Rest_API_Abstract
{

	public $route_id;


	abstract public function register_routes();


	abstract public function handle();


	abstract public function validate();





}
