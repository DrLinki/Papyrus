<?php
namespace App\Inc;
use Config\Conf;

class Session{
	
	public function __construct(){
		if(!isset($_SESSION)){
			session_start();
		}
	}

	/**
	 * Sets a flash message in the user session.
	 * @param string $message The message to display.
	 * @param string $type The type of message (default 'success').
	 * @return void This method returns nothing.
	 **/
	public function setFlash(string $message, string $type = 'success'): void
	{
		$_SESSION['flash'] = [
			'message' => $message,
			'type' => $type,
		];
	}

	/**
	 * Shows a flash message if set in the user session.
	 * @return string|null The HTML code of the flash message, or null if there is no message.
	 **/
	public function flash(): ?string
	{
		if (isset($_SESSION['flash']['message'])) {
			$html = '<div class="alert_message ' . $_SESSION['flash']['type'] . '">' . $_SESSION['flash']['message'] . '</div>';
			
			if (!isset($_SESSION['redirect'])) {
				$_SESSION['flash'] = [];
			} else {
				unset($_SESSION['redirect']);
			}
			
			return $html;
		}
		
		return null;
	}

	/**
	 * Writes a value to the session for a given key.
	 * @param string $key The key under which the value will be stored in the session.
	 * @param mixed $value The value to store in the session.
	 * @return void This method returns nothing.
	 **/
	public function write(string $key, mixed $value): void
	{
		$_SESSION[$key] = $value;
	}

	/**
	 * Reads a session value for a given key.
	 * @param string|null $key The key whose value should be read into the session. If null, returns all values ​​in the session.
	 * @return mixed The value stored in the session for the given key, or false if the key does not exist, or all values ​​in the session if the key is null.
	 **/
	public function read(?string $key = null): mixed
	{
		if ($key !== null) {
			return $_SESSION[$key] ?? false;
		}

		return $_SESSION;
	}

	/**
	 * Checks if a guest user is currently logged in.
	 * @return bool True if a guest user is not logged in, false otherwise.
	 **/
	public function isGuest(): bool
	{
		return isset($_SESSION['guest_' . Conf::$settings->sitecode]->id);
	}

	/**
	 * Retrieves specific information about the guest user.
	 * @param string $key The key to the information to be retrieved.
	 * @return mixed The value of the requested information, or false if not found.
	 **/
	public function guest(string $key): mixed
	{
		$guestKey = 'guest_' . Conf::$settings->sitecode;
		$guestData = $this->read($guestKey);
		
		if ($guestData && isset($guestData->$key)) {
			return $guestData->$key;
		}
		
		return false;
	}

	/**
	 * Checks if a user is logged in.
	 * @return bool True if a user is logged in, otherwise false.
	 **/
	public function isLogged(): bool
	{
		return isset($_SESSION['user']->id);
	}

	/**
	 * Retrieves a specific value from the logged in user.
	 * @param string $key The key to the value to retrieve.
	 * @return mixed The corresponding value if it exists, otherwise false.
	 **/
	public function user(string $key)
	{
		$userData = $this->read('user');
		
		if ($userData && isset($userData->$key)) {
			return $userData->$key;
		}
		
		return false;
	}

	/**
	 * Retrieves the client's IP address.
	 * @return string The client's IP address.
	 **/
	public function ip(): string
	{
		if (filter_var($_SERVER['HTTP_CLIENT_IP'] ?? null, FILTER_VALIDATE_IP)) {
			return $_SERVER['HTTP_CLIENT_IP'];
		} elseif (filter_var($_SERVER['HTTP_X_FORWARDED_FOR'] ?? null, FILTER_VALIDATE_IP)) {
			return $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			return $_SERVER['REMOTE_ADDR'];
		}
	}

}
?>