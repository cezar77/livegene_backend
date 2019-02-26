<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\ApiResource;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Entity\Traits\RoleTrait;

/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass="App\Repository\SDGRoleRepository")
 * @ORM\Table(
 *     name="app_sdg_role",
 *     uniqueConstraints={@ORM\UniqueConstraint(name="project_sdg", columns={"project_id", "sdg_id"})}
 * )
 * @UniqueEntity({"project", "sdg"})
 */
class SDGRole
{
    use RoleTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Project", inversedBy="sdgRoles")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotBlank()
     */
    private $project;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\SDG", inversedBy="sdgRoles")
     * @ORM\JoinColumn(nullable=false)
     * @Assert\NotBlank()
     */
    private $sdg;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getSDG(): ?SDG
    {
        return $this->sdg;
    }

    public function setSDG(?SDG $sdg): self
    {
        $this->sdg = $sdg;

        return $this;
    }
}