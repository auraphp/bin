<?php
class Config
{
    protected $values = [];
    
    public function __construct(array $values = [])
    {
        $this->values = $values;
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
