<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity()]
#[ORM\Table(name: 'addressbookinstances')]
#[UniqueEntity(fields: ['principalUri', 'uri'], errorPath: 'uri', message: 'form.uri.unique')]
class AddressBookInstance
{
    public static function getOwnerAccesses(): array
    {
        return [
            SharingPlugin::ACCESS_NOTSHARED,
            SharingPlugin::ACCESS_SHAREDOWNER,
        ];
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: "App\Entity\AddressBook", cascade: ['persist'], inversedBy: 'instances')]
    #[ORM\JoinColumn(name: 'addressbookid', nullable: false)]
    private $addressBook;

    #[ORM\Column(name: 'principaluri', type: 'string', length: 255, nullable: true)]
    private $principalUri;

    #[ORM\Column(type: 'smallint', options: ['default' => 1])]
    private $access;

    #[ORM\Column(name: 'displayname', type: 'string', length: 255, nullable: true)]
    private $displayName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Regex("/[0-9a-z\-]+/")]
    private $uri;

    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\Column(name: 'share_href', type: 'string', length: 255, nullable: true)]
    private $shareHref;

    #[ORM\Column(name: 'share_displayname', type: 'string', length: 255, nullable: true)]
    private $shareDisplayName;

    #[ORM\Column(name: 'share_invitestatus', type: 'integer', options: ['default' => 2])]
    private $shareInviteStatus;

    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $permissions = 0;

    public function __construct()
    {
        $this->shareInviteStatus = SharingPlugin::INVITE_ACCEPTED;
        $this->access = SharingPlugin::ACCESS_SHAREDOWNER;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAddressBook(): ?AddressBook
    {
        return $this->addressBook;
    }

    public function setAddressBook(?AddressBook $addressBook): self
    {
        $this->addressBook = $addressBook;

        return $this;
    }

    public function getPrincipalUri(): ?string
    {
        return $this->principalUri;
    }

    public function setPrincipalUri(?string $principalUri): self
    {
        $this->principalUri = $principalUri;

        return $this;
    }

    public function getAccess(): ?int
    {
        return $this->access;
    }

    public function setAccess(int $access): self
    {
        $this->access = $access;

        return $this;
    }

    public function isShared(): bool
    {
        return !in_array($this->access, self::getOwnerAccesses());
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(?string $uri): self
    {
        $this->uri = $uri;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getShareHref(): ?string
    {
        return $this->shareHref;
    }

    public function setShareHref(?string $shareHref): self
    {
        $this->shareHref = $shareHref;

        return $this;
    }

    public function getShareDisplayName(): ?string
    {
        return $this->shareDisplayName;
    }

    public function setShareDisplayName(?string $shareDisplayName): self
    {
        $this->shareDisplayName = $shareDisplayName;

        return $this;
    }

    public function getShareInviteStatus(): ?int
    {
        return $this->shareInviteStatus;
    }

    public function setShareInviteStatus(int $shareInviteStatus): self
    {
        $this->shareInviteStatus = $shareInviteStatus;

        return $this;
    }

    public function getPermissions(): int
    {
        return $this->permissions;
    }

    public function setPermissions(int $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }

    public function canWrite(): bool
    {
        return (bool) ($this->permissions & 1);
    }

    public function canCreate(): bool
    {
        return (bool) ($this->permissions & 2);
    }

    public function canDelete(): bool
    {
        return (bool) ($this->permissions & 4);
    }
}