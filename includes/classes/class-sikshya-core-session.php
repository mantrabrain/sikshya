<?php

class Sikshya_Core_Session
{
	private $session_key = 'sikshya_session';

	public function __construct()
	{
		$this->session_start();;
	}

	private function session_start()
	{
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}

	}

	public function set($key, $value)
	{

		$sikshya_session = array();

		if (isset($_SESSION[$this->session_key])) {

			$sikshya_session = $_SESSION[$this->session_key];
			$sikshya_session[$key] = $value;

		} else {
			$sikshya_session[$key] = $value;
		}
		$_SESSION[$this->session_key] = $sikshya_session;
	}

	public function get($key)
	{
		if (isset($_SESSION[$this->session_key])) {
			$all_session = $_SESSION[$this->session_key];
			if (isset($all_session[$key])) {
				return $all_session[$key];
			}
		}
		return null;
	}

	public function get_all()
	{
		if (isset($_SESSION[$this->session_key])) {
			return $_SESSION[$this->session_key];
		}
		return array();

	}
}
