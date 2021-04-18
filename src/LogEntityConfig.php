<?php


namespace APIORM;


use APIORM\Enums\DBOperationTypeEnum;

class LogEntityConfig
{
    public string $type;

    public array $filterAuthor = [
        DBOperationTypeEnum::Insert => true,
        DBOperationTypeEnum::Update => true,
        DBOperationTypeEnum::Delete => true,
    ];

    public function __construct(string $type, array $filterAuthor = null)
    {
        $this->type = $type;

        if ($filterAuthor !== nul) {
            $this->filterAuthor = $filterAuthor;
        }
    }
}