<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Native Session Library
 *
 * @package     Session
 * @subpackage  Libraries
 * @category    Session
 * @author      Bo-Yi Wu (appleboy) <appleboy.tw@gmail.com>
 * @author      Marko Martinović <marko@techytalk.info>
 */

class Session
{
    protected $sess_namespace = '';
    protected $ci;
    protected $store = array();
    protected $flashdata_key = 'flash';
    private $_config = array();

    /**
     * Constructor
     *
     * @access  public
     * @param   array   config preferences
     *
     * @return void
     **/
    public function __construct()
    {
        $this->ci = get_instance();
        // Default to 2 years if expiration is "0"
        $this->_expiration = 60 * 60 * 24 * 365 * 2;

        $this->initialize();

        // Delete 'old' flashdata (from last request)
        $this->_flashdata_sweep();

        // Mark all new flashdata as old (data will be deleted before next request)
        $this->_flashdata_mark();
    }

    /**
     * Initialize the configuration options
     *
     * @access  private
     * @return void
     */
     private function initialize()
     {
        $this->ci->load->config('session');

        $this->_config = array();
        $prefs = array(
            'sess_cookie_name',
            'sess_expire_on_close',
            'sess_expiration',
            'sess_match_ip',
            'sess_match_useragent',
            'sess_time_to_update',
            'cookie_prefix',
            'cookie_path',
            'cookie_domain',
            'cookie_secure',
            'cookie_httponly'
        );

        foreach ($prefs as $key) {
            $this->_config[$key] = $this->ci->config->item($key);
        }

        $this->_config = array_merge(
            array(
                'sess_namespace' => $this->ci->config->item('sess_namespace')
            ),
            $this->_config
        );

        foreach ($this->_config as $key => $val) {
            if (method_exists($this, 'set_'.$key)) {
                $this->{'set_'.$key}($val);
            } elseif (isset($this->$key)) {
                $this->$key = $val;
            }
        }

        // Set expiration, path, and domain
        $expire = 7200;
        $path = '/';
        $domain = '';
        $secure = (bool) $this->_config['cookie_secure'];
        $http_only = (bool) $this->_config['cookie_httponly'];

        if ($this->_config['sess_expiration'] !== false) {
            // Default to 2 years if expiration is "0"
            $expire = ($this->_config['sess_expiration'] == 0) ? $this->_expiration : $this->_config['sess_expiration'];
        }

        if ($this->_config['cookie_path']) {
            // Use specified path
            $path = $this->_config['cookie_path'];
        }

        if ($this->_config['cookie_domain']) {
            // Use specified domain
            $domain = $this->_config['cookie_domain'];
        }

        session_set_cookie_params($this->_config['sess_expire_on_close'] ? 0 : $expire, $path, $domain, $secure, $http_only);

        if ( ! isset($_SESSION)) {
            session_start();
        }

        if (isset($_SESSION[$this->sess_namespace]) ) {
            $this->store = $_SESSION[$this->sess_namespace];
        }

        $destroy = false;
        $now = time();

        $last_activity = $this->userdata('last_activity');
        $ip_address = $this->userdata('ip_address');
        $user_agent = $this->userdata('user_agent');
        if (!empty($last_activity) && (($last_activity + $expire) < $now or $last_activity > $now)) {
            // Expired - destroy
            log_message('debug', 'Session: Expired');
            $destroy = true;
        } elseif ($this->_config['sess_match_ip'] === true && !empty($ip_address)
            && $ip_address !== $this->ci->input->ip_address()) {
            // IP doesn't match - destroy
            log_message('debug', 'Session: IP address mismatch');
            $destroy = true;
        } elseif ($this->_config['sess_match_useragent'] === true && !empty($user_agent)
            && $user_agent !== trim(substr($this->ci->input->user_agent(), 0, 50))) {
            // Agent doesn't match - destroy
            log_message('debug', 'Session: User Agent string mismatch');
            $destroy = true;
        }

        // update last activity time
        $this->set_userdata('last_activity', $now);

        if (!$destroy) {
            return;
        }

        $this->sess_create();
    }

