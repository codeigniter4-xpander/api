<?php namespace CI4Xpander_API\Config;

class Api extends \CodeIgniter\Config\BaseConfig
{
    public $alg = 'HS256';
    public $kty = 'oct';
    public $k = '';
    public $use = 'sig';
}
