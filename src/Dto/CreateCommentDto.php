<?php

namespace Qd\SchemaBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCommentDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Entity FQCN is required')]
        #[Assert\Type('string')]
        public readonly string $entityFqcn,

        #[Assert\NotBlank(message: 'Body is required')]
        #[Assert\Type('string')]
        #[Assert\Length(
            min: 1,
            max: 10000,
            minMessage: 'Body must be at least {{ limit }} character long',
            maxMessage: 'Body cannot be longer than {{ limit }} characters'
        )]
        public readonly string $body
    ) {
    }
}
