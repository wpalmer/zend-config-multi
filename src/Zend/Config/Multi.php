<?php

class DIW_Zend_Config_Multi extends Zend_Config
{
	protected static $novalue;
	protected $_cfg_fallbacks;
	protected $_cfg_writeable;
	protected $_cfg_data_deep;
	protected $_cfg_default_path_prefix;

	public function __construct($allowModifications = false)
	{
		if (!isset(self::$novalue)) {
			self::$novalue = new DIW_Zend_Config_Multi_NoValue();
		}

		$this->_cfg_fallbacks = array();
		$this->_cfg_data_deep = array();

		if ($allowModifications) {
			$this->_cfg_writeable = new Zend_Config(array(), true);
		}

		parent::__construct(array(), $allowModifications);
	}

	public function setDefaultPathPrefix($path)
	{
		$this->_cfg_default_path_prefix = (array)$path;
	}

	public function attach(Zend_Config $config, $is_dirty = false)
	{
		$this->_cfg_fallbacks[] = array('is_dirty' => $is_dirty, 'config' => $config);
	}

	public function override(Zend_Config $config, $is_dirty = false)
	{
		array_unshift($this->_cfg_fallbacks, array('is_dirty' => $is_dirty, 'config' => $config));
	}

	public function attachDirty(Zend_Config $config)
	{
		return $this->attach($config, true);
	}

	public function overrideDirty(Zend_Config $config)
	{
		return $this->override($config, true);
	}

	protected function _fallbacks($only_dirty = false)
	{
		$fallbacks = array();
		if ($this->_cfg_writeable) $fallbacks[] = $this->_cfg_writeable;

		foreach ($this->_cfg_fallbacks as $fallback) {
			if ($only_dirty && !$fallback['is_dirty']) continue;
			$fallbacks[] = $fallback['config'];
		}

		return $fallbacks;
	}

	/**
	* When we get() something, we need to get a Zend_Config_Multi, so that the
	* result is just as writeable as we are. But we also need to ensure that we
	* always return the *same* Zend_Config_Multi
	*/
	public function get($name, $default = null, $prefix = null)
	{
		if ($prefix === null) $prefix = $this->_cfg_default_path_prefix;
		if ($prefix !== false) $name = array_merge((array)$prefix, (array)$name);

		if (is_array($name)) {
			$next = $this;
			foreach ($name as $component) {
				if (!($next instanceof Zend_Config)) return $default;
				$next = $next->get($component, self::$novalue, false);
			}

			if ($next === self::$novalue) return $default;
			return $next;
		}

		// loop through fallbacks
		//   is the fallback's value for $name known by our "multi" cache?
		//   if so, continue. If not, add it to our "multi" cache
		//   if we find a non-Zend_Config along the way, return that.
		//   once we're done looping, return the "multi" cache.

		$deep = new self(!$this->readOnly());
		$found = self::$novalue;
		if ($this->_cfg_writeable) {
			$found = $this->_cfg_writeable->get($name, self::$novalue);
			if (
				$found !== self::$novalue &&
				!($found instanceof Zend_Config) // writeable attach() is handled below
			) {
				return $found;
			}
		}

		foreach ($this->_cfg_fallbacks as $fallback) {
			$found = $fallback['config']->get($name, self::$novalue);
			if ($found === self::$novalue) continue;
			if ($found instanceof Zend_Config){
				$deep->attach( $found );
			} else {
				return $found;
			}
		}

		// if novalue, return default. else, we've found at least one Zend_Config
		if ($found === self::$novalue) return $default;

		// Use the base Multi's "writeable" as the "writeable" of the child
		// this is instead of attach()ing it, earlier
		if (
			!$this->readOnly() &&
			!($this->_cfg_writeable->{$name} instanceof Zend_Config)
		) {
			$this->_cfg_writeable->{$name} = new Zend_Config(array(), true);
		}
		$deep->_cfg_writeable = $this->_cfg_writeable->{$name};

		return $deep;
	}

