<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 *
 * https://github.com/mjphaynes/core/blob/1.1/develop/classes/session/native.php
 */

namespace Fuel\Core;



// --------------------------------------------------------------------

class Session_Native extends \Session_Driver {

	/**
	 * array of driver config defaults
	 */
	protected static $_defaults = array(
		'cookie_name'  => 'fuelcid',
		'session_name' => 'fuelsession'
	);

	// --------------------------------------------------------------------

	public function __construct($config = array())
	{
		// merge the driver config with the global config
		$this->config = array_merge($config, (isset($config['native']) and is_array($config['native'])) ? $config['native'] : static::$_defaults);

		$this->config = $this->_validate_config($this->config);
	}

	// --------------------------------------------------------------------

	/**
	 * driver initialisation
	 *
	 * @access	public
	 * @return	void
	 */
	public function init() {
		// generic driver initialisation
		parent::init();

		// Do not allow PHP to send Cache-Control headers
		// session_cache_limiter(false);

		// Start the session
		session_name($this->config['cookie_name']);
		session_start();
	}

	// --------------------------------------------------------------------

	/**
	 * create a new session
	 *
	 * @access	public
	 * @return	Fuel\Core\Session_Native
	 */
	public function create()
	{
		// create a new session
		$this->keys['session_id']	= session_id();
		$this->keys['ip_hash']		= md5(\Input::ip().\Input::real_ip());
		$this->keys['user_agent']	= \Input::user_agent();
		$this->keys['created'] 		= $this->time->get_timestamp();
		$this->keys['updated'] 		= $this->keys['created'];
		$this->keys['payload'] 		= '';

		$payload = $this->_serialize(array($this->keys, $this->data, $this->flash));
		$_SESSION[$this->config['session_name']] = $payload;

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * read the session
	 *
	 * @access	public
	 * @param	boolean, set to true if we want to force a new session to be created
	 * @return	Fuel\Core\Session_Driver
	 */
	public function read($force = false)
	{
		if ( empty($_SESSION[$this->config['session_name']]) || $force ) {
			$this->create();
		}

		// read the session file
		$payload = $_SESSION[$this->config['session_name']];
		if ($payload === false) {
			// cookie present, but session record missing. force creation of a new session
			$this->read(true);
			return;
		}

		// unpack the payload
		$payload = $this->_unserialize($payload);

		if (isset($payload[0])) $this->keys = $payload[0];
		if (isset($payload[1])) $this->data = $payload[1];
		if (isset($payload[2])) $this->flash = $payload[2];

		return parent::read();
	}

	// --------------------------------------------------------------------

	/**
	 * write the current session
	 *
	 * @access	public
	 * @return	Fuel\Core\Session_Native
	 */
	public function write()
	{
		// do we have something to write?
		if ( ! empty($this->keys))
		{
			parent::write();

			// rotate the session id if needed
			$this->rotate(false);

			// session payload
			$payload = $this->_serialize(array($this->keys, $this->data, $this->flash));

			// create the session file
			$_SESSION[$this->config['session_name']] = $payload;

			session_write_close();
		}

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * destroy the current session
	 *
	 * @access	public
	 * @return	Fuel\Core\Session_Native
	 */
	public function destroy()
	{
		// do we have something to destroy?
		if ( ! empty($this->keys))
		{
			session_destroy();

			// delete the session cookie
			\Cookie::delete($this->config['cookie_name']);
		}

		// reset the stored session data
		$this->keys = $this->flash = $this->data = array();

		return $this;
	}

	// --------------------------------------------------------------------

	/**
	 * generate a new session id
	 *
	 * @access	private
	 * @return  void
	 */
	protected function _new_session_id() {
		// Regenerate the session id
		session_regenerate_id();

		return session_id();
	}

	// --------------------------------------------------------------------

	/**
	 * validate a driver config value
	 *
	 * @param	array	array with configuration values
	 * @access	public
	 * @return  array	validated and consolidated config
	 */
	public function _validate_config($config)
	{
		$validated = array();

		foreach ($config as $name => $item)
		{
			// filter out any driver config
			if (!is_array($item))
			{
				switch ($name)
				{
					case 'cookie_name':
						if ( empty($item) or ! is_string($item))
						{
							$item = 'fuelcid';
						}
					break;

					case 'session_name':
						if ( empty($item) or ! is_string($item))
						{
							$item = 'fuelsession';
						}
					break;

					default:
						// no config item for this driver
					break;
				}

				// global config, was validated in the driver
				$validated[$name] = $item;
			}
		}

		// validate all global settings as well
		return parent::_validate_config($validated);
	}
}