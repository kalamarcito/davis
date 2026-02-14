<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity()]
#[ORM\Table(name: 'addressbooks')]
class AddressBook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $synctoken;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => false])]
    private $includedInBirthdayCalendar;

    #[ORM\OneToMany(targetEntity: "App\Entity\AddressBookInstance", mappedBy: 'addressBook')]
    private $instances;

    #[ORM\OneToMany(targetEntity: "App\Entity\Card", mappedBy: 'addressBook')]
    private $cards;

    #[ORM\OneToMany(targetEntity: "App\Entity\AddressBookChange", mappedBy: 'addressBook')]
    private $changes;

    public function __construct()
    {
        $this->synctoken = 1;
        $this->includedInBirthdayCalendar = false;
        $this->instances = new ArrayCollection();
        $this->cards = new ArrayCollection();
        $this->changes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isIncludedInBirthdayCalendar(): ?bool
    {
        return $this->includedInBirthdayCalendar;
    }

    public function setIncludedInBirthdayCalendar(bool $includedInBirthdayCalendar): self
    {
        $this->includedInBirthdayCalendar = $includedInBirthdayCalendar;

        return $this;
    }

    public function getSynctoken(): ?string
    {
        return $this->synctoken;
    }

    public function setSynctoken(string $synctoken): self
    {
        $this->synctoken = $synctoken;

        return $this;
    }

    /**
     * @return Collection|AddressBookInstance[]
     */
    public function getInstances(): Collection
    {
        return $this->instances;
    }

    public function addInstance(AddressBookInstance $instance): self
    {
        if (!$this->instances->contains($instance)) {
            $this->instances[] = $instance;
            $instance->setAddressBook($this);
        }

        return $this;
    }

    public function removeInstance(AddressBookInstance $instance): self
    {
        if ($this->instances->contains($instance)) {
            $this->instances->removeElement($instance);
            // set the owning side to null (unless already changed)
            if ($instance->getAddressBook() === $this) {
                $instance->setAddressBook(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Card[]
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(Card $card): self
    {
        if (!$this->cards->contains($card)) {
            $this->cards[] = $card;
            $card->setAddressBook($this);
        }

        return $this;
    }

    public function removeCard(Card $card): self
    {
        if ($this->cards->contains($card)) {
            $this->cards->removeElement($card);
            // set the owning side to null (unless already changed)
            if ($card->getAddressBook() === $this) {
                $card->setAddressBook(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|AddressBookChange[]
     */
    public function getChanges(): Collection
    {
        return $this->changes;
    }

    public function addChange(AddressBookChange $change): self
    {
        if (!$this->changes->contains($change)) {
            $this->changes[] = $change;
            $change->setCalendar($this);
        }

        return $this;
    }

    public function removeChange(AddressBookChange $change): self
    {
        if ($this->changes->contains($change)) {
            $this->changes->removeElement($change);
            // set the owning side to null (unless already changed)
            if ($change->getCalendar() === $this) {
                $change->setCalendar(null);
            }
        }

        return $this;
    }
}