    /**
     * Create Session
     *
     * @access  public
     * @return void
     */
    public function sess_create()
    {
        // Send a new session id to client
        session_regenerate_id();
        
        $_SESSION[$this->sess_namespace] = array(
            'session_id' => md5(microtime()),
            'last_activity' => time()
        );

        // Set matching values as required
        if ($this->_config['sess_match_ip'] === true) {
            // Store user IP address
            $_SESSION[$this->sess_namespace]['ip_address'] = $this->ci->input->ip_address();
        }

        if ($this->_config['sess_match_useragent'] === true) {
            // Store user agent string
            $_SESSION[$this->sess_namespace]['user_agent'] = trim(substr($this->ci->input->user_agent(), 0, 50));
        }

        $this->store = $_SESSION[$this->sess_namespace];
    }

    /**
     * Destroy session
     *
     * @access  public
     */
    public function sess_destroy()
    {
        // get session name.
        $name = session_name();
        if (isset($_COOKIE[$name])) {
            // Clear session cookie
            $params = session_get_cookie_params();
            setcookie($name, '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            unset($_COOKIE[$name]);
        }

        $this->sess_create();
    }

    /**
     * Get specific user data element
     *
     * @access  public
     * @param   string  element key
     * @return object element value
     */
    public function userdata($value)
    {
        if (isset($this->store[$value])) {
            return $this->store[$value];
        } else {
            return false;
        }
    }

    /**
     * Set value for specific user data element
     *
     * @access  public
     * @param   array  list of data to be stored
     * @param   object  value to be stored if only one element is passed
     * @return void
     */
    public function set_userdata($data = array(), $value = '')
    {
        if (is_string($data)) {
            $data = array($data => $value);
        }
        foreach ($data as $key => $val) {
            $this->store[$key] = $val;
        }
        $_SESSION[$this->sess_namespace] = $this->store;
    }

    /**
     * remove array value for specific user data element
     *
     * @access  public
     * @param   array  list of data to be removed
     * @return void
     */
    public function unset_userdata($data = array())
    {
        if (is_string($data)) {
            $data = array($data => '');
        }

        if (count($data) > 0) {
            foreach ($data as $key => $val) {
                unset($this->store[$key]);
            }
        }

        $_SESSION[$this->sess_namespace] = $this->store;
    }

    /**
     * Fetch all session data
     *
     * @access  public
     * @return array
     */
    public function all_userdata()
    {
        return $this->store;
    }

    /**
     * Add or change flashdata, only available
     * until the next request
     *
     * @access  public
     * @param   mixed
     * @param   string
     * @return void
     */
    public function set_flashdata($newdata = array(), $newval = '')
    {
        if (is_string($newdata)) {
            $newdata = array($newdata => $newval);
        }

        if (count($newdata) > 0) {
            foreach ($newdata as $key => $val) {
                $flashdata_key = $this->flashdata_key.':new:'.$key;
                $this->set_userdata($flashdata_key, $val);
            }
        }
    }

    /**
     * Keeps existing flashdata available to next request.
     *
     * @access  public
     * @param   string
     * @return void
     */
    public function keep_flashdata($key)
    {
        // 'old' flashdata gets removed.  Here we mark all
        // flashdata as 'new' to preserve it from _flashdata_sweep()
        // Note the function will return false if the $key
        // provided cannot be found
        $old_flashdata_key = $this->flashdata_key.':old:'.$key;
        $value = $this->userdata($old_flashdata_key);

        $new_flashdata_key = $this->flashdata_key.':new:'.$key;
        $this->set_userdata($new_flashdata_key, $value);
    }

    /**
     * Fetch a specific flashdata item from the session array
     *
     * @access  public
     * @param   string
     * @return string
     */
    public function flashdata($key)
    {
        $flashdata_key = $this->flashdata_key.':old:'.$key;
        return $this->userdata($flashdata_key);
    }

    /**
     * Identifies flashdata as 'old' for removal
     * when _flashdata_sweep() runs.
     *
     * @access  private
     * @return void
     */
    private function _flashdata_mark()
    {
        $userdata = $this->all_userdata();
        foreach ($userdata as $name => $value) {
            $parts = explode(':new:', $name);
            if (is_array($parts) && count($parts) === 2) {
                $new_name = $this->flashdata_key.':old:'.$parts[1];
                $this->set_userdata($new_name, $value);
                $this->unset_userdata($name);
            }
        }
    }

    /**
     * Removes all flashdata marked as 'old'
     *
     * @access  private
     * @return void
     */
    private function _flashdata_sweep()
    {
        $userdata = $this->all_userdata();
        foreach ($userdata as $key => $value) {
            if (strpos($key, ':old:')) {
                $this->unset_userdata($key);
            }
        }
    }
}
