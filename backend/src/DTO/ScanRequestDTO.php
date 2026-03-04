<?php
namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ScanRequestDTO
{
    #[Assert\NotBlank(message: 'gitUrl est requis.')]
    #[Assert\Url(message: 'gitUrl doit être une URL valide.')]
    public string $gitUrl = '';

    #[Assert\NotBlank(message: 'Le nom du projet est requis.')]
    #[Assert\Length(min: 2, max: 255)]
    public string $name = 'Projet sans nom';

    #[Assert\Choice(choices: ['github', 'gitlab', 'zip', 'other'])]
    public string $sourceType = 'github';
}