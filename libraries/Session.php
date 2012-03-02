<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * CodeIgniter Native Session Library
 *
 * @package     Session
 * @subpackage  Libraries
 * @category    Session
 * @author      Topic Deisgn
 * @modified    Bo-Yi Wu <appleboy.tw@gmail.com>
 * @date        2012-03-02
 */

class MY_Session
{
    protected $app_name = '';
    protected $ci;
    protected $store = array();

    // --------------------------------------------------------------------

    /**
     * Constructor
     *
     * @access  public
     * @param   array   config preferences
     *
     * @return  void
     **/

    function __construct($config = array())
    {
        $this->ci = get_instance();
        $this->app_name = $this->ci->config->item('app_name');

        if ( ! isset($_SESSION))
        {
            session_start();
        }
        $this->initialize($config);
    }

    // --------------------------------------------------------------------

    /**
     * Initialize the configuration options
     *
     * @access  private
     * @param   array   config options
     * @return  void
     */
     private function initialize($config)
     {
        foreach ($config as $key => $val)
        {
            if (method_exists($this, 'set_'.$key))
            {
                $this->{'set_'.$key}($val);
            }
            else if (isset($this->$key))
            {
                $this->$key = $val;
            }
        }
        if(isset($_SESSION[$this->app_name]) )
        {
            $this->store = $_SESSION[$this->app_name];
            if(! $this->is_expired())
            {
                return;
            }
        }
        $this->create_session();
    }
    /**
     * Create Session
     *
     * @access  public
     * @return  void
     */
    public function create_session()
    {
        $expire_time = time() + intval($this->ci->config->item('sess_expiration'));
        $_SESSION[$this->app_name] = array(
            'session_id' => md5(microtime()),
            'expire_at' => $expire_time
        );
        $this->store = $_SESSION[$this->app_name];
    }

    /**
     * Check if session is expired
     *
     * @access  public
     * @return  void
     */
    public function is_expired()
    {
        if ( ! isset($this->store['expire_at']))
        {
            return TRUE;
        }
        return (time() > $this->store['expire_at']);
    }
    /**
     * Destroy session
     *
     * @access  public
     */
    public function sess_destroy()
    {
        $this->create_session();
    }
    /**
     * Get specific user data element
     *
     * @access  public
     * @param   string  element key
     * @return  object  element value
     */
    public function userdata($value)
    {
        if ($value == 'session_id')
        {
            return $this->store['session_id'];
        }
        if (isset($this->store[$value]))
        {
            return $this->store[$value];
        }
        else
        {
            return FALSE;
        }
    }
    /**
     * Set value for specific user data element
     *
     * @access  public
     * @param   array  list of data to be stored
     * @param   object  value to be stored if only one element is passed
     * @return  void
     */
    public function set_userdata($data = array(), $value = '')
    {
        if(is_string($data))
        {
            $data = array($data => $value);
        }
        foreach ($data as $key => $val)
        {
            $this->store[$key] = $val;
        }
        $_SESSION[$this->app_name] = $this->store;
    }

    /**
     * remove array value for specific user data element
     *
     * @access  public
     * @param   array  list of data to be removed
     * @return  void
     */    
    public function unset_userdata($data = array())
    {
        if (is_string($data))
        {
            $data = array($data => '');
        }

        if (count($data) > 0)
        {
            foreach ($data as $key => $val)
            {
                unset($this->store[$key]);
            }
        }

        $_SESSION[$this->app_name] = $this->store;    
    }
    
    /**
     * Fetch all session data
     *
     * @access  public
     * @return  array
     */
    public function all_userdata()
    {
        return $this->store;
    }
}