<?php

namespace App\Entity;

use ApiPlatform\Core\Annotation\{
    ApiResource,
    ApiProperty
};
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\{
    ArrayCollection,
    Collection
};
use App\Entity\Traits\PersonTrait;

/**
 * @ApiResource(
 *     collectionOperations={"get"={"method"="GET", "path"="/staff"}},
 *     itemOperations={"get"={"method"="GET", "path"="/staff/{id}", "requirements"={"id"="[a-z]+"}}},
 *     attributes={
 *         "normalization_context"={"groups"={"read"}},
 *     }
 * )
 * @ORM\Entity(repositoryClass="App\Repository\StaffMemberRepository")
 * @ORM\Table(name="app_staff_member")
 * @UniqueEntity("username")
 * @UniqueEntity("email")
 */
class StaffMember
{
    use PersonTrait;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @ApiProperty(identifier=false)
     * @Groups({"read", "role"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=15, unique=true)
     * @Assert\NotBlank()
     * @ApiProperty(identifier=true)
     * @Groups({"read", "role"})
     */
    private $username;

    /**
     * @ORM\Column(type="string", length=100, unique=true)
     * @Assert\NotBlank()
     * @Assert\Email(mode="strict")
     * @Groups({"read", "role"})
     */
    private $email;

    /**
     * @ORM\Column(type="string", length=30)
     * @Assert\NotBlank()
     * @Groups({"read", "role"})
     */
    private $homeProgram;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Project", mappedBy="principalInvestigator")
     */
    private $projects;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\StaffRole", mappedBy="staffMember", orphanRemoval=true, cascade={"persist", "remove"})
     * @Assert\Valid()
     */
    private $staffRoles;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->staffRoles = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->id
            ? sprintf('%s %s', $this->firstName, $this->lastName)
            : ''
        ;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = strtolower($username);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower($email);

        return $this;
    }

    public function getHomeProgram(): ?string
    {
        return $this->homeProgram;
    }

    public function setHomeProgram(string $homeProgram): self
    {
        $this->homeProgram = $homeProgram;

        return $this;
    }

    /**
     * @return Collection|Project[]
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects[] = $project;
            $project->setPrincipalInvestigator($this);
        }

        return $this;
    }

    public function removeProject(Project $project): self
    {
        if ($this->projects->contains($project)) {
            $this->projects->removeElement($project);
            // set the owning side to null (unless already changed)
            if ($project->getPrincipalInvestigator() === $this) {
                $project->setPrincipalInvestigator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|StaffRole[]
     */
    public function getStaffRoles(): Collection
    {
        return $this->staffRoles;
    }

    public function addStaffRole(StaffRole $staffRole): self
    {
        if (!$this->staffRoles->contains($staffRole)) {
            $this->staffRoles[] = $staffRole;
            $staffRole->setStaffMember($this);
        }

        return $this;
    }

    public function removeStaffRole(StaffRole $staffRole): self
    {
        if ($this->staffRoles->contains($staffRole)) {
            $this->staffRoles->removeElement($staffRole);
            // set the owning side to null (unless already changed)
            if ($staffRole->getStaffMember() === $this) {
                $staffRole->setStaffMember(null);
            }
        }

        return $this;
    }

    /**
     * Get the total percent sum for all staff roles.
     * The function array_reduce accepts array as first argument.
     * The collection staffRoles has to be therefore converted to array.
     * In the callback function the sum is calculated.
     *
     * @Groups({"read"})
     */
    public function getTotalRolesPercent()
    {
        return array_reduce(
            $this->getStaffRoles()->toArray(),
            function($carry, $item) {
                $carry += $item->getPercent();
                return round($carry, 2);
            },
            0
        );
    }
}
