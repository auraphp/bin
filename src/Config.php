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
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        throw new \Exception("No such config key '$key'");
    }
}
