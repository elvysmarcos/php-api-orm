<?php


namespace APIORM;


class EntityRelationship
{
    public $to;
    public $from;
    public $of;
    public $required;

    public  function __construct(string $from, string $of , string $to, bool $required = true)
    {
        $this->from = $from;
        $this->of = $of;
        $this->to = $to;
        $this->required = $required;
    }
}