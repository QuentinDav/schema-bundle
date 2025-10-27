<?php

namespace Qd\SchemaBundle\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class CreateReleaseDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Version type is required')]
        #[Assert\Choice(
            choices: ['auto', 'major', 'minor', 'patch'],
            message: 'Version type must be one of: {{ choices }}'
        )]
        public readonly string $versionType,

        #[Assert\Type('string')]
        #[Assert\Length(
            max: 1000,
            maxMessage: 'Description cannot be longer than {{ limit }} characters'
        )]
        public readonly ?string $description = null
    ) {
    }
}
