<?php

/**
 * Represents a host-aware route.
 *
 * Example usage:
 *
 *     dashboard:
 *       url:   :username.%APP_HOST%/dashboard/:section
 *       class: IceHostAwareRoute
 *       param: { module: dashboard, action: showSection }
 *
 * The `%APP_HOST%` token will be replaced by the return value of a call to
 * `sfConfig::get('app_host')`. You can interpolate any config value into the
 * `url` value in this way.
 *
 * @package iceLibsPlugin
 *
 * @author Kris Wallsmith <kris.wallsmith@symfony-project.com>
 * @author Kiril Angov <kiril.angov@icepique.com>
 */
class IceHostAwareRoute extends IceRequestRoute
{
  protected $_object = null;

  public function __construct($pattern, array $defaults = array(), array $requirements = array(), array $options = array())
  {
    if ('/' != $pattern[0])
    {
      list($host, $pattern) = explode('/', $pattern, 2);

      $options['host_route'] = $this->createHostRoute($host, $defaults, $requirements, $options);
    }

    parent::__construct($pattern, $defaults, $requirements, $options);
  }

  public function matchesUrl($url, $context = array())
  {
    if (isset($this->requirements['sf_host']) && $this->requirements['sf_host'] != $context['host'])
    {
      return false;
    }

    if (false === $parameters = parent::matchesUrl($url, $context))
    {
      return false;
    }

    if (isset($this->options['host_route']))
    {
      if (false === $hostParameters = $this->options['host_route']->matchesUrl('/'.$context['host'], $context))
      {
        // host does not match
        return false;
      }

      $parameters = array_merge($parameters, $hostParameters);
    }

    return $parameters;
  }

  /**
   * @see sfRequestRoute
   */
  public function matchesParameters($params, $context = array())
  {
    if (!isset($this->options['host_route']))
    {
      return parent::matchesParameters($params, $context);
    }

    $hostParams = $this->extractHostParams($params);

    return parent::matchesParameters($params, $context) && $this->options['host_route']->matchesParameters($hostParams, $context);
  }

  /**
   * @see sfRequestRoute
   */
  public function generate($params, $context = array(), $absolute = false)
  {
    if (!isset($this->options['host_route']))
    {
      $url = parent::generate($params, $context, $absolute);

      if (isset($this->requirements['sf_host']) && $this->requirements['sf_host'] != @$context['host'])
      {
        // apply the required host
        $protocol = !empty($context['is_secure']) ? 'https' : 'http';
        $url = $protocol .'://'. $this->requirements['sf_host'] . $url;
      }

      return $url;
    }

    $hostParams = $this->extractHostParams($params);

    /**
     * Special case for :subdomain
     */
    if (in_array(':subdomain', $this->options['host_route']->getVariables()))
    {
      if (!isset($hostParams['subdomain']) && !empty($context['host']))
      {
        $parts = explode('.', $context['host']);
        $hostParams['subdomain'] = $parts[0];
      }
    }

    if (isset($this->requirements['sf_host']) && $this->requirements['sf_host'] != @$context['host'])
    {
      $host = $this->requirements['sf_host'];
    }
    else
    {
      $host = $this->options['host_route']->generate($hostParams, $context, false);
    }

    $protocol = isset($context['is_secure']) && $context['is_secure'] ? 'https' : 'http';
    $prefix = isset($context['prefix']) ? $context['prefix'] : '';
    $uri = parent::generate($params, $context, false);

    return $protocol.':/'.$host.$prefix.$uri;
  }

  /**
   * Returns the internal route used for inspecting and generating host values.
   *
   * @return sfRoute The internal route
   */
  public function getHostRoute()
  {
    return isset($this->options['host_route']) ? $this->options['host_route'] : null;
  }

  public function setObject($v)
  {
    $this->_object = $v;
  }

  public function getObject()
  {
    return $this->_object;
  }

  /**
   * Returns a new route object for inspecting and generating the host.
   *
   * @param string $pattern      The host pattern
   * @param array  $defaults     All defaults for the current route
   * @param array  $requirements All requirements for the current route
   * @param array  $options      All options for the current route
   *
   * @return sfRoute
   */
  protected function createHostRoute($pattern, $defaults, $requirements, $options)
  {
    $filteredDefaults = array();
    $filteredRequirements = array();

    // this temporary route is just for extracting variables from the pattern
    $tmp = new sfRoute($pattern);

    foreach (array_keys($tmp->getVariables()) as $name)
    {
      if (isset($defaults[$name]))
      {
        $filteredDefaults[$name] = $defaults[$name];
      }

      if (isset($requirements[$name]))
      {
        $filteredRequirements[$name] = $requirements[$name];
      }
    }

    return new sfRoute($pattern, $filteredDefaults, $filteredRequirements, $options);
  }

  /**
   * Removes parameters use by the host route from the supplied array and returns them.
   *
   * @param array $params All parameters for the current route
   *
   * @return array An array of parameters for the internal host route
   */
  protected function extractHostParams(& $params)
  {
    $hostParams = array();
    foreach (array_keys($this->options['host_route']->getVariables()) as $name)
    {
      if (isset($params[$name]))
      {
        $hostParams[$name] = $params[$name];
        unset($params[$name]);
      }
    }

    return $hostParams;
  }
}
