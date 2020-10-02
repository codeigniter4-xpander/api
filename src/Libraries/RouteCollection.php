<?php namespace CI4Xpander_API\Libraries;

class RouteCollection extends \CodeIgniter\Router\RouteCollection {
    protected $isApi = false;

    public function isApi() {
        return $this->isApi;
    }

    public function api() {
        $this->isApi = true;
        return $this;
    }

    protected $groupNamespace = '';

    public function getGroupNamespace() {
        return $this->groupNamespace;
    }

    public function getGroupSet() {
        return $this->group;
    }

    public function setGroupSet($group = '') {
        $this->group = $group;
    }

    public function group(string $name, ...$params)
	{
		$oldGroup   = $this->group;
		$oldOptions = $this->currentOptions;

		// To register a route, we'll set a flag so that our router
		// so it will see the group name.
		$this->group = ltrim($oldGroup . '/' . $name, '/');

		$callback = array_pop($params);

		if ($params && is_array($params[0]))
		{
            $this->currentOptions = array_shift($params);
            $this->groupNamespace = $this->currentOptions['namespace'] . '\\' ?? $this->getDefaultNamespace();
		}

		if (is_callable($callback))
		{
			$callback($this);
		}

		$this->group          = $oldGroup;
		$this->currentOptions = $oldOptions;
    }
    
    public $version = [''];
    public function version($version = ['']) {
        return $this;
    }
}