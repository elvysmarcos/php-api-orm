<?php


namespace APIORM;


class EntityRelationship
{
    public $to;
    public $from;
    public $of;
    public $requerid;

    public  function __construct(string $from, string $of , string $to, bool $requerid = true)
    {
        $this->from = $from;
        $this->of = $of;
        $this->to = $to;
        $this->requerid = $requerid;
    }
}