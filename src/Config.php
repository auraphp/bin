<?php
namespace Aura\Bin;

class Config
{
    protected $values = [];

    public function __construct(array $env = [])
    {
        foreach ($env as $key => $val) {
            if (substr($key, 0, 9) != 'AURA_BIN_') {
                continue;
            }
            $key = strtolower(substr($key, 9));
            $this->values[$key] = $val;
        }
    }

    public function __get($key)
    {
        if (isset($this->values[$key])) {
            return $this->values[$key];
        } else {
            return null;
        }
    }
}