	public function __set($name, $value)
	{
		if (!$this->_allowModifications) {
			throw new Zend_Config_Exception('Zend_Config is read only');
		}

		$prefix = $this->_cfg_default_path_prefix;
		if ($prefix !== false) $name = array_merge((array)$prefix, (array)$name);

		if (is_array($name)) {
			$tail = array_pop($name);
			$next = $this->_cfg_writeable;
			while (true) {
				if (!($next instanceof Zend_Config)) {
					// we're missing a deep config (or part of the path is not a config)
					// so let's create it, deeply
					$prev->{ $component } = new Zend_Config(array(), true);
					$next = $prev->{ $component };
					while(!empty($name)) {
						$component = array_shift($name);
						$next->{ $component } = new Zend_Config(array(), true);
						$next = $next->{ $component };
					}
					break;
				}

				if (empty($name)) break;
				$component = array_shift($name);
				$prev = $next;
				$next = $next->get($component, self::$novalue, false);
			}

			return $next->__set($tail, $value);
		}

		return $this->_cfg_writeable->__set($name, $value);
	}

	public function __clone()
	{
		parent::__clone();

		$fallbacks = array();
		foreach ($this->_cfg_fallbacks as $fallback) {
			$fallbacks[] = array(
				'is_dirty' => $fallback['is_dirty'],
				'config' => clone $config
			);
		}
		$this->_cfg_fallbacks = $fallbacks;

		if( $this->_cfg_writeable ) $this->_cfg_writeable = clone $this->_cfg_writeable;
	}

	protected static function _mergeDeep(array $a, array $b)
	{
		$merged = $a;

		foreach ($b as $key => $value) {
			if (
				is_array($value) &&
				(array_key_exists($key, $merged) && is_array($merged[$key]))
			){
				$merged[$key] = self::_mergeDeep($merged[$key], $value);
			} else {
				$merged[$key] = $value;
			}
		}

		return $merged;
	}

	public function toArray($only_dirty = false)
	{
		$result = array();
		foreach ( array_reverse($this->_fallbacks($only_dirty)) as $config) {
			$result = self::_mergeDeep($result, $config->toArray());
		}
		return $result;
	}

	public function toDirtyArray()
	{
		return $this->toArray(true);
	}

	public function getDirty()
	{
		return new Zend_Config($this->toDirtyArray());
	}

	public function __isset($name)
	{
		foreach ($this->_cfg_fallbacks as $fallback) {
			if ($fallback['config']->__isset($name)) return true;
		}

		return $this->_cfg_writeable && $this->_cfg_writeable->__isset($name);
	}

	public function __unset($name)
	{
		throw new Zend_Config_Exception('Zend_Config_Multi does not implement __unset()');
	}

	public function count($only_dirty = false)
	{
		return count( $this->toArray($only_dirty) );
	}

	public function current()
	{
		throw new Zend_Config_Exception('Zend_Config_Multi does not implement the Iterator interface');
	}

	public function key()
	{
		throw new Zend_Config_Exception('Zend_Config_Multi does not implement the Iterator interface');
	}

	public function next()
	{
		throw new Zend_Config_Exception('Zend_Config_Multi does not implement the Iterator interface');
	}

	public function rewind()
	{
		throw new Zend_Config_Exception('Zend_Config_Multi does not implement the Iterator interface');
	}

	public function valid()
	{
		throw new Zend_Config_Exception('Zend_Config_Multi does not implement the Iterator interface');
	}

	/*
	* Zend_Config_Multi does not implement section-specific loading
	*/
	public function getSectionName(){ return null; }
	public function areAllSectionsLoaded(){ return true; }

	/*
	* Zend_Config_Multi does not implement "extends", as its functionality doesn't
	* work sanely across multiple Zend_Config objects. Specifically, "extends"
	* tends to be implemented at load-time, meaning there is no way to determine
	* which values are from the base section and which are overrides.
	*/
	public function getExtends(){ return array(); }

	/*
	* We assume that merge()'d in data is not dirty, and is just as writeable as
	* we are.
	*/
	public function merge(Zend_Config $merge)
	{
		$this->override(new Zend_Config($merge->toArray(), !$this->readOnly()));
		return $this;
	}

}
